<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use InvalidArgumentException;

final class ProductBarcodePolicy
{
    public function international(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^[0-9]+$/D', $value)) {
            throw new InvalidArgumentException(__('Product code must contain digits only.'));
        }

        return $value;
    }

    public function supplierDigits(string $stableCode): string
    {
        $digits = preg_replace('/\D/', '', strtoupper(trim($stableCode))) ?? '';
        if (strlen($digits) < 4) {
            throw new InvalidArgumentException(__('The supplier stable code must contain at least four numeric digits before a local barcode can be generated.'));
        }

        return substr($digits, 0, 4);
    }

    public function local(string $supplierDigits, int $serial): string
    {
        if (! preg_match('/^\d{4}$/', $supplierDigits) || $serial < 1 || $serial > 999999) {
            throw new InvalidArgumentException(__('The local barcode sequence input is invalid.'));
        }

        return '0'.$supplierDigits.str_pad((string) $serial, 6, '0', STR_PAD_LEFT);
    }
}
