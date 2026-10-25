<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SaveStockTransferReceivingDraftAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Actions\UpdateStockTransferDraftAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceivingDraft;
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
        $this->user->storeScopes()->create(['store_id' => $this->source->id, 'status' => 'active']);
        Auth::login($this->user);
        Gate::define('transfers.create', static fn (): bool => true);
        Gate::define('transfers.edit', static fn (): bool => true);
        Gate::define('inventory_stock_card.view', static fn (): bool => true);
        Gate::define('transfers.dispatch', static fn (): bool => true);
        Gate::define('transfers.receive', static fn (): bool => true);
        Gate::define('transfers.view', static fn (): bool => true);
        Gate::define('transfers.approve', static fn (): bool => true);
        Gate::define('transfers.submit', static fn (): bool => true);
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
        self::assertSame(1, StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->where('movement_type', 'transfer_receipt')->count());

        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$b->id => '2'], null, null);
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('in_transit'));
        self::assertSame('received', (string) StockTransfer::query()->whereKey($transfer->id)->value('status'));
        self::assertSame(2, StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->where('movement_type', 'transfer_receipt')->count());
        self::assertThrows(fn (): StockTransfer => app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$b->id => '2'], null, null), InvalidArgumentException::class);
    }

    public function test_destination_receiving_draft_can_be_reopened_then_finalized_independently_with_difference(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = $transfer->fresh('lines.allocations')->lines->first();
        $a = $line->allocations->firstWhere('destination_store_id', $this->destinationA->id);
        $b = $line->allocations->firstWhere('destination_store_id', $this->destinationB->id);
        $beforeMovements = StockMovement::query()->count();

        $draft = app(SaveStockTransferReceivingDraftAction::class)->execute($transfer->id, $this->destinationA->id, [$line->id.'.'.$a->id => '2'], 'shortage', 'One unit missing');
        self::assertSame('2', $draft->fresh()->received_quantities[$line->id.'.'.$a->id]);
        self::assertNull($draft->fresh()->finalized_at);
        self::assertSame($beforeMovements, StockMovement::query()->count());
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));

        app(ReceiveStockTransferAction::class)->finalizeDraft($transfer->id, $this->destinationA->id);
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->value('in_transit'));
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('in_transit'));
        self::assertSame('in_transit', $transfer->fresh()->status);
        self::assertNotNull($draft->fresh()->finalized_at);

        app(SaveStockTransferReceivingDraftAction::class)->execute($transfer->id, $this->destinationB->id, [$line->id.'.'.$b->id => '2'], null, null);
        app(ReceiveStockTransferAction::class)->finalizeDraft($transfer->id, $this->destinationB->id);
        self::assertSame('difference_review', $transfer->fresh()->status);
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $this->destinationB->id)->value('on_hand'));
        self::assertSame(2, StockTransferReceivingDraft::query()->where('stock_transfer_id', $transfer->id)->whereNotNull('finalized_at')->count());
        self::assertThrows(fn (): StockTransfer => app(ReceiveStockTransferAction::class)->finalizeDraft($transfer->id, $this->destinationA->id), InvalidArgumentException::class);
    }

    public function test_submitted_transfer_requires_line_review_version_before_approval(): void
    {
        $transfer = app(CreateStockTransferDraftAction::class)->execute(
            $this->source->id,
            $this->destinationA->id,
            [['product_id' => $this->product->id, 'quantity_requested' => '5', 'allocations' => [$this->destinationA->id => '3', $this->destinationB->id => '2']]],
            idempotencyKey: 'qa-review-'.uniqid(),
        );
        $transfer = app(SubmitStockTransferAction::class)->execute($transfer->id);
        $this->actingAs($this->user)->withoutVite();
        $this->post(route('inventory.transfers.approve', $transfer), ['review_version' => $transfer->lock_version])->assertStatus(409);
        self::assertSame('submitted', $transfer->fresh()->status);
        $this->get(route('inventory.transfers.approval-review', $transfer))->assertOk()
            ->assertSee($this->product->item_code)
            ->assertSee($this->destinationB->name_en);
        $this->post(route('inventory.transfers.approve', $transfer), [])->assertSessionHasErrors('review_version');
        self::assertSame('submitted', $transfer->fresh()->status);
        $this->post(route('inventory.transfers.approve', $transfer), ['review_version' => $transfer->lock_version]);
        self::assertSame('approved', $transfer->fresh()->status);
    }

    public function test_destination_draft_rejects_other_destinations_allocation(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = $transfer->fresh('lines.allocations')->lines->first();
        $other = $line->allocations->firstWhere('destination_store_id', $this->destinationB->id);
        self::assertThrows(fn (): StockTransferReceivingDraft => app(SaveStockTransferReceivingDraftAction::class)->execute($transfer->id, $this->destinationA->id, [$line->id.'.'.$other->id => '2'], null, null), InvalidArgumentException::class);
        self::assertSame(0, StockTransferReceivingDraft::query()->where('stock_transfer_id', $transfer->id)->count());
    }

    public function test_destination_only_receiver_sees_only_own_allocation_in_register_and_review(): void
    {
        $transfer = $this->approvedTransfer('5', '3', '2');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $receiver = User::factory()->create(['status' => 'active']);
        $receiver->storeScopes()->create(['store_id' => $this->destinationB->id, 'status' => 'active']);
        $this->actingAs($receiver)->withoutVite();

        $this->get(route('inventory.transfers'))->assertOk()
            ->assertSee($this->source->code.' → '.$this->destinationB->code.' · '.$this->product->item_code.' · 2');
        $this->get(route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $this->destinationB->id]))->assertOk()
            ->assertSee($this->product->item_code)
            ->assertDontSee($this->destinationA->code);
        $this->get(route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $this->destinationA->id]))->assertForbidden();
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

    public function test_unavailable_stock_keeps_the_draft_and_submit_does_not_post(): void
    {
        $this->balance($this->source, 0);
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '6', 'allocations' => [$this->destinationA->id => '4', $this->destinationB->id => '2']]], 'replenishment', 'Keep the note', 'qa-zero-'.uniqid());
        self::assertSame('draft', $transfer->status);
        self::assertThrows(fn (): StockTransfer => app(SubmitStockTransferAction::class)->execute($transfer->id), InvalidArgumentException::class);
        $saved = $transfer->fresh(['lines.allocations']);
        self::assertSame('draft', $saved->status);
        self::assertSame('Keep the note', $saved->notes);
        self::assertSame('6', (string) $saved->lines->first()->quantity_requested);
        self::assertSame(2, $saved->lines->first()->allocations->count());
        self::assertSame(0, StockMovement::query()->where('source_id', $transfer->id)->count());
        $editUrl = route('inventory.transfers.edit', $transfer);
        $this->get($editUrl)->assertOk()->assertSee('Keep the note')->assertSee('value="6"', false);
        $this->from($editUrl)->post(route('inventory.transfers.submit', $transfer))->assertRedirect($editUrl)->assertSessionHas('error');
        self::assertSame('draft', $transfer->fresh()->status);
        self::assertSame(0, StockMovement::query()->where('source_id', $transfer->id)->count());
    }

    public function test_failed_dispatch_rolls_back_earlier_lines_and_leaves_approved_document(): void
    {
        $unavailableProduct = Product::factory()->create(['status' => 'active', 'item_code' => 'QA-UNAVAILABLE-'.uniqid(), 'name_ar' => 'منتج غير متوفر', 'sale_price' => 20]);
        Barcode::query()->create(['product_id' => $unavailableProduct->id, 'barcode' => 'QA-UNAVAILABLE-BAR-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [
            ['product_id' => $this->product->id, 'quantity_requested' => '2'],
            ['product_id' => $unavailableProduct->id, 'quantity_requested' => '1'],
        ], idempotencyKey: 'qa-failed-dispatch-'.uniqid());
        $transfer->update(['status' => 'approved', 'approved_by' => $this->user->id, 'approved_at' => now()]);

        self::assertThrows(fn (): StockTransfer => app(DispatchStockTransferAction::class)->execute($transfer->id), InvalidArgumentException::class);
        self::assertSame('approved', $transfer->fresh()->status);
        self::assertSame('10.000000', (string) StockBalance::query()->where('store_id', $this->source->id)->where('product_id', $this->product->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $this->destinationA->id)->where('product_id', $this->product->id)->value('in_transit'));
        self::assertSame(0, StockMovement::query()->where('source_id', $transfer->id)->count());
    }

    public function test_unauthorized_store_is_rejected(): void
    {
        $this->user->is_super_admin = false;
        $this->user->save();
        Gate::define('transfers.create', static fn (): bool => false);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($this->source->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-auth-'.uniqid()), AuthorizationException::class);
    }

    public function test_active_branchless_warehouse_is_a_source_with_central_numbering(): void
    {
        $centralSource = $this->branchlessWarehouse();
        $this->balance($centralSource, 10);

        $first = app(CreateStockTransferDraftAction::class)->execute($centralSource->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '2']], idempotencyKey: 'qa-central-source-'.uniqid());
        $second = app(CreateStockTransferDraftAction::class)->execute($centralSource->id, $this->destinationB->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-central-source-'.uniqid());

        self::assertSame('CENTRAL-000001', $first->transfer_number);
        self::assertSame('CENTRAL-000002', $second->transfer_number);
        self::assertNull($centralSource->fresh()->branch_id);
        self::assertSame($centralSource->id, $first->source_store_id);
    }

    public function test_active_branchless_warehouse_is_a_destination_and_linked_source_keeps_numbering(): void
    {
        $centralDestination = $this->branchlessWarehouse();
        $transfer = app(CreateStockTransferDraftAction::class)->execute($this->source->id, $centralDestination->id, [['product_id' => $this->product->id, 'quantity_requested' => '2', 'allocations' => [$centralDestination->id => '2']]], idempotencyKey: 'qa-central-destination-'.uniqid());

        self::assertNull($centralDestination->fresh()->branch_id);
        self::assertSame($centralDestination->id, $transfer->destination_store_id);
        self::assertStringStartsWith('QA-BR-', $transfer->transfer_number);
        self::assertSame('2.000000', (string) $transfer->lines()->firstOrFail()->allocations()->firstOrFail()->quantity);
    }

    public function test_transfer_form_offers_active_branchless_warehouses_without_branch_error(): void
    {
        $central = $this->branchlessWarehouse();
        $this->actingAs($this->user);

        $response = $this->get('/inventory/transfers/create');

        $response->assertOk();
        $response->assertSee('value="'.$central->id.'"', false);
        $response->assertDontSee('المتجر المصدر لازم يكون تابع لفرع');
        self::assertNull($central->fresh()->branch_id);
    }

    public function test_branchless_source_draft_can_be_updated_without_assigning_a_branch(): void
    {
        $centralSource = $this->branchlessWarehouse();
        $this->balance($centralSource, 10);
        $transfer = app(CreateStockTransferDraftAction::class)->execute($centralSource->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '2']], idempotencyKey: 'qa-central-edit-'.uniqid());
        Gate::define('transfers.edit', static fn (): bool => true);

        $updated = app(UpdateStockTransferDraftAction::class)->execute($transfer->id, $centralSource->id, $this->destinationB->id, [['product_id' => $this->product->id, 'quantity_requested' => '3']], expectedVersion: $transfer->lock_version);

        self::assertSame($transfer->transfer_number, $updated->transfer_number);
        self::assertSame($this->destinationB->id, $updated->destination_store_id);
        self::assertNull($centralSource->fresh()->branch_id);
    }

    public function test_inactive_or_out_of_scope_branchless_warehouse_is_rejected(): void
    {
        $central = $this->branchlessWarehouse();
        $this->balance($central, 10);
        $central->update(['status' => 'inactive']);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($central->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-inactive-source-'.uniqid()), InvalidArgumentException::class);
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($this->source->id, $central->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-inactive-destination-'.uniqid()), InvalidArgumentException::class);

        $central->update(['status' => 'active']);
        $this->user->forceFill(['is_super_admin' => false])->save();
        self::assertThrows(fn (): StockTransfer => app(CreateStockTransferDraftAction::class)->execute($central->id, $this->destinationA->id, [['product_id' => $this->product->id, 'quantity_requested' => '1']], idempotencyKey: 'qa-out-of-scope-'.uniqid()), AuthorizationException::class);
    }

    private function branchlessWarehouse(): Store
    {
        $warehouse = $this->source->replicate();
        $warehouse->code = 'QA-CENTRAL-'.uniqid();
        $warehouse->branch_id = null;
        $warehouse->save();

        return $warehouse;
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
