<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Retail\Models\Sale;
use Tests\Support\Module7TestCase;

final class Module7SaleFixtureTest extends Module7TestCase
{
    public function test_fixture_posts_completed_sales_and_a_price_free_gift_receipt(): void
    {
        $sale1 = $this->sale1->fresh(['lines']);
        $sale2 = $this->sale2->fresh(['lines']);
        self::assertSame('approved', $sale1->status);
        self::assertSame('approved', $sale2->status);
        self::assertNotNull($sale1->approved_at);
        self::assertNotNull($sale2->approved_at);

        $sale1Line = $sale1->lines->sole();
        self::assertSame($this->productA->id, $sale1Line->product_id);
        self::assertSame('5.000000', $sale1Line->quantity);
        self::assertSame(2, $sale2->lines->count());
        self::assertSame('1.000000', $sale2->lines->firstWhere('product_id', $this->productA->id)->quantity);
        self::assertSame('2.000000', $sale2->lines->firstWhere('product_id', $this->productB->id)->quantity);
        self::assertSame(2, StockMovement::query()->where('store_id', $this->store->id)->where('movement_type', 'opening_inventory')->count());

        foreach ([$sale1, $sale2] as $sale) {
            foreach ($sale->lines as $line) {
                $movement = StockMovement::query()
                    ->where('source_type', Sale::class)
                    ->where('source_id', (string) $sale->id)
                    ->where('source_line_id', $line->id)
                    ->firstOrFail();

                self::assertSame('sale', $movement->movement_type);
                self::assertSame('-'.$line->quantity, $movement->quantity);
            }
        }

        self::assertSame('14.000000', StockBalance::query()->where('product_id', $this->productA->id)->where('store_id', $this->store->id)->value('on_hand'));
        self::assertSame('18.000000', StockBalance::query()->where('product_id', $this->productB->id)->where('store_id', $this->store->id)->value('on_hand'));

        $receipt = $this->giftReceipt->fresh(['sale', 'lines.saleLine']);
        self::assertSame($sale1->id, $receipt->sale_id);
        self::assertSame($sale1Line->id, $receipt->lines->sole()->sale_line_id);
        self::assertSame('active', $receipt->status);
        self::assertArrayNotHasKey('unit_price', $receipt->lines->sole()->getAttributes());
        self::assertArrayNotHasKey('total_amount', $receipt->lines->sole()->getAttributes());

        $this->actingAs($this->cashier)
            ->get(route('gift.receipts.print', $receipt))
            ->assertOk()
            ->assertSee(__('Price-free return reference'))
            ->assertDontSee('123.45')
            ->assertDontSee('617.25');
    }

    public function test_return_fixture_data_references_a_real_sale_line_and_valid_return_store(): void
    {
        $data = $this->returnData($this->sale1, 0, 1, 'damaged', 'quarantine', 'cash_refund');
        $line = $this->sale1->lines()->sole();

        self::assertSame($this->sale1->id, $data['source_sale_id']);
        self::assertSame($line->id, $data['lines'][0]['sale_line_id']);
        self::assertSame((string) $this->damagedStore->id, (string) $data['lines'][0]['return_store_id']);
        self::assertSame('damaged', $data['lines'][0]['condition']);
        self::assertSame('quarantine', $data['lines'][0]['disposition']);
    }
}
