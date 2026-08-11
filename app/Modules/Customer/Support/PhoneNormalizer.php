<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use InvalidArgumentException;

final class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/[^0-9]+/', '', trim($phone));
        if (! is_string($digits) || ! preg_match('/^[0-9]{7,20}$/', $digits)) {
            throw new InvalidArgumentException(__('Enter a valid phone number using 7 to 20 digits.'));
        }

        return $digits;
    }
}
