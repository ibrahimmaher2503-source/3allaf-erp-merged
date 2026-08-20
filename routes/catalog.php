<?php

use App\Modules\Catalog\Actions\DownloadProductImportErrorsAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('catalog/products', 'catalog::products')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products');

    $router->livewire('catalog/products/import', 'catalog::product-import')
        ->middleware('can:products_categories_brands.create')
        ->name('catalog.products.import');

    $router->livewire('catalog/product-options', 'catalog::product-options')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.product-options');

    $router->view('catalog/lookups', 'catalog.lookups')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.lookups');

    $router->get('catalog/products/import/{batch}/errors', function (ProductImportBatch $batch, DownloadProductImportErrorsAction $action) {
        return $action->execute($batch);
    })->whereNumber('batch')->middleware('can:products_categories_brands.export')->name('catalog.products.import.errors');

    $router->get('catalog/products/import/{batch}/source/{attachment}', function (ProductImportBatch $batch, Attachment $attachment) {
        abort_unless($attachment->purpose === 'import_source' && $attachment->source_type === ProductImportBatch::class && $attachment->source_id === (string) $batch->id, 404);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('products_categories_brands.view')
                && $candidate->source_type === ProductImportBatch::class
                && $candidate->source_id === (string) $batch->id,
        );
    })->whereNumber('batch')->middleware('can:products_categories_brands.view')->name('catalog.products.import.source');

    $router->livewire('catalog/products/create', 'catalog::product-form')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.create');

    $router->livewire('catalog/products/{product}/edit', 'catalog::product-form')
        ->whereNumber('product')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.edit');

    $router->livewire('catalog/products/{product}', 'catalog::product-detail')
        ->whereNumber('product')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.show');

    $router->get('catalog/products/{product}/media/{attachment}', function (Product $product, Attachment $attachment) {
        Gate::authorize('products_categories_brands.view');

        $authorized = $attachment->purpose === 'product_image'
            && $attachment->source_type === Product::class
            && $attachment->source_id === (string) $product->id
            && $product->images()->where('attachment_id', $attachment->id)->exists();

        if (! $authorized) {
            abort(403);
        }

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $sourceAttachment): bool => Gate::forUser($user)->allows('products_categories_brands.view')
                && $sourceAttachment->purpose === 'product_image'
                && $sourceAttachment->source_type === Product::class
                && $sourceAttachment->source_id === (string) $product->id
                && $product->images()->where('attachment_id', $sourceAttachment->id)->exists(),
        );
    })->whereNumber('product')->name('catalog.products.media');

    $router->livewire('catalog/categories', 'catalog::categories')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.categories');

    $router->livewire('catalog/brands', 'catalog::brands')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.brands');

    $router->livewire('catalog/suppliers', 'catalog::suppliers')
        ->middleware('can:suppliers.view')
        ->name('catalog.suppliers');

    $router->livewire('catalog/suppliers/import', 'catalog::supplier-import')
        ->middleware('can:suppliers.create')->name('catalog.suppliers.import');
    $router->get('catalog/suppliers/import/template', function () {
        $path = tempnam(storage_path('app'), 'supplier-import-').'.xlsx'; $writer = WriterFactory::createFromFile($path); $writer->openToFile($path); $writer->addRow(Row::fromValues(StageSupplierImportAction::templateHeaders())); $writer->close();
        return response()->download($path, 'supplier-import-template.xlsx')->deleteFileAfterSend(true);
    })->middleware('can:suppliers.create')->name('catalog.suppliers.import.template');

    $router->livewire('catalog/reference-import', 'catalog::reference-import')
        ->middleware('can:products_categories_brands.create')
        ->name('catalog.reference-import');
    $router->get('catalog/reference-import/template/{type}', function (string $type) {
        abort_unless(in_array($type, ['category', 'brand', 'age', 'character', 'colour', 'gender'], true), 404);

        $path = tempnam(storage_path('app'), "{$type}-import-").'.xlsx'; $writer = WriterFactory::createFromFile($path); $writer->openToFile($path); $writer->addRow(Row::fromValues(\App\Modules\Catalog\Actions\StageCatalogReferenceImportAction::templateHeaders($type))); $writer->close();
        return response()->download($path, "{$type}-import-template.xlsx")->deleteFileAfterSend(true);
    })->middleware('can:products_categories_brands.create')->name('catalog.reference-import.template');

    $router->livewire('suppliers', 'catalog::suppliers')
        ->middleware('can:suppliers.view')
        ->name('suppliers.index');
});
