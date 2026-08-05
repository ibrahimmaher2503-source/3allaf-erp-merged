<?php

use App\Modules\Catalog\Actions\DownloadProductImportErrorsAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Models\Attachment;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('catalog/products', 'catalog::products')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products');

    $router->livewire('catalog/products/import', 'catalog::product-import')
        ->middleware('can:products_categories_brands.create')
        ->name('catalog.products.import');

    $router->get('catalog/products/import/{batch}/errors', function (\App\Modules\Catalog\Models\ProductImportBatch $batch, DownloadProductImportErrorsAction $action) {
        return $action->execute($batch);
    })->whereNumber('batch')->middleware('can:products_categories_brands.export')->name('catalog.products.import.errors');

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

    $router->livewire('suppliers', 'catalog::suppliers')
        ->middleware('can:suppliers.view')
        ->name('suppliers.index');
});
