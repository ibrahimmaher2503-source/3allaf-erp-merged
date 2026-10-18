<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class InventoryMultiDestinationTransferFocusedTest extends TestCase
{
    private User $user;

    private Product $product;

    private Store $source;

    private Store $destinationA;

    private Store $destinationB;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $company = Company::query()->create(['code' => 'QA-'.uniqid(), 'name_ar' => 'شركة اختبار', 'name_en' => 'QA Company', 'currency_code' => 'EGP', 'currency_symbol' => 'EGP', 'timezone' => 'UTC', 'locale_default' => 'en', 'status' => 'active']);
        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => 'QA-BR-'.uniqid(), 'name_ar' => 'فرع اختبار', 'name_en' => 'QA Branch', 'timezone' => 'UTC', 'status' => 'active']);
        $this->source = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'QA-S-'.uniqid(), 'type' => 'warehouse', 'name_ar' => 'المصدر', 'name_en' => 'Source', 'status' => 'active', 'allows_negative_stock' => false]);
        $this->destinationA = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'QA-A-'.uniqid(), 'type' => 'warehouse', 'name_ar' => 'الوجهة أ', 'name_en' => 'Destination A', 'status' => 'active', 'allows_negative_stock' => false]);
        $this->destinationB = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'QA-B-'.uniqid(), 'type' => 'warehouse', 'name_ar' => 'الوجهة ب', 'name_en' => 'Destination B', 'status' => 'active', 'allows_negative_stock' => false]);
        $this->user = User::query()->create(['name' => 'QA Transfer User', 'email' => 'qa-transfer-'.uniqid().'@example.test', 'password' => 'password', 'status' => 'active', 'is_super_admin' => true]);
        $this->user->forceFill(['is_super_admin' => true])->save();
        Auth::login($this->user);
        Gate::define('transfers.create', static fn (): bool => true);
        Gate::define('transfers.dispatch', static fn (): bool => true);
        Gate::define('transfers.receive', static fn (): bool => true);
        $this->product = Product::factory()->create(['status' => 'active', 'item_code' => 'QA-SKU-'.uniqid(), 'name_ar' => 'منتج اختبار', 'sale_price' => 20, 'average_cost' => 10]);
        Barcode::query()->create(['product_id' => $this->product->id, 'barcode' => 'QA-BAR-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $this->balance($this->source, 10);
        $this->balance($this->destinationA, 0);
        $this->balance($this->destinationB, 0);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Auth::logout();
        parent::tearDown();
    }

    public function test_dispatches_one_line_to_two_destinations_once_and_preserves_independence(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        $transfer = app(DispatchStockTransferAction::class)->execute($transfer->id);
        self::assertSame('5.000000', (string) StockBalance::query()->where('store_id', $this->source->id)->value('on_hand'));
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('in_transit'));
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('in_transit'));
        self::assertSame(1, StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->where('movement_type', 'transfer_dispatch')->count());
        self::assertSame('3.000000', (string) $transfer->lines->first()->allocations->where('destination_store_id', $this->destinationA->id)->value('quantity'));
        self::assertSame('2.000000', (string) $transfer->lines->first()->allocations->where('destination_store_id', $this->destinationB->id)->value('quantity'));
        self::assertNotSame('3', (string) $transfer->lines->first()->allocations->where('destination_store_id', $this->destinationB->id)->value('quantity'));
    }

    public function test_partial_and_full_allocations_have_expected_remaining_stock(): void
    {
        $partial = $this->approvedTransfer('4', '2', '2');
        app(DispatchStockTransferAction::class)->execute($partial->id);
        self::assertSame('6.000000', (string) StockBalance::query()->where('store_id', $this->source->id)->value('on_hand'));
        $this->balance($this->source, 10);
        $full = $this->approvedTransfer('10', '4', '6');
        app(DispatchStockTransferAction::class)->execute($full->id);
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->source->id)->value('on_hand'));
    }

    public function test_over_allocation_is_rejected_before_any_movement(): void
    {
        $before = StockMovement::query()->count();
        $this->expectException(InvalidArgumentException::class);
        try {
            app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '10', 'allocations' => [$this->destinationA->id => '6', $this->destinationB->id => '5']]], idempotencyKey: 'qa-over-'.uniqid());
        } finally {
            self::assertSame($before, StockMovement::query()->count());
        }
    }

    public function test_receive_is_independent_per_destination_and_idempotent(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $transfer = StockTransfer::query()->with('lines.allocations')->findOrFail($transfer->id);
        $line = $transfer->lines->first();
        $a = $line->allocations->firstWhere('destination_store_id', $this->destinationA->id);
        $b = $line->allocations->firstWhere('destination_store_id', $this->destinationB->id);

        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$a->id => '3'], null, null);
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('in_transit'));
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('in_transit'));
        self::assertSame('in_transit', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));

        self::assertThrows(fn (): StockTransfer => app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$a->id => '3'], null, null), InvalidArgumentException::class);
        self::assertSame(1, StockMovement::query()->where('movement_type', 'transfer_receipt')->count());

        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$b->id => '2'], null, null);
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('in_transit'));
        self::assertSame('received', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));
        self::assertSame(2, StockMovement::query()->where('movement_type', 'transfer_receipt')->count());
        self::assertThrows(fn (): StockTransfer => app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$b->id => '2'], null, null), InvalidArgumentException::class);
    }

    public function test_receive_order_b_then_a_also_completes_allocation_lifecycle(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = StockTransfer::query()->with('lines.allocations')->findOrFail($transfer->id)->lines->first();
        $a = $line->allocations->firstWhere('destination_store_id', $this->destinationA->id);
        $b = $line->allocations->firstWhere('destination_store_id', $this->destinationB->id);
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$b->id => '2'], null, null);
        self::assertSame('in_transit', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$a->id => '3'], null, null);
        self::assertSame('received', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('on_hand'));
    }

    public function test_legacy_single_destination_transfer_still_receives_normally(): void
    {
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '3']], idempotencyKey: 'qa-legacy-'.uniqid());
        $transfer->update(['status' => 'approved', 'approved_by' => $this->user->id, 'approved_at' => now()]);
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = StockTransfer::query()->with('lines')->findOrFail($transfer->id)->lines->first();
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '3'], null, null);
        self::assertSame('received', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));
    }

    public function test_direct_source_balance_is_authoritative(): void
    {
        $this->balance($this->destinationA, 99);
        self::assertSame('10.000000', (string) StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $this->source->id)->value('on_hand'));
        self::assertSame('99.000000', (string) StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $this->destinationA->id)->value('on_hand'));
    }

    public function test_source_availability_bounds_requested_quantity_before_draft_save(): void
    {
        $this->balance($this->source, 0);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-zero-'.uniqid()), InvalidArgumentException::class);

        $this->balance($this->source, 5);
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '5']], idempotencyKey: 'qa-max-'.uniqid());
        self::assertSame('5', (string) $transfer->lines()->firstOrFail()->quantity_requested);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '6']], idempotencyKey: 'qa-over-'.uniqid()), InvalidArgumentException::class);
    }

    public function test_unauthorized_store_is_rejected(): void
    {
        $this->user->is_super_admin = false;
        $this->user->save();
        Gate::define('transfers.create', static fn (): bool => false);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-auth-'.uniqid()), AuthorizationException::class);
    }

    private function approvedTransfer(string $requested, string $a, string $b): StockTransfer
    {
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => $requested, 'allocations' => [$this->destinationA->id => $a, $this->destinationB->id => $b]]], idempotencyKey: 'qa-transfer-'.uniqid());
        $transfer->update(['status' => 'approved', 'approved_by' => $this->user->id, 'approved_at' => now()]);

        return $transfer->fresh(['lines.allocations']);
    }

    private function balance(Store $store, int $onHand): void
    {
        StockBalance::query()->updateOrCreate(['product_id' => $this->product->id, 'store_id' => $store->id], ['on_hand' => $onHand, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => $onHand * 10, 'version' => 1]);
    }
}
