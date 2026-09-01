<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveOpeningInventoryAction;
use App\Modules\Inventory\Actions\ImportOpeningInventoryWorkbookAction;
use App\Modules\Inventory\Actions\ReverseOpeningInventoryAction;
use App\Modules\Inventory\Actions\SaveOpeningInventoryDraftAction;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\OpeningInventoryZeroDecision;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

$router = app('router');
$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $render = function (?OpeningInventoryDocument $document = null) {
        Gate::authorize('inventory_stock_card.create');
        $user = Auth::user();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->orderBy('code')->get();
        $products = Product::query()->sellable()->where('product_type', '!=', 'service')->whereNotNull('average_cost')->orderBy('item_code')->limit(500)->get();
        $companyId = (int) Company::query()->where('status', 'active')->value('id');
        $documents = OpeningInventoryDocument::query()->where('company_id', $companyId)->with(['lines.product', 'lines.store'])->latest('id')->limit(25)->get();
        return view('inventory.opening', compact('document', 'documents', 'products', 'stores'));
    };
    $router->get('inventory/opening', fn () => $render())->middleware('can:inventory_stock_card.create')->name('inventory.opening.index');
    $router->get('inventory/opening/{document}', fn (OpeningInventoryDocument $document) => $render(tap($document, fn ($item) => abort_unless((int) $item->company_id === (int) Company::query()->where('status', 'active')->value('id'), 404))->load(['lines.product', 'lines.store'])))->whereNumber('document')->middleware('can:inventory_stock_card.view')->name('inventory.opening.show');
    $router->get('inventory/opening-template.xlsx', fn (MasterDataDocument $documents) => $documents->xlsx('opening-inventory-template.xlsx', ImportOpeningInventoryWorkbookAction::HEADERS, [['01234000001', 'WH-CAI-01', '10']], [[__('Instructions')], [__('Use a warehouse or stock-bearing Sales Outlet code. Unit cost comes from the Product Card and is never accepted from this workbook.')]]))->middleware('can:inventory_stock_card.create')->name('inventory.opening.template');
    $router->post('inventory/opening/import', function (ImportOpeningInventoryWorkbookAction $action) {
        $data = request()->validate(['workbook' => ['required', 'file', 'mimes:xlsx', 'max:10240'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $result = $action->execute($data['workbook']->getRealPath(), $data['notes'] ?? null);
        session(['opening_inventory_rejections' => $result['rejections']]);
        $target = $result['document'] ? route('inventory.opening.show', $result['document']) : route('inventory.opening.index');
        return redirect($target)->with('success', __('Opening inventory import: :total total, :accepted accepted, :rejected rejected.', $result));
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.import');
    $router->get('inventory/opening-import-rejections.xlsx', function (MasterDataDocument $documents) {
        $rows = session('opening_inventory_rejections', []); abort_if($rows === [], 404);
        return $documents->xlsx('opening-inventory-rejections.xlsx', [__('Original row'), ...ImportOpeningInventoryWorkbookAction::HEADERS, __('Rejection reason')], $rows);
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.rejections');
    $router->post('inventory/opening', function (SaveOpeningInventoryDraftAction $action) {
        request()->merge(['lines' => collect(request()->input('lines', []))->filter(fn (array $line): bool => collect($line)->contains(fn ($value): bool => filled($value)))->values()->all()]);
        $data = request()->validate(['notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:5000'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.store_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'integer', 'min:1']]);
        $document = $action->execute($data['lines'], notes: $data['notes'] ?? null);
        return redirect()->route('inventory.opening.show', $document)->with('success', __('Opening inventory draft saved. Review totals before approval.'));
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.store');
    $router->post('inventory/opening/{document}/approve', function (OpeningInventoryDocument $document, ApproveOpeningInventoryAction $action) { $action->execute($document->id); return back()->with('success', __('Opening inventory approved and posted once.')); })->whereNumber('document')->middleware('can:inventory_stock_card.approve')->name('inventory.opening.approve');
    $router->post('inventory/opening/{document}/reverse', function (OpeningInventoryDocument $document, ReverseOpeningInventoryAction $action) { $data = request()->validate(['reason' => ['required', 'string', 'max:1000']]); $action->execute($document->id, $data['reason']); return redirect()->route('inventory.opening.index')->with('success', __('Opening inventory reversal posted with audit history.')); })->whereNumber('document')->middleware('can:inventory_stock_card.reverse')->name('inventory.opening.reverse');
    $router->post('inventory/opening/start-without-stock', function () {
        Gate::authorize('inventory_stock_card.approve');
        $company = Company::query()->where('status', 'active')->firstOrFail();
        $validated = request()->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $decision = OpeningInventoryZeroDecision::query()->updateOrCreate(['company_id' => $company->id], ['decided_by' => Auth::id(), 'reason' => $validated['reason'] ?? null, 'decided_at' => now()]);
        app(RecordAuditEvent::class)->execute('inventory', 'start_without_opening_inventory', $decision, after: $decision->only(['company_id', 'decided_by', 'decided_at']));
        return back()->with('success', __('The audited decision to start without opening inventory was recorded.'));
    })->middleware('can:inventory_stock_card.approve')->name('inventory.opening.zero');
});
