<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class OpenShiftScopeRevocationConcurrencyTest extends TestCase
{
    public function test_scope_revoked_while_waiting_on_branch_lock_does_not_authorize_shift_open(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'shifts_cash_movements.create'],
            [
                'module' => 'shifts_cash_movements',
                'action' => 'create',
                'sensitivity' => 'medium',
                'status' => 'active',
            ],
        );
        $role = Role::query()->create([
            'code' => 'redteam-pos-scope-'.Str::lower(Str::random(8)),
            'name_ar' => 'Red Team POS Scope',
            'name_en' => 'Red Team POS Scope',
            'status' => 'active',
        ]);
        $role->permissions()->attach($permission->getKey());
        $cashier = User::factory()->create(['status' => 'active']);
        $cashier->roles()->attach($role->getKey());

        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'selling',
            'status' => 'active',
        ]);
        $scope = $cashier->storeScopes()->create([
            'store_id' => $store->id,
            'status' => 'active',
        ]);
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'status' => 'active',
        ]);

        $idempotencyKey = 'SHIFT-OPEN:'.Str::uuid();
        $storagePath = (string) getenv('QA_STORAGE_PATH');
        $barrierPath = $storagePath.DIRECTORY_SEPARATOR.'shift-scope-race-'.Str::uuid();
        mkdir($barrierPath);
        $scopeCheckedPath = $barrierPath.DIRECTORY_SEPARATOR.'scope-checked';
        $projectRoot = dirname(__DIR__, 2);
        $process = null;
        $connection = DB::connection();

        try {
            $connection->beginTransaction();
            Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            $worker = <<<'PHP'
$projectRoot = $argv[1];
$cashierId = (int) $argv[2];
$drawerId = (int) $argv[3];
$idempotencyKey = $argv[4];
$scopeCheckedPath = $argv[5];
$storagePath = (string) getenv('QA_STORAGE_PATH');
require $projectRoot.'/vendor/autoload.php';
$application = require $projectRoot.'/bootstrap/app.php';
$application->addAbsoluteCachePathPrefix(substr($storagePath, 0, 3));
$application->loadEnvironmentFrom('.env.module1-qa-unused');
$application->useStoragePath($storagePath);
$application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cashier = \App\Models\User::query()->findOrFail($cashierId);
$drawer = \App\Modules\Platform\Models\CashDrawer::query()->findOrFail($drawerId);
$scopeCheckObserved = false;
\Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $event) use ($scopeCheckedPath, &$scopeCheckObserved): void {
    if (! $scopeCheckObserved && str_contains(strtolower($event->sql), 'user_store_scopes')) {
        file_put_contents($scopeCheckedPath, 'checked', LOCK_EX);
        $scopeCheckObserved = true;
    }
});
auth()->login($cashier);
try {
    $shift = app(\App\Modules\Retail\Actions\OpenShiftAction::class)->execute($cashier, $drawer, '50.00', $idempotencyKey);
    echo json_encode(['outcome' => 'opened', 'shift_id' => $shift->getKey()], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    echo json_encode([
        'outcome' => 'rejected',
        'exception' => get_class($exception),
        'status' => $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : null,
    ], JSON_THROW_ON_ERROR);
}
PHP;
            $process = new Process([
                PHP_BINARY,
                '-r',
                $worker,
                $projectRoot,
                (string) $cashier->id,
                (string) $drawer->id,
                $idempotencyKey,
                $scopeCheckedPath,
            ], $projectRoot);
            $process->setTimeout(30);
            $process->start();

            $deadline = microtime(true) + 10;
            while (! is_file($scopeCheckedPath) && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }

            self::assertFileExists($scopeCheckedPath, 'The worker must complete its initial store-scope check before the scope is revoked. '.$process->getErrorOutput());

            DB::table('user_store_scopes')
                ->where('id', $scope->id)
                ->delete();
            $connection->commit();

            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $persistedShift = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
            $assignmentExists = $persistedShift !== null && DB::table('active_pos_shift_assignments')->where('shift_id', $persistedShift->id)->exists();
            $auditExists = $persistedShift !== null && DB::table('audit_logs')->where('event', 'open_shift')->where('source_id', (string) $persistedShift->id)->exists();

            self::assertSame('rejected', $result['outcome'], 'A revoked store scope must prevent shift creation after the lock wait. '.json_encode([
                'worker' => $result,
                'persisted_shift_id' => $persistedShift?->id,
                'active_assignment' => $assignmentExists,
                'open_audit' => $auditExists,
            ], JSON_THROW_ON_ERROR));
            self::assertSame(403, $result['status']);
            self::assertNull($persistedShift);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            if ($process !== null && $process->isRunning()) {
                $process->stop(1);
            }

            $shiftIds = PosShift::query()->where('idempotency_key', $idempotencyKey)->pluck('id');
            DB::table('active_pos_shift_assignments')->where('cash_drawer_id', $drawer->id)->delete();
            DB::table('audit_logs')
                ->where('source_type', PosShift::class)
                ->whereIn('source_id', $shiftIds->map(static fn (int $id): string => (string) $id))
                ->delete();
            PosShift::query()->whereIn('id', $shiftIds)->delete();
            DB::table('cash_drawers')->where('id', $drawer->id)->delete();
            DB::table('user_store_scopes')->where('id', $scope->id)->delete();
            DB::table('stores')->where('id', $store->id)->delete();
            DB::table('branches')->where('id', $branch->id)->delete();
            DB::table('companies')->where('id', $company->id)->delete();
            $cashier->roles()->detach($role->getKey());
            $role->permissions()->detach($permission->getKey());
            $cashier->delete();
            $role->delete();

            foreach (glob($barrierPath.DIRECTORY_SEPARATOR.'*') ?: [] as $marker) {
                unlink($marker);
            }
            rmdir($barrierPath);
        }
    }
}
