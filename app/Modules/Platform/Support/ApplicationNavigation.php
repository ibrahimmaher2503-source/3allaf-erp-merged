<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ApplicationNavigation
{
    /** @return array<int, array<string, mixed>> */
    public function for(User $user, string $locale): array
    {
        return collect(config('navigation', []))
            ->map(function (array $group) use ($user, $locale): array {
                $group['label'] = $group['label'][$locale] ?? $group['label']['en'];
                $group['items'] = collect($group['items'])
                    ->filter(fn (array $item): bool => Route::has($item['route']) && $user->can($item['permission']))
                    ->map(function (array $item) use ($locale): array {
                        $item['label'] = $item['label'][$locale] ?? $item['label']['en'];
                        $item['url'] = route($item['route'], $item['parameters'] ?? []);
                        $item['active'] = request()->routeIs($item['route'])
                            && collect($item['parameters'] ?? [])->every(fn (mixed $value, string $key): bool => (string) request($key, request()->route($key)) === (string) $value);

                        return $item;
                    })->values()->all();

                $group['active'] = collect($group['items'])->contains('active', true);

                return $group;
            })
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()->all();
    }
}
