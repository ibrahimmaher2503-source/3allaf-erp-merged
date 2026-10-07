<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\BarcodeLabelService;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use Database\Seeders\ProductionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class ApprovedPriceConsumptionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_approved_store_price_is_used_for_labels_matrix_and_pos_without_cross_store_leakage(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();

        $pricingRole = Role::query()->where('code', 'pricing-officer')->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('code', ['pricing_labels.view', 'pricing_labels.create', 'pricing_labels.submit', 'pricing_labels.approve', 'pricing_lists.view'])
            ->where('status', 'active')
            ->pluck('id');
        $this->assertCount(5, $permissionIds);
        $pricingRole->permissions()->syncWithoutDetaching($permissionIds);

        $requester = User::factory()->create(['status' => 'active']);
        $approver = User::factory()->create(['status' => 'active']);
        $pricingRole->users()->syncWithoutDetaching([$requester->id, $approver->id]);

        $branch = Branch::factory()->create(['code' => 'DO-01']);
        $store = Store::factory()->create([
            'branch_id' => $branch->id,
            'company_id' => $branch->company_id,
            'code' => 'LOCAL-RETAIL',
        ]);
        $otherBranch = Branch::factory()->create(['company_id' => $branch->company_id, 'code' => 'QA-OTHER']);
        $otherStore = Store::factory()->create([
            'branch_id' => $otherBranch->id,
            'company_id' => $branch->company_id,
            'code' => 'QA-OTHER-STORE',
        ]);
        $requester->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $approver->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        $product = Product::factory()->create([
            'model_number' => 'QA-PRICE-28',
            'sale_price' => '100.000',
        ]);
        $barcode = $product->barcodes()->create([
            'barcode' => 'QA-PRICE-28-'.fake()->unique()->numerify('######'),
            'source' => 'manual',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($requester);
        $version = app(CreatePriceProposalAction::class)->execute(
            product: $product,
            store: $store,
            priceListCode: 'LOCAL-RETAIL',
            priceListNameAr: 'قائمة التجزئة المحلية',
            priceListNameEn: 'Local Retail',
            amount: '140.000',
            sourceType: 'product_card',
            sourceReference: 'BUG-28',
            effectiveFrom: null,
            effectiveTo: null,
            reasonText: 'Approved store price consumption regression.',
            referenceAmount: '100.000',
        );
        $store->update(['price_list_id' => $version->price_list_id]);
        $version = app(SubmitPriceProposalAction::class)->execute($version);

        $this->actingAs($approver);
        $version = app(ApprovePriceProposalAction::class)->execute($version);
        $approvedLine = $version->lines->firstOrFail();
        $prices = app(EffectivePriceResolver::class);
        $posPrice = $prices->resolveForStore([$product->id], $store->id)->get($product->id)?->amount;
        $labelService = app(BarcodeLabelService::class);
        $document = $labelService->compose(
            $approver,
            [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]],
            $labelService->profile($approver),
            $store->id,
        );
        $matrix = Livewire::test('pricing::product-matrix', ['product' => $product])->set('storeId', (string) $store->id);
        $matrixText = preg_replace('/\s+/', ' ', strip_tags($matrix->html())) ?: '';

        $this->assertSame([
            'pos' => '140.000',
            'label' => '140.000',
            'matrix_contains_authorized_store_price' => true,
        ], [
            'pos' => $posPrice,
            'label' => $document['labels'][0]['price'],
            'matrix_contains_authorized_store_price' => str_contains($matrixText, 'DO-01') && str_contains($matrixText, '140.000'),
        ]);

        $this->assertStringNotContainsString('QA-OTHER', $matrixText);
        try {
            $labelService->compose(
                $approver,
                [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]],
                $labelService->profile($approver),
                $otherStore->id,
            );
            $this->fail('An out-of-scope store must not be used for label pricing.');
        } catch (ModelNotFoundException) {
        }

        $unpriced = Product::factory()->create(['sale_price' => null]);
        $this->assertNull($prices->resolve($unpriced->id, $store->id));
        $this->assertFalse($prices->resolveForStore([$unpriced->id], $store->id)->has($unpriced->id));

        try {
            $approvedLine->update(['amount' => '999.000']);
            $this->fail('An approved store price line must remain immutable.');
        } catch (LogicException) {
        }
        $this->assertSame('140.000', $approvedLine->fresh()->amount);
    }

    public function test_manual_labels_require_a_visible_outlet_and_a_positive_effective_price(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $branch = Branch::factory()->create();
        $store = Store::factory()->create(['branch_id' => $branch->id, 'company_id' => $branch->company_id]);
        PriceList::query()->create([
            'company_id' => $branch->company_id, 'code' => 'PL-0000', 'list_number' => 0,
            'name_ar' => 'السعر الأساسي', 'name_en' => 'Base price', 'status' => 'active', 'percentage_increase' => '0',
        ]);
        $actor->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $product = Product::factory()->create(['sale_price' => '25.000', 'model_number' => 'QA-LABEL-18']);
        $barcode = $product->barcodes()->create(['barcode' => 'QA-LABEL-18', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $this->actingAs($actor);
        $service = app(BarcodeLabelService::class);
        $profile = $service->profile($actor);
        $rows = [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]];
        Gate::before(static fn (): bool => true);
        $this->get(route('pricing.labels'))->assertOk()->assertSeeHtml('name="store_id"')->assertSee(__('Select a selling outlet'));
        $this->from(route('pricing.labels'))
            ->post(route('pricing.labels.preview'), ['products' => [[...$rows[0], 'selected' => true]]])
            ->assertSessionHasErrors('store_id');

        try {
            $service->compose($actor, $rows, $profile);
            self::fail('Label pricing requires an explicit outlet.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('store_id', $exception->errors());
        }

        $document = $service->compose($actor, $rows, $profile, $store->id);
        self::assertSame('25.000', $document['labels'][0]['price']);
        self::assertTrue($document['settings']['show_outlet']);
        self::assertStringContainsString($store->branch->code, view('pricing.label-print', $document)->render());

        $product->update(['sale_price' => null]);
        try {
            $service->compose($actor, $rows, $profile, $store->id);
            self::fail('Unpriced products must not generate priced labels.');
        } catch (ValidationException $exception) {
            self::assertSame([__('No selling price')], $exception->errors()['products']);
        } catch (ModelNotFoundException $exception) {
            self::assertSame(Product::class, $exception->getModel());
        }
    }
}
