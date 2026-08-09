<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\AssertInventoryStoreScope;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockTransfer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Direct regression for the shared `AssertInventoryStoreScope` helper — the
 * single choke point every mutating inventory action (transfers, adjustments,
 * stock counts) relies on to fail closed for an out-of-scope store. Also
 * proves a second real call site beyond `ApproveStockTransferAction` (already
 * covered by `InventoryWorkflowIntegrityTest`) actually wires the guard.
 */
final class InventoryStoreScopeGuardTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_the_guard_requires_an_authenticated_user(): void
    {
        $store = $this->store($this->branch('SCOPE-GUARD-BR'), 'SCOPE-GUARD-ST');

        $this->expectException(AuthorizationException::class);
        app(AssertInventoryStoreScope::class)->execute($store->id);
    }

    public function test_the_guard_lets_a_super_administrator_pass_regardless_of_scope(): void
    {
        $store = $this->store($this->branch('SCOPE-GUARD-SA-BR'), 'SCOPE-GUARD-SA-ST');
        $this->actingAs($this->administrator('scope-guard-super'));

        app(AssertInventoryStoreScope::class)->execute($store->id);
        $this->addToAssertionCount(1);
    }

    public function test_the_guard_passes_for_a_visible_store_and_rejects_a_foreign_one(): void
    {
        $branch = $this->branch('SCOPE-GUARD-BR2');
        $store = $this->store($branch, 'SCOPE-GUARD-ST2');
        $foreignStore = $this->store($this->branch('SCOPE-GUARD-FGN-BR2'), 'SCOPE-GUARD-FGN-ST2');
        $user = $this->userWith('scope-guard-scoped', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->actingAs($user);

        app(AssertInventoryStoreScope::class)->execute($store->id);
        $this->addToAssertionCount(1);

        $this->expectException(AuthorizationException::class);
        app(AssertInventoryStoreScope::class)->execute($foreignStore->id);
    }

    public function test_the_transfer_helper_checks_source_and_destination_independently(): void
    {
        $branch = $this->branch('SCOPE-GUARD-TR-BR');
        $sourceStore = $this->store($branch, 'SCOPE-GUARD-TR-SRC');
        $foreignStore = $this->store($this->branch('SCOPE-GUARD-TR-FGN-BR'), 'SCOPE-GUARD-TR-FGN-ST');
        $user = $this->userWith('scope-guard-transfer', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$sourceStore->id]);
        $this->actingAs($user);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'SCOPE-GUARD-TR-1', 'source_store_id' => $sourceStore->id,
            'destination_store_id' => $foreignStore->id, 'status' => 'draft',
            'requested_by' => $user->id, 'idempotency_key' => 'SCOPE-GUARD-TR-KEY-1',
        ]);

        $guard = app(AssertInventoryStoreScope::class);

        // Source only: the user is scoped to the source store, so this passes.
        $guard->transfer($transfer, source: true, destination: false);
        $this->addToAssertionCount(1);

        // Destination only: the destination store is foreign, so this must reject.
        try {
            $guard->transfer($transfer, source: false, destination: true);
            $this->fail('An out-of-scope transfer destination was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // Both: rejects because the destination leg is out of scope.
        try {
            $guard->transfer($transfer);
            $this->fail('An out-of-scope transfer was accepted when checking both legs.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_submitting_an_adjustment_for_an_out_of_scope_store_is_rejected(): void
    {
        $ownerBranch = $this->branch('SCOPE-GUARD-ADJ-OWN-BR');
        $ownerStore = $this->store($ownerBranch, 'SCOPE-GUARD-ADJ-OWN-ST');
        $foreignBranch = $this->branch('SCOPE-GUARD-ADJ-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'SCOPE-GUARD-ADJ-FGN-ST');

        $creator = $this->administrator('scope-guard-adj-creator');
        $this->actingAs($creator);
        $adjustment = InventoryAdjustment::query()->create([
            'adjustment_number' => 'SCOPE-GUARD-ADJ-1', 'store_id' => $ownerStore->id,
            'adjustment_type' => 'entry', 'status' => 'draft', 'reason_code' => 'RECOUNT',
            'created_by' => $creator->id, 'idempotency_key' => 'SCOPE-GUARD-ADJ-KEY-1',
        ]);

        $foreignManager = $this->userWith('scope-guard-adj-foreign', ['warehouse-manager'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignManager);

        $this->expectException(AuthorizationException::class);
        app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
    }
}
