<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** PRC-03..PRC-07, TSK-017, and store-scoped approval controls. */
final class PriceProposalIntegrityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CanonicalAuthorizationSeeder::class);
        $this->actingAs($this->administrator('pricing-setup'));
    }

    public function test_price_proposals_require_separate_approval_and_leave_one_active_version(): void
    {
        [$product, $store] = $this->productAndStore('version');
        $proposer = $this->administrator('pricing-proposer');
        $approver = $this->administrator('pricing-approver');
        $this->actingAs($proposer);

        $first = app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'RETAIL', 'أساسي', 'Retail', '20.000', 'product_card', 'CARD-1', null, null, null,
        );
        $first = app(SubmitPriceProposalAction::class)->execute($first);

        $this->expectException(ValidationException::class);
        app(ApprovePriceProposalAction::class)->execute($first);

        $this->actingAs($approver);
        $first = app(ApprovePriceProposalAction::class)->execute($first->fresh());
        self::assertSame(PriceVersionState::Approved, $first->state);
        self::assertSame('20.000', (string) $first->lines->firstOrFail()->amount);

        $this->actingAs($proposer);
        $second = app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'RETAIL', 'أساسي', 'Retail', '25.000', 'product_card', 'CARD-2', null, null, 'seasonal update',
        );
        $second = app(SubmitPriceProposalAction::class)->execute($second);
        $this->actingAs($approver);
        $second = app(ApprovePriceProposalAction::class)->execute($second);

        self::assertSame(PriceVersionState::Superseded, $first->fresh()->state);
        self::assertSame(PriceVersionState::Approved, $second->fresh()->state);
        self::assertSame(1, PriceLine::query()->where('product_id', $product->id)->where('store_id', $store->id)->whereNotNull('active_key')->count());
        self::assertSame('25.000', (string) app(EffectivePriceResolver::class)->resolve($product->id, $store->id)->amount);
    }

    public function test_effective_price_is_store_scoped_and_invalid_proposals_are_rejected(): void
    {
        [$product, $store] = $this->productAndStore('scope');
        $otherBranch = $this->branch('BR-scope-other');
        $otherStore = $this->store($otherBranch, 'ST-scope-other');
        $proposer = $this->administrator('pricing-scope-proposer');
        $approver = $this->administrator('pricing-scope-approver');
        $this->actingAs($proposer);

        $version = app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'RETAIL', 'أساسي', 'Retail', '30.000', 'product_card', null, null, null, null,
        );
        $version = app(SubmitPriceProposalAction::class)->execute($version);
        $this->actingAs($approver);
        app(ApprovePriceProposalAction::class)->execute($version);

        $resolver = app(EffectivePriceResolver::class);
        self::assertNotNull($resolver->resolve($product->id, $store->id));
        self::assertNull($resolver->resolve($product->id, $otherStore->id));

        $this->actingAs($proposer);
        $this->expectException(ValidationException::class);
        app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'RETAIL', 'أساسي', 'Retail', '0', 'product_card', null, null, null, null,
        );
    }

    public function test_label_queue_route_is_read_only_and_server_gated(): void
    {
        $this->actingAs($this->administrator('label-viewer'));
        $this->get(route('pricing.labels'))
            ->assertOk()
            ->assertSee('Real printing is not enabled')
            ->assertSee('Execution disabled')
            ->assertSee('No label queues');

        $this->actingAs($this->userWith('label-no-access'));
        $this->get(route('pricing.labels'))->assertForbidden();
    }

    public function test_pricing_focused_modes_are_addressable_and_invalid_modes_are_rejected(): void
    {
        foreach ([
            'workspace' => 'Pricing workspace',
            'versions' => 'Price lists & versions',
            'unpriced' => 'Unpriced products',
            'history' => 'Price change history',
        ] as $mode => $heading) {
            $this->get('/pricing/'.$mode)
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('data-pricing-mode="'.$mode.'"', escape: false);
        }

        $this->get('/pricing/not-a-mode')->assertNotFound();
    }

    public function test_unpriced_focused_mode_paginates_the_complete_visible_catalog(): void
    {
        [$product] = $this->productAndStore('unpriced-page');

        foreach (range(2, 13) as $number) {
            $copy = $product->replicate();
            $copy->item_code = sprintf('ITEM-unpriced-page-%02d', $number);
            $copy->name_en = 'Unpriced product '.$number;
            $copy->name_ar = 'Unpriced product '.$number;
            $copy->save();
        }

        $this->get('/pricing/unpriced?unpriced_page=2')
            ->assertOk()
            ->assertSee('Unpriced product 13')
            ->assertDontSee('Price versions');
    }

    /** @return array{0: Product, 1: Store} */
    private function productAndStore(string $suffix): array
    {
        $branch = $this->branch('BR-'.$suffix);
        $store = $this->store($branch, 'ST-'.$suffix);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-'.$suffix, 'name_ar' => 'تصنيف '.$suffix, 'name_en' => 'Category '.$suffix,
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'ITEM-'.$suffix, 'name_ar' => 'منتج '.$suffix, 'name_en' => 'Product '.$suffix,
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        return [$product, $store];
    }
}
