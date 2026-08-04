<?php

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('catalog/products', 'catalog::products')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products');

    $router->livewire('catalog/categories', 'catalog::categories')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.categories');

    $router->livewire('catalog/brands', 'catalog::brands')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.brands');
});
