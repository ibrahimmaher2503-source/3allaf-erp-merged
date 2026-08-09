<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Str;

/**
 * CONC-NUM-001 (testing/results/CONCURRENCY-SCENARIOS.md) — concurrent
 * document-number allocation. AllocatePurchaseOrderNumberAction locks the
 * DocumentSequence row with lockForUpdate() before reading next_value; this
 * proves that lock actually serializes real concurrent allocators on a real
 * RDBMS (SQLite's coarser locking cannot exercise or disprove this).
 */
final class DocumentSequenceConcurrencyTest extends ConcurrencyTestCase
{
    public function test_concurrent_po_number_allocation_never_duplicates_or_skips(): void
    {
        $this->seedCanonicalAuthorization();
        $admin = $this->administrator('conc-num-admin-'.Str::random(6));

        // Reset to a known, deterministic starting point. document_type is
        // globally unique, so re-running this suite against the same
        // persistent database must upsert, not insert.
        DocumentSequence::query()->updateOrCreate(
            ['document_type' => 'purchase_order'],
            ['prefix' => 'RACE-PO-', 'padding_length' => 5, 'suffix' => null, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1, 'policy_notes' => 'Concurrency race fixture.'],
        );

        $workerCount = 6;
        $calls = array_fill(0, $workerCount, ['po_number', ['user_id' => $admin->id]]);
        $results = $this->race($calls);

        $numbers = [];
        foreach ($results as $index => $result) {
            self::assertTrue($result['ok'] ?? false, "Worker #{$index} failed: ".json_encode($result));
            $numbers[] = $result['result']['number'];
        }

        self::assertCount($workerCount, array_unique($numbers), 'Every concurrently-allocated PO number must be distinct: '.implode(', ', $numbers));

        sort($numbers);
        $expected = [];
        for ($i = 1; $i <= $workerCount; $i++) {
            $expected[] = 'RACE-PO-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
        }
        self::assertSame($expected, $numbers, 'Allocated numbers must be exactly the gapless sequence 1..N, proving no lost update and no double-allocation under real concurrency.');

        $sequence = DocumentSequence::query()->where('document_type', 'purchase_order')->firstOrFail();
        self::assertSame($workerCount + 1, $sequence->next_value, 'next_value must have advanced by exactly the number of concurrent allocations.');
    }
}
