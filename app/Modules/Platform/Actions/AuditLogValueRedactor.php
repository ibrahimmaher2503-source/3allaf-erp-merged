<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\AuditLog;

class AuditLogValueRedactor
{
    /** @param array<string, mixed>|null $values */
    public function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            if ($this->isAlwaysRedacted((string) $key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isAlwaysRedacted(string $key): bool
    {
        $normalizedKey = strtolower($key);

        if (in_array($normalizedKey, AuditLog::ALWAYS_REDACTED_FIELDS, true)) {
            return true;
        }

        return str_contains($normalizedKey, 'password')
            || str_contains($normalizedKey, 'secret')
            || str_contains($normalizedKey, 'token')
            || str_contains($normalizedKey, 'api_key')
            || str_contains($normalizedKey, 'authorization')
            || str_contains($normalizedKey, 'cookie')
            || str_contains($normalizedKey, 'private_key')
            || str_contains($normalizedKey, 'recovery_code')
            || str_contains($normalizedKey, 'session');
    }
}
