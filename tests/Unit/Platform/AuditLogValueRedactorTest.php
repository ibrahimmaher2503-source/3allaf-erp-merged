<?php

namespace Tests\Unit\Platform;

use App\Modules\Platform\Actions\AuditLogValueRedactor;
use Tests\TestCase;

/**
 * TSK-009 — Audit foundation: sensitive value redaction.
 *
 * @group tsk-009
 */
class AuditLogValueRedactorTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function sensitiveKeys(): array
    {
        return array_map(fn (string $key) => [$key], array_combine([
            'password', 'password_confirmation', 'current_password', 'token', 'access_token',
            'refresh_token', 'secret', 'client_secret', 'api_key', 'authorization', 'cookie',
            'recovery_codes', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token',
            'private_key',
        ], [
            'password', 'password_confirmation', 'current_password', 'token', 'access_token',
            'refresh_token', 'secret', 'client_secret', 'api_key', 'authorization', 'cookie',
            'recovery_codes', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token',
            'private_key',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sensitiveKeys')]
    public function test_a_sensitive_key_is_redacted_at_the_top_level(string $key): void
    {
        $redacted = app(AuditLogValueRedactor::class)->redact([$key => 'super-secret-value']);

        $this->assertSame('[redacted]', $redacted[$key], "Key [{$key}] must be redacted.");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sensitiveKeys')]
    public function test_a_sensitive_key_is_redacted_when_deeply_nested(string $key): void
    {
        $redacted = app(AuditLogValueRedactor::class)->redact([
            'user' => [
                'profile' => [
                    'credentials' => [$key => 'super-secret-value'],
                ],
            ],
        ]);

        $this->assertSame('[redacted]', $redacted['user']['profile']['credentials'][$key]);
        $this->assertStringNotContainsString('super-secret-value', json_encode($redacted));
    }

    public function test_the_key_match_is_case_insensitive(): void
    {
        $redacted = app(AuditLogValueRedactor::class)->redact([
            'Password' => 'x',
            'ACCESS_TOKEN' => 'y',
            'Client_Secret' => 'z',
        ]);

        $this->assertSame(['Password' => '[redacted]', 'ACCESS_TOKEN' => '[redacted]', 'Client_Secret' => '[redacted]'], $redacted);
    }

    public function test_a_sensitive_subtree_is_redacted_as_a_whole(): void
    {
        $redacted = app(AuditLogValueRedactor::class)->redact([
            'authorization' => ['bearer' => 'abc', 'nested' => ['deep' => 'def']],
        ]);

        $this->assertSame('[redacted]', $redacted['authorization']);
    }

    public function test_non_sensitive_values_are_preserved_unchanged(): void
    {
        $values = [
            'code' => 'BR-01',
            'name_ar' => 'فرع',
            'status' => 'active',
            'nested' => ['rate' => 14.5, 'flag' => true, 'nothing' => null],
            'list' => [1, 2, 3],
        ];

        $this->assertSame($values, app(AuditLogValueRedactor::class)->redact($values));
    }

    public function test_null_input_stays_null(): void
    {
        $this->assertNull(app(AuditLogValueRedactor::class)->redact(null));
    }
}
