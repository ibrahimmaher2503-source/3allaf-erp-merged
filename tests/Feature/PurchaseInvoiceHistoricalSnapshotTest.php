<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A printed purchase invoice must keep the product name and code it had when the
 * invoice was written, even after the catalog product is renamed or repriced.
 */
final class PurchaseInvoiceHistoricalSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_invoice_print_keeps_original_product_name_after_rename_and_reprice(): void
    {
        $now = now();
        $userId = DB::table('users')->insertGetId(['name' => 'Snap', 'email' => 'snap@test.invalid', 'password' => 'x', 'is_super_admin' => true, 'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $companyId = DB::table('companies')->insertGetId(['code' => 'SN-T', 'name_ar' => 'اختبار', 'name_en' => 'Snap Co', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'SN-B', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeId = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'SN-S', 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('user_branch_scopes')->insert(['user_id' => $userId, 'branch_id' => $branchId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $categoryId = DB::table('categories')->insertGetId(['code' => 'SN-C', 'name_ar' => 'تصنيف', 'name_en' => 'Cat', 'created_at' => $now, 'updated_at' => $now]);
        $supplierId = DB::table('suppliers')->insertGetId(['code' => 'SN-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'created_at' => $now, 'updated_at' => $now]);
        $productId = DB::table('products')->insertGetId(['item_code' => 'SNAP-OLD-CODE', 'name_ar' => 'اسم قديم', 'name_en' => 'Old Feed Name', 'category_id' => $categoryId, 'sale_price' => 14, 'average_cost' => 10, 'created_at' => $now, 'updated_at' => $now]);
        $invoiceId = DB::table('purchase_invoices')->insertGetId(['invoice_number' => 'SNAP-PI-1', 'supplier_id' => $supplierId, 'store_id' => $storeId, 'invoice_date' => $now->toDateString(), 'currency_code' => 'EGP', 'status' => 'draft', 'subtotal' => 100, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 100, 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_invoice_lines')->insert(['purchase_invoice_id' => $invoiceId, 'product_id' => $productId, 'item_code_snapshot' => 'SNAP-OLD-CODE', 'name_ar_snapshot' => 'اسم قديم', 'name_en_snapshot' => 'Old Feed Name', 'quantity' => 10, 'unit_cost' => 10, 'subtotal' => 100, 'line_total' => 100, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('products')->where('id', $productId)->update(['item_code' => 'SNAP-NEW-CODE', 'name_ar' => 'اسم جديد', 'name_en' => 'New Feed Name', 'sale_price' => 99, 'average_cost' => 77]);

        $html = $this->actingAs(User::query()->findOrFail($userId))->get(route('purchasing.invoices.print', ['invoice' => $invoiceId]))->assertOk()->getContent();

        $this->assertTrue(str_contains($html, 'SNAP-OLD-CODE'), 'old item code expected');
        $this->assertTrue(str_contains($html, 'اسم قديم') || str_contains($html, 'Old Feed Name'), 'old product name expected');
        $this->assertFalse(str_contains($html, 'SNAP-NEW-CODE'), 'renamed code must not leak into history');
        $this->assertFalse(str_contains($html, 'New Feed Name') || str_contains($html, 'اسم جديد'), 'renamed product must not leak into history');
        $this->assertStringContainsString('10.00', $html);
    }
}
