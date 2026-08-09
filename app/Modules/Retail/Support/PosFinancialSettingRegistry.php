<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Retail\Models\PosFinancialSettingVersion;
use InvalidArgumentException;

/**
 * Owner-configurable POS financial values (DEC-066, DEC-065).
 *
 * DEC-066 adopted `docs/48` but deliberately left POSF-02 (cash rounding
 * denomination) undecided. It lives here as an unset value: the calculation
 * service must refuse to round a cash tender until the owner sets it. There is
 * no default and no silent fallback.
 */
final class PosFinancialSettingRegistry
{
    public const CASH_ROUNDING_DENOMINATION = 'pos.cash_rounding_denomination';

    public const DISCOUNT_APPROVAL_LIMIT = 'pos.discount_approval_limit_percent';

    public const OPEN_PRICE_APPROVAL_LIMIT = 'pos.open_price_approval_limit_percent';

    /**
     * @return array<string, array{title: string, description: string}>
     */
    public static function all(): array
    {
        return [
            self::CASH_ROUNDING_DENOMINATION => [
                'title' => 'Cash rounding denomination',
                'description' => 'POSF-02 — smallest cash denomination used to round the payable amount. When unset, every cash tender is blocked explicitly; no denomination is inferred.',
            ],
            self::DISCOUNT_APPROVAL_LIMIT => [
                'title' => 'Discount approval limit (percent)',
                'description' => 'Discount percentage above which an approval is required and bound to the invoice (docs/48 §4). Unset means no discount may exceed zero without approval.',
            ],
            self::OPEN_PRICE_APPROVAL_LIMIT => [
                'title' => 'Open price approval limit (percent)',
                'description' => 'Permitted deviation from the approved effective price before an approval is required (PRC-08).',
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
}
