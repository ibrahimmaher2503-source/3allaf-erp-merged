<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Quotation\Actions\CreateQuotationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class V2ProductQuotationFocusedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mixed_product_and_manual_quotation_calculates_without_posting(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $customer = Customer::factory()->create(['created_store_id' => $fixture['store']->id, 'created_branch_id' => $fixture['branch']->id]);
        $customer->scopes()->create(['branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id, 'created_by' => $fixture['admin']->id]);
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'sale_price' => '300.00']);
        $product->barcodes()->create(['barcode' => 'QA-QT-'.Str::random(12), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $productCount = Product::query()->count();
        $movementCount = StockMovement::query()->count();
        $postingTables = ['stock_balances', 'sales', 'cash_movements', 'product_price_overrides', 'price_lines'];
        $beforePosting = collect($postingTables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $quotation = app(CreateQuotationAction::class)->execute($fixture['admin'], [
            'activity_type' => 'retail', 'customer_id' => $customer->id,
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
            'valid_until' => now()->addWeek()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
            'discount_type' => 'percent', 'discount_value' => 10, 'tax_rate' => 14,
            'lines' => [
                ['line_type' => 'product', 'product_id' => $product->id, 'description_ar' => 'منتج', 'description_en' => 'Product', 'quantity' => 2, 'unit_price' => 250],
                ['line_type' => 'manual', 'product_id' => null, 'source_reference' => 'MAN-1', 'description_ar' => 'يدوي', 'description_en' => 'Manual', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        self::assertSame('600.00', $quotation->subtotal);
        self::assertSame('60.00', $quotation->discount_amount);
        self::assertSame('75.60', $quotation->tax_amount);
        self::assertSame('615.60', $quotation->total);
        self::assertNull($quotation->lines->last()->product_id);
        self::assertSame($productCount, Product::query()->count());
        self::assertSame($movementCount, StockMovement::query()->count());
        foreach ($beforePosting as $table => $count) {
            self::assertSame($count, DB::table($table)->count(), $table.' must remain unchanged');
        }
        self::assertSame('300.00', (string) $product->fresh()->sale_price);
    }
}
