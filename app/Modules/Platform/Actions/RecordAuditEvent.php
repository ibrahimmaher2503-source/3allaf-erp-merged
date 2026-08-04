<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class RecordAuditEvent
{
    /**
     * Persist a single append-only audit event inside the caller's transaction.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $category,
        string $event,
        Model|string|null $source = null,
        ?array $before = null,
        ?array $after = null,
        ?int $branchId = null,
        ?int $storeId = null,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        array $metadata = [],
        ?string $requestId = null,
    ): AuditLog {
        $actor = Auth::user();
        $sourceType = $source instanceof Model ? $source::class : $source;
        $sourceId = $source instanceof Model ? (string) $source->getKey() : null;
        $redactor = app(AuditLogValueRedactor::class);

        return AuditLog::create([
            'event_id' => (string) Str::uuid(),
            'category' => $category,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'before_values' => $redactor->redact($before),
            'after_values' => $redactor->redact($after),
            'changed_fields' => $this->changedFields($before, $after),
            'metadata' => $this->metadata($metadata),
            'request_id' => $requestId ?? Context::get('request_id') ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after @return array<int, string> */
    private function changedFields(?array $before, ?array $after): array
    {
        return array_values(array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))));
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function metadata(array $metadata): array
    {
        if (! request()->hasSession()) {
            return $metadata;
        }

        return [
            ...$metadata,
            'session_hash' => hash('sha256', request()->session()->getId()),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
        ];
    }
}
