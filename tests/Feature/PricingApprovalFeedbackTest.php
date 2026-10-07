<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Platform\Support\AuditLogPresentation;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

final class PricingApprovalFeedbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_requester_sees_maker_checker_explanation_and_visible_denial_without_changing_the_submitted_price(): void
    {
        ['requester' => $requester, 'version' => $version] = $this->submittedProposal();
        app()->setLocale('ar');
        $this->actingAs($requester);

        Livewire::test('pricing::index', ['mode' => 'versions'])
            ->assertDontSeeHtml('wire:click="approveProposal('.$version->id.')"')
            ->assertDontSeeHtml('wire:click="rejectProposal('.$version->id.')"')
            ->assertDontSeeHtml('wire:model="rejectionReason"')
            ->assertSee(__('The proposer cannot approve the same price version.'))
            ->assertSee(__('A requester cannot reject their own request.'))
            ->call('approveProposal', $version->id)
            ->assertHasErrors('approval')
            ->assertSee(__('The proposer cannot approve the same price version.'));

        $version->refresh();
        $version->load('approvalRecord', 'lines');
        $this->assertSame(PriceVersionState::Submitted, $version->state);
        $this->assertSame(ApprovalState::Pending, $version->approvalRecord->approval_state);
        $this->assertNull($version->approvalRecord->approver_id);
        $this->assertNull($version->lines->first()->active_key);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'price_version_approved',
            'source_type' => PriceVersion::class,
            'source_id' => (string) $version->id,
        ]);
    }

    public function test_stale_reviewer_decision_is_visible_and_rolls_back_all_price_effects(): void
    {
        ['reviewer' => $reviewer, 'version' => $version] = $this->submittedProposal();
        $version->approvalRecord()->update(['source_version' => 'stale-version']);
        $submittedLockVersion = $version->lock_version;
        $this->actingAs($reviewer);

        Livewire::test('pricing::index', ['mode' => 'versions'])
            ->assertSeeHtml('wire:click="rejectProposal('.$version->id.')"')
            ->assertSeeHtml('wire:model="rejectionReason"')
            ->call('approveProposal', $version->id)
            ->assertHasErrors('approval')
            ->assertSee(__('The approval request is stale. Reload the source record and try again.'));

        $version->refresh();
        $version->load('approvalRecord', 'lines');
        $this->assertSame(PriceVersionState::Submitted, $version->state);
        $this->assertSame($submittedLockVersion, $version->lock_version);
        $this->assertSame(ApprovalState::Pending, $version->approvalRecord->approval_state);
        $this->assertNull($version->approvalRecord->approver_id);
        $this->assertNull($version->lines->first()->active_key);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'price_version_approved',
            'source_type' => PriceVersion::class,
            'source_id' => (string) $version->id,
        ]);
    }

    public function test_distinct_authorized_reviewer_activates_the_submitted_price_and_approved_line_stays_immutable(): void
    {
        ['reviewer' => $reviewer, 'product' => $product, 'store' => $store, 'version' => $version] = $this->submittedProposal();
        $this->actingAs($reviewer);

        Livewire::test('pricing::index', ['mode' => 'versions'])
            ->call('approveProposal', $version->id)
            ->assertHasNoErrors()
            ->assertSee(__('Price version approved and effective where its date allows.'));

        $version->refresh();
        $version->load('approvalRecord', 'lines');
        $line = $version->lines->firstOrFail();
        $this->assertSame(PriceVersionState::Approved, $version->state);
        $this->assertSame(ApprovalState::Approved, $version->approvalRecord->approval_state);
        $this->assertSame($reviewer->id, $version->approvalRecord->approver_id);
        $this->assertSame($product->id.':'.$store->id, $line->active_key);
        $this->assertSame(1, PriceLine::query()->where('product_id', $product->id)->where('store_id', $store->id)->whereNotNull('active_key')->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'price_version_approved',
            'actor_id' => $reviewer->id,
            'source_type' => PriceVersion::class,
            'source_id' => (string) $version->id,
        ]);

        $effective = app(EffectivePriceResolver::class)->resolve($product->id, $store->id);
        $this->assertSame($line->id, $effective?->id);
        $this->assertSame('55.000', $effective?->amount);

        try {
            $line->update(['amount' => '99.000']);
            $this->fail('An approved price line should remain immutable.');
        } catch (\LogicException) {
            $this->assertSame('55.000', $line->fresh()->amount);
        }
    }

    public function test_product_barcode_and_superseded_price_audits_are_named_and_transactional(): void
    {
        app()->setLocale('en');
        ['requester' => $requester, 'reviewer' => $reviewer, 'product' => $product, 'store' => $store, 'version' => $oldVersion] = $this->submittedProposal();
        $this->actingAs($requester);

        $barcode = app(ReserveProductIdentityAction::class)->createPrimaryBarcode($product, 'QA-'.$product->id.'-IDENTITY', 'local');
        $barcodeAudit = AuditLog::query()->where('source_type', Barcode::class)->where('source_id', (string) $barcode->id)->firstOrFail();

        self::assertSame('create_product_barcode', $barcodeAudit->event);
        self::assertSame('Product barcode added', app(AuditLogPresentation::class)->event($barcodeAudit->event));
        self::assertSame('Barcode', app(AuditLogPresentation::class)->source($barcodeAudit->source_type));
        self::assertSame('Product card created', app(AuditLogPresentation::class)->event('create_product_card'));
        self::assertSame('Product card updated', app(AuditLogPresentation::class)->event('update_product_card'));
        self::assertSame('Product type changed', app(AuditLogPresentation::class)->event('change_product_type'));
        self::assertSame('Product status changed', app(AuditLogPresentation::class)->event('toggle_product_status'));

        $this->actingAs($reviewer);
        app(ApprovePriceProposalAction::class)->execute($oldVersion);
        $oldVersion->refresh();
        $this->actingAs($requester);
        $newVersion = app(CreatePriceProposalAction::class)->execute(
            product: $product,
            store: $store,
            priceListCode: 'QA-PRICE-232',
            priceListNameAr: 'قائمة اختبار',
            priceListNameEn: 'QA Price List',
            amount: '65.000',
            sourceType: 'product_card',
            sourceReference: 'QA-BUG-23-SUPERSEDE',
            effectiveFrom: null,
            effectiveTo: null,
            reasonText: 'Replace the active approved price.',
            referenceAmount: '19.000',
        );
        $newVersion = app(SubmitPriceProposalAction::class)->execute($newVersion);
        $this->actingAs($reviewer);
        app(ApprovePriceProposalAction::class)->execute($newVersion);

        $oldVersion->refresh();
        $newVersion->refresh();
        self::assertSame(PriceVersionState::Superseded, $oldVersion->state);
        self::assertNotNull($oldVersion->superseded_at);
        self::assertSame(PriceVersionState::Approved, $newVersion->state);
        self::assertSame(1, PriceLine::query()->where('product_id', $product->id)->where('store_id', $store->id)->whereNotNull('active_key')->count());

        $supersededAudit = AuditLog::query()->where('event', 'price_version_superseded')->where('source_id', (string) $oldVersion->id)->firstOrFail();
        self::assertSame(['state' => 'approved'], $supersededAudit->before_values);
        self::assertSame(PriceVersionState::Superseded->value, $supersededAudit->after_values['state']);
        self::assertSame($store->branch_id, $supersededAudit->branch_id);
        self::assertSame($store->id, $supersededAudit->store_id);
        self::assertSame('Price version superseded', app(AuditLogPresentation::class)->event($supersededAudit->event));
        self::assertDatabaseHas('audit_logs', ['event' => 'price_proposal_created', 'source_id' => (string) $newVersion->id]);
        self::assertDatabaseHas('audit_logs', ['event' => 'price_proposal_submitted', 'source_id' => (string) $newVersion->id]);
        self::assertDatabaseHas('audit_logs', ['event' => 'price_version_approved', 'source_id' => (string) $newVersion->id]);
    }

    public function test_rejecting_without_a_reason_renders_validation_feedback_and_preserves_the_request(): void
    {
        ['reviewer' => $reviewer, 'version' => $version] = $this->submittedProposal();
        $this->actingAs($reviewer);

        Livewire::test('pricing::index', ['mode' => 'versions'])
            ->call('rejectProposal', $version->id)
            ->assertHasErrors('rejectionReason')
            ->assertSee(__('The rejection reason field is required.'));

        $version->refresh();
        $version->load('approvalRecord', 'lines');
        $this->assertSame(PriceVersionState::Submitted, $version->state);
        $this->assertSame(ApprovalState::Pending, $version->approvalRecord->approval_state);
        $this->assertNull($version->approvalRecord->approver_id);
        $this->assertNull($version->lines->first()->active_key);
    }

    /** @return array{requester: User, reviewer: User, product: Product, store: Store, version: PriceVersion} */
    private function submittedProposal(): array
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();

        $pricingRole = Role::query()->where('code', 'pricing-officer')->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('code', ['pricing_labels.view', 'pricing_labels.create', 'pricing_labels.submit', 'pricing_labels.approve'])
            ->where('status', 'active')
            ->pluck('id');
        $this->assertCount(4, $permissionIds);
        $pricingRole->permissions()->syncWithoutDetaching($permissionIds);

        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = User::factory()->create(['status' => 'active']);
        $pricingRole->users()->syncWithoutDetaching([$requester->id, $reviewer->id]);

        $branch = Branch::factory()->create();
        $store = Store::factory()->create(['branch_id' => $branch->id, 'company_id' => $branch->company_id]);
        $requester->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $reviewer->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $product = Product::factory()->create(['sale_price' => '19.000']);

        $this->actingAs($requester);
        $version = app(CreatePriceProposalAction::class)->execute(
            product: $product,
            store: $store,
            priceListCode: 'QA-PRICE-232',
            priceListNameAr: 'قائمة اختبار',
            priceListNameEn: 'QA Price List',
            amount: '55.000',
            sourceType: 'product_card',
            sourceReference: 'QA-BUG-232',
            effectiveFrom: null,
            effectiveTo: null,
            reasonText: 'Focused approval feedback regression.',
            referenceAmount: '19.000',
        );
        $version = app(SubmitPriceProposalAction::class)->execute($version);

        return compact('requester', 'reviewer', 'product', 'store', 'version');
    }
}
