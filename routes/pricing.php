<?php

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\BarcodeDocumentSource;
use App\Modules\Pricing\Services\BarcodeLabelService;
use App\Modules\Pricing\Services\BarcodeSellingPriceResolver;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('pricing', 'pricing::index')
        ->middleware('can:pricing_labels.view')
        ->name('pricing.index');

    $router->livewire('pricing/lists/manage', 'pricing::lists')
        ->middleware('can:pricing_lists.view')
        ->name('pricing.lists');

    $router->livewire('pricing/approvals', 'pricing::index')
        ->middleware('can:pricing_labels.approve')
        ->name('pricing.approvals');

    $router->get('pricing/labels', function (BarcodeLabelService $labels) {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $search = trim((string) request('search'));
        $prefill = [];
        $documentPrices = [];
        $documentDestination = null;
        $documentStoreId = null;
        if (filled(request('source')) && filled(request('number'))) {
            Gate::authorize('pricing_labels.view');
            Gate::authorize('pricing_labels.create');
            $documentStoreId = (int) request('selling_store_id');
            $prepared = app(BarcodeDocumentSource::class)->prepare(
                $actor, (string) request('source'), trim((string) request('number')),
                (int) request('destination_store_id'), $documentStoreId,
            );
            $prefill = $prepared['quantities'];
            $documentPrices = $prepared['prices'];
            $documentDestination = $prepared['destination'];
            session(['barcode_label_document_context' => ['store_id' => $documentStoreId, 'product_ids' => array_keys($prefill)]]);
        } else {
            session()->forget('barcode_label_document_context');
        }
        $invoiceId = (int) request('invoice');
        $destinationId = (int) request('store');
        if ($invoiceId && $destinationId && $prefill === []) {
            abort_unless(Gate::allows('pricing_labels.view') || Gate::allows('purchase_invoices.print_labels'), 403);
            $destination = Store::visibleTo($actor)->where('type', 'selling')->findOrFail($destinationId);
            $invoice = PurchaseInvoice::query()->where('status', 'approved')->with(['distributions' => fn ($query) => $query->where('destination_store_id', $destination->id), 'distributions.line'])->findOrFail($invoiceId);
            foreach ($invoice->distributions as $distribution) {
                $prefill[(int) $distribution->line->product_id] = max(1, (int) $distribution->quantity);
            }
            session(['barcode_label_invoice_context' => ['invoice_id' => $invoice->id, 'store_id' => $destination->id, 'product_ids' => array_keys($prefill)]]);
        } else {
            Gate::authorize('pricing_labels.view');
            session()->forget('barcode_label_invoice_context');
        }
        $restored = session()->pull('barcode_label_workspace', []);
        $products = $prefill === [] ? collect() : Product::query()->sellable()->with(['barcodes' => fn ($q) => $q->active()->orderByDesc('is_primary')->orderBy('id')])->whereKey(array_keys($prefill))->get();
        $profile = $labels->profile($actor);

        return view('layouts.app', [
            'title' => __('Barcode labels'),
            'slot' => new HtmlString(view('pricing.labels', [
                'products' => $products,
                'documentPrices' => $documentPrices,
                'documentDestination' => $documentDestination,
                'documentStoreId' => $documentStoreId,
                'documentStores' => Store::query()->visibleTo($actor)->where('status', 'active')->orderBy('code')->get(),
                'stores' => Store::query()->visibleTo($actor)->where('status', 'active')->where('type', 'selling')->with('branch')->orderBy('code')->get(),
                'templates' => PrintTemplate::visibleTo($actor)->where('status', 'active')->where('document_type', 'barcode_label')->orderBy('code')->get(),
                'printers' => PrinterConfiguration::visibleTo($actor)->where('status', 'active')->with('printTemplate')->orderBy('name')->get(),
                'search' => $search, 'prefill' => $prefill, 'restored' => $restored, 'defaultProfile' => $profile,
                'canCreateLabels' => Gate::allows('pricing_labels.create') || is_array(session('barcode_label_invoice_context')),
                'canManageBarcodes' => Gate::allows('products_categories_brands.edit') || Gate::allows('pricing_labels.create'),
            ])->render()),
        ]);
    })
        ->name('pricing.labels');

    $router->get('pricing/labels/prices', function () {
        abort_unless(Gate::allows('pricing_labels.view') || Gate::allows('purchase_invoices.print_labels'), 403);
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $data = request()->validate(['store_id' => ['required', 'integer'], 'product_ids' => ['required', 'array', 'max:100'], 'product_ids.*' => ['required', 'integer']]);
        if (! Gate::allows('pricing_labels.view')) {
            $invoiceContext = session('barcode_label_invoice_context');
            abort_unless(is_array($invoiceContext)
                && (int) $invoiceContext['store_id'] === (int) $data['store_id']
                && collect($data['product_ids'])->every(fn ($id) => in_array((int) $id, array_map('intval', $invoiceContext['product_ids']), true)), 403);
        }
        $store = Store::query()->visibleTo($actor)->where('status', 'active')->where('type', 'selling')->findOrFail((int) $data['store_id']);
        $prices = app(BarcodeSellingPriceResolver::class)->resolveForStore($data['product_ids'], $store);

        return response()->json(['prices' => $prices->all()]);
    })->name('pricing.labels.prices');

    $router->get('pricing/labels/search', function () {
        Gate::authorize('pricing_labels.view');
        $term = trim((string) request('q'));
        if ($term === '') {
            return response()->json(['data' => [], 'next_page' => null]);
        }
        $page = max(1, min(100, (int) request('page', 1)));
        $exact = request()->boolean('exact');
        $query = Product::query()->sellable()->with(['barcodes' => fn ($q) => $q->active()->orderByDesc('is_primary')->orderBy('id')]);
        $query->where(function ($scope) use ($term, $exact): void {
            $prefix = addcslashes($term, '%_\\').'%';
            $contains = '%'.addcslashes($term, '%_\\').'%';
            $scope->whereHas('barcodes', fn ($barcodes) => $barcodes->active()->where('barcode', $exact ? '=' : 'like', $exact ? $term : $prefix));
            if (! $exact) {
                $scope->orWhere('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)->orWhere('name_ar', 'like', $contains)->orWhere('name_en', 'like', $contains);
            }
        });
        $rows = $query->orderByRaw('CASE WHEN item_code = ? THEN 0 ELSE 1 END', [$term])->orderBy('item_code')->forPage($page, 21)->get();
        $more = $rows->count() > 20;

        return response()->json(['data' => $rows->take(20)->map(fn (Product $product): array => [
            'id' => $product->id, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en,
            'item_code' => $product->item_code, 'model_number' => $product->model_number,
            'barcodes' => $product->barcodes->map(fn ($barcode): array => ['id' => $barcode->id, 'barcode' => $barcode->barcode, 'source' => $barcode->source, 'is_primary' => (bool) $barcode->is_primary])->values(),
        ])->values(), 'next_page' => $more ? $page + 1 : null]);
    })->name('pricing.labels.search');

    $router->post('pricing/labels/preview', function () {
        $invoiceContext = session('barcode_label_invoice_context');
        $normalAccess = Gate::allows('pricing_labels.create');
        abort_unless($normalAccess || is_array($invoiceContext), 403);
        $data = request()->validate(['products' => ['required', 'array', 'max:100'], 'products.*.selected' => ['nullable', 'boolean'], 'products.*.product_id' => ['required', 'integer'], 'products.*.barcode_id' => ['nullable', 'integer'], 'products.*.copies' => ['required', 'integer', 'min:1', 'max:500'], 'store_id' => [$normalAccess ? 'required' : 'nullable', 'integer'], 'template_id' => ['nullable', 'integer'], 'printer_id' => ['nullable', 'integer']]);
        $data['rows'] = collect($data['products'])->filter(fn ($row) => (bool) ($row['selected'] ?? false))->values()->all();
        if ($data['rows'] === []) {
            throw ValidationException::withMessages(['products' => __('Select at least one product with an active barcode.')]);
        }
        $documentContext = session('barcode_label_document_context');
        if (is_array($documentContext)) {
            abort_unless((int) ($data['store_id'] ?? 0) === (int) $documentContext['store_id'], 403);
            abort_unless(collect($data['rows'])->every(fn ($row) => in_array((int) $row['product_id'], $documentContext['product_ids'], true)), 403);
        }
        if (! $normalAccess) {
            $allowed = array_map('intval', (array) ($invoiceContext['product_ids'] ?? []));
            abort_unless(collect($data['rows'])->every(fn ($row) => in_array((int) $row['product_id'], $allowed, true)), 403);
            $data['store_id'] = (int) $invoiceContext['store_id'];
        }
        session(['barcode_label_preview' => $data, 'barcode_label_workspace' => $data['products']]);

        return redirect()->route('pricing.labels.preview.show');
    })->name('pricing.labels.preview');

    $router->get('pricing/labels/preview', function (BarcodeLabelService $service) {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $data = session('barcode_label_preview');
        abort_unless(is_array($data), 404);

        return view('pricing.label-print', $service->compose($actor, $data['rows'], $service->profile($actor, $data['template_id'] ?? null, $data['printer_id'] ?? null), $data['store_id'] ?? null, $data['prices'] ?? []) + ['toolbar' => true]);
    })->middleware('auth')->name('pricing.labels.preview.show');

    $router->get('pricing/labels/download', function (BarcodeLabelService $service) {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $data = session('barcode_label_preview');
        abort_unless(is_array($data), 404);
        try {
            $document = $service->compose($actor, $data['rows'], $service->profile($actor, $data['template_id'] ?? null, $data['printer_id'] ?? null), $data['store_id'] ?? null, $data['prices'] ?? []);

            return $service->pdfResponse($document);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('pricing.labels.preview.show')->with('error', __('The barcode PDF could not be generated. Verify the label settings and try again.'));
        }
    })->middleware('auth')->name('pricing.labels.download');

    $router->post('pricing/labels/barcodes', function (AddBarcodeAction $action) {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $data = request()->validate(['barcode_product_id' => ['required', 'integer'], 'barcode_action' => ['required', 'in:manual,generate'], 'barcode_values' => ['nullable', 'array'], 'barcode_values.*' => ['nullable', 'string', 'max:48'], 'products' => ['nullable', 'array'], 'return_search' => ['nullable', 'string', 'max:100']]);
        session(['barcode_label_workspace' => $data['products'] ?? []]);
        $value = (string) ($data['barcode_values'][$data['barcode_product_id']] ?? '');
        if ($data['barcode_action'] === 'generate') {
            $action->generateWorkspaceCode128((int) $data['barcode_product_id']);
        } elseif (preg_match('/^\d{13}$/', trim($value))) {
            $action->addSupplierBarcode((int) $data['barcode_product_id'], $value);
        } else {
            $action->addManualCode128((int) $data['barcode_product_id'], $value);
        }

        return redirect()->route('pricing.labels', ['search' => $data['return_search']]);
    })->name('pricing.labels.barcodes.store');

    $router->livewire('pricing/{mode}', 'pricing::index')
        ->whereIn('mode', ['workspace', 'versions', 'unpriced', 'history'])
        ->middleware('can:pricing_labels.view')
        ->name('pricing.focus');
});
