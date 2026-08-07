<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Support\CustomerPolicySettingRegistry;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveCustomerPolicySettingAction
{
    public function execute(string $key, ?string $value, ?string $notes): CustomerPolicySettingVersion
    {
        Gate::authorize('company_settings.edit');

        if (! array_key_exists($key, CustomerPolicySettingRegistry::all())) {
            throw ValidationException::withMessages(['key' => 'The selected customer policy setting is not allowed.']);
        }

        $normalizedValue = $value !== null ? trim($value) : null;
        $normalizedNotes = $notes !== null ? trim($notes) : null;

        return DB::transaction(function () use ($key, $normalizedValue, $normalizedNotes): CustomerPolicySettingVersion {
            $latest = CustomerPolicySettingVersion::query()
                ->where('key', $key)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            $setting = CustomerPolicySettingVersion::query()->create([
                'key' => $key,
                'value' => $normalizedValue !== '' ? $normalizedValue : null,
                'value_type' => 'text',
                'version' => ($latest->version ?? 0) + 1,
                'created_by' => Auth::id(),
                'notes' => $normalizedNotes !== '' ? $normalizedNotes : null,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_policy_settings',
                event: 'create_customer_policy_setting_version',
                source: $setting,
                before: $latest?->only(['key', 'value', 'value_type', 'version', 'notes']),
                after: $setting->only(['key', 'value', 'value_type', 'version', 'notes']),
                metadata: ['approval_state' => 'owner_approval_required', 'setting_key' => $key],
            );

            return $setting;
        });
    }
}
