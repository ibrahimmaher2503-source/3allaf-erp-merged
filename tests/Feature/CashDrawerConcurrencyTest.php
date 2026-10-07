<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CashDrawerConcurrencyTest extends TestCase
{
    public function test_create_keeps_the_existing_no_baseline_contract_and_starts_at_version_one(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $fixtureDrawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $createdDrawer = null;

        try {
            $createdDrawer = app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'assigned_user_id' => '',
                'code' => 'NEW-'.Str::upper(Str::random(8)),
                'name_ar' => 'درج جديد',
                'name_en' => 'New drawer',
                'status' => 'active',
                'policy_notes' => '',
            ]);

            self::assertSame(1, (int) $createdDrawer->lock_version);
            self::assertFalse($createdDrawer->isFillable('lock_version'));
        } finally {
            if ($createdDrawer !== null) {
                AuditLog::query()
                    ->where('source_type', CashDrawer::class)
                    ->where('source_id', (string) $createdDrawer->id)
                    ->delete();
                CashDrawer::query()->whereKey($createdDrawer->id)->delete();
            }

            $this->deleteDrawerFixture($actor, $company, $branch, $store, $fixtureDrawer);
        }
    }

    public function test_update_requires_a_positive_integer_baseline_before_any_write_or_audit(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $payload = $this->drawerPayload($drawer, $branch, $store);
        unset($payload['lock_version']);
        $auditCount = $this->updateAuditCount($drawer);

        try {
            foreach ([null, 0, 'bad-version', true, 1.5, 999] as $invalidVersion) {
                $attempt = $payload;
                $attempt['lock_version'] = $invalidVersion;

                try {
                    app(SaveCashDrawerAction::class)->execute($attempt, $drawer->id);
                    self::fail('A missing, malformed, or non-positive drawer version should be rejected.');
                } catch (ValidationException $exception) {
                    $this->assertDrawerVersionConflict($exception);
                }
            }

            self::assertSame('Original drawer name', $drawer->fresh()->name_en);
            self::assertSame($auditCount, $this->updateAuditCount($drawer));
        } finally {
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_status_transition_requires_and_advances_the_expected_version(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $oldVersion = (int) $drawer->lock_version;
        $auditCount = AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->where('event', 'toggle_cash_drawer_status')
            ->count();

        try {
            $action = app(SaveCashDrawerAction::class);
            $action->toggleStatus($drawer->id, 'maintenance', $oldVersion);
            $updated = $drawer->fresh();

            self::assertSame('maintenance', $updated->status);
            self::assertSame($oldVersion + 1, (int) $updated->lock_version);
            self::assertSame($auditCount + 1, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->where('event', 'toggle_cash_drawer_status')
                ->count());

            try {
                $action->toggleStatus($drawer->id, 'inactive', $oldVersion);
                self::fail('A stale status transition should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertDrawerVersionConflict($exception);
            }

            try {
                $action->toggleStatus($drawer->id, 'inactive');
                self::fail('A status transition without an expected version should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertDrawerVersionConflict($exception);
            }

            self::assertSame('maintenance', $drawer->fresh()->status);
            self::assertSame($oldVersion + 1, (int) $drawer->fresh()->lock_version);
            self::assertSame($auditCount + 1, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->where('event', 'toggle_cash_drawer_status')
                ->count());
        } finally {
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_livewire_edit_uses_the_locked_version_captured_when_the_form_opens(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $this->withoutVite();

        try {
            $component = Livewire::test('platform::admin.drawers')
                ->call('openEditDrawerModal', $drawer->id)
                ->assertSet('editingDrawerVersion', (int) $drawer->lock_version);

            try {
                $component->set('editingDrawerVersion', (int) $drawer->lock_version + 1);
                self::fail('The edit form baseline version must not be client-tamperable.');
            } catch (CannotUpdateLockedPropertyException) {
                self::assertSame((int) $drawer->lock_version, $component->get('editingDrawerVersion'));
            }

            $component->set('drawerForm.name_en', 'Stale tab name');

            $currentVersion = (int) $drawer->lock_version;
            $payload = $this->drawerPayload($drawer, $branch, $store);
            app(SaveCashDrawerAction::class)->execute([
                ...$payload,
                'name_en' => 'Newer tab name',
            ], $drawer->id);
            $auditCountAfterNewerWrite = $this->updateAuditCount($drawer);

            $component->call('saveDrawer');

            self::assertSame('Newer tab name', $drawer->fresh()->name_en);
            self::assertSame($currentVersion + 1, (int) $drawer->fresh()->lock_version);
            self::assertSame($auditCountAfterNewerWrite, $this->updateAuditCount($drawer));
            $component->assertSet('editingDrawerVersion', $currentVersion);
            $component
                ->assertSet('drawerForm.name_en', 'Stale tab name')
                ->assertSet('showDrawerModal', true)
                ->assertSee(__('This cash drawer changed in another session. Reload it before saving.'))
                ->assertDispatched('toast-show', fn (string $event, array $parameters): bool => ($parameters['slots']['text'] ?? null) === __('This cash drawer changed in another session. Reload it before saving.'));
        } finally {
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_stale_livewire_status_and_delete_actions_dispatch_the_conflict_without_side_effects(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $this->withoutVite();
        $staleVersion = (int) $drawer->lock_version;

        try {
            app(SaveCashDrawerAction::class)->execute([
                ...$this->drawerPayload($drawer, $branch, $store),
                'name_en' => 'Newer tab name',
            ], $drawer->id);

            $currentVersion = $staleVersion + 1;
            $auditCount = AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count();
            $approvalCount = ApprovalRecord::query()
                ->where('source_id', 'cash_drawer_delete:'.$drawer->id)
                ->count();
            $component = Livewire::test('platform::admin.drawers');
            $assertConflictToast = fn (string $event, array $parameters): bool => ($parameters['slots']['text'] ?? null) === __('This cash drawer changed in another session. Reload it before saving.');

            $component->call('toggleDrawerStatus', $drawer->id, 'inactive', $staleVersion)
                ->assertDispatched('toast-show', $assertConflictToast);
            self::assertSame('active', $drawer->fresh()->status);
            self::assertSame($currentVersion, (int) $drawer->fresh()->lock_version);
            self::assertSame($auditCount, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count());

            $component->call('deleteDrawer', $drawer->id, $staleVersion)
                ->assertDispatched('toast-show', $assertConflictToast);
            self::assertSame('Newer tab name', $drawer->fresh()->name_en);
            self::assertSame('active', $drawer->fresh()->status);
            self::assertSame($currentVersion, (int) $drawer->fresh()->lock_version);
            self::assertSame($auditCount, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count());
            self::assertSame($approvalCount, ApprovalRecord::query()
                ->where('source_id', 'cash_drawer_delete:'.$drawer->id)
                ->count());
        } finally {
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_two_concurrent_action_submissions_from_the_same_version_allow_only_one_write(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $baseline = $this->drawerPayload($drawer, $branch, $store);
        $auditCount = $this->updateAuditCount($drawer);
        $projectRoot = dirname(__DIR__, 2);
        $storagePath = (string) getenv('QA_STORAGE_PATH');
        $barrierPath = $storagePath.DIRECTORY_SEPARATOR.'drawer-race-'.Str::uuid();
        mkdir($barrierPath);

        try {
            $worker = <<<'PHP'
$projectRoot = $argv[1];
$actorId = (int) $argv[2];
$drawerId = (int) $argv[3];
$baseline = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
$barrierPath = $argv[5];
$storagePath = (string) getenv('QA_STORAGE_PATH');
require $projectRoot.'/vendor/autoload.php';
$application = require $projectRoot.'/bootstrap/app.php';
$application->addAbsoluteCachePathPrefix(substr($storagePath, 0, 3));
$application->loadEnvironmentFrom('.env.module1-qa-unused');
$application->useStoragePath($storagePath);
$application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
auth()->loginUsingId($actorId);
file_put_contents($barrierPath.DIRECTORY_SEPARATOR.'ready-'.getmypid(), 'ready', LOCK_EX);
$deadline = microtime(true) + 10;
do {
    $readyFiles = glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: [];
    if (count($readyFiles) === 2) {
        break;
    }
    usleep(10000);
} while (microtime(true) < $deadline);
if (count(glob($barrierPath.DIRECTORY_SEPARATOR.'ready-*') ?: []) !== 2) {
    throw new \RuntimeException('Both stale drawer submissions did not reach the race barrier.');
}
try {
    app(\App\Modules\Platform\Actions\SaveCashDrawerAction::class)->execute([
        ...$baseline,
        'name_en' => 'Concurrent writer '.$argv[6],
    ], $drawerId);
    echo json_encode(['outcome' => 'saved'], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException $exception) {
    if (! array_key_exists('lock_version', $exception->errors())) {
        throw $exception;
    }
    echo json_encode(['outcome' => 'stale'], JSON_THROW_ON_ERROR);
}
PHP;
            $processes = [];
            foreach (['A', 'B'] as $suffix) {
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $worker,
                    $projectRoot,
                    (string) $actor->id,
                    (string) $drawer->id,
                    json_encode($baseline, JSON_THROW_ON_ERROR),
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
                if (! $process->isSuccessful()) {
                    self::fail('Concurrent worker failed: '.$process->getErrorOutput());
                }
                $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['outcome'];
            }

            sort($outcomes);
            self::assertSame(['saved', 'stale'], $outcomes);
            $updated = $drawer->fresh();
            self::assertContains($updated->name_en, ['Concurrent writer A', 'Concurrent writer B']);
            self::assertSame((int) $baseline['lock_version'] + 1, (int) $updated->lock_version);
            self::assertSame($auditCount + 1, $this->updateAuditCount($drawer));
        } finally {
            foreach (glob($barrierPath.DIRECTORY_SEPARATOR.'*') ?: [] as $marker) {
                unlink($marker);
            }
            rmdir($barrierPath);
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_cash_drawer_delete_request_rejects_stale_version_and_approved_delete_advances_it(): void
    {
        $this->enableDrawerPermissions();
        [$actor, $company, $branch, $store, $drawer] = $this->createDrawerFixture();
        $this->actingAs($actor);
        $oldVersion = (int) $drawer->lock_version;

        try {
            app(SaveCashDrawerAction::class)->execute([
                ...$this->drawerPayload($drawer, $branch, $store),
                'name_en' => 'Newer drawer name',
            ], $drawer->id);

            $auditCount = AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count();
            $approvalCount = ApprovalRecord::query()
                ->where('source_id', 'cash_drawer_delete:'.$drawer->id)
                ->count();

            try {
                app(PlatformSettingsApprovalAction::class)->request(
                    'cash_drawer_delete',
                    $drawer->id,
                    ['deleted' => true],
                    $drawer->getAttributes(),
                    $branch->id,
                    $store->id,
                    expectedLockVersion: $oldVersion,
                );
                self::fail('A stale cash drawer deletion request should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertDrawerVersionConflict($exception);
            }

            self::assertSame($approvalCount, ApprovalRecord::query()
                ->where('source_id', 'cash_drawer_delete:'.$drawer->id)
                ->count());
            self::assertSame($auditCount, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count());
            self::assertSame('Newer drawer name', $drawer->fresh()->name_en);
            self::assertSame($oldVersion + 1, (int) $drawer->fresh()->lock_version);

            app(PlatformSettingsApprovalAction::class)->request(
                'cash_drawer_delete',
                $drawer->id,
                ['deleted' => true],
                $drawer->getAttributes(),
                $branch->id,
                $store->id,
                expectedLockVersion: $oldVersion + 1,
            );

            self::assertSame('inactive', $drawer->fresh()->status);
            self::assertSame($oldVersion + 2, (int) $drawer->fresh()->lock_version);
            self::assertSame($auditCount + 1, AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->count());
        } finally {
            $this->deleteDrawerFixture($actor, $company, $branch, $store, $drawer);
        }
    }

    public function test_stale_direct_action_update_is_rejected_without_replacing_newer_data_or_audit(): void
    {
        $this->enableDrawerPermissions();
        Carbon::setTestNow('2026-10-07 12:00:00');

        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'status' => 'active',
            'type' => 'selling',
        ]);
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'name_en' => 'Original drawer name',
        ]);

        $this->actingAs($actor);

        try {
            $action = app(SaveCashDrawerAction::class);
            $baseline = [
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'assigned_user_id' => '',
                'code' => $drawer->code,
                'name_ar' => $drawer->name_ar,
                'name_en' => 'Original drawer name',
                'status' => $drawer->status,
                'policy_notes' => $drawer->policy_notes,
                // This is ignored by the current action but remains safe on the
                // pre-migration schema used to prove the stale overwrite.
                'lock_version' => 1,
            ];

            $auditCount = AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->where('event', 'update_cash_drawer')
                ->count();

            $action->execute([...$baseline, 'name_en' => 'Newer tab saved this name'], $drawer->id);
            $newerDrawer = $drawer->fresh();
            $newerUpdatedAt = $newerDrawer->updated_at?->format('Y-m-d H:i:s');
            $auditCountAfterNewerWrite = AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->where('event', 'update_cash_drawer')
                ->count();

            self::assertSame('Newer tab saved this name', $newerDrawer->name_en);
            self::assertSame('2026-10-07 12:00:00', $newerUpdatedAt);
            self::assertSame($auditCount + 1, $auditCountAfterNewerWrite);

            try {
                $action->execute($baseline, $drawer->id);
                self::fail('The stale direct-action update should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertDrawerVersionConflict($exception);
            }

            self::assertSame('Newer tab saved this name', $drawer->fresh()->name_en);
            self::assertSame($newerUpdatedAt, $drawer->fresh()->updated_at?->format('Y-m-d H:i:s'));
            self::assertSame(
                $auditCountAfterNewerWrite,
                AuditLog::query()
                    ->where('source_type', CashDrawer::class)
                    ->where('source_id', (string) $drawer->id)
                    ->where('event', 'update_cash_drawer')
                    ->count(),
            );
        } finally {
            Carbon::setTestNow();
            AuditLog::query()
                ->where('source_type', CashDrawer::class)
                ->where('source_id', (string) $drawer->id)
                ->delete();
            CashDrawer::query()->whereKey($drawer->id)->delete();
            Store::query()->whereKey($store->id)->delete();
            Branch::query()->whereKey($branch->id)->delete();
            Company::query()->whereKey($company->id)->delete();
            User::query()->whereKey($actor->id)->delete();
        }
    }

    /** @return array{0: User, 1: Company, 2: Branch, 3: Store, 4: CashDrawer} */
    private function createDrawerFixture(): array
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'status' => 'active',
            'type' => 'selling',
        ]);
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'name_en' => 'Original drawer name',
        ]);

        return [$actor, $company, $branch, $store, $drawer];
    }

    /** @return array<string, mixed> */
    private function drawerPayload(CashDrawer $drawer, Branch $branch, Store $store): array
    {
        return [
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => '',
            'code' => $drawer->code,
            'name_ar' => $drawer->name_ar,
            'name_en' => $drawer->name_en,
            'status' => $drawer->status,
            'policy_notes' => $drawer->policy_notes,
            'lock_version' => $drawer->lock_version ?? 1,
        ];
    }

    private function updateAuditCount(CashDrawer $drawer): int
    {
        return AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->where('event', 'update_cash_drawer')
            ->count();
    }

    private function assertDrawerVersionConflict(ValidationException $exception): void
    {
        self::assertSame(
            __('This cash drawer changed in another session. Reload it before saving.'),
            $exception->errors()['lock_version'][0] ?? null,
        );
    }

    private function deleteDrawerFixture(User $actor, Company $company, Branch $branch, Store $store, CashDrawer $drawer): void
    {
        ApprovalRecord::query()
            ->where('source_id', 'cash_drawer_delete:'.$drawer->id)
            ->delete();
        AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->delete();
        CashDrawer::query()->whereKey($drawer->id)->delete();
        Store::query()->whereKey($store->id)->delete();
        Branch::query()->whereKey($branch->id)->delete();
        Company::query()->whereKey($company->id)->delete();
        User::query()->whereKey($actor->id)->delete();
    }

    private function enableDrawerPermissions(): void
    {
        foreach ([
            'drawers_payments_tax_numbering_printers.view',
            'drawers_payments_tax_numbering_printers.create',
            'drawers_payments_tax_numbering_printers.edit',
            'drawers_payments_tax_numbering_printers.logical_delete',
            'company_settings.approve',
        ] as $code) {
            [$module, $action] = explode('.', $code, 2);
            DB::table('permissions')->insertOrIgnore([
                'code' => $code,
                'module' => $module,
                'action' => $action,
                'sensitivity' => 'normal',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
