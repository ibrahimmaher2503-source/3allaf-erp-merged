<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Platform\Models\Company;
use Carbon\CarbonInterface;

final class CompanyDateTime
{
    public static function display(?CarbonInterface $value, ?Company $company = null): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->copy()->setTimezone($company?->timezone ?: 'Africa/Cairo')->format('Y-m-d H:i');
    }
}
