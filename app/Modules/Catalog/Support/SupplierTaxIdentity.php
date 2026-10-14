<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use InvalidArgumentException;

final class SupplierTaxIdentity
{
    public const DEFAULT_COUNTRY = 'EG';

    public static function normalizeCountry(mixed $country): string
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '') {
            return self::DEFAULT_COUNTRY;
        }

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new InvalidArgumentException(__('Supplier country must be a two-letter ISO code.'));
        }

        return $country;
    }

    public static function normalizeTaxNumber(mixed $taxNumber, string $country): ?string
    {
        $taxNumber = trim((string) $taxNumber);
        if ($taxNumber === '') {
            return null;
        }

        if ($country !== 'EG') {
            return $taxNumber;
        }

        $digits = strtr($taxNumber, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = preg_replace('/[^0-9]/', '', $digits) ?? '';

        if (preg_match('/^\d{9}$/', $digits) !== 1) {
            throw new InvalidArgumentException(__('Egyptian supplier Tax ID must contain exactly 9 digits when provided.'));
        }

        return $digits;
    }
}
