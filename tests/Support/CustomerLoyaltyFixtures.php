<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Str;

trait CustomerLoyaltyFixtures
{
    protected function configureCustomerLoyaltyPolicies(User $administrator): void
    {
        $this->actingAs($administrator);
        $settings = [
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => json_encode(['service', 'loyalty'], JSON_THROW_ON_ERROR),
            'customer.consent.wording' => json_encode(['version' => 'TSK027-LOCAL-V1', 'text' => 'Local test consent wording.'], JSON_THROW_ON_ERROR),
            'customer.consent.retention' => json_encode(['days' => 365], JSON_THROW_ON_ERROR),
            'customer.children.purpose_scope' => json_encode(['birthday'], JSON_THROW_ON_ERROR),
            'loyalty.retail_rule' => json_encode(['earn_points_per_currency' => '1', 'redeem_currency_per_point' => '0.01'], JSON_THROW_ON_ERROR),
            'loyalty.expiry_policy' => json_encode(['days' => 30], JSON_THROW_ON_ERROR),
            'loyalty.rounding_policy' => json_encode(['earn' => 'floor', 'redeem' => 'floor'], JSON_THROW_ON_ERROR),
            'loyalty.approval_policy' => json_encode(['adjustment_requires_approval' => true], JSON_THROW_ON_ERROR),
            'loyalty.ledger_integrity' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
        ];

        foreach ($settings as $key => $value) {
            app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'TSK-027 automated test policy.');
        }
    }

    protected function configureWalletPolicies(User $administrator): void
    {
        $this->actingAs($administrator);
        $settings = [
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
            app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'TSK-028 automated test policy; not a production financial value.');
        }
    }

    protected function createTestCustomer(User $actor, Store $store, string $phone = '01012345678', ?string $idempotencyKey = null): Customer
    {
        $this->actingAs($actor);

        return app(CreateCustomerAction::class)->execute($actor, $store, [
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'phone' => $phone,
            'name_ar' => 'عميل اختبار',
            'name_en' => 'Test Customer',
            'email' => 'customer@example.test',
            'secondary_phone' => '01012345679',
            'address_ar' => 'عنوان الاختبار',
            'address_en' => 'Test address',
            'consents' => [['purpose' => 'loyalty', 'status' => 'granted', 'source' => 'profile_create']],
        ]);
    }

    protected function approvedCustomerSale(Customer $customer, Store $store, User $cashier, int $subtotal = 100, int $discount = 0): Sale
    {
        return Sale::query()->create([
            'branch_id' => $store->branch_id,
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'document_number' => 'TSK027-SALE-'.Str::upper(Str::random(8)),
            'status' => 'approved',
            'idempotency_key' => 'TSK027-SALE-IDEM-'.Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => 0,
            'tax_applicable' => false,
            'cash_rounding_amount' => 0,
            'payable_total' => $subtotal - $discount,
            'total' => $subtotal - $discount,
            'paid_total' => $subtotal - $discount,
            'change_total' => 0,
            'currency_code' => 'EGP',
            'approved_at' => now(),
            'lock_version' => 2,
        ]);
    }
}
