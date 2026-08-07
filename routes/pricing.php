<?php

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Pricing\Models\LabelQueue;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\HtmlString;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('pricing', 'pricing::index')
        ->middleware('can:pricing_labels.view')
        ->name('pricing.index');

    $router->livewire('pricing/approvals', 'pricing::index')
        ->middleware('can:pricing_labels.approve')
        ->name('pricing.approvals');

    $router->get('pricing/labels', function () {
        return view('layouts.app', [
            'title' => __('Label Queue Readiness'),
            'slot' => new HtmlString(view('pricing.labels', [
                'approvedPriceCount' => PriceVersion::query()->where('state', 'approved')->count(),
                'stockBalanceCount' => StockBalance::query()->count(),
                'printerCount' => PrinterConfiguration::query()->where('status', 'active')->count(),
                'queues' => LabelQueue::query()->with(['product', 'store', 'version', 'printer', 'printEvents'])->latest('id')->get(),
            ])->render()),
        ]);
    })
        ->middleware('can:pricing_labels.view')
        ->name('pricing.labels');
});
