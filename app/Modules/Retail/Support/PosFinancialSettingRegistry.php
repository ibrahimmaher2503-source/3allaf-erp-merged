<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Retail\Models\PosFinancialSettingVersion;
use InvalidArgumentException;

/**
 * Owner-configurable POS financial values (DEC-066, DEC-065).
 *
 * POSF-02 values remain append-only database configuration. Existing 5 EGP
 * installations retain their behavior until an administrator changes it.
 */
final class PosFinancialSettingRegistry
{
    public const CASH_ROUNDING_DENOMINATION = 'pos.cash_rounding_denomination';

    public const CASH_ROUNDING_ENABLED = 'pos.cash_rounding_enabled';

    public const DISCOUNT_APPROVAL_LIMIT = 'pos.discount_approval_limit_percent';

    public const OPEN_PRICE_APPROVAL_LIMIT = 'pos.open_price_approval_limit_percent';

    /**
     * @return array<string, array{title: string, description: string}>
     */
    public static function all(): array
    {
        return [
            self::CASH_ROUNDING_ENABLED => [
                'title' => 'Cash rounding',
                'description' => 'Enable or disable cash rounding for new cash and mixed sales. Existing sale snapshots are unchanged.',
            ],
            self::CASH_ROUNDING_DENOMINATION => [
                'title' => 'Cash rounding denomination',
                'description' => 'When enabled, cash tender payable amounts round to the nearest approved configured denomination.',
            ],
            self::DISCOUNT_APPROVAL_LIMIT => [
                'title' => 'Discount approval limit (percent)',
                'description' => 'Discount percentage above which approval is required and linked to the invoice. When unset, discounts cannot exceed zero without approval.',
            ],
            self::OPEN_PRICE_APPROVAL_LIMIT => [
                'title' => 'Open price approval limit (percent)',
                'description' => 'Permitted deviation from the approved effective price before approval is required.',
            ],
        ];
    }

    /**
     * Latest configured value for a key, or null when the owner has not set it.
     */
    public static function value(string $key): ?string
    {
        $version = PosFinancialSettingVersion::query()
            ->where('key', $key)
            ->orderByDesc('version')
            ->first();

        $value = $version?->getAttribute('value');
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * A configured value that must be usable in financial arithmetic.
     *
     * The owner enters these as free text, so the registry — not each caller —
     * is responsible for rejecting a value that cannot be computed with.
     *
     * @return numeric-string|null
     */
    public static function numericValue(string $key): ?string
    {
        $value = self::value($key);
        if ($value === null) {
            return null;
        }

        if ($value === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('The configured value for :key must be a non-negative number.', ['key' => $key]));
        }

        return DecimalMoney::normalize($value, 4);
    }

    public static function isConfigured(string $key): bool
    {
        return self::value($key) !== null;
    }

    public static function cashRoundingEnabled(): bool
    {
        $enabled = self::value(self::CASH_ROUNDING_ENABLED);
        if ($enabled === '1') {
            return true;
        }
        if ($enabled === '0') {
            return false;
        }
        if ($enabled !== null) {
            throw new InvalidArgumentException(__('The cash rounding enabled setting must be Yes or No.'));
        }

        // Existing installations with a denomination retain their settlement policy.
        return self::isConfigured(self::CASH_ROUNDING_DENOMINATION);
    }
}
