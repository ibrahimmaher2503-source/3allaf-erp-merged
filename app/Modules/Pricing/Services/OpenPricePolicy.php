<?php

namespace App\Modules\Pricing\Services;

use Illuminate\Validation\ValidationException;

final class OpenPricePolicy
{
    /** @return array{allowed: bool, reason: string|null} */
    public function validate(
        float $referenceAmount,
        float $requestedAmount,
        ?float $minimum,
        ?float $maximum,
        bool $hasPermission,
        ?string $reason,
        bool $offline = false,
    ): array {
        if (! $hasPermission) {
            return ['allowed' => false, 'reason' => __('Open-price permission is required.')];
        }

        if ($offline) {
            return ['allowed' => false, 'reason' => __('Open-price selling is blocked while offline.')];
        }

        if ($minimum === null || $maximum === null) {
            return ['allowed' => false, 'reason' => __('Open-price bounds are pending owner configuration.')];
        }

        if ($requestedAmount < $minimum || $requestedAmount > $maximum) {
            return ['allowed' => false, 'reason' => __('The requested open price is outside the configured range.')];
        }

        if (blank($reason)) {
            return ['allowed' => false, 'reason' => __('A reason is required for an open price.')];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /** @return array{allowed: bool, reason: string|null} */
    public function validateOrThrow(
        float $referenceAmount,
        float $requestedAmount,
        ?float $minimum,
        ?float $maximum,
        bool $hasPermission,
        ?string $reason,
        bool $offline = false,
    ): array {
        $result = $this->validate($referenceAmount, $requestedAmount, $minimum, $maximum, $hasPermission, $reason, $offline);
        if (! $result['allowed']) {
            throw ValidationException::withMessages(['open_price' => $result['reason']]);
        }

        return $result;
    }
}
