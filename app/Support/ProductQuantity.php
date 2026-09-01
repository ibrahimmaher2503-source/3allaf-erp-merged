<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ProductQuantity
{
    public static function format(string|int|float|null $value): string
    {
        if ($value === null || $value === '') return '0';
        return (string) (int) $value;
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
