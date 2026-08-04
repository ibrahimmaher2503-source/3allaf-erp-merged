<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->middleware('can:dashboard_reports.view')->name('dashboard');
    Route::view('pos', 'pages.pos.index')->middleware('can:pos_sales.view')->name('pos');
});

require __DIR__.'/platform.php';
require __DIR__.'/catalog.php';
require __DIR__.'/settings.php';
