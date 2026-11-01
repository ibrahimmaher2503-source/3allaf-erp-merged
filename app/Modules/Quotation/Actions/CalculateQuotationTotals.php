<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use Illuminate\Validation\ValidationException;

final class CalculateQuotationTotals
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function execute(float $subtotal, array $data): array
    {
        $type = $data['discount_type'] ?? null;
        $value = (float) ($data['discount_value'] ?? 0);
        $rate = (float) ($data['tax_rate'] ?? 0);
        if (! in_array($type, [null, '', 'fixed', 'percent'], true) || $value < 0 || $rate < 0 || $rate > 100) {
            throw ValidationException::withMessages(['discount_value' => __('Invalid quotation discount or tax.')]);
        }
        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => __('Percentage discount cannot exceed 100%.')]);
        }
        if ($type === 'fixed' && $value > $subtotal) {
            throw ValidationException::withMessages(['discount_value' => __('Fixed discount cannot exceed subtotal.')]);
        }
        $discount = round($type === 'percent' ? $subtotal * $value / 100 : ($type === 'fixed' ? $value : 0), 2);
        $tax = round(($subtotal - $discount) * $rate / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_type' => $type ?: null,
            'discount_value' => $type ? $value : 0,
            'discount_amount' => $discount,
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'total' => round($subtotal - $discount + $tax, 2),
        ];
    }
}
