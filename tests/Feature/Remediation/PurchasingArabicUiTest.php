<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use Tests\TestCase;

final class PurchasingArabicUiTest extends TestCase
{
    public function test_purchase_invoice_readiness_renders_its_arabic_decision_context(): void
    {
        $administrator = User::query()
            ->where('username', 'local.system-administrator')
            ->firstOrFail();

        $this->withSession(['locale' => 'ar'])
            ->actingAs($administrator)
            ->get(route('purchasing.invoices.readiness'))
            ->assertOk()
            ->assertSee('جاهزية فواتير المشتريات')
            ->assertSee('سياسة التكلفة');
    }

    public function test_supplier_returns_empty_state_is_fully_arabic(): void
    {
        $administrator = User::query()
            ->where('username', 'local.system-administrator')
            ->firstOrFail();

        $this->withSession(['locale' => 'ar'])
            ->actingAs($administrator)
            ->get(route('purchasing.returns'))
            ->assertOk()
            ->assertSee('لا توجد مرتجعات للموردين حتى الآن.')
            ->assertDontSee('لا المورد returns yet.');
    }
}
