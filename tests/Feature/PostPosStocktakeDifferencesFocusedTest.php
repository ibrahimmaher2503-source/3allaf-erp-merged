<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostPosStocktakeDifferencesFocusedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exception_report_filters_lines_without_settlement_or_stock_movement(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $count = StockCount::query()->create([
            'count_number' => 'QA-COUNT-'.Str::random(12), 'count_type' => 'full', 'scope_type' => 'full',
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
            'status' => 'in_progress', 'reference_at' => now()->subMinute(),
            'opened_at' => now()->subMinute(), 'created_by' => $fixture['admin']->id,
            'manager_id' => $fixture['admin']->id, 'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 2,
        ]);
        $count->locations()->create(['store_id' => $fixture['store']->id, 'snapshot_at' => now()->subMinute()]);
        foreach ([
            ['SHORT', true, '8', '-2'],
            ['PLUS', true, '12', '2'],
            ['MISS', false, null, '0'],
            ['MATCH', true, '10', '0'],
        ] as [$suffix, $isCounted, $quantity, $variance]) {
            $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => 'QA-R7-'.$suffix]);
            $count->lines()->create([
                'store_id' => $fixture['store']->id, 'product_id' => $product->id,
                'reference_on_hand' => '10', 'counted_quantity' => $quantity,
                'variance_quantity' => $variance, 'is_counted' => $isCounted,
                'counted_at' => $isCounted ? now() : null,
            ]);
        }

        $before = StockMovement::query()->count();
        $base = route('inventory.counts.reconcile-page', $count);
        $this->get($base.'?count_filter=differences')->assertOk()
            ->assertSee('QA-R7-SHORT')->assertSee('QA-R7-PLUS')->assertSee('QA-R7-MISS')->assertDontSee('QA-R7-MATCH');
        $this->get($base.'?count_filter=shortage')->assertOk()
            ->assertSee('QA-R7-SHORT')->assertDontSee('QA-R7-PLUS')->assertDontSee('QA-R7-MISS');
        $this->get($base.'?count_filter=excess')->assertOk()
            ->assertSee('QA-R7-PLUS')->assertDontSee('QA-R7-SHORT');
        $this->get($base.'?count_filter=not_counted')->assertOk()
            ->assertSee('QA-R7-MISS')->assertDontSee('QA-R7-MATCH');
        self::assertSame($before, StockMovement::query()->count());
        self::assertSame('in_progress', $count->fresh()->status);
    }
}
