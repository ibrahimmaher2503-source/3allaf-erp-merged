<?php

use App\Models\User;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (app()->environment('local') && (bool) env('DEMO_AUTH', false)) {
    Route::get('/__demo/auth', function () {
        abort_unless(app()->environment('local') && (bool) env('DEMO_AUTH', false), 404);

        $personas = [
            'demo-admin' => 'demo.admin@toyjoy.local',
            'demo-branch-manager' => 'demo.branch.manager@toyjoy.local',
            'demo-cashier' => 'demo.cashier@toyjoy.local',
            'demo-reviewer' => 'demo.reviewer@toyjoy.local',
            'demo-no-access' => 'demo.no.access@toyjoy.local',
        ];

        $personaKey = request()->query('as');
        if ($personaKey === null || $personaKey === '') {
            $personaKey = 'demo-admin';
        }

        abort_unless(is_string($personaKey) && array_key_exists($personaKey, $personas), 404);

        $user = User::query()->where('email', $personas[$personaKey])->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        $redirectTarget = request()->query('redirect', '/catalog/products/import');
        if (! is_string($redirectTarget) || ! str_starts_with($redirectTarget, '/') || str_starts_with($redirectTarget, '//')) {
            $redirectTarget = '/catalog/products/import';
        }

        return redirect()->intended($redirectTarget);
    })->name('demo.auth');
}

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (InitialSetupStatus $setupStatus) {
        return view('dashboard', ['setup' => $setupStatus->snapshot()]);
    })->middleware('can:dashboard_reports.view')->name('dashboard');
    Route::view('pos', 'pages.pos.index')->middleware('can:pos_sales.view')->name('pos');
});

require __DIR__.'/platform.php';
require __DIR__.'/catalog.php';
require __DIR__.'/purchasing.php';
require __DIR__.'/settings.php';
