<?php

use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('purchasing/orders', 'purchasing::orders')
        ->middleware('can:purchase_orders.view')
        ->name('purchasing.orders');

    $router->get('purchasing/orders/{order}/print', function (PurchaseOrder $order) {
        Gate::authorize('purchase_orders.print');

        return view('purchasing.print', [
            'order' => $order->load(['supplier', 'store', 'creator', 'lines.product']),
        ]);
    })->whereNumber('order')->middleware('can:purchase_orders.print')->name('purchasing.orders.print');
});
