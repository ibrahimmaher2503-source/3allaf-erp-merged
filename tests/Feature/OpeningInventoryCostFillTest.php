<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Inventory\Actions\ApproveOpeningInventoryAction;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Approving opening stock copies the line's unit cost to a Product Card that has no cost,
 * and never overwrites a cost that is already there. Runs against MariaDB/MySQL inside a
 * transaction that is always rolled back.
 */
final class OpeningInventoryCostFillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_approval_fills_only_empty_product_costs_with_the_first_positive_line(): void
    {
        $now = now();
        $suffix = substr(md5((string) microtime(true)), 0, 6);
        $companyId = DB::table('companies')->insertGetId(['code' => 'OCF-'.$suffix, 'name_ar' => 'شركة اختبار', 'name_en' => 'Cost Fill Co', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'OCF-B-'.$suffix, 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeA = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'OCF-SA-'.$suffix, 'type' => 'selling', 'name_ar' => 'مخزن أ', 'name_en' => 'Store A', 'created_at' => $now, 'updated_at' => $now]);
        $storeB = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'OCF-SB-'.$suffix, 'type' => 'selling', 'name_ar' => 'مخزن ب', 'name_en' => 'Store B', 'created_at' => $now, 'updated_at' => $now]);
        $categoryId = DB::table('categories')->insertGetId(['code' => 'OCF-C-'.$suffix, 'name_ar' => 'أعلاف', 'name_en' => 'Feed', 'created_at' => $now, 'updated_at' => $now]);
        $product = fn (string $code, ?string $cost): int => DB::table('products')->insertGetId(['item_code' => $code.'-'.$suffix, 'name_ar' => 'منتج '.$code, 'name_en' => 'Product '.$code, 'category_id' => $categoryId, 'average_cost' => $cost, 'created_at' => $now, 'updated_at' => $now]);
        $noCost = $product('NOCOST', null);
        $zeroCost = $product('ZERO', '0');
        $priced = $product('PRICED', '15.5');
        $userId = DB::table('users')->insertGetId(['name' => 'Cost Fill Tester', 'username' => 'ocf-'.$suffix, 'email' => 'ocf-'.$suffix.'@example.test', 'password' => bcrypt('unused-'.$suffix), 'created_at' => $now, 'updated_at' => $now]);

        $document = new OpeningInventoryDocument;
        $document->forceFill(['company_id' => $companyId, 'document_number' => 'OCF-'.$suffix, 'status' => 'draft', 'created_by' => $userId, 'idempotency_key' => 'ocf-'.$suffix, 'lock_version' => 0])->save();
        foreach ([[$noCost, $storeA, '42.25'], [$noCost, $storeB, '99'], [$zeroCost, $storeA, '7.5'], [$priced, $storeA, '77']] as [$productId, $storeId, $cost]) {
            $document->lines()->create(['product_id' => $productId, 'store_id' => $storeId, 'quantity' => '1', 'unit_cost' => $cost, 'total_value' => $cost]);
        }
        $locks = DB::table('products')->whereIn('id', [$noCost, $zeroCost, $priced])->pluck('lock_version', 'id');

        $fill = new ReflectionMethod(ApproveOpeningInventoryAction::class, 'fillEmptyProductCosts');
        $filled = $fill->invoke(new ApproveOpeningInventoryAction, $document);

        $after = DB::table('products')->whereIn('id', [$noCost, $zeroCost, $priced])->get(['id', 'average_cost', 'lock_version'])->keyBy('id');
        self::assertEqualsCanonicalizing([$noCost, $zeroCost], $filled);
        self::assertSame(0, bccomp((string) $after[$noCost]->average_cost, '42.25', 4), 'Empty cost takes the first positive line (store A), not store B.');
        self::assertSame(0, bccomp((string) $after[$zeroCost]->average_cost, '7.5', 4), 'A zero cost counts as empty.');
        self::assertSame(0, bccomp((string) $after[$priced]->average_cost, '15.5', 4), 'An existing cost is never overwritten.');
        self::assertSame((int) $locks[$noCost] + 1, (int) $after[$noCost]->lock_version);
        self::assertSame((int) $locks[$priced], (int) $after[$priced]->lock_version);
    }

    public function test_approval_runs_the_cost_fill_after_posting_and_records_it_in_the_audit(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Modules/Inventory/Actions/ApproveOpeningInventoryAction.php');
        $posting = strpos($source, "'opening_inventory'");
        $fill = strpos($source, '$costsFilled = $this->fillEmptyProductCosts($document);');
        self::assertNotFalse($posting);
        self::assertNotFalse($fill);
        self::assertGreaterThan($posting, $fill, 'Costs are filled only after every line is posted, inside the same transaction.');
        self::assertStringContainsString("'product_costs_filled' => \$costsFilled", $source);
    }
}
