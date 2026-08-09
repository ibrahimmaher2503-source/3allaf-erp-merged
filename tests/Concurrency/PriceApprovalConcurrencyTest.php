<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Support\Str;

/**
 * CONC-PRC-001 (testing/results/CONCURRENCY-SCENARIOS.md) — concurrent price
 * approval and active-version uniqueness. ApprovePriceProposalAction takes a
 * lockForUpdate() on the PriceLine row keyed by product+store's active_key
 * before flipping it; two competing approvals for the SAME product+store
 * must serialize through that lock so exactly one PriceLine ends up active,
 * never zero and never two. This can only be proven with a real row lock.
 */
final class PriceApprovalConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_approvals_for_the_same_product_and_store_never_leave_zero_or_two_active_lines(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('CONC-PRC-'.Str::random(6));
        $store = $this->store($branch, 'CONC-PRC-'.Str::random(6));
        $admin = $this->administrator('conc-prc-admin-'.Str::random(6));
        $this->actingAs($admin);

        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CONC-PRC-CAT-'.Str::random(6), 'name_ar' => 'فئة', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'CONC-PRC-'.Str::random(8), 'name_ar' => 'منتج', 'name_en' => 'Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        $proposer = $this->userWith('conc-prc-proposer-'.Str::random(6), ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);
        $approver = $this->userWith('conc-prc-approver-'.Str::random(6), ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);

        $this->actingAs($proposer);
        $listCode = 'CONC-PRC-LIST-'.Str::random(6);
        $versionA = app(CreatePriceProposalAction::class)->execute($product, $store, $listCode, 'قائمة', 'List', '30.000', 'product_card', 'A', null, null, null);
        $versionA = app(SubmitPriceProposalAction::class)->execute($versionA);
        $versionB = app(CreatePriceProposalAction::class)->execute($product, $store, $listCode, 'قائمة', 'List', '32.000', 'product_card', 'B', null, null, null);
        $versionB = app(SubmitPriceProposalAction::class)->execute($versionB);

        $results = $this->race([
            ['price_approve', ['user_id' => $approver->id, 'version_id' => $versionA->id]],
            ['price_approve', ['user_id' => $approver->id, 'version_id' => $versionB->id]],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, 'Approval A failed: '.json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, 'Approval B failed: '.json_encode($results[1]));

        $activeLines = PriceLine::query()
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->whereNotNull('active_key')
            ->get();

        self::assertCount(1, $activeLines, 'Exactly one PriceLine must be active for this product+store after two concurrent approvals — never zero (lost update), never two (double-active).');

        $activeVersionIds = [$versionA->fresh()->id => $versionA->fresh()->state->value, $versionB->fresh()->id => $versionB->fresh()->state->value];
        $approvedCount = count(array_filter($activeVersionIds, fn ($state) => $state === 'approved'));
        $supersededCount = count(array_filter($activeVersionIds, fn ($state) => $state === 'superseded'));
        self::assertSame(1, $approvedCount, 'Exactly one version ends Approved (the one whose PriceLine is active).');
        self::assertSame(1, $supersededCount, 'The loser of the race must be correctly marked Superseded, not left dangling as Approved-but-inactive.');
    }
}
