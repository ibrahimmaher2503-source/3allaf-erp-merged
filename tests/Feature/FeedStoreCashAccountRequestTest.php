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
 * The HTTP layer receives cash_account_id as a string. Posting a cash customer
 * receipt or supplier payment through the real route must not fail with a
 * TypeError (regression: the controller passed the raw string to a ?int).
 */
final class FeedStoreCashAccountRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_cash_customer_receipt_posts_through_the_http_route(): void
    {
        $now = now();
        $userId = DB::table('users')->insertGetId(['name' => 'Receipt HTTP', 'email' => 'receipt-http@test.invalid', 'password' => 'x', 'is_super_admin' => true, 'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $companyId = DB::table('companies')->insertGetId(['code' => 'RH-T', 'name_ar' => 'اختبار', 'name_en' => 'RH Test', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'RH-B', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeId = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'RH-S', 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('user_branch_scopes')->insert(['user_id' => $userId, 'branch_id' => $branchId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $cashAccountId = DB::table('cash_accounts')->insertGetId(['company_id' => $companyId, 'code' => 'RH-CASH', 'name_ar' => 'خزينة', 'name_en' => 'Cash', 'type' => 'cash', 'currency_code' => 'EGP', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $methodId = DB::table('payment_methods')->insertGetId(['code' => 'RH-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'created_at' => $now, 'updated_at' => $now]);
        $customerId = DB::table('customers')->insertGetId(['public_id' => (string) Str::uuid(), 'phone_normalized' => null, 'phone_display' => null, 'name_ar' => 'عميل آجل', 'name_en' => 'Credit Customer', 'status' => 'active', 'created_by' => $userId, 'created_branch_id' => $branchId, 'created_store_id' => $storeId, 'customer_type' => 'credit', 'credit_limit' => '5000.0000', 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);

        $response = $this->actingAs(User::query()->findOrFail($userId))->post(route('feed-store.customer-receipts.store'), [
            'customer_id' => $customerId,
            'collection_store_id' => $storeId,
            'currency_code' => 'EGP',
            'payment_method_id' => $methodId,
            'cash_account_id' => (string) $cashAccountId,
            'amount' => '100.00',
            'date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertSame(1, DB::table('customer_receipts')->where('customer_id', $customerId)->where('cash_account_id', $cashAccountId)->count());
    }
}
