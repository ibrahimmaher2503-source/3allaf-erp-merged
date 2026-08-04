<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (app()->environment('local') && (bool) env('DEMO_AUTH', false)) {
    Route::get('/__demo/auth', function () {
        abort_unless(app()->environment('local') && (bool) env('DEMO_AUTH', false), 404);
        $user = User::query()->where('email', 'demo.admin@toyjoy.local')->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->intended('/catalog/products/import');
    })->name('demo.auth');
}

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->middleware('can:dashboard_reports.view')->name('dashboard');
    Route::view('pos', 'pages.pos.index')->middleware('can:pos_sales.view')->name('pos');
});

require __DIR__.'/platform.php';
require __DIR__.'/catalog.php';
require __DIR__.'/settings.php';
