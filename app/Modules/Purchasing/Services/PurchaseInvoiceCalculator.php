<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Support\ProductQuantity;
use InvalidArgumentException;

final class PurchaseInvoiceCalculator
{
    /**
     * Purchase unit costs are supplier-gross prices. Tax columns remain only as
     * compatibility snapshots and are always zero for newly saved invoices.
     *
     * @param  array<string, mixed>  $line
     * @return array{quantity: numeric-string, unit_cost: numeric-string, discount_type: null|string, discount_value: numeric-string, discount_amount: numeric-string, tax_rate: numeric-string, tax_code: null|string, tax_inclusive: bool, tax_amount: numeric-string, subtotal: numeric-string, line_total: numeric-string}
     */
    public function calculateLine(array $line): array
    {
        $quantity = bcadd((string) ProductQuantity::normalize($line['quantity'] ?? null), '0', 6);
        $unitCost = $this->decimal($line['unit_cost'] ?? null, 4, 'Unit cost');
        $discountType = ($line['discount_type'] ?? '') !== '' ? ($line['discount_type'] ?? null) : null;
        $discountValue = $this->decimal($line['discount_value'] ?? 0, 4, 'Discount');
        if ($discountType === null) {
            $discountValue = '0.0000';
        }

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }
        if (bccomp($unitCost, '0', 4) < 0) {
            throw new InvalidArgumentException(__('Unit cost cannot be negative.'));
        }
        if ($discountType !== null && ! in_array($discountType, ['percentage', 'amount'], true)) {
            throw new InvalidArgumentException(__('Discount type is not supported.'));
        }
        if (bccomp($discountValue, '0', 4) < 0) {
            throw new InvalidArgumentException(__('Discount cannot be negative.'));
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

        $lineTotal = bcsub($gross, $discount, 8);

        return [
            'quantity' => bcadd($quantity, '0', 6),
            'unit_cost' => bcadd($unitCost, '0', 4),
            'discount_type' => $discountType,
            'discount_value' => bcadd($discountValue, '0', 4),
            'discount_amount' => bcadd($discount, '0', 4),
            'tax_rate' => '0.0000',
            'tax_code' => null,
            'tax_inclusive' => false,
            'tax_amount' => '0.0000',
            'subtotal' => bcadd($lineTotal, '0', 4),
            'line_total' => bcadd($lineTotal, '0', 4),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal: numeric-string, discountAmount: numeric-string, taxAmount: numeric-string, totalAmount: numeric-string, calculated: list<array<string, mixed>>}
     */
    public function calculateDocument(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('A purchase invoice must contain at least one line item.'));
        }

        $subtotal = $discountAmount = $totalAmount = '0.0000';
        $calculated = [];
        foreach ($lines as $line) {
            $result = $this->calculateLine($line);
            $subtotal = bcadd($subtotal, (string) $result['subtotal'], 4);
            $discountAmount = bcadd($discountAmount, (string) $result['discount_amount'], 4);
            $totalAmount = bcadd($totalAmount, (string) $result['line_total'], 4);
            $calculated[] = $result;
        }

        return compact('subtotal', 'discountAmount', 'totalAmount') + ['taxAmount' => '0.0000', 'calculated' => $calculated];
    }

    /** @return numeric-string */
    private function decimal(mixed $value, int $scale, string $label): string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__(':label must be a valid non-negative number.', ['label' => $label]));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', $scale);
    }
}
