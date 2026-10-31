<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use InvalidArgumentException;

final class InvoiceDiscount
{
    public static function amount(?string $type, string $value, string $discountable): string
    {
        if ($type === null) {
            return '0.00';
        }
        if (! in_array($type, ['fixed', 'percentage'], true) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(__('Enter a valid cart discount.'));
        }
        if ($type === 'percentage' && bccomp($value, '100', 2) > 0) {
            throw new InvalidArgumentException(__('A percentage discount cannot exceed 100%.'));
        }
        $amount = $type === 'percentage'
            ? DecimalMoney::round(bcdiv(bcmul($discountable, $value, 4), '100', 4))
            : DecimalMoney::round($value);
        if (bccomp($amount, $discountable, 2) > 0) {
            throw new InvalidArgumentException(__('The cart discount cannot exceed the discountable cart value.'));
        }

        return $amount;
    }
}
