<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Str;

/** @group tsk-009 */
final class ApprovalRequestConcurrencyTest extends ConcurrencyTestCase
{
    public function test_concurrent_replay_of_the_same_approval_request_returns_one_record(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('CONC-APR-'.Str::random(6));
        $store = $this->store($branch, 'CONC-APR-'.Str::random(6));
        $requester = $this->userWith('conc-apr-'.Str::random(6), ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);
        $idempotencyKey = 'concurrent-approval-'.Str::uuid();
        $params = [
            'user_id' => $requester->id,
            'source_type' => 'pricing_labels',
            'source_id' => (string) random_int(100000, 999999),
            'source_version' => '1',
            'requested_action' => 'approve_price_version',
            'request_permission' => 'pricing_labels.submit',
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
        ];

        $results = $this->race([
            ['approval_request', $params],
            ['approval_request', $params],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results[1]));
        self::assertSame($results[0]['result']['approval_id'], $results[1]['result']['approval_id']);
        self::assertSame(1, ApprovalRecord::query()->where('idempotency_key', $idempotencyKey)->count());
        self::assertSame('pending', ApprovalRecord::query()->where('idempotency_key', $idempotencyKey)->firstOrFail()->approval_state->value);
    }
}
