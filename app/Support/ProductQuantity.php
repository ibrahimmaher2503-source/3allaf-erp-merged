<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class ProductQuantity
{
    public static function format(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        $raw = trim((string) $value);
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new UnexpectedValueException(__('Invalid product quantity data. Please review the source document.'));
        }
        $formatted = number_format((float) $raw, 2, '.', '');
        if ((float) $formatted === 0.0) {
            return '0';
        }

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    public static function normalize(mixed $value, string $field = 'quantity', bool $allowZero = false): int
    {
        $raw = trim((string) $value);
        if (! preg_match('/^\d+(?:\.0+)?$/', $raw)) {
            throw ValidationException::withMessages([$field => __('Product quantities must be whole numbers.')]);
        }
        // DECIMAL columns retain a zero-only fractional suffix. Validate it
        // before extracting the integer text; never round or pass through float.
        $raw = explode('.', $raw, 2)[0];
        if ((! $allowZero && bccomp($raw, '1', 0) < 0) || ($allowZero && bccomp($raw, '0', 0) < 0)) {
            throw ValidationException::withMessages([$field => __('Product quantities must be whole numbers.')]);
        }
        if (bccomp($raw, (string) PHP_INT_MAX, 0) > 0) {
            throw ValidationException::withMessages([$field => __('Product quantity exceeds the allowed limit.')]);
        }

        return (int) $raw;
    }

    public static function normalizeSigned(mixed $value, string $field = 'quantity', bool $allowZero = false): int
    {
        $raw = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.0+)?$/', $raw)) {
            throw ValidationException::withMessages([$field => __('Product quantities must be whole numbers.')]);
        }
        $raw = explode('.', $raw, 2)[0];
        if (! $allowZero && bccomp($raw, '0', 0) === 0) {
            throw ValidationException::withMessages([$field => __('Product quantities must be whole numbers.')]);
        }
        if (bccomp($raw, (string) PHP_INT_MAX, 0) > 0 || bccomp($raw, (string) PHP_INT_MIN, 0) < 0) {
            throw ValidationException::withMessages([$field => __('Product quantity exceeds the allowed limit.')]);
        }

        return (int) $raw;
    }

    /** @return array<int|string, string> */
    public static function rule(bool $allowZero = false): array
    {
        return ['integer', $allowZero ? 'min:0' : 'min:1'];
    }
}
