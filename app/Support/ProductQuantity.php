<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class ProductQuantity
{
    public static function format(string|int|float|null $value): string
    {
        if ($value === null || $value === '') return '0';
        $raw = trim((string) $value);
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) throw new UnexpectedValueException(__('Invalid product quantity data. Please review the source document.'));
        [$whole, $fraction] = array_pad(explode('.', ltrim($raw, '+'), 2), 2, '');
        if ($fraction !== '' && trim($fraction, '0') !== '') throw new UnexpectedValueException(__('A non-integral product quantity was found. Correct the source data before continuing.'));
        return (string) ((int) $whole);
    }

    public static function normalize(mixed $value, string $field = 'quantity', bool $allowZero = false): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (!$allowZero && (int) $value < 1) || ($allowZero && (int) $value < 0)) {
            throw ValidationException::withMessages([$field => __('Product quantities must be whole numbers.')]);
        }
        return (int) $value;
    }

    /** @return array<int|string, string> */
    public static function rule(bool $allowZero = false): array
    {
        return ['integer', $allowZero ? 'min:0' : 'min:1'];
    }
}
