<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentSequenceConcurrencyTest extends TestCase
{
    public function test_independent_workers_allocate_unique_numbers_under_real_mariadb_lock_contention(): void
    {
        $branch = Branch::factory()->create(['code' => 'QA'.Str::upper(Str::random(8))]);
        $branchId = (int) $branch->id;
        $companyId = (int) $branch->company_id;
        $storagePath = (string) getenv('QA_STORAGE_PATH');
        $workerPath = $storagePath.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'numbering-worker.php';
        $token = (string) Str::uuid();
        $workerPaths = [];
        $processes = [];
        $lockHeld = false;

        file_put_contents($workerPath, $this->workerSource());

        try {
            DB::beginTransaction();
            DB::table('branches')->where('id', $branchId)->lockForUpdate()->first();
            $lockHeld = true;

            for ($index = 0; $index < 2; $index++) {
                $readyPath = $storagePath.DIRECTORY_SEPARATOR.$token.'-'.$index.'-ready.json';
                $resultPath = $storagePath.DIRECTORY_SEPARATOR.$token.'-'.$index.'-result.json';
                $stdoutPath = $storagePath.DIRECTORY_SEPARATOR.$token.'-'.$index.'-stdout.log';
                $stderrPath = $storagePath.DIRECTORY_SEPARATOR.$token.'-'.$index.'-stderr.log';
                $workerPaths[] = compact('readyPath', 'resultPath', 'stdoutPath', 'stderrPath');
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $workerPath, base_path(), (string) $branchId, $readyPath, $resultPath],
                    [0 => ['pipe', 'r'], 1 => ['file', $stdoutPath, 'w'], 2 => ['file', $stderrPath, 'w']],
                    $pipes,
                    base_path(),
                );

                self::assertIsResource($process, 'The independent PHP numbering worker must start.');
                fclose($pipes[0]);
                $processes[] = $process;
            }

            $readyDeadline = microtime(true) + 15;
            while (microtime(true) < $readyDeadline) {
                if (count(array_filter($workerPaths, static fn (array $paths): bool => is_file($paths['readyPath']))) === 2) {
                    break;
                }

                usleep(10000);
            }

            self::assertCount(2, array_filter($workerPaths, static fn (array $paths): bool => is_file($paths['readyPath'])), 'Both independent workers must finish Laravel boot before the race starts. '.$this->workerErrors($workerPaths));
            $connectionIds = array_map(
                static fn (array $paths): int => (int) json_decode((string) file_get_contents($paths['readyPath']), true, 512, JSON_THROW_ON_ERROR)['connection_id'],
                $workerPaths,
            );

            $lockDeadline = microtime(true) + 10;
            $waitingWorkers = 0;
            while (microtime(true) < $lockDeadline) {
                $result = DB::selectOne(
                    "SELECT COUNT(*) AS waiting_count FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT' AND trx_mysql_thread_id IN (?, ?)",
                    $connectionIds,
                );
                $waitingWorkers = (int) ($result->waiting_count ?? 0);
                if ($waitingWorkers === 2) {
                    break;
                }

                usleep(10000);
            }

            $workerDiagnostics = array_map(static function (array $paths): array {
                return [
                    'ready' => is_file($paths['readyPath']) ? file_get_contents($paths['readyPath']) : null,
                    'result' => is_file($paths['resultPath']) ? file_get_contents($paths['resultPath']) : null,
                    'stderr' => is_file($paths['stderrPath']) ? file_get_contents($paths['stderrPath']) : null,
                ];
            }, $workerPaths);
            $activeTransactions = DB::select('SELECT trx_mysql_thread_id, trx_state, trx_query FROM information_schema.INNODB_TRX');
            self::assertSame(2, $waitingWorkers, 'Both workers must be observed waiting on the same locked branch before releasing the allocator. '.json_encode(['workers' => $workerDiagnostics, 'transactions' => $activeTransactions], JSON_THROW_ON_ERROR));
            DB::commit();
            $lockHeld = false;

            $exitCodes = $this->waitForWorkers($processes, 15);
            $processes = [];
            self::assertSame([0, 0], $exitCodes, $this->workerErrors($workerPaths));

            $results = array_map(
                static fn (array $paths): array => json_decode((string) file_get_contents($paths['resultPath']), true, 512, JSON_THROW_ON_ERROR),
                $workerPaths,
            );
            $numbers = array_column($results, 'number');

            self::assertCount(2, array_unique($numbers));
            self::assertSame(2, DocumentSequence::query()
                ->where('document_type', 'inventory_adjustment')
                ->where('scope_key', 'branch:'.$branchId)
                ->value('next_value') - 1);
            $allocationAudits = DB::table('audit_logs')
                ->where('event', 'document_number_allocated')
                ->where('branch_id', $branchId)
                ->get();
            self::assertSame(2, $allocationAudits->count());
            self::assertEqualsCanonicalizing(
                [strtoupper($branch->code).'-IA-000001', strtoupper($branch->code).'-IA-000002'],
                $numbers,
            );
        } finally {
            if ($lockHeld && DB::transactionLevel() > 0) {
                DB::commit();
            }

            if ($processes !== []) {
                $this->waitForWorkers($processes, 15);
            }

            $this->cleanupSequenceFixture($branchId, $companyId);
            foreach ($workerPaths as $paths) {
                foreach ($paths as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
            if (is_file($workerPath)) {
                unlink($workerPath);
            }
        }
    }

    public function test_editing_sequence_metadata_does_not_rewind_an_allocated_counter(): void
    {
        $branch = Branch::factory()->create(['code' => 'QE'.Str::upper(Str::random(8))]);
        $branchId = (int) $branch->id;
        $companyId = (int) $branch->company_id;
        $sequence = null;
        $user = null;

        try {
            $allocatedNumber = app(AllocateDocumentNumber::class)->executeForBranch('inventory_adjustment', $branchId);
            $sequence = DocumentSequence::query()
                ->where('document_type', 'inventory_adjustment')
                ->where('scope_key', 'branch:'.$branchId)
                ->firstOrFail();
            $user = User::factory()->create();
            $user->forceFill(['is_super_admin' => true, 'status' => 'active'])->save();
            $role = Role::query()->create([
                'code' => 'qa-sequence-edit-'.Str::lower(Str::random(8)),
                'name_ar' => 'مسؤول اختبار',
                'name_en' => 'QA sequence editor',
                'status' => 'active',
            ]);
            $permission = Permission::query()->create([
                'code' => 'company_settings.edit',
                'module' => 'company_settings',
                'action' => 'edit',
                'status' => 'active',
            ]);
            $role->permissions()->attach($permission);
            $user->roles()->attach($role);
            $this->actingAs($user);
            self::assertSame('active', $user->status);
            self::assertTrue($user->hasGlobalApplicationAccess());
            self::assertTrue($user->hasPermission('company_settings.edit'));

            app(SaveLocalSettingsAction::class)->saveDocumentSequence([
                'document_type' => 'inventory_adjustment',
                'scope_type' => 'branch',
                'scope_id' => $branchId,
                'prefix' => 'QA-EDIT-',
                'suffix' => null,
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'policy_notes' => 'Verify metadata edit preserves the live counter.',
            ], $sequence->id);

            $sequence->refresh();
            self::assertSame(strtoupper($branch->code).'-IA-000001', $allocatedNumber);
            self::assertSame('QA-EDIT-', $sequence->prefix);
            self::assertSame(2, $sequence->next_value);
            self::assertSame(2, $sequence->lock_version);

            $sequence->next_value = 1;
            $this->expectException(\LogicException::class);
            $sequence->save();
        } finally {
            auth()->logout();
            $this->cleanupSequenceFixture($branchId, $companyId);
            if ($user !== null) {
                $user->delete();
            }
            if (isset($role)) {
                $role->delete();
            }
            if (isset($permission)) {
                $permission->delete();
            }
        }
    }

    /** @return list<int> */
    private function waitForWorkers(array $processes, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $exitCodes = array_fill(0, count($processes), null);

        do {
            foreach ($processes as $index => $process) {
                if ($exitCodes[$index] !== null) {
                    continue;
                }

                $status = proc_get_status($process);
                if (! $status['running']) {
                    $exitCodes[$index] = (int) $status['exitcode'];
                }
            }

            if (! in_array(null, $exitCodes, true)) {
                break;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        foreach ($processes as $index => $process) {
            if ($exitCodes[$index] === null) {
                proc_terminate($process);
                $exitCodes[$index] = 124;
            }
            proc_close($process);
        }

        return $exitCodes;
    }

    private function workerErrors(array $workerPaths): string
    {
        return implode("\n", array_map(
            static fn (array $paths): string => (string) @file_get_contents($paths['stderrPath']),
            $workerPaths,
        ));
    }

    private function workerSource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use App\Modules\Platform\Actions\AllocateDocumentNumber;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = $argv[1];
$branchId = (int) $argv[2];
$readyPath = $argv[3];
$resultPath = $argv[4];
$storagePath = (string) getenv('QA_STORAGE_PATH');

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix(substr($storagePath, 0, 3));
    $app->loadEnvironmentFrom('.env.module1-qa-unused');
    $app->useStoragePath($storagePath);
    $app->make(Kernel::class)->bootstrap();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS connection_id')->connection_id;
    file_put_contents($readyPath, json_encode(['connection_id' => $connectionId], JSON_THROW_ON_ERROR));

    $number = app(AllocateDocumentNumber::class)->executeForBranch('inventory_adjustment', $branchId);
    file_put_contents($resultPath, json_encode(['number' => $number], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultPath, json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR));
    fwrite(STDERR, (string) $exception);
    exit(1);
}
PHP;
    }

    private function cleanupSequenceFixture(int $branchId, int $companyId): void
    {
        $sequenceIds = DocumentSequence::query()
            ->where('document_type', 'inventory_adjustment')
            ->where('scope_key', 'branch:'.$branchId)
            ->pluck('id');

        if ($sequenceIds->isNotEmpty()) {
            DB::table('audit_logs')
                ->where('source_type', DocumentSequence::class)
                ->whereIn('source_id', $sequenceIds->map(static fn (int $id): string => (string) $id))
                ->delete();
            DocumentSequence::query()->whereIn('id', $sequenceIds)->delete();
        }

        Branch::query()->whereKey($branchId)->delete();
        Company::query()->whereKey($companyId)->delete();
    }
}
