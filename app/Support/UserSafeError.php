<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;
use WeakMap;

final class UserSafeError
{
    /** @var WeakMap<Throwable, bool>|null */
    private static ?WeakMap $reported = null;

    public static function message(Throwable $exception): string
    {
        self::$reported ??= new WeakMap;

        if (! isset(self::$reported[$exception])) {
            report($exception);
            self::$reported[$exception] = true;
        }

        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        }

        if ($exception instanceof PosFinancialConfigurationException
            || $exception instanceof StaleCatalogRecordException
            || $exception instanceof InvalidArgumentException) {
            $message = trim($exception->getMessage());

            if ($message !== '') {
                return $message;
            }
        }

        return __('Unable to complete the request. Please review the data and try again.');
    }
}
