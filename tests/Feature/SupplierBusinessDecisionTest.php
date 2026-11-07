<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

final class SupplierBusinessDecisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_supplier_groups_has_one_primary_add_action(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor)->withoutVite();
        Gate::before(static fn (): bool => true);

        $response = $this->get(route('catalog.suppliers', ['section' => 'supplier-groups']))->assertOk();
        self::assertSame(1, substr_count($response->getContent(), 'data-guide="supplier-groups-add-action"'));
    }

    public function test_tax_id_unique_constraint_allows_multiple_missing_values_but_rejects_duplicates(): void
    {
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-001',
            'name_ar' => 'مورد اختبار 1',
            'name_en' => 'QA Supplier 1',
            'tax_number' => null,
            'country_code' => 'EG',
        ]);
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-002',
            'name_ar' => 'مورد اختبار 2',
            'name_en' => 'QA Supplier 2',
            'tax_number' => null,
            'country_code' => 'EG',
        ]);

        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-003',
            'name_ar' => 'مورد اختبار 3',
            'name_en' => 'QA Supplier 3',
            'tax_number' => '123456789',
            'country_code' => 'EG',
        ]);

        $this->expectException(QueryException::class);
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-004',
            'name_ar' => 'مورد اختبار 4',
            'name_en' => 'QA Supplier 4',
            'tax_number' => '123456789',
            'country_code' => 'EG',
        ]);
    }

    public function test_settlement_and_preferred_payment_methods_persist_independently_and_are_audited(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor);
        Gate::before(static fn (): bool => true);

        $bankTransfer = PaymentMethod::factory()->create([
            'code' => 'QA-BANK-31',
            'name_en' => 'Bank transfer',
            'name_ar' => 'تحويل بنكي',
            'type' => 'bank_transfer',
        ]);
        $cash = PaymentMethod::factory()->create([
            'code' => 'QA-CASH-31',
            'name_en' => 'Cash',
            'name_ar' => 'نقدي',
        ]);

        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'QA-SUP-31',
            'name_ar' => 'مورد اختبار 31',
            'name_en' => 'QA Supplier 31',
            'country_code' => 'EG',
            'settlement_method' => 'installments',
            'settlement_other_description' => null,
            'preferred_payment_method_id' => $bankTransfer->id,
            'status' => 'active',
        ]);

        self::assertSame('installments', $supplier->settlement_method);
        self::assertSame($bankTransfer->id, $supplier->preferred_payment_method_id);

        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => $supplier->code,
            'name_ar' => $supplier->name_ar,
            'name_en' => $supplier->name_en,
            'country_code' => $supplier->country_code,
            'settlement_method' => 'cash',
            'settlement_other_description' => null,
            'preferred_payment_method_id' => $bankTransfer->id,
            'status' => 'active',
        ], $supplier->id, $supplier->lock_version);

        self::assertSame('cash', $supplier->settlement_method);
        self::assertSame($bankTransfer->id, $supplier->preferred_payment_method_id);

        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => $supplier->code,
            'name_ar' => $supplier->name_ar,
            'name_en' => $supplier->name_en,
            'country_code' => $supplier->country_code,
            'settlement_method' => 'cash',
            'settlement_other_description' => null,
            'preferred_payment_method_id' => $cash->id,
            'status' => 'active',
        ], $supplier->id, $supplier->lock_version);

        self::assertSame('cash', $supplier->settlement_method);
        self::assertSame($cash->id, $supplier->preferred_payment_method_id);
        $audit = AuditLog::query()->where('event', 'update_supplier')->latest('id')->firstOrFail();
        self::assertSame('cash', $audit->before_values['settlement_method']);
        self::assertSame($cash->id, $audit->after_values['preferred_payment_method_id']);
    }

    public function test_supplier_detail_displays_settlement_and_preferred_payment_separately(): void
    {
        Company::factory()->create(['status' => 'active']);
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor);
        Gate::before(static fn (): bool => true);
        $paymentMethod = PaymentMethod::factory()->create([
            'code' => 'QA-WALLET-31',
            'name_en' => 'Wallet',
            'name_ar' => 'محفظة',
            'type' => 'wallet',
        ]);
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'QA-SUP-31-D',
            'name_ar' => 'مورد تفاصيل 31',
            'name_en' => 'QA Supplier Detail 31',
            'country_code' => 'EG',
            'settlement_method' => 'installments',
            'settlement_other_description' => null,
            'preferred_payment_method_id' => $paymentMethod->id,
            'status' => 'active',
        ]);

        app()->setLocale('en');
        Livewire::test('catalog::suppliers')
            ->call('openSupplierDetailModal', $supplier->id)
            ->assertSee('Supplier Settlement Method')
            ->assertSee('Installments')
            ->assertSee('Preferred supplier payment method')
            ->assertSee('Wallet');
    }
}
