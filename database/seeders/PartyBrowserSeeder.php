<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

/** Local-only data for the headed Party workflow walkthrough. */
final class PartyBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) throw new LogicException('Party browser fixtures require local Demo Auth.');

        $admin = User::query()->where('email', 'demo.admin@toyjoy.local')->firstOrFail();
        Auth::login($admin);
        $selling = Store::query()->where('code', 'DEMO-SELL')->firstOrFail();
        $party = Store::query()->updateOrCreate(['code' => 'DEMO-PARTY'], ['company_id' => $selling->company_id, 'branch_id' => $selling->branch_id, 'type' => 'party', 'name_ar' => 'مركز الحفلات التجريبي', 'name_en' => 'Demo Party Center', 'status' => 'active', 'policy_notes' => 'Local Party workflow fixture.']);

        foreach ([['party_booking', 'PB-'], ['party_invoice', 'PI-'], ['party_payment_receipt', 'PPR-'], ['party_final_invoice', 'PFI-'], ['party_final_receipt', 'PFR-'], ['party_operating_order', 'POO-']] as [$type, $prefix]) {
            DocumentSequence::query()->updateOrCreate(['document_type' => $type], ['prefix' => $prefix, 'padding_length' => 6, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1, 'policy_notes' => 'Local Party workflow fixture.']);
        }

        $settings = [
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => json_encode(['service', 'loyalty'], JSON_THROW_ON_ERROR),
            'customer.consent.wording' => json_encode(['version' => 'PARTY-LOCAL-V1', 'text' => 'Local Party workflow consent.'], JSON_THROW_ON_ERROR),
            'customer.consent.retention' => json_encode(['days' => 365], JSON_THROW_ON_ERROR),
            'wallet.party.credit_limit' => '1000.0000',
            'wallet.party.debt_limit' => '1000.0000',
            'wallet.party.settlement_policy' => json_encode(['enabled' => true, 'operations' => ['credit', 'debit', 'settlement']], JSON_THROW_ON_ERROR),
            'wallet.party.adjustment_policy' => json_encode(['enabled' => true, 'approval_required' => true], JSON_THROW_ON_ERROR),
            'wallet.party.visibility_scope' => json_encode(['mode' => 'branch_store'], JSON_THROW_ON_ERROR),
        ];
        foreach ($settings as $key => $value) app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'Local Party browser fixture.');

        $customer = app(CreateCustomerAction::class)->execute($admin, $party, ['idempotency_key' => 'PARTY-BROWSER-CUSTOMER-1', 'phone' => '01055555555', 'name_ar' => 'عميل حفلة تجريبي', 'name_en' => 'Party Browser Customer', 'email' => 'party.browser@example.test', 'consents' => [['purpose' => 'service', 'status' => 'granted', 'source' => 'browser_fixture']]]);
        $category = Category::query()->firstOrCreate(['code' => 'PARTY-BROWSER'], ['name_ar' => 'مستهلكات حفلات', 'name_en' => 'Party consumables', 'status' => 'active']);
        $product = Product::query()->firstOrCreate(['item_code' => 'PARTY-CUPS'], ['name_ar' => 'أكواب حفلات', 'name_en' => 'Party cups', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->updateOrCreate(['product_id' => $product->id, 'store_id' => $party->id], ['on_hand' => '20', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '1', 'total_value' => '20', 'version' => 1]);
        $this->command?->info("Party fixture ready: store={$party->id}, customer={$customer->id}, product={$product->id}");
    }
}
