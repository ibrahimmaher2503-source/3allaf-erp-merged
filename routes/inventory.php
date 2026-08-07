<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\ResolveTransferDifferenceAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

$router = app('router');
$renderInventory = static function (?int $productId = null, ?string $focus = null) {
    Gate::authorize('inventory_stock_card.view');
    $user = Auth::user();
    abort_unless($user instanceof User, 403);
    $visibleStoreIds = Store::query()->visibleTo($user)->pluck('id');
    $balancesQuery = StockBalance::query()->with(['product', 'store'])->orderBy('store_id')->orderBy('product_id');
    $movementsQuery = StockMovement::query()->with(['product', 'store', 'creator'])->latest('posted_at');
    $balancesQuery->whereIn('store_id', $visibleStoreIds);
    $movementsQuery->whereIn('store_id', $visibleStoreIds);
    if ($productId !== null) {
        $balancesQuery->where('product_id', $productId);
        $movementsQuery->where('product_id', $productId);
    }
    $balances = $balancesQuery->get();
    $movements = $movementsQuery->limit(50)->get();
    $transfers = StockTransfer::query()->with(['sourceStore', 'destinationStore', 'lines.product'])->where(function ($query) use ($visibleStoreIds): void {
        $query->whereIn('source_store_id', $visibleStoreIds)->orWhereIn('destination_store_id', $visibleStoreIds);
    })->latest('id')->limit(20)->get();
    $adjustments = InventoryAdjustment::query()->with(['store', 'lines.product'])->whereIn('store_id', $visibleStoreIds)->latest('id')->limit(20)->get();
    $counts = StockCount::query()->with(['store', 'lines.product'])->whereIn('store_id', $visibleStoreIds)->latest('id')->limit(20)->get();

    return view('inventory/index', compact('balances', 'movements', 'transfers', 'adjustments', 'counts', 'focus') + ['canViewCost' => Gate::allows('inventory_stock_card.cost_view')]);
};

$router->middleware(['auth', 'verified'])->group(function () use ($router, $renderInventory): void {
    $router->get('inventory', fn () => $renderInventory())->middleware('can:inventory_stock_card.view')->name('inventory.index');
    $router->get('inventory/products/{product}', fn (Product $product) => $renderInventory($product->id, 'stock-card'))->middleware('can:inventory_stock_card.view')->name('inventory.stock-card');
    $router->get('inventory/movements', fn () => $renderInventory(null, 'movements'))->middleware('can:inventory_stock_card.view')->name('inventory.movements');
    $router->get('inventory/transfers', fn () => $renderInventory(null, 'transfers'))->middleware('can:transfers.view')->name('inventory.transfers');
    $router->get('inventory/transfers/{transfer}/dispatch', fn () => $renderInventory(null, 'transfer-dispatch'))->middleware('can:transfers.view')->name('inventory.transfers.dispatch-page');
    $router->get('inventory/transfers/{transfer}/receive', fn () => $renderInventory(null, 'transfer-receive'))->middleware('can:transfers.view')->name('inventory.transfers.receive-page');
    $router->get('inventory/transfers/{transfer}/differences', fn () => $renderInventory(null, 'transfer-differences'))->middleware('can:transfers.difference')->name('inventory.transfers.differences');
    $router->get('inventory/adjustments', fn () => $renderInventory(null, 'adjustments'))->middleware('can:inventory_stock_card.view')->name('inventory.adjustments');
    $router->get('inventory/counts', fn () => $renderInventory(null, 'counts'))->middleware('can:inventory_stock_card.view')->name('inventory.counts');
    $router->get('inventory/counts/{count}/entry', fn (StockCount $count) => $renderInventory(null, 'count-entry'))->middleware('can:stock_counts.view')->name('inventory.counts.entry');
    $router->get('inventory/counts/{count}/reconcile', fn (StockCount $count) => $renderInventory(null, 'count-reconcile'))->middleware('can:stock_counts.reconcile')->name('inventory.counts.reconcile-page');

    $router->post('inventory/transfers/{transfer}/approve', function (StockTransfer $transfer, ApproveStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer approved in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.approve')->name('inventory.transfers.approve');

    $router->post('inventory/transfers/{transfer}/dispatch', function (StockTransfer $transfer, DispatchStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer dispatched and source stock posted.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.dispatch')->name('inventory.transfers.dispatch');

    $router->post('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer, ReceiveStockTransferAction $action) {
        try {
            $validated = request()->validate(['received_quantities' => ['required', 'array', 'min:1'], 'received_quantities.*' => ['required', 'numeric', 'min:0'], 'difference_type' => ['nullable', 'in:shortage,damage,refusal'], 'difference_reason' => ['nullable', 'string', 'max:1000']]);
            $action->execute($transfer->id, $validated['received_quantities'], $validated['difference_type'] ?? null, $validated['difference_reason'] ?? null);

            return back()->with('success', __('Transfer receipt recorded in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.receive')->name('inventory.transfers.receive');

    $router->post('inventory/transfers/{transfer}/differences/resolve', function (StockTransfer $transfer, ResolveTransferDifferenceAction $action) {
        try {
            $validated = request()->validate(['difference_type' => ['required', 'in:shortage,damage,refusal'], 'difference_reason' => ['required', 'string', 'max:1000']]);
            $action->execute($transfer->id, $validated['difference_type'], $validated['difference_reason']);

            return back()->with('success', __('Transfer difference resolved in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.difference')->name('inventory.transfers.differences.resolve');

    $router->post('inventory/adjustments/{adjustment}/submit', function (InventoryAdjustment $adjustment, SubmitInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment submitted in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.submit')->name('inventory.adjustments.submit');

    $router->post('inventory/adjustments/{adjustment}/approve', function (InventoryAdjustment $adjustment, ApproveInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment approved and movement posted.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.approve')->name('inventory.adjustments.approve');

    $router->post('inventory/counts/{count}/submit', function (StockCount $count, SubmitStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count submitted with movement-window calculation.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.submit')->name('inventory.counts.submit');

    $router->post('inventory/counts/{count}/reconcile', function (StockCount $count, ReconcileStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count reconciled; uncounted products were preserved.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.reconcile')->name('inventory.counts.reconcile');
});
