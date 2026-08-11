<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Str;

/** KS-013 â€” genuine MariaDB allocation-versus-override race. */
final class SequenceOverrideAllocationRaceTest extends ConcurrencyTestCase
{
    public function test_stale_safe_override_never_rolls_back_or_duplicates_during_allocation(): void
    {
        $this->seedCanonicalAuthorization();
        $admin = $this->administrator('ks013-race-admin-'.Str::random(8));
        $allocator = $this->administrator('ks013-race-allocator-'.Str::random(8));
        $documentType = 'ks013_race_'.Str::lower(Str::random(12));
        $sequence = DocumentSequence::query()->create([
            'document_type' => $documentType,
            'prefix' => 'KS013-RACE-',
            'padding_length' => 4,
            'next_value' => 700,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
            'policy_notes' => 'Disposable KS-013 race fixture.',
        ]);

        $results = $this->race([
            ['sequence_override', [
                'user_id' => $admin->id,
                'sequence_id' => $sequence->id,
                'next_value' => 750,
                'expected_lock_version' => 1,
                'reason' => 'KS-013 genuine allocation race recovery.',
            ]],
            ['sequence_allocate', [
                'user_id' => $allocator->id,
                'document_type' => $documentType,
            ]],
        ]);

        $overrideWon = $results[0]['ok'] ?? false;
        $allocationWon = $results[1]['ok'] ?? false;
        self::assertTrue($overrideWon || $allocationWon, 'At least one concurrent operation must complete.');

        $sequence = $sequence->fresh();
        self::assertNotNull($sequence);
        if ($overrideWon) {
            self::assertSame(750, $results[0]['result']['next_value']);
            if ($allocationWon) {
                self::assertSame('KS013-RACE-0750', $results[1]['result']['number']);
                self::assertSame(751, $sequence->next_value);
            } else {
                self::assertSame(750, $sequence->next_value);
            }
        } else {
            self::assertFalse($results[0]['ok']);
            self::assertStringContainsString('Reload before overriding', $results[0]['message']);
            self::assertTrue($allocationWon);
            self::assertSame('KS013-RACE-0700', $results[1]['result']['number']);
            self::assertSame(701, $sequence->next_value);
        }

        self::assertGreaterThanOrEqual(2, $sequence->lock_version);
        self::assertTrue(AuditLog::query()->where('source_id', (string) $sequence->id)->where('event', 'document_number_allocated')->exists());
        if ($overrideWon) {
            self::assertTrue(AuditLog::query()->where('source_id', (string) $sequence->id)->where('event', 'document_sequence_counter_overridden')->where('reason_text', 'KS-013 genuine allocation race recovery.')->exists());
        }
    }
}
