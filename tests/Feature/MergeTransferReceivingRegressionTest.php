<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SaveStockTransferReceivingDraftAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;

final class MergeTransferReceivingRegressionTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    public function test_final_draft_posts_only_the_remaining_quantity_after_partial_receipt(): void
    {
        [$transfer, $source, $destination, $product] = $this->approvedTransfer('2.500000');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $action = app(ReceiveStockTransferAction::class);
        $lineId = $transfer->lines->first()->id;
        $action->execute($transfer->id, [$lineId => '1'], null, null, 'partial-'.$transfer->id);
        app(SaveStockTransferReceivingDraftAction::class)->execute($transfer->id, $destination->id, [$lineId => '2.5'], null, null);
        $result = $action->finalizeDraft($transfer->id, $destination->id);
        self::assertSame('received', $result->status);
        self::assertSame('2.500000', $result->lines->first()->quantity_received);
        self::assertSame('2.500000', $this->balance($product, $destination)->on_hand);
        self::assertSame('0.000000', $this->balance($product, $destination)->in_transit);
    }

    public function test_final_draft_cannot_reduce_an_already_posted_receipt(): void
    {
        [$transfer, , $destination, $product] = $this->approvedTransfer('2.500000');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $action = app(ReceiveStockTransferAction::class);
        $lineId = $transfer->lines->first()->id;
        $action->execute($transfer->id, [$lineId => '1'], null, null, 'partial-'.$transfer->id);
        app(SaveStockTransferReceivingDraftAction::class)->execute($transfer->id, $destination->id, [$lineId => '0.5'], 'shortage', 'Test shortage');
        try {
            $action->finalizeDraft($transfer->id, $destination->id);
            self::fail('An already posted receipt was reduced.');
        } catch (InvalidArgumentException) {
            self::assertSame('1.000000', $this->balance($product, $destination)->on_hand);
            self::assertSame('1.500000', $this->balance($product, $destination)->in_transit);
        }
    }

    /** @return array{StockTransfer, Store, Store, Product} */
    private function approvedTransfer(string $quantity): array
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $actor->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $source = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $destination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->fractional()->create();
        app(PostInventoryMovement::class)->execute($product->id, $source->id, '5', 'test_opening', '10', 'opening-'.str()->uuid());
        $this->balance($product, $destination);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'P03-'.str()->upper(str()->random(8)),
            'source_store_id' => $source->id,
            'destination_store_id' => $destination->id,
            'status' => 'approved',
            'reason_code' => 'replenishment',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'idempotency_key' => (string) str()->uuid(),
        ]);
        $transfer->lines()->create(['product_id' => $product->id, 'quantity_requested' => $quantity, 'unit_cost' => '10']);

        return [$transfer->fresh('lines'), $source, $destination, $product];
    }

    private function balance(Product $product, Store $store): StockBalance
    {
        return StockBalance::query()->firstOrCreate(
            ['product_id' => $product->id, 'store_id' => $store->id],
            ['on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0],
        )->fresh();
    }
}
