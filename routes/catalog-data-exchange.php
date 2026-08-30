<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\CatalogReferenceImportBatch;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Support\DataExchange\MasterDataDocument;

$router = app('router');
$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->get('catalog/reference-import/{batch}/rejections', function (CatalogReferenceImportBatch $batch, MasterDataDocument $documents) {
        abort_unless((int) $batch->created_by === (int) auth()->id(), 404);
        $headers = [__('Original row'), ...StageCatalogReferenceImportAction::templateHeaders($batch->type), __('Rejection reason')];
        $rows = $batch->rows()->where('status', 'invalid')->orderBy('row_number')->get()->map(fn ($row) => [$row->row_number, ...array_map(fn ($key) => $row->raw_data[$key] ?? '', StageCatalogReferenceImportAction::templateHeaders($batch->type)), collect($row->errors)->join(' | ')]);
        return $documents->xlsx($batch->type.'-import-rejections-'.$batch->id.'.xlsx', $headers, $rows);
    })->whereNumber('batch')->middleware('can:products_categories_brands.view')->name('catalog.reference-import.rejections');

    $router->get('catalog/products/import/{batch}/rejections.xlsx', function (ProductImportBatch $batch, MasterDataDocument $documents) {
        abort_unless((int) $batch->created_by === (int) auth()->id(), 404);
        $headers = [__('Original row'), __('Item code'), __('Arabic name'), __('English name'), __('Status'), __('Rejection reason')];
        $rows = $batch->rows()->where('status', 'invalid')->orderBy('row_number')->get()->map(fn ($row) => [$row->row_number, $row->mapped_data['item_code'] ?? $row->raw_data['item_code'] ?? '', $row->mapped_data['name_ar'] ?? $row->raw_data['name_ar'] ?? '', $row->mapped_data['name_en'] ?? $row->raw_data['name_en'] ?? '', $row->status, collect($row->errors)->join(' | ')]);
        return $documents->xlsx('product-import-rejections-'.$batch->id.'.xlsx', $headers, $rows);
    })->whereNumber('batch')->middleware('can:products_categories_brands.export')->name('catalog.products.import.rejections.xlsx');

    $router->post('catalog/products/import-card', function (ImportProductCardsWorkbookAction $action) {
        $data = request()->validate(['workbook' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $result = $action->execute($data['workbook']->getRealPath()); session(['product_card_import_rejections' => $result['rejections']]);
        return back()->with('status', __('Product Card import: :total total, :added added, :rejected rejected.', $result));
    })->middleware('can:products_categories_brands.create')->name('catalog.products.import-card');
    $router->get('catalog/products/import-card-rejections.xlsx', function (MasterDataDocument $documents) { $rows = session('product_card_import_rejections', []); abort_if($rows === [], 404); return $documents->xlsx('product-card-import-rejections.xlsx', [__('Original row'), ...ImportProductCardsWorkbookAction::HEADERS, __('Rejection reason')], $rows); })->middleware('can:products_categories_brands.export')->name('catalog.products.import-card.rejections');
    $router->get('catalog/products/import/template.xlsx', function (MasterDataDocument $documents) {
        $headers = ['barcode_registration_type', 'barcode', 'supplier_code', 'model_number', 'name_ar', 'name_en', 'unit_cost', 'base_consumer_price', 'category_code', 'brand_code', 'open_price', 'product_type', 'unit_of_measure', 'filter_codes', 'length', 'width', 'height', 'dimension_unit', 'weight', 'weight_unit', 'sell_online', 'short_description_ar', 'short_description_en', 'detailed_description_ar', 'detailed_description_en', 'uses_battery', 'status'];
        return $documents->xlsx('product-card-import-template.xlsx', $headers, [['international', '6221234567890', '', 'MODEL-001', 'لعبة تعليمية', 'Educational toy', '75.00', '110.00', 'TOYS', 'BRAND-01', 'no', 'standard', 'piece', 'COLOUR:RED', '20', '10', '5', 'cm', '0.5', 'kg', 'yes', 'وصف قصير', 'Short description', '', '', 'no', 'active'], ['local', '', 'SUP-1234', 'MODEL-002', 'صنف محلي', 'Local product', '50.00', '80.00', 'TOYS', '', 'no', 'standard', 'piece', '', '', '', '', 'cm', '', 'kg', 'no', '', '', '', '', 'no', 'active']], [[__('Instructions')], [__('International rows require a valid unique GTIN barcode. Local rows require a stable supplier code with at least four numeric digits and receive the next committed supplier sequence.')], [__('References resolve by stable code. Existing products and duplicate barcodes are rejected and never overwritten.')]]);
    })->middleware('can:products_categories_brands.create')->name('catalog.products.import.template.xlsx');

    $router->get('catalog/{type}/import-template.xlsx', function (string $type, MasterDataDocument $documents) {
        abort_unless(in_array($type, ['categories', 'brands'], true), 404);
        $kind = $type === 'categories' ? 'category' : 'brand'; $headers = StageCatalogReferenceImportAction::templateHeaders($kind);
        $example = $kind === 'category' ? ['TOYS', 'ألعاب', 'Toys', '', 'active', 10] : ['BRAND-01', 'علامة', 'Brand', 'active', 10];
        return $documents->xlsx($kind.'-import-template.xlsx', $headers, [$example], [[__('Instructions')], [__('Stable codes are permanent. Category parent_code resolves only by stable code. Valid rows are imported while rejected rows remain downloadable in a separate workbook.')]]);
    })->middleware('can:products_categories_brands.create')->name('catalog.data-exchange.template');
    $router->get('catalog/{type}/export/{format}', function (string $type, string $format, MasterDataDocument $documents) {
        abort_unless(in_array($type, ['products', 'categories', 'brands'], true) && in_array($format, ['xlsx', 'pdf'], true), 404);
        if ($type === 'products') {
            $query = Product::query()->with(['category.parent', 'brand', 'barcodes'])->familiesAndSimple()->limit(5000);
            if ($q = trim((string) request('q'))) $query->where(fn ($nested) => $nested->where('item_code', 'like', '%'.$q.'%')->orWhere('name_ar', 'like', '%'.$q.'%')->orWhere('name_en', 'like', '%'.$q.'%')->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', 'like', '%'.$q.'%')));
            if (in_array(request('status'), ['active', 'inactive'], true)) $query->where('status', request('status'));
            $headers = [__('Item code'), __('Barcode'), __('Arabic name'), __('English name'), __('Model number'), __('Category path'), __('Brand'), __('Product type'), __('Unit cost'), __('Base consumer selling price'), __('Status')];
            $rows = $query->get()->map(fn ($p) => [$p->item_code, $p->barcodes->firstWhere('is_primary', true)?->barcode, $p->name_ar, $p->name_en, $p->model_number, collect([$p->category?->parent?->name_ar, $p->category?->name_ar])->filter()->join(' / '), $p->brand?->name_ar, __($p->product_type), $p->average_cost, $p->sale_price, __($p->status)]);
        } else {
            $model = $type === 'categories' ? Category::class : Brand::class;
            $query = $model::query()->limit(5000);
            if ($type === 'categories') $query->with('parent')->orderBy('parent_id')->orderBy('sort_order'); else $query->orderBy('code');
            if ($q = trim((string) request('q'))) $query->where(fn ($nested) => $nested->where('code', 'like', '%'.$q.'%')->orWhere('name_ar', 'like', '%'.$q.'%')->orWhere('name_en', 'like', '%'.$q.'%'));
            if (in_array(request('status'), ['active', 'inactive'], true)) $query->where('status', request('status'));
            $headers = [__('Code'), __('Arabic name'), __('English name'), __('Parent code'), __('Order'), __('Status')];
            $rows = $query->get()->map(fn ($record) => [$record->code, $record->name_ar, $record->name_en, $record->parent?->code ?? '', $record->sort_order ?? 0, __($record->status)]);
        }
        return $format === 'pdf' ? $documents->pdf($type.'.pdf', __(ucfirst($type)), $headers, $rows) : $documents->xlsx($type.'.xlsx', $headers, $rows);
    })->middleware('can:products_categories_brands.export')->name('catalog.data-exchange.export');
});
