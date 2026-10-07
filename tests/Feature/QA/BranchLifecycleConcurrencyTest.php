<?php

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BranchLifecycleConcurrencyTest extends TestCase
{
    public function test_concurrent_branch_deactivation_and_store_activation_preserve_the_parent_child_invariant(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'type' => 'warehouse',
            'status' => 'inactive',
        ]);
        $role = Role::query()->create([
            'code' => 'qa-branch-race-'.Str::lower(Str::random(12)),
            'name_ar' => 'اختبار تزامن الفرع',
            'name_en' => 'Branch concurrency test',
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'branches_stores.edit'],
            [
                'module' => 'branches_stores',
                'action' => 'edit',
                'status' => 'active',
            ],
        );
        $role->permissions()->sync([$permission->id]);
        $actor->roles()->attach($role->id);
        UserBranchScope::query()->create([
            'user_id' => $actor->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        UserStoreScope::query()->create([
            'user_id' => $actor->id,
            'store_id' => $store->id,
            'status' => 'active',
        ]);
        $projectRoot = dirname(__DIR__, 3);
        $storagePath = (string) getenv('QA_STORAGE_PATH');
        $barrierPath = $storagePath.DIRECTORY_SEPARATOR.'branch-store-race-'.Str::uuid();
        mkdir($barrierPath);

        try {
            $processes = [];
            foreach (['branch', 'store'] as $operation) {
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $this->workerSource(),
                    $projectRoot,
                    (string) $actor->id,
                    (string) $branch->id,
                    (string) $store->id,
                    $barrierPath,
                    $operation,
                ], $projectRoot);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertTrue($process->isSuccessful(), 'Concurrent branch/store worker failed: '.$process->getErrorOutput());
                $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['outcome'];
            }

            sort($outcomes);
            self::assertSame(['blocked', 'saved'], $outcomes);
            self::assertNotSame(
                ['inactive', 'active'],
                [$branch->fresh()->status, $store->fresh()->status],
            );
            self::assertSame(1, AuditLog::query()
                ->where('branch_id', $branch->id)
                ->whereIn('event', ['toggle_branch_status', 'toggle_store_status'])
                ->count());
        } finally {
            foreach (glob($barrierPath.DIRECTORY_SEPARATOR.'*') ?: [] as $marker) {
                unlink($marker);
            }
            rmdir($barrierPath);
            AuditLog::query()->where('branch_id', $branch->id)->delete();
            UserBranchScope::query()->where('user_id', $actor->id)->delete();
            UserStoreScope::query()->where('user_id', $actor->id)->delete();
            Store::query()->whereKey($store->id)->delete();
            Branch::query()->whereKey($branch->id)->delete();
            Company::query()->whereKey($company->id)->delete();
            User::query()->whereKey($actor->id)->delete();
            Role::query()->whereKey($role->id)->delete();
        }
    }

    private function workerSource(): string
    {
        return <<<'PHP'
$projectRoot = $argv[1];
$actorId = (int) $argv[2];
$branchId = (int) $argv[3];
$storeId = (int) $argv[4];
$barrierPath = $argv[5];
$operation = $argv[6];
$storagePath = (string) getenv('QA_STORAGE_PATH');
require $projectRoot.'/vendor/autoload.php';
$application = require $projectRoot.'/bootstrap/app.php';
$application->addAbsoluteCachePathPrefix(substr($storagePath, 0, 3));
$application->loadEnvironmentFrom('.env.module1-qa-unused');
$application->useStoragePath($storagePath);
$application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
auth()->loginUsingId($actorId);
file_put_contents($barrierPath.DIRECTORY_SEPARATOR.'ready-'.getmypid(), 'ready', LOCK_EX);
$deadline = microtime(true) + 10;
do {
    if (count(glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: []) === 2) {
        break;
    }
    usleep(10000);
} while (microtime(true) < $deadline);
if (count(glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: []) !== 2) {
    throw new \RuntimeException('Both branch lifecycle workers did not reach the race barrier.');
}
try {
    if ($operation === 'branch') {
        app(\App\Modules\Platform\Actions\SaveBranchAction::class)->toggleStatus($branchId);
    } else {
        app(\App\Modules\Platform\Actions\SaveStoreAction::class)->toggleStatus($storeId);
    }
    echo json_encode(['outcome' => 'saved'], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException|\InvalidArgumentException $exception) {
    echo json_encode(['outcome' => 'blocked'], JSON_THROW_ON_ERROR);
}
PHP;
    }
}
