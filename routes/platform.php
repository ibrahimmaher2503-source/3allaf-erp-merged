<?php

$router = app('router');

$router->post('locale', function (Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:'.implode(',', config('app.supported_locales', ['ar', 'en']))],
    ]);

    session(['locale' => $validated['locale']]);

    return back();
})->name('locale.switch');

$router->middleware(['auth', 'verified'])->group(function () use ($router) {
    $router->livewire('admin/settings', 'platform::admin.settings')->middleware('can:company_settings.view')->name('admin.settings');
    $router->livewire('admin/branches', 'platform::admin.branches')->middleware('can:branches_stores.view')->name('admin.branches');
    $router->livewire('admin/stores', 'platform::admin.stores')->middleware('can:branches_stores.view')->name('admin.stores');
    $router->livewire('admin/cash-drawers', 'platform::admin.drawers')->middleware('can:drawers_payments_tax_numbering_printers.view')->name('admin.cash-drawers');
    $router->livewire('admin/authorization-baseline', 'platform::admin.authorization-baseline')->middleware('can:users_roles_permissions.view')->name('admin.authorization-baseline');

    $router->livewire('admin/system/health', 'platform::system.health')->middleware('can:audit_logs.view')->name('system.health');
    $router->livewire('admin/audit', 'platform::system.audit-log')->middleware('can:audit_logs.view')->name('admin.audit');
    $router->livewire('admin/system/ui-showcase', 'platform::system.ui-showcase')->middleware('can:dashboard_reports.view')->name('system.ui-showcase');
    $router->view('system/app', 'platform.system.app')->middleware('can:dashboard_reports.view')->name('system.app');
});

$router->get('forbidden', function () {
    return response()->view('errors.403', [], 403);
})->name('forbidden');
