<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use InvalidArgumentException;

final class ResidenceGeography
{
    /** @return array{0:int,1:?int} */
    public static function validate(int $companyId, mixed $governorateId, mixed $cityId, bool $cityOptional = false): array
    {
        if (! filled($governorateId)) {
            throw new InvalidArgumentException(__('Please select a governorate.'));
        }
        if (! $cityOptional && ! filled($cityId)) {
            throw new InvalidArgumentException(__('Governorate and city/locality are required for residence data.'));
        }
        $governorate = Governorate::query()->active()->find((int) $governorateId);
        $city = filled($cityId) ? City::query()->visibleToCompany($companyId)->active()->find((int) $cityId) : null;
        if ($governorate === null) {
            throw new InvalidArgumentException(__('The selected governorate is unavailable.'));
        }
        if (filled($cityId) && ($city === null || (int) $city->governorate_id !== (int) $governorate->id)) {
            throw new InvalidArgumentException(__('The selected city/locality is not active in the selected governorate.'));
        }

        return [(int) $governorate->id, $city?->id];
    }
}
