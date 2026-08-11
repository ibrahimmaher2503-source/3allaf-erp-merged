<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

/**
 * TSK-027 browser-only fixture. It is intentionally separate from the normal
 * DemoSeeder so customer/loyalty evidence cannot alter another local schema.
 */
final class Tsk027BrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('TSK-027 browser fixtures require local Demo Auth.');
        }

        $administrator = User::query()->where('username', 'demo-admin')->firstOrFail();
        $store = Store::query()->where('code', 'DEMO-SELL')->where('status', 'active')->firstOrFail();
        Auth::setUser($administrator);

        $settings = [
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => json_encode(['service', 'loyalty'], JSON_THROW_ON_ERROR),
            'customer.consent.wording' => json_encode(['version' => 'TSK027-BROWSER-V1', 'text' => 'Local browser consent wording.'], JSON_THROW_ON_ERROR),
            'customer.consent.retention' => json_encode(['days' => 365], JSON_THROW_ON_ERROR),
            'customer.children.purpose_scope' => json_encode(['birthday'], JSON_THROW_ON_ERROR),
            'loyalty.retail_rule' => json_encode(['earn_points_per_currency' => '1', 'redeem_currency_per_point' => '0.01'], JSON_THROW_ON_ERROR),
            'loyalty.expiry_policy' => json_encode(['days' => 30], JSON_THROW_ON_ERROR),
            'loyalty.rounding_policy' => json_encode(['earn' => 'floor', 'redeem' => 'floor'], JSON_THROW_ON_ERROR),
            'loyalty.approval_policy' => json_encode(['adjustment_requires_approval' => true], JSON_THROW_ON_ERROR),
            'loyalty.ledger_integrity' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
            'wallet.product.credit_limit' => '1000.0000',
            'wallet.product.debt_limit' => '1000.0000',
            'wallet.product.settlement_policy' => json_encode(['enabled' => true, 'operations' => ['credit', 'debit', 'settlement']], JSON_THROW_ON_ERROR),
            'wallet.product.adjustment_policy' => json_encode(['enabled' => true, 'approval_required' => true], JSON_THROW_ON_ERROR),
            'wallet.product.visibility_scope' => json_encode(['mode' => 'branch_store'], JSON_THROW_ON_ERROR),
            'wallet.party.credit_limit' => '1000.0000',
            'wallet.party.debt_limit' => '1000.0000',
            'wallet.party.settlement_policy' => json_encode(['enabled' => true, 'operations' => ['credit', 'debit', 'settlement']], JSON_THROW_ON_ERROR),
            'wallet.party.adjustment_policy' => json_encode(['enabled' => true, 'approval_required' => true], JSON_THROW_ON_ERROR),
            'wallet.party.visibility_scope' => json_encode(['mode' => 'branch_store'], JSON_THROW_ON_ERROR),
        ];

        foreach ($settings as $key => $value) {
            app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'TSK-027 browser fixture policy.');
        }

        $existingCustomer = Customer::query()->where('phone_normalized', '01002702700')->first();

        if ($existingCustomer !== null) {
            $existingCustomer->forceFill([
                'name_ar' => 'عميل المتصفح',
                'name_en' => 'Browser customer',
                'address_ar' => 'عنوان العميل',
                'address_en' => 'Customer address',
            ])->save();

            return;
        }

        app(CreateCustomerAction::class)->execute($administrator, $store, [
            'idempotency_key' => 'TSK027-BROWSER-CUSTOMER-'.Str::uuid(),
            'phone' => '01002702700',
            'name_ar' => 'عميل المتصفح',
            'name_en' => 'Browser customer',
            'email' => 'tsk027.browser@example.test',
            'secondary_phone' => '01002702701',
            'address_ar' => 'عنوان العميل',
            'address_en' => 'Customer address',
            'consents' => [[
                'purpose' => 'loyalty',
                'status' => 'granted',
                'source' => 'browser_fixture',
            ]],
        ]);
    }
}
