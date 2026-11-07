<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Saves an explicit versioned Local/Dev POS financial configuration value. */
final class SavePosFinancialSettingAction
{
    public function execute(string $key, ?string $value, ?string $notes = null): PosFinancialSettingVersion
    {
        Gate::authorize('company_settings.edit');

        if (! array_key_exists($key, PosFinancialSettingRegistry::all())) {
            throw ValidationException::withMessages(['key' => __('The POS financial setting is not supported.')]);
        }

        $value = $value === null ? null : trim($value);
        if ($key === PosFinancialSettingRegistry::CASH_ROUNDING_ENABLED) {
            if (! in_array($value, ['0', '1'], true)) {
                throw ValidationException::withMessages(['value' => __('Choose Yes or No for cash rounding.')]);
            }

            return $this->saveVersion($key, $value, $notes);
        }

        if ($value !== null && $value !== '') {
            if ($key === PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION
                && preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
                throw ValidationException::withMessages(['value' => __('Enter a positive cash rounding denomination in whole units or cents.')]);
            }
            try {
                $value = DecimalMoney::normalize($value, 4);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['value' => __('A decimal value must be a valid number.')]);
            }

            if (bccomp($value, '0', 4) <= 0) {
                throw ValidationException::withMessages(['value' => __('The configured value must be greater than zero.')]);
            }
        } else {
            $value = null;
        }

        return $this->saveVersion($key, $value, $notes);
    }

    private function saveVersion(string $key, ?string $value, ?string $notes): PosFinancialSettingVersion
    {
        return DB::transaction(function () use ($key, $value, $notes): PosFinancialSettingVersion {
            $latest = PosFinancialSettingVersion::query()->where('key', $key)->lockForUpdate()->orderByDesc('version')->first();
            $nextVersion = ((int) ($latest?->getAttribute('version') ?? 0)) + 1;

            $setting = PosFinancialSettingVersion::query()->create([
                'key' => $key,
                'value' => $value,
                'value_type' => $key === PosFinancialSettingRegistry::CASH_ROUNDING_ENABLED ? 'boolean' : 'decimal',
                'version' => $nextVersion,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'created_by' => auth()->id(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'pos_financial_setting_version_created',
                source: $setting,
                before: $latest?->only(['key', 'value', 'version']),
                after: $setting->only(['key', 'value', 'version']),
                reasonText: $setting->notes,
                metadata: ['configuration_scope' => 'local_dev'],
            );

            return $setting;
        });
    }
}
