<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Support\ProductQuantity;
use InvalidArgumentException;

final class PurchaseOrderCalculator
{
    /**
     * @param  array<string, mixed>  $line
     * @return array{quantity_ordered: numeric-string, unit_cost: numeric-string, discount_type: null|string, discount_value: numeric-string, discount_amount: numeric-string, tax_rate: numeric-string, tax_code: null|string, tax_inclusive: bool, tax_amount: numeric-string, subtotal: numeric-string, line_total: numeric-string}
     */
    public function calculateLine(array $line): array
    {
        $quantity = bcadd((string) ProductQuantity::normalize($line['quantity_ordered'] ?? null, decimalPlaces: 6), '0', 6);
        $unitCost = $this->decimal($line['unit_cost'] ?? null, 4, __('Unit cost'));
        $discountType = filled($line['discount_type'] ?? null) ? (string) $line['discount_type'] : null;
        $discountValue = $this->decimal($line['discount_value'] ?? 0, 4, __('Discount'));
        if ($discountType === null) {
            $discountValue = '0.0000';
        }
        $taxRate = $this->decimal($line['tax_rate'] ?? 0, 4, __('Tax rate'));
        $taxInclusive = (bool) ($line['tax_inclusive'] ?? false);

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }
        if (bccomp($unitCost, '0', 4) < 0) {
            throw new InvalidArgumentException(__('Unit cost cannot be negative.'));
        }
        if ($discountType !== null && ! in_array($discountType, ['percentage', 'amount'], true)) {
            throw new InvalidArgumentException(__('Discount type is not supported.'));
        }
        if (bccomp($discountValue, '0', 4) < 0 || bccomp($taxRate, '100', 4) > 0) {
            throw new InvalidArgumentException(__('Discount and tax values must be within their allowed range.'));
        }

        $gross = bcmul($quantity, $unitCost, 8);
        $discount = match ($discountType) {
            'percentage' => bcdiv(bcmul($gross, $discountValue, 8), '100', 8),
            'amount' => $discountValue,
            default => '0',
        };
        if (bccomp($discount, $gross, 8) > 0) {
            throw new InvalidArgumentException(__('Discount cannot exceed the line value.'));
        }

        $discounted = bcsub($gross, $discount, 8);
        if ($taxInclusive && bccomp($taxRate, '0', 4) > 0) {
            $tax = bcdiv(bcmul($discounted, $taxRate, 8), bcadd('100', $taxRate, 4), 8);
            $subtotal = bcsub($discounted, $tax, 8);
            $lineTotal = $discounted;
        } else {
            $subtotal = $discounted;
            $tax = bcdiv(bcmul($subtotal, $taxRate, 8), '100', 8);
            $lineTotal = bcadd($subtotal, $tax, 8);
        }

        return [
            'quantity_ordered' => bcadd($quantity, '0', 6),
            'unit_cost' => bcadd($unitCost, '0', 4),
            'discount_type' => $discountType,
            'discount_value' => bcadd($discountValue, '0', 4),
            'discount_amount' => bcadd($discount, '0', 4),
            'tax_rate' => bcadd($taxRate, '0', 4),
            'tax_code' => filled($line['tax_code'] ?? null) ? trim((string) $line['tax_code']) : null,
            'tax_inclusive' => $taxInclusive,
            'tax_amount' => bcadd($tax, '0', 4),
            'subtotal' => bcadd($subtotal, '0', 4),
            'line_total' => bcadd($lineTotal, '0', 4),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{subtotal: numeric-string, discountAmount: numeric-string, taxAmount: numeric-string, totalAmount: numeric-string, calculated: list<array<string, mixed>>}
     */
    public function calculateDocument(array $lines): array
    {
        $subtotal = $discountAmount = $taxAmount = $totalAmount = '0.0000';
        $calculated = [];
        foreach ($lines as $line) {
            $result = $this->calculateLine($line);
            $subtotal = bcadd($subtotal, $result['subtotal'], 4);
            $discountAmount = bcadd($discountAmount, $result['discount_amount'], 4);
            $taxAmount = bcadd($taxAmount, $result['tax_amount'], 4);
            $totalAmount = bcadd($totalAmount, $result['line_total'], 4);
            $calculated[] = $result;
        }

        return compact('subtotal', 'discountAmount', 'taxAmount', 'totalAmount', 'calculated');
    }

    /** @return numeric-string */
    private function decimal(mixed $value, int $scale, string $label): string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException(__(':label must be a valid non-negative number.', ['label' => $label]));
        }

        return bcadd($value, '0', $scale);
    }
}
