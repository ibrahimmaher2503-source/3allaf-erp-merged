<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\AddAllStockCountProductsAction;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\AssertInventoryStoreScope;
use App\Modules\Inventory\Actions\CancelStockCountSessionAction;
use App\Modules\Inventory\Actions\CompleteStockCountLineAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\ImportStockCountWorkbookAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\RecordStockCountContributionAction;
use App\Modules\Inventory\Actions\RecordStockCountLineAction;
use App\Modules\Inventory\Actions\RecordStockCountZeroAction;
use App\Modules\Inventory\Actions\RequestStockCountRecountAction;
use App\Modules\Inventory\Actions\RequestStockTransferApprovalAction;
use App\Modules\Inventory\Actions\ResolveTransferDifferenceAction;
use App\Modules\Inventory\Actions\ReverseInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SaveStockTransferReceivingDraftAction;
use App\Modules\Inventory\Actions\SetStockCountQuantityAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Actions\UpdateStockTransferDraftAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceivingDraft;
use App\Modules\Inventory\Support\InventoryMovementLabel;
use App\Modules\Inventory\Support\LowStockRule;
use App\Modules\Inventory\Support\StockMovementSourceReferences;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\WorkContext;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Support\DataExchange\MasterDataDocument;
use App\Support\HumanName;
use App\Support\ProductQuantity;
use App\Support\UserSafeError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

$router = app('router');
$renderInventory = static function (?int $productId = null, ?string $focus = null, ?StockTransfer $transfer = null, ?InventoryAdjustment $adjustment = null, ?StockCount $count = null) {
    Gate::authorize($focus === 'count-create' ? 'stock_counts.create' : (str_starts_with((string) $focus, 'count') ? 'stock_counts.view' : 'inventory_stock_card.view'));
    $user = Auth::user();
    abort_unless($user instanceof User, 403);
    $visibleStores = Store::query()->with('branch')->visibleTo($user)->orderBy('branch_id')->orderBy('name_ar')->orderBy('code')->limit(200)->get();
    $visibleStoreIds = $visibleStores->modelKeys();
    $search = trim((string) request()->query('q', ''));
    $filterStoreId = request()->has('store_id') ? (request()->integer('store_id') ?: null) : ($focus === 'balances' ? null : app(WorkContext::class)->id($user));
    if ($filterStoreId !== null && ! in_array($filterStoreId, $visibleStoreIds, true)) {
        abort(403);
    }
    $movementType = trim((string) request()->query('movement_type', ''));
    abort_unless($movementType === '' || array_key_exists($movementType, InventoryMovementLabel::options()), 422);
    $stockState = trim((string) request()->query('stock_state', ''));
    $includeZero = request()->boolean('include_zero');
    abort_unless(in_array($stockState, ['', 'available', 'low', 'out', 'negative'], true), 422);
    $canViewCost = Gate::allows('inventory_stock_card.cost_view');
    $showBalances = in_array($focus, [null, 'balances', 'stock-card'], true);
    $showMovements = $focus !== 'balances';
    $showTransfers = $focus === null || str_starts_with((string) $focus, 'transfer');
    $showCountWorkspace = $focus === null || str_starts_with((string) $focus, 'count') || str_starts_with((string) $focus, 'adjustment') || in_array($focus, ['addition', 'deduction'], true);
    $balancesQuery = StockBalance::reportRows($visibleStoreIds)
        ->with(['product.barcodes', 'store.branch'])->orderBy('store_id')->orderBy('product_id');
    $movementsQuery = StockMovement::query()->with(['product', 'store.branch', 'creator'])->whereIn('store_id', $visibleStoreIds)->latest('posted_at');
    if (! $includeZero && ! in_array($stockState, ['out', 'negative'], true)) {
        $balancesQuery->whereRaw('(stock_balances.on_hand - stock_balances.reserved) != 0');
    }
    if ($filterStoreId !== null) {
        $balancesQuery->where('store_id', $filterStoreId);
        $movementsQuery->where('store_id', $filterStoreId);
    }
    if ($search !== '') {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $balancesQuery->whereHas('product', static function ($query) use ($like): void {
            $query->where('item_code', 'like', $like)->orWhere('model_number', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)
                ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', 'like', $like));
        });
        $movementsQuery->whereHas('product', static function ($query) use ($like): void {
            $query->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)
                ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', 'like', $like));
        });
    }
    if ($productId !== null) {
        $balancesQuery->where('product_id', $productId);
        $movementsQuery->where('product_id', $productId);
    }
    if ($movementType !== '') {
        $movementsQuery->where('movement_type', $movementType);
        $balancesQuery->whereExists(fn ($query) => $query->selectRaw('1')->from('stock_movements')
            ->whereColumn('stock_movements.product_id', 'stock_balances.product_id')
            ->whereColumn('stock_movements.store_id', 'stock_balances.store_id')
            ->where('stock_movements.movement_type', $movementType));
    }
    match ($stockState) {
        'available' => $balancesQuery->whereRaw('(stock_balances.on_hand - stock_balances.reserved) > 0')
            ->whereHas('product', fn ($product) => $product->whereNull('reorder_threshold')
                ->orWhereRaw('products.reorder_threshold < (stock_balances.on_hand - stock_balances.reserved)')),
        'low' => LowStockRule::apply($balancesQuery),
        'out' => $balancesQuery->whereRaw('(stock_balances.on_hand - stock_balances.reserved) <= 0'),
        'negative' => $balancesQuery->whereRaw('(stock_balances.on_hand - stock_balances.reserved) < 0'),
        default => null,
    };
    $scopedBalances = StockBalance::query()->whereIn('store_id', $visibleStoreIds);
    $scopedMovements = StockMovement::query()->whereIn('store_id', $visibleStoreIds);
    $inventoryOverview = $focus === null ? [
        'active_products' => Product::query()->active()->whereNull('parent_product_id')->count(),
        'active_variants' => Product::query()->active()->whereNotNull('parent_product_id')->count(),
        'visible_products' => (clone $scopedBalances)->distinct()->count('product_id'),
        'stock_on_hand' => (string) (clone $scopedBalances)->sum('on_hand'),
        'inventory_value' => $canViewCost ? (string) (clone $scopedBalances)->sum('total_value') : null,
        'low_stock' => LowStockRule::apply((clone $scopedBalances))->count(),
        'out_of_stock' => (clone $scopedBalances)->whereRaw('(on_hand - reserved) = 0')->count(),
        'negative_stock' => (clone $scopedBalances)->whereRaw('(on_hand - reserved) < 0')->count(),
        'today_movements' => (clone $scopedMovements)->whereBetween('posted_at', [today()->startOfDay(), today()->endOfDay()])->count(),
        'missing_barcodes' => Product::query()->sellable()->whereDoesntHave('barcodes', fn ($barcode) => $barcode->where('status', 'active'))->count(),
        'missing_catalog_data' => Product::query()->active()->where(function ($product): void {
            $product->whereNull('category_id')->orWhereNull('unit_of_measure')->orWhere('unit_of_measure', '');
        })->count(),
        'pending_transfers' => StockTransfer::query()->where(function ($query) use ($visibleStoreIds): void {
            $query->whereIn('source_store_id', $visibleStoreIds)->orWhereIn('destination_store_id', $visibleStoreIds);
        })->whereIn('status', ['draft', 'submitted', 'approved', 'in_transit', 'difference'])->count(),
        'active_counts' => StockCount::query()->whereIn('store_id', $visibleStoreIds)->whereIn('status', ['draft', 'in_progress', 'submitted'])->count(),
        'pending_adjustments' => InventoryAdjustment::query()->whereIn('store_id', $visibleStoreIds)->whereIn('status', ['draft', 'submitted'])->count(),
        'stock_distribution' => StockBalance::query()->join('stores', 'stores.id', '=', 'stock_balances.store_id')->whereIn('stock_balances.store_id', $visibleStoreIds)->select(['stores.id', 'stores.code', 'stores.name_ar', 'stores.name_en'])->selectRaw('SUM(stock_balances.on_hand) as on_hand')->groupBy('stores.id', 'stores.code', 'stores.name_ar', 'stores.name_en')->orderByDesc('on_hand')->limit(6)->get(),
        'top_moving_products' => StockMovement::query()->join('products', 'products.id', '=', 'stock_movements.product_id')->whereIn('stock_movements.store_id', $visibleStoreIds)->where('posted_at', '>=', now()->subDays(7))->select(['products.id', 'products.item_code', 'products.name_ar', 'products.name_en'])->selectRaw('SUM(ABS(stock_movements.quantity)) as moved_quantity')->groupBy('products.id', 'products.item_code', 'products.name_ar', 'products.name_en')->orderByDesc('moved_quantity')->limit(6)->get(),
    ] : [];
    $transferQuery = StockTransfer::query()->where(function ($query) use ($visibleStoreIds): void {
        $query->whereIn('source_store_id', $visibleStoreIds)
            ->orWhereIn('destination_store_id', $visibleStoreIds)
            ->orWhereHas('allocations', fn ($allocations) => $allocations->whereIn('destination_store_id', $visibleStoreIds));
    });
    $adjustmentQuery = InventoryAdjustment::query()->whereIn('store_id', $visibleStoreIds);
    if (in_array($focus, ['addition', 'deduction'], true)) {
        $adjustmentQuery->where('adjustment_type', $focus === 'addition' ? 'entry' : 'exit');
    }
    $countQuery = StockCount::query()->whereIn('store_id', $visibleStoreIds)
        ->whereDoesntHave('locations', fn ($locations) => $locations->whereNotIn('store_id', $visibleStoreIds));
    $inventorySummary = [
        'transfers' => (clone $transferQuery)->count(),
        'adjustments' => (clone $adjustmentQuery)->count(),
        'counts' => (clone $countQuery)->count(),
    ];
    $balanceTotal = $showBalances ? null : (clone $balancesQuery)->count();
    $movementTotal = $showMovements ? null : (clone $movementsQuery)->count();
    $balances = $showBalances ? $balancesQuery->paginate(25, ['*'], 'balance_page')->withQueryString() : new LengthAwarePaginator([], $balanceTotal, 25);
    $movements = $showMovements ? $movementsQuery->paginate(50, ['*'], 'movement_page')->withQueryString() : new LengthAwarePaginator([], $movementTotal, 50);
    $inventorySummary['balances'] = $balances->total();
    $inventorySummary['movements'] = $movements->total();
    $movementReferences = new StockMovementSourceReferences($movements->getCollection());
    $transfers = $showTransfers ? (clone $transferQuery)->with(['sourceStore', 'destinationStore', 'lines.product', 'lines.allocations.destination', 'receivingDrafts'])->latest('id')->limit(20)->get() : collect();
    $transferApprovals = $showTransfers ? ApprovalRecord::query()
        ->where('source_type', 'stock_transfers')
        ->whereIn('source_id', $transfers->pluck('id')->map(fn (int $id): string => (string) $id))
        ->latest('requested_at')
        ->limit(50)->get()
        ->unique('source_id')
        ->keyBy('source_id') : collect();
    $adjustments = $showCountWorkspace ? (clone $adjustmentQuery)->with(['store', 'lines.product'])->latest('id')->limit(20)->get() : collect();
    $counts = $showCountWorkspace ? (clone $countQuery)
        ->when(request('count_number'), fn ($query, $number) => $query->where('count_number', 'like', '%'.addcslashes((string) $number, '%_\\').'%'))
        ->when(request('count_store_id'), fn ($query, $storeId) => $query->where('store_id', (int) $storeId))
        ->when(request('count_status'), fn ($query, $status) => $query->where('status', (string) $status))
        ->when(request('count_from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
        ->when(request('count_to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
        ->with(['store', 'creator'])->withCount(['lines as total_lines_count', 'lines as counted_lines_count' => fn ($lines) => $lines->where('is_counted', true), 'lines as matched_lines_count' => fn ($lines) => $lines->where('is_counted', true)->where('variance_quantity', 0), 'lines as different_lines_count' => fn ($lines) => $lines->where('variance_quantity', '!=', 0), 'lines as shortage_lines_count' => fn ($lines) => $lines->where('variance_quantity', '<', 0), 'lines as surplus_lines_count' => fn ($lines) => $lines->where('variance_quantity', '>', 0)])
        ->latest('id')->paginate(20, ['*'], 'counts_page')->withQueryString() : collect();
    $needsProductOptions = str_starts_with((string) $focus, 'transfer') || str_starts_with((string) $focus, 'adjustment');
    $products = $needsProductOptions ? Product::query()->sellable()->select(['id', 'item_code', 'name_en', 'name_ar', 'fractional_quantity', 'parent_product_id'])->when($search !== '', static function ($query) use ($search): void {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $query->where(static function ($nested) use ($like): void {
            $nested->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like);
        });
    })->orderBy('item_code')->limit(200)->get() : collect();
    $countAssignees = $focus === 'count-create' ? User::query()->where('status', 'active')->limit(200)->get(['id', 'name', 'email', 'is_super_admin'])->filter(static fn (User $candidate): bool => $candidate->is_super_admin || $candidate->hasPermission('stock_counts.edit') || $candidate->hasPermission('stock_counts.close'))->values() : collect();
    $countCategories = $focus === 'count-create' ? Category::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_ar', 'name_en']) : ($focus === 'count-entry' && $count ? Category::query()->whereIn('id', Product::query()->whereIn('id', $count->lines()->select('product_id'))->select('category_id'))->orderBy('name_en')->get(['id', 'name_ar', 'name_en']) : collect());
    $countSuppliers = $focus === 'count-create' ? Supplier::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_ar', 'name_en']) : collect();
    $countReviewTotals = $focus === 'count-reconcile' && $count && in_array($count->status, ['submitted', 'reconciled'], true)
        ? DB::table('stock_count_lines as lines')
            ->leftJoin('stock_balances as balances', fn ($join) => $join->on('balances.product_id', '=', 'lines.product_id')->on('balances.store_id', '=', 'lines.store_id'))
            ->where('lines.stock_count_id', $count->id)
            ->selectRaw('COUNT(DISTINCT CASE WHEN lines.variance_quantity <> 0 THEN lines.product_id END) AS affected_products')
            ->selectRaw('COALESCE(SUM(CASE WHEN lines.variance_quantity < 0 THEN ABS(lines.variance_quantity) ELSE 0 END), 0) AS shortage_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN lines.variance_quantity > 0 THEN lines.variance_quantity ELSE 0 END), 0) AS surplus_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN lines.variance_quantity < 0 THEN ABS(lines.variance_quantity) * COALESCE(balances.average_cost, 0) ELSE 0 END), 0) AS shortage_value')
            ->selectRaw('COALESCE(SUM(CASE WHEN lines.variance_quantity > 0 THEN lines.variance_quantity * COALESCE(balances.average_cost, 0) ELSE 0 END), 0) AS surplus_value')
            ->first()
        : null;

    return view('inventory/index', compact('balances', 'movements', 'transfers', 'transferApprovals', 'adjustments', 'counts', 'inventorySummary', 'focus', 'transfer', 'adjustment', 'count', 'countReviewTotals', 'visibleStores', 'visibleStoreIds', 'products', 'countAssignees', 'countCategories', 'countSuppliers', 'search', 'filterStoreId', 'movementType', 'stockState', 'includeZero', 'inventoryOverview', 'canViewCost', 'movementReferences'));
};

$deriveTransferLines = static function (array $lines): array {
    $derived = [];
    foreach ($lines as $line) {
        $allocations = [];
        $total = '0';
        foreach ((array) ($line['allocations'] ?? []) as $destinationId => $quantity) {
            if ($quantity === null || $quantity === '') {
                continue;
            }
            if (! preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', (string) $quantity)) {
                throw ValidationException::withMessages(['lines' => __('Destination quantities must be nonnegative numbers.')]);
            }
            if (bccomp((string) $quantity, '0', 6) > 0) {
                $allocations[$destinationId] = (string) $quantity;
                $total = bcadd($total, (string) $quantity, 6);
            }
        }
        if (bccomp($total, '0', 6) > 0) {
            $line['allocations'] = $allocations;
            $line['quantity_requested'] = $total;
            $derived[] = $line;
        }
    }
    if ($derived === []) {
        throw ValidationException::withMessages(['lines' => __('Allocate a positive quantity to at least one destination.')]);
    }

    return $derived;
};

$router->middleware(['auth', 'verified'])->group(function () use ($router, $renderInventory, $deriveTransferLines): void {
    $router->get('inventory', fn () => $renderInventory())->middleware('can:inventory_stock_card.view')->name('inventory.index');
    $router->get('inventory/balances', fn () => $renderInventory(null, 'balances'))->middleware('can:inventory_stock_card.view')->name('inventory.balances');
    $router->get('inventory/products/{product}', function (Product $product) use ($renderInventory): mixed {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless(StockBalance::query()->where('product_id', $product->id)->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))->exists(), 403);

        return $renderInventory($product->id, 'stock-card');
    })->middleware('can:inventory_stock_card.view')->name('inventory.stock-card');
    $router->get('inventory/movements', fn () => $renderInventory(null, 'movements'))->middleware('can:inventory_stock_card.view')->name('inventory.movements');
    $router->get('inventory/export', function () {
        Gate::authorize('inventory_stock_card.export');
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($user)->pluck('id')->all();
        $storeId = request()->integer('store_id') ?: null;
        if ($storeId !== null && ! in_array($storeId, $visibleStoreIds, true)) {
            abort(403);
        }
        $query = StockBalance::query()->with(['product', 'store.branch'])->whereIn('store_id', $visibleStoreIds)->when($storeId !== null, fn ($builder) => $builder->where('store_id', $storeId));
        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('product', fn ($builder) => $builder->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like));
        }
        $canViewCost = Gate::allows('inventory_stock_card.cost_view');
        $rows = $query->orderBy('store_id')->orderBy('product_id')->limit(5000)->get();
        app(RecordAuditEvent::class)->execute('inventory', 'export_stock_balances', 'inventory', after: ['row_count' => $rows->count(), 'cost_included' => $canViewCost], storeId: $storeId, metadata: ['filters' => request()->query()]);

        return response()->streamDownload(static function () use ($rows, $canViewCost): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $canViewCost ? ['product_name', 'product_code', 'location_name', 'location_code', 'location_type', 'branch_name', 'branch_code', 'on_hand', 'reserved', 'available', 'in_transit', 'average_cost'] : ['product_name', 'product_code', 'location_name', 'location_code', 'location_type', 'branch_name', 'branch_code', 'on_hand', 'reserved', 'available', 'in_transit']);
            foreach ($rows as $row) {
                $available = bcsub((string) $row->on_hand, (string) $row->reserved, 6);
                $values = [HumanName::name($row->product), $row->product?->item_code, HumanName::name($row->store), $row->store?->code, $row->store?->type, HumanName::name($row->store?->branch), $row->store?->branch?->code, ProductQuantity::format($row->on_hand), ProductQuantity::format($row->reserved), ProductQuantity::format($available), ProductQuantity::format($row->in_transit)];
                if ($canViewCost) {
                    $values[] = $row->average_cost;
                }
                fputcsv($output, $values);
            }
            fclose($output);
        }, 'inventory-balances-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:inventory_stock_card.export')->name('inventory.export');
    $router->get('inventory/transfers', fn () => $renderInventory(null, 'transfers'))->middleware('can:transfers.view')->name('inventory.transfers');
    $router->get('inventory/transfers/purchase-invoices/{invoice}/products', function (string $invoice) {
        Gate::authorize('transfers.create');
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $sourceStoreId = request()->integer('source_store_id');
        abort_unless($sourceStoreId > 0, 422);
        app(AssertInventoryStoreScope::class)->execute($sourceStoreId);
        $lookup = trim($invoice);
        abort_unless($lookup !== '' && strlen($lookup) <= 50, 404);
        $visibleInvoices = PurchaseInvoice::query()->whereIn('store_id', Store::query()->visibleTo($user)->select('id'));
        $invoice = (clone $visibleInvoices)->where('invoice_number', $lookup)->first();
        if ($invoice === null && ctype_digit($lookup)) {
            $invoice = (clone $visibleInvoices)->whereKey($lookup)->first();
        }
        abort_unless($invoice instanceof PurchaseInvoice, 404);
        $invoice->load(['store', 'lines.product']);
        abort_unless($invoice->status === 'approved', 404);
        $mismatch = (int) $invoice->store_id !== $sourceStoreId;
        $lines = $invoice->lines->map(function ($line) use ($sourceStoreId): array {
            $balance = StockBalance::query()->where('store_id', $sourceStoreId)->where('product_id', $line->product_id)->first();
            $available = bcsub((string) ($balance?->on_hand ?? '0'), (string) ($balance?->reserved ?? '0'), 6);
            $sellingPrice = app(EffectivePriceResolver::class)->resolve((int) $line->product_id, $sourceStoreId)?->amount;

            return [
                'product_id' => (int) $line->product_id,
                'product' => HumanName::name($line->product).' · '.(string) $line->product?->item_code,
                'available' => $available,
                'source_unit_cost' => (string) ($balance?->average_cost ?? '0'),
                'source_selling_price' => $sellingPrice,
            ];
        })->unique('product_id')->values();

        return response()->json(['invoice_id' => $invoice->id, 'invoice_store_id' => (int) $invoice->store_id, 'invoice_store' => HumanName::name($invoice->store), 'source_store_id' => $sourceStoreId, 'warehouse_mismatch' => $mismatch, 'message' => $mismatch ? __('فاتورة الشراء تتبع مخزنًا آخر. استخدم مخزن الفاتورة كمصدر صراحةً لتحميل المنتجات.') : null, 'lines' => $lines]);
    })->middleware('can:transfers.create')->name('inventory.transfers.purchase-invoice-products');
    $router->get('inventory/transfers/create', fn () => $renderInventory(null, 'transfer-create'))->middleware('can:transfers.create')->name('inventory.transfers.create');
    $router->get('inventory/transfers/{transfer}/edit', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-edit', $transfer->load(['sourceStore', 'destinationStore', 'lines.product', 'lines.allocations.destination']));
    })->middleware('can:transfers.edit')->name('inventory.transfers.edit');
    $router->get('inventory/transfers/{transfer}/dispatch', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-dispatch', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.view')->name('inventory.transfers.dispatch-page');
    $router->get('inventory/transfers/{transfer}/approve', function (StockTransfer $transfer) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);
        if ($transfer->status !== 'submitted') {
            return redirect()->route('inventory.transfers')->with('error', __('Only submitted transfers can be approved.'));
        }
        session(['transfer_approval_reviews.'.$transfer->id => $transfer->lock_version]);

        $transfer->load(['sourceStore', 'destinationStore', 'lines.product', 'lines.allocations.destination']);
        $visibleStores = Store::query()->visibleTo(request()->user())->where('status', 'active')->orderBy('code')->get();
        $savedLines = $transfer->lines->map(fn ($line): array => [
            'product_id' => $line->product_id,
            'quantity_requested' => $line->quantity_requested,
            'allocations' => $line->allocations->pluck('quantity', 'destination_store_id')->all(),
        ])->all();
        $reviewLines = old('lines', $savedLines);
        $reviewProducts = Product::query()->whereIn('id', collect($reviewLines)->pluck('product_id')->filter())->get()->keyBy('id');

        return view('inventory.transfer-approval-review', compact('transfer', 'visibleStores', 'reviewLines', 'reviewProducts'));
    })->middleware('can:transfers.approve')->name('inventory.transfers.approval-review');
    $router->get('inventory/transfers/receiving', function () {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $visibleIds = Store::query()->visibleTo($actor)->pluck('id');
        $transfers = StockTransfer::query()
            ->whereIn('status', ['in_transit', 'received', 'difference_review'])
            ->where(function ($query) use ($visibleIds) {
                $query->whereHas('allocations', fn ($allocations) => $allocations->whereIn('destination_store_id', $visibleIds)->where('quantity', '>', 0))
                    ->orWhere(fn ($legacy) => $legacy->whereIn('destination_store_id', $visibleIds)->whereDoesntHave('allocations'));
            })
            ->with(['sourceStore', 'destinationStore', 'lines.allocations.destination', 'lines.product'])
            ->latest('id')->paginate(20);
        $drafts = StockTransferReceivingDraft::query()
            ->whereIn('stock_transfer_id', $transfers->getCollection()->modelKeys())
            ->whereIn('destination_store_id', $visibleIds)
            ->get()->keyBy(fn ($draft) => $draft->stock_transfer_id.':'.$draft->destination_store_id);

        return view('inventory.transfer-receiving-inbox', compact('transfers', 'drafts', 'visibleIds'));
    })->middleware('can:transfers.receive')->name('inventory.transfers.receiving-inbox');
    $router->get('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer) {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $transfer->load(['sourceStore', 'destinationStore', 'lines.product', 'lines.allocations.destination']);
        $destinationIds = $transfer->allocations->isNotEmpty()
            ? $transfer->allocations->filter(fn ($allocation) => bccomp((string) $allocation->quantity, '0', 6) > 0)->pluck('destination_store_id')->unique()
            : collect([$transfer->destination_store_id]);
        $destinations = Store::query()->visibleTo($actor)->whereIn('id', $destinationIds)->orderBy('code')->get();
        abort_if($destinations->isEmpty(), 403);
        $selectedId = request()->integer('destination_store_id') ?: ($destinations->count() === 1 ? $destinations->first()->id : null);
        $destination = $selectedId ? $destinations->firstWhere('id', $selectedId) : null;
        abort_if($selectedId && $destination === null, 403);
        $draft = $destination ? StockTransferReceivingDraft::query()->where('stock_transfer_id', $transfer->id)->where('destination_store_id', $destination->id)->first() : null;

        return view('inventory.transfer-receiving-review', compact('transfer', 'destinations', 'destination', 'draft'));
    })->middleware('can:transfers.receive')->name('inventory.transfers.receive-page');
    $router->get('inventory/transfers/{transfer}/differences', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-differences', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.difference')->name('inventory.transfers.differences');
    $router->get('inventory/adjustments', fn () => $renderInventory(null, 'adjustments'))->middleware('can:inventory_stock_card.view')->name('inventory.adjustments');
    $router->get('inventory/additions', fn () => $renderInventory(null, 'addition'))->middleware('can:inventory_stock_card.view')->name('inventory.additions');
    $router->get('inventory/deductions', fn () => $renderInventory(null, 'deduction'))->middleware('can:inventory_stock_card.view')->name('inventory.deductions');
    $router->get('inventory/adjustments/create', fn () => $renderInventory(null, 'adjustment-create'))->middleware('can:inventory_stock_card.create')->name('inventory.adjustments.create');
    $router->get('inventory/adjustments/{adjustment}/edit', function (InventoryAdjustment $adjustment) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->execute((int) $adjustment->store_id);

        $focus = match ($adjustment->adjustment_type) {
            'entry' => 'addition',
            'exit' => 'deduction',
            default => 'adjustment-edit',
        };

        return $renderInventory(null, $focus, null, $adjustment->load(['store', 'lines.product']));
    })->middleware('can:inventory_stock_card.edit')->name('inventory.adjustments.edit');
    $router->get('inventory/counts', fn () => $renderInventory(null, 'counts'))->middleware('can:stock_counts.view')->name('inventory.counts');
    $router->get('inventory/counts/create', fn () => $renderInventory(null, 'count-create'))->middleware('can:stock_counts.create')->name('inventory.counts.create');
    $router->get('inventory/counts/{count}/entry', function (StockCount $count) use ($renderInventory) {
        foreach ($count->locations as $location) {
            app(AssertInventoryStoreScope::class)->execute((int) $location->store_id);
        }

        return $renderInventory(null, 'count-entry', null, null, $count->load(['store', 'locations.store', 'members.user']));
    })->middleware('can:stock_counts.view')->name('inventory.counts.entry');
    $router->get('inventory/counts/{count}/reconcile', function (StockCount $count) use ($renderInventory) {
        foreach ($count->locations as $location) {
            app(AssertInventoryStoreScope::class)->execute((int) $location->store_id);
        }

        return $renderInventory(null, 'count-reconcile', null, null, $count->load(['store', 'locations.store', 'members.user']));
    })->middleware('can:stock_counts.view')->name('inventory.counts.reconcile-page');

    $router->post('inventory/transfers', function (CreateStockTransferDraftAction $action) use ($deriveTransferLines) {
        $validated = request()->validate(['source_store_id' => ['required', 'integer', 'exists:stores,id'], 'destination_store_id' => ['nullable', 'integer', 'different:source_store_id', 'exists:stores,id'], 'destination_store_ids' => ['nullable', 'array', 'min:1'], 'destination_store_ids.*' => ['integer', 'exists:stores,id', 'different:source_store_id'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.allocations' => ['nullable', 'array'], 'lines.*.allocations.*' => ['nullable', 'numeric', 'min:0'], 'lock_version' => ['nullable', 'integer'], 'idempotency_key' => ['nullable', 'uuid'], 'invoice_reference' => ['nullable', 'string', 'max:120']]);
        $validated['lines'] = $deriveTransferLines($validated['lines']);
        try {
            $transfer = $action->execute((int) $validated['source_store_id'], (int) $validated['destination_store_id'], $validated['lines'], $validated['reason_code'], $validated['reason_notes'] ?? null, $validated['idempotency_key'] ?? null, $validated['invoice_reference'] ?? null);

            return redirect()->route('inventory.transfers.edit', $transfer)->with('success', __('Transfer saved as draft.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', UserSafeError::message($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:transfers.create')->name('inventory.transfers.store');

    $router->post('inventory/transfers/{transfer}', function (StockTransfer $transfer, UpdateStockTransferDraftAction $action) use ($deriveTransferLines) {
        $validated = request()->validate(['source_store_id' => ['required', 'integer', 'exists:stores,id'], 'destination_store_id' => ['nullable', 'integer', 'different:source_store_id', 'exists:stores,id'], 'destination_store_ids' => ['nullable', 'array', 'min:1'], 'destination_store_ids.*' => ['integer', 'exists:stores,id', 'different:source_store_id'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.allocations' => ['nullable', 'array'], 'lines.*.allocations.*' => ['nullable', 'numeric', 'min:0'], 'lock_version' => ['nullable', 'integer'], 'idempotency_key' => ['nullable', 'uuid'], 'invoice_reference' => ['nullable', 'string', 'max:120']]);
        $validated['lines'] = $deriveTransferLines($validated['lines']);
        try {
            $saved = $action->execute($transfer->id, (int) $validated['source_store_id'], (int) $validated['destination_store_id'], $validated['lines'], $validated['reason_code'], $validated['reason_notes'] ?? null, $validated['lock_version'] ?? $transfer->lock_version, $validated['invoice_reference'] ?? null);

            return redirect()->route('inventory.transfers.edit', $saved)->with('success', __('Transfer draft updated.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', UserSafeError::message($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.edit')->name('inventory.transfers.update');

    $router->post('inventory/transfers/{transfer}/submit', function (StockTransfer $transfer, SubmitStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer submitted for approval.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', UserSafeError::message($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.submit')->name('inventory.transfers.submit');

    $router->post('inventory/transfers/{transfer}/approve', function (StockTransfer $transfer, ApproveStockTransferAction $action) use ($deriveTransferLines) {
        $review = request()->validate([
            'review_version' => ['required', 'integer'],
            'source_store_id' => ['required', 'integer', 'exists:stores,id'],
            'destination_store_id' => ['required', 'integer', 'different:source_store_id', 'exists:stores,id'],
            'reason_code' => ['required', 'string', 'max:100'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
            'invoice_reference' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.allocations' => ['nullable', 'array'],
            'lines.*.allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        if ((int) $review['review_version'] !== (int) $transfer->lock_version
            || (int) session('transfer_approval_reviews.'.$transfer->id) !== (int) $review['review_version']) {
            return back()->withInput()->with('error', __('The transfer changed during review. Reload the review before confirming.'));
        }
        $review['lines'] = $deriveTransferLines($review['lines']);
        try {
            $action->execute($transfer->id, [
                'source_store_id' => (int) $review['source_store_id'],
                'destination_store_id' => (int) $review['destination_store_id'],
                'reason_code' => $review['reason_code'],
                'notes' => $review['reason_notes'] ?? null,
                'invoice_reference' => $review['invoice_reference'] ?? null,
                'lines' => $review['lines'],
            ], (int) $review['review_version']);
            session()->forget('transfer_approval_reviews.'.$transfer->id);

            return redirect()->route('inventory.transfers')->with('success', app()->getLocale() === 'en' ? 'Transfer approved and dispatched successfully.' : 'تم اعتماد وإرسال التحويل بنجاح');
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['transfer' => $exception->getMessage()]);
        } catch (Throwable $exception) {

            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.approve')->name('inventory.transfers.approve');

    $router->post('inventory/transfers/{transfer}/request-approval', function (StockTransfer $transfer, RequestStockTransferApprovalAction $action) {
        try {
            $action->execute($transfer->id);

            return redirect()->route('admin.approvals')->with('approval-success', __('Transfer approval requested.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.submit')->name('inventory.transfers.request-approval');

    $router->post('inventory/transfers/{transfer}/receive/draft', function (StockTransfer $transfer, SaveStockTransferReceivingDraftAction $action) {
        try {
            $validated = request()->validate(['destination_store_id' => ['required', 'integer'], 'received_quantities' => ['required', 'array', 'min:1'], 'received_quantities.*' => ['required', 'numeric', 'min:0'], 'difference_type' => ['nullable', 'in:shortage,damage,refusal'], 'difference_reason' => ['nullable', 'string', 'max:1000'], 'intent' => ['nullable', 'in:save,final']]);
            if (($validated['intent'] ?? 'save') === 'final') {
                DB::transaction(function () use ($action, $transfer, $validated): void {
                    $action->execute($transfer->id, (int) $validated['destination_store_id'], $validated['received_quantities'], $validated['difference_type'] ?? null, $validated['difference_reason'] ?? null);
                    app(ReceiveStockTransferAction::class)->finalizeDraft($transfer->id, (int) $validated['destination_store_id']);
                });

                return redirect()->route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $validated['destination_store_id']])->with('success', __('Destination receipt finally confirmed.'));
            }
            $action->execute($transfer->id, (int) $validated['destination_store_id'], $validated['received_quantities'], $validated['difference_type'] ?? null, $validated['difference_reason'] ?? null);

            return redirect()->route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $validated['destination_store_id']])->with('success', __('Receiving draft saved. No stock was posted.'));
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.receive')->name('inventory.transfers.receive-draft');
    $router->post('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer, ReceiveStockTransferAction $action) {
        $validated = request()->validate(['destination_store_id' => ['required', 'integer']]);
        try {
            $action->finalizeDraft($transfer->id, (int) $validated['destination_store_id']);

            return redirect()->route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $validated['destination_store_id']])->with('success', __('Destination receipt finally confirmed.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }
            report($exception);

            return back()->with('error', UserSafeError::message($exception));
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
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', UserSafeError::message($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.approve')->name('inventory.adjustments.approve');

    $router->post('inventory/documents/{documentType}', function (string $documentType, SaveInventoryAdjustmentAction $action) {
        $validated = request()->validate([
            'store_id' => ['required', 'integer'],
            'reason_code' => ['required', 'string', 'max:100'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
            'allow_negative' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity_delta' => ['required', 'integer', 'not_in:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $document = $action->execute([...$validated, 'adjustment_type' => $documentType], $validated['lines']);

            return redirect()->route('inventory.adjustments.edit', $document)->with('success', __('Inventory movement saved as draft.'));
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['document' => $exception->getMessage()]);
        }
    })->whereIn('documentType', ['entry', 'exit'])->middleware('can:inventory_stock_card.create')->name('inventory.documents.store');

    $router->post('inventory/adjustments', function (SaveInventoryAdjustmentAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer'], 'adjustment_type' => ['required', 'in:entry,exit,exchange,adjustment'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'allow_negative' => ['nullable', 'boolean'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_delta' => ['required', 'integer', 'not_in:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0']]);
        try {
            $document = $action->execute($validated, $validated['lines']);

            return redirect()->route('inventory.adjustments.edit', $document)->with('success', __('Inventory movement saved as draft.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:inventory_stock_card.create')->name('inventory.adjustments.store');

    $router->post('inventory/adjustments/{adjustment}', function (InventoryAdjustment $adjustment, SaveInventoryAdjustmentAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer'], 'adjustment_type' => ['required', 'in:entry,exit,exchange,adjustment'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'allow_negative' => ['nullable', 'boolean'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_delta' => ['required', 'integer', 'not_in:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0']]);
        try {
            $document = $action->execute($validated, $validated['lines'], $adjustment->id, $adjustment->lock_version);

            return redirect()->route('inventory.adjustments.edit', $document)->with('success', __('Inventory movement draft updated.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.edit')->name('inventory.adjustments.update');

    $router->post('inventory/adjustments/{adjustment}/reverse', function (InventoryAdjustment $adjustment, ReverseInventoryAdjustmentAction $action) {
        $validated = request()->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $action->execute($adjustment->id, $validated['reason']);

            return back()->with('success', __('Approved movement reversed with a linked corrective document.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.reverse')->name('inventory.adjustments.reverse');

    $router->post('inventory/counts/{count}/submit', function (StockCount $count, SubmitStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count submitted with movement-window calculation.'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.submit')->name('inventory.counts.submit');

    $router->post('inventory/counts', function (SaveStockCountAction $action) {
        $validated = request()->validate(['branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'location_ids' => ['required', 'array', 'min:1'], 'location_ids.*' => ['integer', 'distinct', 'exists:stores,id'], 'member_ids' => ['required', 'array', 'min:1'], 'member_ids.*' => ['integer', 'distinct', 'exists:users,id'], 'manager_id' => ['required', 'integer', 'exists:users,id'], 'count_type' => ['required', 'in:full,partial'], 'scope_type' => ['required', 'in:full,category,supplier,partial'], 'category_id' => ['nullable', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:1000'], 'assignments' => ['nullable', 'array'], 'assignments.*.store_id' => ['integer'], 'assignments.*.user_id' => ['integer'], 'assignments.*.zone' => ['nullable', 'string', 'max:120']]);
        try {
            $count = $action->execute($validated);

            return redirect()->route('inventory.counts.entry', $count)->with('success', __('Count session created. Review its scope and open it when the team is ready.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:stock_counts.create')->name('inventory.counts.store');

    $router->post('inventory/counts/{count}/open', function (StockCount $count, OpenStockCountSessionAction $action) {
        $action->execute($count->id);

        return back()->with('success', __('Count session opened with immutable location snapshots.'));
    })->whereNumber('count')->middleware('can:stock_counts.open')->name('inventory.counts.open');
    $router->post('inventory/counts/{count}/add-all', function (StockCount $count, AddAllStockCountProductsAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer', 'exists:stores,id']]);
        try {
            $added = $action->execute($count->id, (int) $validated['store_id']);

            return back()->with('success', __('Products added to the stocktake: :count', ['count' => $added]));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', UserSafeError::message($exception));
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.add-all');
    $router->get('inventory/counts/{count}/import-template.xlsx', function (StockCount $count, MasterDataDocument $documents) {
        abort_unless($count->members()->where('user_id', Auth::id())->exists()
            && $count->locations()->whereHas('store', fn ($store) => $store->visibleTo(Auth::user()))->exists(), 404);

        return $documents->importTemplate('stocktake-count-template.xlsx', 'rajeh.stocktake-count.v1',
            ImportStockCountWorkbookAction::HEADERS, [], [
                ['ar' => 'أدخل كود الصنف أولاً، أو باركوداً فعالاً. الكود له الأولوية؛ الرموز الملتبسة مرفوضة.', 'en' => 'Use the product code first, or an active barcode. Product code has priority; ambiguous identifiers are rejected.'],
                ['ar' => 'أدخل كمية فعلية صحيحة وغير سالبة. اختر موقع الجرد في شاشة الاستيراد. لا يغير الاستيراد المخزون الحي.', 'en' => 'Enter a non-negative whole actual count. Select the count location on the import screen. Import does not change live stock.'],
            ], requiredColumns: [0, 1], formats: [1 => '0']);
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.import-template');
    $router->post('inventory/counts/{count}/import', function (StockCount $count, ImportStockCountWorkbookAction $action) {
        $validated = request()->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'import_file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv'],
        ]);
        try {
            $summary = $action->execute($count->id, (int) $validated['store_id'], $validated['import_file']);

            return back()->with('stocktake_import_summary', $summary);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', UserSafeError::message($exception))->with('stocktake_import_open', true);
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.import');
    $router->post('inventory/counts/{count}/quantity', function (StockCount $count, SetStockCountQuantityAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'lookup' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'min:0'], 'request_id' => ['required', 'uuid'], 'device_id' => ['required', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:1000']]);
        try {
            $line = $action->execute($count->id, (int) $validated['store_id'], $validated['lookup'], (string) $validated['quantity'], $validated['request_id'], $validated['device_id'], $validated['note'] ?? null);
            if (request()->expectsJson()) {
                return response()->json(['product_id' => $line->product_id, 'name' => HumanName::name($line->product), 'sku' => $line->product?->item_code, 'quantity' => ProductQuantity::format($line->counted_quantity)]);
            }

            return back()->with('success', __('Stocktake quantity saved.'));
        } catch (InvalidArgumentException $exception) {
            if (request()->expectsJson()) {
                return response()->json(['message' => UserSafeError::message($exception)], 422);
            }

            return back()->withInput()->with('error', UserSafeError::message($exception));
        }
    })->whereNumber('count')->middleware('can:stock_counts.participate')->name('inventory.counts.quantity');
    $router->post('inventory/counts/{count}/contributions', function (StockCount $count, RecordStockCountContributionAction $action, RecordStockCountZeroAction $zeroAction) {
        $validated = request()->validate(['store_id' => ['required', 'integer'], 'lookup' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'min:0'], 'request_id' => ['required', 'uuid'], 'device_id' => ['required', 'string', 'max:120'], 'input_method' => ['required', 'in:scan,batch,manual']]);
        if (! $count->members()->where('user_id', Auth::id())->exists()) {
            return back()->withInput()->with('error', __('You are not on this count team. Ask the count manager to assign you.'));
        }
        try {
            if (bccomp((string) $validated['quantity'], '0', 6) === 0) {
                $zeroAction->execute($count->id, (int) $validated['store_id'], $validated['lookup']);

                return back()->with('success', __('Product/location count marked complete at zero.'));
            }
            $entry = $action->execute($count->id, (int) $validated['store_id'], $validated['lookup'], (string) $validated['quantity'], $validated['request_id'], $validated['device_id'], $validated['input_method']);

            $total = $entry->count->lines()->where('store_id', $entry->store_id)->where('product_id', $entry->product_id)->value('counted_quantity');
            if (request()->expectsJson()) {
                $product = $entry->product;

                return response()->json(['product_id' => $product->id, 'name' => HumanName::name($product), 'sku' => $product->item_code, 'quantity' => ProductQuantity::format($total)]);
            }

            return back()->with('success', __('Quantity added. Updated counted total: :total', ['total' => $total]));
        } catch (InvalidArgumentException $exception) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => UserSafeError::message($exception)]);
            }

            return back()->withInput()->with('error', UserSafeError::message($exception));
        }
    })->whereNumber('count')->middleware('can:stock_counts.participate')->name('inventory.counts.contribute');
    $router->post('inventory/count-lines/{line}/complete', function (StockCountLine $line, CompleteStockCountLineAction $action) {
        $action->execute($line->id, request()->boolean('explicit_zero'));

        return back()->with('success', __('Product/location count marked complete.'));
    })->whereNumber('line')->middleware('can:stock_counts.participate')->name('inventory.counts.complete-line');
    $router->post('inventory/counts/{count}/recount', function (StockCount $count, RequestStockCountRecountAction $action) {
        $v = request()->validate(['line_ids' => ['required', 'array', 'min:1'], 'line_ids.*' => ['integer'], 'reason' => ['required', 'string', 'max:1000']]);
        $action->execute($count->id, $v['line_ids'], $v['reason']);

        return redirect()->route('inventory.counts.entry', $count)->with('success', __('Recount requested with history preserved.'));
    })->whereNumber('count')->middleware('can:stock_counts.request_recount')->name('inventory.counts.request-recount');
    $router->post('inventory/counts/{count}/cancel', function (StockCount $count, CancelStockCountSessionAction $action) {
        $v = request()->validate(['reason' => ['required', 'string', 'max:1000']]);
        $action->execute($count->id, $v['reason']);

        return redirect()->route('inventory.counts')->with('success', __('Count session cancelled.'));
    })->whereNumber('count')->middleware('can:stock_counts.cancel')->name('inventory.counts.cancel');
    $router->get('inventory/counts/{count}/products', function (StockCount $count) {
        $user = Auth::user();
        abort_unless($user instanceof User && $count->members()->where('user_id', $user->id)->exists(), 404);
        $term = trim((string) request('q'));
        abort_if(mb_strlen($term) < 2, 422);
        $like = '%'.addcslashes($term, '%_\\').'%';

        return Product::query()->sellable()->where(fn ($q) => $q->where('item_code', 'like', $like)->orWhere('model_number', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like))->orderBy('item_code')->limit(20)->get(['id', 'item_code', 'name_ar', 'name_en', 'model_number']);
    })->whereNumber('count')->middleware('can:stock_counts.participate')->name('inventory.counts.products');
    $router->get('inventory/counts/{count}/report', function (StockCount $count) {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $visible = Store::query()->visibleTo($user)->select('id');
        abort_if($count->locations()->whereNotIn('store_id', $visible)->exists() || ! $count->locations()->exists(), 404);
        $count->load(['locations.store.branch', 'members.user', 'contributions.product', 'contributions.store', 'contributions.counter', 'lines.product', 'lines.store', 'creator']);

        return view('inventory.count-report', compact('count'));
    })->whereNumber('count')->middleware('can:stock_counts.view')->name('inventory.counts.report');
    $router->get('inventory/counts/{count}/lines/{line}/movements', function (StockCount $count, StockCountLine $line) {
        abort_unless((int) $line->stock_count_id === (int) $count->id && $count->locations()->where('store_id', $line->store_id)->exists(), 404);
        app(AssertInventoryStoreScope::class)->execute((int) $line->store_id);
        $line->load(['product', 'store', 'counter']);
        $movements = StockMovement::query()->with('creator')->where('store_id', $line->store_id)->where('product_id', $line->product_id)
            ->when($line->reference_movement_id !== null,
                fn ($query) => $query->where('id', '>', $line->reference_movement_id),
                fn ($query) => $query->where('posted_at', '>', $count->reference_at))
            ->orderBy('id')->paginate(50)->withQueryString();

        return view('inventory.count-movements', compact('count', 'line', 'movements'));
    })->whereNumber('count')->whereNumber('line')->middleware('can:stock_counts.view')->name('inventory.counts.movements');

    $router->post('inventory/counts/{count}/entry', function (StockCount $count, RecordStockCountLineAction $action) {
        $validated = request()->validate(['counted_quantities' => ['required', 'array'], 'counted_quantities.*' => ['required', 'numeric', 'min:0'], 'input_methods' => ['nullable', 'array'], 'recount' => ['nullable', 'boolean']]);
        try {
            $action->execute($count->id, $validated['counted_quantities'], $validated['input_methods'] ?? [], (bool) ($validated['recount'] ?? false));

            return back()->with('success', __('Stock count quantities saved.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.entry.save');

    $router->post('inventory/counts/{count}/entry/recount', function (StockCount $count, RecordStockCountLineAction $action) {
        $validated = request()->validate(['counted_quantities' => ['required', 'array'], 'counted_quantities.*' => ['required', 'numeric', 'min:0'], 'input_methods' => ['nullable', 'array']]);
        try {
            $action->execute($count->id, $validated['counted_quantities'], $validated['input_methods'] ?? [], true);

            return back()->with('success', __('Stock recount saved.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.entry.recount');

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
