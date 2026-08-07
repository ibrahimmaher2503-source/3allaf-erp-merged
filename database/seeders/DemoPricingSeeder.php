<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoPricingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('DemoPricingSeeder requires local Demo Auth.');
        }

        $admin = User::query()->where('username', 'demo-admin')->firstOrFail();
        $store = Store::query()->where('code', 'DEMO-SELL')->firstOrFail();
        $product = Product::query()->where('item_code', 'DEMO-PROD-001')->firstOrFail();

        $existing = PriceLine::query()
            ->with('version')
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->whereHas('version', function ($query): void {
                $query->where('state', 'approved')
                    ->where(fn ($scope) => $scope->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($scope) => $scope->whereNull('effective_to')->orWhere('effective_to', '>', now()));
            })
            ->first();

        if ($existing !== null) {
            return;
        }

        DB::transaction(function () use ($admin, $store, $product): void {
            $list = PriceList::query()->updateOrCreate(
                ['company_id' => $store->company_id, 'code' => 'DEMO-RETAIL'],
                [
                    'name_ar' => 'قائمة أسعار البيع التجريبية',
                    'name_en' => 'Demo Retail Price List',
                    'status' => 'active',
                    'notes' => 'DEMO ONLY. Not Production pricing authority.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            $version = PriceVersion::query()->updateOrCreate(
                ['price_list_id' => $list->id, 'version' => 1],
                [
                    'state' => 'approved',
                    'source_type' => 'product_card',
                    'source_reference' => 'DEMO-PRICE-001',
                    'source_hash' => hash('sha256', 'DEMO-PRICE-001|DEMO-PROD-001|DEMO-SELL|125.000'),
                    'requested_by' => $admin->id,
                    'submitted_by' => $admin->id,
                    'approved_by' => $admin->id,
                    'effective_from' => Carbon::now()->subDay(),
                    'effective_to' => null,
                    'submitted_at' => Carbon::now()->subDay(),
                    'approved_at' => Carbon::now()->subDay(),
                    'reason_text' => 'DEMO ONLY approved price for label walkthrough.',
                    'lock_version' => 1,
                ],
            );

            $approval = ApprovalRecord::query()->firstOrCreate(
                ['idempotency_key' => 'DEMO-PRICE-APPROVAL-001'],
                [
                    'source_type' => 'price_version',
                    'source_id' => (string) $version->id,
                    'source_version' => (string) $version->lock_version,
                    'source_hash' => $version->source_hash,
                    'requested_action' => 'approve_price',
                    'approval_state' => 'approved',
                    'requester_id' => $admin->id,
                    'approver_id' => $admin->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'reason_text' => 'DEMO ONLY approval record for local label walkthrough.',
                    'decision_note' => 'Not Production/UAT approval.',
                    'requested_at' => Carbon::now()->subDay(),
                    'decided_at' => Carbon::now()->subDay(),
                ],
            );

            if ($version->approval_record_id !== $approval->id) {
                $version->forceFill(['approval_record_id' => $approval->id])->save();
            }

            PriceLine::query()->updateOrCreate(
                ['price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id],
                [
                    'branch_id' => $store->branch_id,
                    'amount' => '125.000',
                    'reference_amount' => '125.000',
                    'open_price_allowed' => false,
                    'active_key' => $product->id.':'.$store->id,
                    'notes' => 'DEMO ONLY. Used for local label queue walkthrough.',
                ],
            );
        });
    }
}
