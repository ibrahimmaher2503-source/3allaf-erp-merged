<?php

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\UserBranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PlatformSettingsApprovalConcurrencyTest extends TestCase
{
    public function test_two_concurrent_approvals_for_one_branch_source_apply_once(): void
    {
        [$company, $branch, $requester, $approver, $approval, $role] = $this->createApprovalFixture();
        $projectRoot = dirname(__DIR__, 3);
        $storagePath = (string) getenv('QA_STORAGE_PATH');
        $barrierPath = $storagePath.DIRECTORY_SEPARATOR.'branch-approval-race-'.Str::uuid();
        mkdir($barrierPath);

        try {
            $processes = [];
            foreach (['A', 'B'] as $suffix) {
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $this->approvalWorkerSource(),
                    $projectRoot,
                    (string) $approver->id,
                    (string) $approval->id,
                    $barrierPath,
                    $suffix,
                ], $projectRoot);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertTrue($process->isSuccessful(), 'Concurrent approval worker failed: '.$process->getErrorOutput());
                $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['outcome'];
            }

            sort($outcomes);
            self::assertSame(['approved', 'rejected'], $outcomes);
            self::assertSame('inactive', $branch->fresh()->status);
            self::assertSame('approved', $approval->fresh()->approval_state->value);
            self::assertSame(1, AuditLog::query()
                ->where('source_type', Branch::class)
                ->where('source_id', (string) $branch->id)
                ->where('event', 'delete_branch')
                ->count());
            self::assertSame(1, AuditLog::query()
                ->where('source_type', ApprovalRecord::class)
                ->where('source_id', (string) $approval->id)
                ->where('event', 'approval_approved')
                ->count());
        } finally {
            foreach (glob($barrierPath.DIRECTORY_SEPARATOR.'*') ?: [] as $marker) {
                unlink($marker);
            }
            rmdir($barrierPath);
            $this->deleteApprovalFixture($company, $branch, $requester, $approver, $approval, $role);
        }
    }

    public function test_tampered_source_hash_cannot_apply_a_pending_branch_delete(): void
    {
        [$company, $branch, $requester, $approver, $approval, $role] = $this->createApprovalFixture();

        try {
            DB::table('approval_records')->where('id', $approval->id)->update(['source_hash' => str_repeat('a', 64)]);
            $this->actingAs($approver);

            $exception = null;
            try {
                app(PlatformSettingsApprovalAction::class)->approve($approval->fresh());
            } catch (Throwable $caught) {
                $exception = $caught;
            }

            self::assertInstanceOf(ValidationException::class, $exception, (string) $exception);
            self::assertArrayHasKey('approval', $exception->errors());
            self::assertSame('pending', $approval->fresh()->approval_state->value);
            self::assertSame('active', $branch->fresh()->status);
            $this->assertNoApprovalEffects($approval, $branch);
        } finally {
            $this->deleteApprovalFixture($company, $branch, $requester, $approver, $approval, $role);
        }
    }

    public function test_changed_branch_snapshot_cannot_be_approved_and_keeps_request_pending(): void
    {
        [$company, $branch, $requester, $approver, $approval, $role] = $this->createApprovalFixture();

        try {
            DB::table('branches')->where('id', $branch->id)->update(['name_en' => 'Changed after request']);
            $this->actingAs($approver);

            $exception = null;
            try {
                app(PlatformSettingsApprovalAction::class)->approve($approval->fresh());
            } catch (Throwable $caught) {
                $exception = $caught;
            }

            self::assertInstanceOf(ValidationException::class, $exception, (string) $exception);
            self::assertArrayHasKey('approval', $exception->errors());
            self::assertSame('pending', $approval->fresh()->approval_state->value);
            self::assertSame('active', $branch->fresh()->status);
            self::assertSame('Changed after request', $branch->fresh()->name_en);
            $this->assertNoApprovalEffects($approval, $branch);
        } finally {
            $this->deleteApprovalFixture($company, $branch, $requester, $approver, $approval, $role);
        }
    }

    /** @return array{Company, Branch, User, User, ApprovalRecord, Role} */
    private function createApprovalFixture(): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $requester = User::factory()->create(['status' => 'active']);
        $approver = User::factory()->create(['status' => 'active']);
        $approver->forceFill(['is_super_admin' => true])->save();

        $role = Role::query()->create([
            'code' => 'qa-branch-approval-'.Str::lower(Str::random(12)),
            'name_ar' => 'اختبار موافقة الفرع',
            'name_en' => 'Branch approval test',
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'branches_stores.logical_delete'],
            [
                'module' => 'branches_stores',
                'action' => 'logical_delete',
                'status' => 'active',
            ],
        );
        $role->permissions()->sync([$permission->id]);
        $requester->roles()->attach($role->id);
        UserBranchScope::query()->create([
            'user_id' => $requester->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $this->actingAs($requester);
        $approval = app(PlatformSettingsApprovalAction::class)->request(
            resource: 'branch_delete',
            id: $branch->id,
            proposed: ['deleted' => true],
            branchId: $branch->id,
        );

        return [$company, $branch, $requester, $approver, $approval, $role];
    }

    private function deleteApprovalFixture(
        Company $company,
        Branch $branch,
        User $requester,
        User $approver,
        ApprovalRecord $approval,
        Role $role,
    ): void {
        AuditLog::query()->where('branch_id', $branch->id)->delete();
        ApprovalRecord::query()->whereKey($approval->id)->delete();
        UserBranchScope::query()->where('user_id', $requester->id)->where('branch_id', $branch->id)->delete();
        Branch::query()->whereKey($branch->id)->delete();
        Company::query()->whereKey($company->id)->delete();
        User::query()->whereKey($requester->id)->delete();
        User::query()->whereKey($approver->id)->delete();
        Role::query()->whereKey($role->id)->delete();
    }

    private function assertNoApprovalEffects(ApprovalRecord $approval, Branch $branch): void
    {
        self::assertSame(0, AuditLog::query()
            ->where('source_type', Branch::class)
            ->where('source_id', (string) $branch->id)
            ->where('event', 'delete_branch')
            ->count());
        self::assertSame(0, AuditLog::query()
            ->where('source_type', ApprovalRecord::class)
            ->where('source_id', (string) $approval->id)
            ->where('event', 'approval_approved')
            ->count());
    }

    private function approvalWorkerSource(): string
    {
        return <<<'PHP'
$projectRoot = $argv[1];
$approverId = (int) $argv[2];
$approvalId = (int) $argv[3];
$barrierPath = $argv[4];
$storagePath = (string) getenv('QA_STORAGE_PATH');
require $projectRoot.'/vendor/autoload.php';
$application = require $projectRoot.'/bootstrap/app.php';
$application->addAbsoluteCachePathPrefix(substr($storagePath, 0, 3));
$application->loadEnvironmentFrom('.env.module1-qa-unused');
$application->useStoragePath($storagePath);
$application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
auth()->loginUsingId($approverId);
file_put_contents($barrierPath.DIRECTORY_SEPARATOR.'ready-'.getmypid(), 'ready', LOCK_EX);
$deadline = microtime(true) + 10;
do {
    if (count(glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: []) === 2) {
        break;
    }
    usleep(10000);
} while (microtime(true) < $deadline);
if (count(glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: []) !== 2) {
    throw new \RuntimeException('Both approval workers did not reach the race barrier.');
}
try {
    app(\App\Modules\Platform\Actions\PlatformSettingsApprovalAction::class)
        ->approve(\App\Modules\Platform\Models\ApprovalRecord::query()->findOrFail($approvalId));
    echo json_encode(['outcome' => 'approved'], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException $exception) {
    if (! array_key_exists('approval', $exception->errors())) {
        throw $exception;
    }
    echo json_encode(['outcome' => 'rejected'], JSON_THROW_ON_ERROR);
}
PHP;
    }
}
