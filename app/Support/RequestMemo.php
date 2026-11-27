<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * Remembers a value for the current HTTP request only (one Livewire round trip counts as
 * one request). Used for read-only lookups that the POS repeats many times per request.
 * Outside an HTTP request (console, queue) the value is computed every time.
 */
final class RequestMemo
{
    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $key, Closure $resolve): mixed
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return $resolve();
        }
        $attributes = request()->attributes;
        $key = 'request-memo.'.$key;
        if (! $attributes->has($key)) {
            $attributes->set($key, $resolve());
        }

        return $attributes->get($key);
    }

    /** Stores a value for the current request (used to pre-load many keys in one query). */
    public static function put(string $key, mixed $value): void
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return;
        }
        request()->attributes->set('request-memo.'.$key, $value);
    }

    /** Forgets every remembered value whose key starts with the prefix. Always returns true. */
    public static function forget(string $prefix): bool
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return true;
        }
        $attributes = request()->attributes;
        foreach (array_keys($attributes->all()) as $key) {
            if (str_starts_with((string) $key, 'request-memo.'.$prefix)) {
                $attributes->remove($key);
            }
        }

        return true;
    }
}
