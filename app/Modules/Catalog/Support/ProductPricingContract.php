<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use InvalidArgumentException;

final class ProductPricingContract
{
    /** @return array{average_cost: string|null, sale_price: string} */
    public function forProductCard(mixed $cost, mixed $consumerPrice): array
    {
        return ['average_cost' => $this->optionalCost($cost), 'sale_price' => $this->money($consumerPrice, __('Base consumer selling price'))];
    }

    public function optionalCost(mixed $cost): ?string
    {
        return trim((string) $cost) === '' ? null : $this->money($cost, __('Unit cost'));
    }

    /** @return array<int, string|\Closure> */
    public function costRules(): array
    {
        return ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
            try {
                $this->optionalCost($value);
            } catch (InvalidArgumentException $exception) {
                $fail($exception->getMessage());
            }
        }];
    }

    public function explicitConsumerPrice(mixed $consumerPrice): string
    {
        return $this->money($consumerPrice, __('Base consumer selling price'));
    }

    private function money(mixed $value, string $label): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException(__(':label must be a non-negative amount with up to four decimal places.', ['label' => $label]));
        }
        return bcadd($value, '0', 4);
    }
}
