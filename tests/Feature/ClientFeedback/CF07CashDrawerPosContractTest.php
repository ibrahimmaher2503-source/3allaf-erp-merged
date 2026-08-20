<?php

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * CF-07: active cash drawers must use the branch's canonical POS location.
 */
class CF07CashDrawerPosContractTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('cf07-admin'));
    }

    public function test_valid_save_populates_company_and_edit_persists(): void
    {
        [$branch, $store] = $this->canonicalPos();
        $action = app(SaveCashDrawerAction::class);

        $drawer = $action->execute($this->payload($branch->id, $store->id, 'CF07-01'));

        $this->assertSame($branch->company_id, $drawer->company_id);
        $this->assertSame($store->id, $drawer->store_id);
        $this->assertSame('active', $drawer->status);

        $action->execute($this->payload($branch->id, $store->id, 'CF07-01', 'Edited CF-07 drawer'), $drawer->id);

        $this->assertSame('Edited CF-07 drawer', $drawer->fresh()->name_en);
        $this->assertSame($branch->company_id, $drawer->fresh()->company_id);
    }

    public function test_active_drawer_rejects_null_wrong_type_and_cross_branch_locations(): void
    {
        [$branch, $store] = $this->canonicalPos();
        $otherBranch = $this->branch('CF07-OTHER');
        $otherStore = $this->store($otherBranch, 'CF07-OTHER-POS');
        $warehouse = $this->store($branch, 'CF07-WAREHOUSE', 'warehouse');
        $action = app(SaveCashDrawerAction::class);

        foreach ([
            ['store_id' => null, 'message' => 'requires a POS selling location'],
            ['store_id' => $warehouse->id, 'message' => 'active selling location'],
            ['store_id' => $otherStore->id, 'message' => 'does not belong to the chosen branch'],
        ] as $index => $case) {
            try {
                $action->execute($this->payload($branch->id, $case['store_id'], 'CF07-BAD-'.($index + 1)));
                $this->fail('Invalid CF-07 drawer configuration was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($case['message'], $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('cash_drawers', 0);
        $this->assertSame($store->id, $branch->fresh()->activeSellingStoreMapping?->store_id);
    }

    /** @return array{0: Branch, 1: Store} */
    private function canonicalPos(): array
    {
        $branch = $this->branch('CF07-BR');
        $store = $this->store($branch, 'CF07-POS');
        BranchSellingStore::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'status' => 'active',
            'effective_from' => now(),
        ]);

        return [$branch, $store];
    }

    /** @return array<string, mixed> */
    private function payload(int $branchId, ?int $storeId, string $code, string $name = 'CF-07 drawer'): array
    {
        return [
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'code' => $code,
            'name_ar' => 'درج CF-07',
            'name_en' => $name,
            'status' => 'active',
        ];
    }
}
