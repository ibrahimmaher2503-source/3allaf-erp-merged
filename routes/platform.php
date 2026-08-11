<?php

use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Actions\ExportAuditLogs;
use App\Modules\Platform\Http\Controllers\DashboardAssistantController;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Support\TutorialRegistry;
use App\Modules\Platform\Support\UserFlowRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Backup\BackupDestination\BackupDestination;

$router = app('router');

$router->post('locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:'.implode(',', config('app.supported_locales', ['ar', 'en']))],
    ]);

    session(['locale' => $validated['locale']]);

    return back()->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
})->name('locale.switch');

$router->middleware(['auth', 'verified'])->group(function () use ($router) {
    $router->post('ui/preferences', [DashboardAssistantController::class, 'preferences'])->name('platform.ui-preferences');
    $router->post('ui/tutorial-progress', [DashboardAssistantController::class, 'tutorialProgress'])->name('platform.tutorial-progress');
    $router->get('help/screens/{screenId}', [DashboardAssistantController::class, 'screen'])->whereIn('screenId', TutorialRegistry::screenIds())->name('platform.help.screen');
    $router->get('help/flows/{flowId}', [DashboardAssistantController::class, 'flow'])->whereIn('flowId', array_keys(UserFlowRegistry::all()))->name('platform.help.flow');

    $router->view('initial-setup', 'platform.initial-setup')->middleware('can:company_settings.edit')->name('initial-setup');
    $router->livewire('admin/settings', 'platform::admin.settings')->middleware('can:company_settings.view')->name('admin.settings');
    $router->get('admin/settings/printers/{printer}/preview', function (PrinterConfiguration $printer) {
        abort_unless(auth()->user()?->can('company_settings.view'), 403);

        return view('platform.admin.printer-preview', compact('printer'));
    })->name('admin.settings.printer-preview');
    $router->livewire('admin/branches', 'platform::admin.branches')->middleware('can:branches_stores.view')->name('admin.branches');
    $router->livewire('admin/stores', 'platform::admin.stores')->middleware('can:branches_stores.view')->name('admin.stores');
    $router->livewire('admin/cash-drawers', 'platform::admin.drawers')->middleware('can:drawers_payments_tax_numbering_printers.view')->name('admin.cash-drawers');
    $router->livewire('admin/authorization-baseline', 'platform::admin.authorization-baseline')->middleware('can:users_roles_permissions.view')->name('admin.authorization-baseline');

    $router->livewire('admin/system/health', 'platform::system.health')->middleware('can:audit_logs.view')->name('system.health');
    $router->get('admin/system/backups', function () {
        $name = (string) config('backup.backup.name', config('app.name'));
        $disks = array_values(array_filter((array) config('backup.backup.destination.disks', []), static fn (mixed $disk): bool => is_string($disk)));
        $destinations = array_map(function (string $disk) use ($name): array {
            $destination = BackupDestination::create($disk, $name);
            $newest = $destination->newestBackup();

            return [
                'disk' => $disk,
                'reachable' => $destination->isReachable(),
                'backup_count' => $destination->backups()->count(),
                'newest' => $newest?->date()?->toIso8601String(),
                'size_bytes' => $destination->usedStorage(),
                'connection_error' => $destination->connectionError() === null ? null : 'unavailable',
            ];
        }, $disks);

        return response()->json([
            'name' => $name,
            'verify_backup' => (bool) config('backup.backup.verify_backup'),
            'encrypted' => filled(config('backup.backup.password')),
            'destinations' => $destinations,
        ]);
    })->middleware('can:audit_logs.view')->name('system.backups');
    $router->livewire('admin/audit', 'platform::system.audit-log')->middleware('can:audit_logs.view')->name('admin.audit');
    $router->get('admin/audit/export', function (Request $request) {
        $filters = $request->validate([
            'mode' => ['nullable', 'string', 'in:all,override,print'],
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:150'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'store_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return app(ExportAuditLogs::class)->execute($filters);
    })->name('admin.audit.export');
    $router->livewire('approvals', 'platform::system.approval-inbox')->name('admin.approvals');
    $router->get('approvals/{approval}/attachments/{attachment}', function (ApprovalRecord $approval, Attachment $attachment) {
        abort_unless($attachment->purpose === 'approval_evidence'
            && $attachment->source_type === ApprovalRecord::class
            && $attachment->source_id === (string) $approval->id, 404);
        Gate::authorize('view', $approval);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('view', $approval)
                && $candidate->source_type === ApprovalRecord::class
                && $candidate->source_id === (string) $approval->id,
        );
    })->name('admin.approvals.attachments.download');
    $router->livewire('admin/system/ui-showcase', 'platform::system.ui-showcase')->middleware('can:dashboard_reports.view')->name('system.ui-showcase');
    $router->view('system/app', 'platform.system.app')->middleware('can:dashboard_reports.view')->name('system.app');
});

$router->get('forbidden', function () {
    return response()->view('errors.403', [], 403);
})->name('forbidden');
