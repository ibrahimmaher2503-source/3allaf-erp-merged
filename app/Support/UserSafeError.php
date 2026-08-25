<?php

declare(strict_types=1);

namespace App\Support;

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

        return __('Unable to complete the request. Please review the data and try again.');
    }
}
