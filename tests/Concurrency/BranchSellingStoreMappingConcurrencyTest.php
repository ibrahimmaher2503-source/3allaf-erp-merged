<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Platform\Models\BranchSellingStore;
use Illuminate\Support\Str;

/**
 * TSK-006: the branch row lock must prevent two concurrent active mappings.
 */
final class BranchSellingStoreMappingConcurrencyTest extends ConcurrencyTestCase
{
    public function test_concurrent_mapping_requests_leave_exactly_one_active_mapping(): void
    {
        $this->seedCanonicalAuthorization();
        $admin = $this->administrator('conc-map-admin-'.Str::random(6));
        $branch = $this->branch('RACE-BR-'.Str::upper(Str::random(5)));
        $first = $this->store($branch, 'RACE-S1-'.Str::upper(Str::random(5)));
        $second = $this->store($branch, 'RACE-S2-'.Str::upper(Str::random(5)));

        $results = $this->race([
            ['branch_mapping', ['user_id' => $admin->id, 'branch_id' => $branch->id, 'store_id' => $first->id]],
            ['branch_mapping', ['user_id' => $admin->id, 'branch_id' => $branch->id, 'store_id' => $second->id]],
        ]);

        foreach ($results as $index => $result) {
            self::assertTrue($result['ok'] ?? false, "Worker #{$index} failed: ".json_encode($result));
        }

        self::assertSame(1, BranchSellingStore::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->count());
        self::assertSame(1, BranchSellingStore::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'inactive')
            ->count());
    }
}
