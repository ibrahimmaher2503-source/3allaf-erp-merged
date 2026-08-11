<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Reporting\Jobs\GenerateReportExportJob;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Queries\ReportSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateExportJobAction
{
    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters, string $format = 'xlsx'): ExportJob
    {
        $format = strtolower(trim($format));
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 422, __('Only PDF and Excel exports are supported.'));
        Gate::forUser($user)->authorize('dashboard_reports.export_'.$format);

        $snapshot = app(ReportSnapshot::class)->execute($user, $filters);
        $job = DB::transaction(fn (): ExportJob => ExportJob::query()->create([
            'report_key' => $snapshot['filters']['module'] ?? 'dashboard',
            'format' => $format,
            'status' => 'queued',
            'requested_by' => $user->id,
            'branch_id' => $snapshot['filters']['branch_id'],
            'store_id' => $snapshot['filters']['store_id'],
            'filters' => $snapshot['filters'],
            'snapshot_hash' => app(ReportSnapshot::class)->fingerprint($snapshot),
            'expires_at' => now()->addDays(7),
        ]));

        app(RecordAuditEvent::class)->execute(
            category: 'reporting',
            event: 'export_requested',
            source: $job,
            after: ['status' => 'queued', 'format' => $format],
            branchId: $job->branch_id,
            storeId: $job->store_id,
            metadata: ['filters' => $job->filters, 'expires_at' => $job->expires_at?->toIso8601String()],
        );

        GenerateReportExportJob::dispatch($job->id)->onQueue('reports');

        return $job->fresh();
    }
}
