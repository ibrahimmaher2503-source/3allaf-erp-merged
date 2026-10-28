<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Pos\RefundWizard;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Module7TestCase;

final class Module7ReturnInventoryTest extends Module7TestCase
{
    public function test_partial_returns_reserve_cumulative_quantity_and_repeated_full_return_is_denied(): void
    {
        $initialStock = $this->stockOnHand($this->productA->id, $this->store->id);
        $first = $this->completeReturn($this->sale1, 1, 'm7-partial-one');
        self::assertSame(bcadd($initialStock, '1', 6), $this->stockOnHand($this->productA->id, $this->store->id));

        $second = $this->completeReturn($this->sale1, 2, 'm7-partial-two');
        self::assertSame(bcadd($initialStock, '3', 6), $this->stockOnHand($this->productA->id, $this->store->id));

        $this->assertRejected(fn () => $this->createReturn($this->sale1, $this->returnData($this->sale1, quantity: 3), $this->cashier));

        $finalKey = 'm7-final-two';
        $final = $this->completeReturn($this->sale1, 2, $finalKey);
        self::assertSame(bcadd($initialStock, '5', 6), $this->stockOnHand($this->productA->id, $this->store->id));
        $this->assertRejected(fn () => $this->createReturn($this->sale1, $this->returnData($this->sale1), $this->cashier));

        $movementCount = $this->returnMovementCount($final);
        $settlementCount = $final->settlements()->count();
        $stockAfterCompletion = $this->stockOnHand($this->productA->id, $this->store->id);

        app(RetailReturnAction::class)->complete($this->reviewer, $final, $finalKey);

        self::assertSame('completed', $first->fresh()->status);
        self::assertSame('completed', $second->fresh()->status);
        self::assertSame(1, $movementCount);
        self::assertSame(1, $settlementCount);
        self::assertSame($movementCount, $this->returnMovementCount($final));
        self::assertSame($settlementCount, $final->settlements()->count());
        self::assertSame($stockAfterCompletion, $this->stockOnHand($this->productA->id, $this->store->id));
    }

    public function test_two_partial_returns_cannot_exceed_the_final_remaining_unit(): void
    {
        $initialStock = $this->stockOnHand($this->productA->id, $this->store->id);

        $this->completeReturn($this->sale1, 2, 'm7-two-plus-two-a');
        $this->completeReturn($this->sale1, 2, 'm7-two-plus-two-b');

        self::assertSame(bcadd($initialStock, '4', 6), $this->stockOnHand($this->productA->id, $this->store->id));
        $this->assertRejected(fn () => $this->createReturn($this->sale1, $this->returnData($this->sale1, quantity: 2), $this->cashier));
        self::assertSame(2, RetailReturn::query()->where('source_sale_id', $this->sale1->id)->where('status', 'completed')->count());
        self::assertSame(bcadd($initialStock, '4', 6), $this->stockOnHand($this->productA->id, $this->store->id));
    }

    public function test_a_return_line_must_belong_to_its_declared_source_sale(): void
    {
        $data = $this->returnData($this->sale2);
        $data['lines'][0]['sale_line_id'] = $this->sale1->lines()->firstOrFail()->id;

        $this->assertRejected(fn () => $this->createReturn($this->sale2, $data, $this->cashier));

        self::assertSame(0, RetailReturn::query()->where('source_sale_id', $this->sale2->id)->count());
    }

    public function test_quarantine_is_deferred_and_condition_is_required(): void
    {
        foreach ([
            ['condition' => 'damaged', 'disposition' => 'quarantine'],
            ['condition' => 'non_sellable', 'disposition' => 'quarantine'],
            ['condition' => 'manager_review', 'disposition' => 'quarantine'],
        ] as $case) {
            $data = $this->returnData(
                $this->sale1,
                inspection: $case['condition'],
                disposition: $case['disposition'],
            );

            $this->assertRejected(fn () => $this->createReturn($this->sale1, $data, $this->cashier));
        }

        $missingCondition = $this->returnData($this->sale1);
        unset($missingCondition['lines'][0]['condition']);

        $this->assertRejected(fn () => $this->createReturn($this->sale1, $missingCondition, $this->cashier));
        self::assertSame(0, RetailReturn::query()->where('source_sale_id', $this->sale1->id)->count());
    }

    public function test_manager_review_condition_restock_posts_to_original_store_after_approval(): void
    {
        $sellingStockBefore = $this->stockOnHand($this->productA->id, $this->store->id);
        $damagedStockBefore = $this->stockOnHand($this->productA->id, $this->damagedStore->id);
        $data = $this->returnData(
            $this->sale1,
            quantity: 1,
            inspection: 'manager_review',
            disposition: 'restock',
            settlement: 'original_tender',
        );

        $this->actingAs($this->cashier);
        $action = app(RetailReturnAction::class);
        $created = $action->create($this->cashier, $data, (string) str()->uuid());
        $submitted = $action->submit($this->cashier, $created);

        $this->assertRejected(fn () => $action->complete($this->reviewer, $submitted, 'm7-manager-review-before-approval'));
        self::assertSame('submitted', $submitted->fresh()->status);
        self::assertSame($sellingStockBefore, $this->stockOnHand($this->productA->id, $this->store->id));
        self::assertSame($damagedStockBefore, $this->stockOnHand($this->productA->id, $this->damagedStore->id));

        $this->actingAs($this->reviewer);
        $approved = $action->approve($this->reviewer, $submitted->fresh());
        self::assertSame('approved', $approved->status);
        $completed = $action->complete($this->reviewer, $approved, 'm7-manager-review-restock');

        self::assertSame('completed', $completed->status);
        self::assertSame(bcadd($sellingStockBefore, '1', 6), $this->stockOnHand($this->productA->id, $this->store->id));
        self::assertSame($damagedStockBefore, $this->stockOnHand($this->productA->id, $this->damagedStore->id));
        $movement = StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $completed->id)->sole();
        self::assertSame('retail_return', $movement->movement_type);
        self::assertSame((int) $this->store->id, (int) $movement->store_id);
    }

    public function test_all_confirmed_return_conditions_restock_the_original_store(): void
    {
        $sellingStockBefore = $this->stockOnHand($this->productA->id, $this->store->id);
        $damagedStockBefore = $this->stockOnHand($this->productA->id, $this->damagedStore->id);

        $sellable = $this->completeReturn($this->sale1, 2, 'm7-mixed-sellable');
        $damaged = $this->completeReturn($this->sale1, 1, 'm7-mixed-damaged', 'damaged', 'restock');
        $nonSellable = $this->completeReturn($this->sale1, 1, 'm7-mixed-non-sellable', 'non_sellable', 'restock');

        self::assertSame(bcadd($sellingStockBefore, '4', 6), $this->stockOnHand($this->productA->id, $this->store->id));
        self::assertSame($damagedStockBefore, $this->stockOnHand($this->productA->id, $this->damagedStore->id));

        $sellableMovement = StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $sellable->id)->sole();
        $damagedMovement = StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $damaged->id)->sole();
        $nonSellableMovement = StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $nonSellable->id)->sole();
        self::assertSame('retail_return', $sellableMovement->movement_type);
        self::assertSame((int) $this->store->id, (int) $sellableMovement->store_id);
        self::assertSame('retail_return', $damagedMovement->movement_type);
        self::assertSame((int) $this->store->id, (int) $damagedMovement->store_id);
        self::assertSame('retail_return', $nonSellableMovement->movement_type);
        self::assertSame((int) $this->store->id, (int) $nonSellableMovement->store_id);
    }

    public function test_pos_refund_uses_explicit_uninspected_condition_after_sale_lookup(): void
    {
        $lineId = $this->sale1->lines()->firstOrFail()->id;

        Livewire::actingAs($this->cashier)
            ->test(RefundWizard::class)
            ->set('lookupTerm', $this->sale1->document_number)
            ->call('lookup')
            ->assertSet('conditions.'.$lineId, 'uninspected');
    }

    private function completeReturn(
        Sale $sale,
        int $quantity,
        string $completionKey,
        string $condition = 'sellable',
        string $disposition = 'restock',
    ): RetailReturn {
        $data = $this->returnData(
            $sale,
            quantity: $quantity,
            inspection: $condition,
            disposition: $disposition,
            settlement: 'original_tender',
        );
        $approved = $this->createApprovedReturn($data);

        return app(RetailReturnAction::class)->complete($this->reviewer, $approved, $completionKey);
    }

    /** @param array<string, mixed> $data */
    private function createApprovedReturn(array $data): RetailReturn
    {
        $this->actingAs($this->cashier);
        $action = app(RetailReturnAction::class);
        $created = $action->create($this->cashier, $data, (string) str()->uuid());
        $submitted = $action->submit($this->cashier, $created);

        $this->actingAs($this->reviewer);

        return $action->approve($this->reviewer, $submitted);
    }

    private function stockOnHand(int $productId, int $storeId): string
    {
        $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->value('on_hand');

        return bcadd((string) ($balance ?? '0'), '0', 6);
    }

    private function returnMovementCount(RetailReturn $return): int
    {
        return StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $return->id)->count();
    }

    /** @param callable(): mixed $operation */
    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the return operation to be rejected.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
        }
    }
}
