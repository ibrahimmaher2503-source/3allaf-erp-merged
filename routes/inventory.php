<?php

declare(strict_types=1);

use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->get('inventory', function () {
        Gate::authorize('inventory_stock_card.view');
        $balances = StockBalance::query()->with(['product', 'store'])->orderBy('store_id')->orderBy('product_id')->get();
        $movements = StockMovement::query()->with(['product', 'store', 'creator'])->latest('posted_at')->limit(50)->get();
        $transfers = StockTransfer::query()->with(['sourceStore', 'destinationStore', 'lines.product'])->latest('id')->limit(20)->get();
        $adjustments = InventoryAdjustment::query()->with(['store', 'lines.product'])->latest('id')->limit(20)->get();
        $counts = StockCount::query()->with(['store', 'lines.product'])->latest('id')->limit(20)->get();

        return view('inventory/index', compact('balances', 'movements', 'transfers', 'adjustments', 'counts'));
    })->middleware('can:inventory_stock_card.view')->name('inventory.index');

    $router->post('inventory/transfers/{transfer}/approve', function (StockTransfer $transfer, ApproveStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer approved in Local Demo.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('transfer')->middleware('can:transfers.approve')->name('inventory.transfers.approve');

    $router->post('inventory/transfers/{transfer}/dispatch', function (StockTransfer $transfer, DispatchStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer dispatched and source stock posted.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('transfer')->middleware('can:transfers.dispatch')->name('inventory.transfers.dispatch');

    $router->post('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer, ReceiveStockTransferAction $action) {
        try {
            $validated = request()->validate(['received_quantity' => ['required', 'numeric', 'min:0'], 'difference_type' => ['nullable', 'string', 'max:40'], 'difference_reason' => ['nullable', 'string', 'max:1000']]);
            $action->execute($transfer->id, (string) $validated['received_quantity'], $validated['difference_type'] ?? null, $validated['difference_reason'] ?? null);

            return back()->with('success', __('Transfer receipt recorded in Local Demo.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('transfer')->middleware('can:transfers.receive')->name('inventory.transfers.receive');

    $router->post('inventory/adjustments/{adjustment}/submit', function (InventoryAdjustment $adjustment, SubmitInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment submitted in Local Demo.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.submit')->name('inventory.adjustments.submit');

    $router->post('inventory/adjustments/{adjustment}/approve', function (InventoryAdjustment $adjustment, ApproveInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment approved and movement posted.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.approve')->name('inventory.adjustments.approve');

    $router->post('inventory/counts/{count}/submit', function (StockCount $count, SubmitStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count submitted with movement-window calculation.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('count')->middleware('can:stock_counts.submit')->name('inventory.counts.submit');

    $router->post('inventory/counts/{count}/reconcile', function (StockCount $count, ReconcileStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count reconciled; uncounted products were preserved.'));
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    })->whereNumber('count')->middleware('can:stock_counts.reconcile')->name('inventory.counts.reconcile');
});
