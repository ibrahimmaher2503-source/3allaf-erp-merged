<?php

use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (InitialSetupStatus $setupStatus) {
        return view('dashboard', ['setup' => $setupStatus->snapshot()]);
    })->middleware('can:dashboard_reports.view')->name('dashboard');
    require __DIR__.'/retail.php';
    require __DIR__.'/customers.php';
    require __DIR__.'/party.php';
    require __DIR__.'/assets.php';
    require __DIR__.'/quotations.php';
    require __DIR__.'/reporting.php';
    require __DIR__.'/returns-gifts.php';
});

require __DIR__.'/platform.php';
require __DIR__.'/catalog.php';
require __DIR__.'/purchasing.php';
require __DIR__.'/pricing.php';
require __DIR__.'/inventory.php';
require __DIR__.'/settings.php';
