<?php

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
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
        $actor = request()->user();
        abort_unless($actor instanceof \App\Models\User, 403);
        $visibleStores = Store::query()->visibleTo($actor)->select('id');

        return view('layouts.app', [
            'title' => __('Label Queue Readiness'),
            'slot' => new HtmlString(view('pricing.labels', [
                'approvedPriceCount' => PriceVersion::query()->where('state', 'approved')->whereHas('lines', fn ($query) => $query->whereIn('store_id', Store::query()->visibleTo($actor)->select('id')))->count(),
                'stockBalanceCount' => StockBalance::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))->count(),
                'printerCount' => PrinterConfiguration::query()->visibleTo($actor)->where('status', 'active')->count(),
                'queues' => LabelQueue::query()->whereIn('store_id', $visibleStores)->with(['product', 'store', 'version', 'printer', 'printEvents'])->latest('id')->limit(100)->get(),
            ])->render()),
        ]);
    })
        ->middleware('can:pricing_labels.view')
        ->name('pricing.labels');

    $router->livewire('pricing/{mode}', 'pricing::index')
        ->whereIn('mode', ['workspace', 'versions', 'unpriced', 'history'])
        ->middleware('can:pricing_labels.view')
        ->name('pricing.focus');
});
