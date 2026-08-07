<?php

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
            ])->render()),
        ]);
    })
        ->middleware('can:pricing_labels.view')
        ->name('pricing.labels');
});
