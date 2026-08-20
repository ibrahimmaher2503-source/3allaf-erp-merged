<?php

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class CF08StoreArchiveSafetyTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_archive_is_pending_and_dependency_locked_across_request_approval_and_direct_delete(): void
    {
        $requester = $this->administrator('cf08-requester');
        $approver = $this->administrator('cf08-approver');
        $branch = $this->branch('CF08-BR');
        $dependencyFree = $this->store($branch, 'CF08-WH', 'warehouse');

        $this->actingAs($requester);
        $approval = app(PlatformSettingsApprovalAction::class)->request(
            resource: 'store_archive',
            id: $dependencyFree->id,
            proposed: ['status' => 'inactive'],
            before: $dependencyFree->getAttributes(),
            branchId: $dependencyFree->branch_id,
            storeId: $dependencyFree->id,
        );

        $this->assertSame('pending', $approval->approval_state->value);
        $this->assertDatabaseHas('stores', ['id' => $dependencyFree->id, 'status' => 'active']);
        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'requested_action' => 'store_archive',
            'approval_state' => 'pending',
        ]);

        $mapped = $this->store($branch, 'CF08-POS', 'selling');
        app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $mapped->id);

        try {
            app(PlatformSettingsApprovalAction::class)->request(
                resource: 'store_archive',
                id: $mapped->id,
                proposed: ['status' => 'inactive'],
                before: $mapped->getAttributes(),
                branchId: $mapped->branch_id,
                storeId: $mapped->id,
            );
            $this->fail('A mapped POS location received an archive request.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unmap POS first', $exception->getMessage());
        }

        $this->actingAs($approver);
        app(DecideApprovalSource::class)->approve($approval);

        $this->assertDatabaseHas('stores', ['id' => $dependencyFree->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('approval_records', ['id' => $approval->id, 'approval_state' => 'approved']);
        $this->assertTrue(AuditLog::query()->where('event', 'delete_store')->where('source_id', (string) $dependencyFree->id)->exists());

        try {
            app(SaveStoreAction::class)->delete($mapped->id);
            $this->fail('Direct hard delete bypassed the locked dependency policy.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unmap POS first', $exception->getMessage());
        }

        $this->assertDatabaseHas('stores', ['id' => $mapped->id, 'status' => 'active']);
    }
}
