<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ApplicationNavigation
{
    private const ACTIVE_ROUTES = [
        'sales.invoices' => ['sales.invoices', 'sales.show', 'sales.print', 'sales.receipt.*'],
        'customers.index' => ['customers.index', 'customers.create', 'customers.show'],
        'customers.groups.index' => ['customers.groups.*'],
        'pos.shift' => ['pos.shift*'],
        'purchasing.orders' => ['purchasing.orders*'],
        'purchasing.invoices' => ['purchasing.invoices*', 'purchasing.receiv*'],
        'purchasing.returns' => ['purchasing.returns*'],
        'catalog.suppliers' => ['catalog.suppliers*', 'suppliers.*'],
        'catalog.products' => ['catalog.products*', 'catalog.product.*'],
        'catalog.categories' => ['catalog.categories*', 'catalog.brands*', 'catalog.product-options*'],
        'inventory.balances' => ['inventory.balances*', 'inventory.movements*', 'inventory.stock-card*'],
        'inventory.transfers' => ['inventory.transfers*'],
        'inventory.counts' => ['inventory.counts*', 'inventory.adjustments*'],
        'pricing.labels' => ['pricing.labels*', 'barcodes.*'],
        'pricing.index' => ['pricing.index', 'pricing.approvals', 'pricing.focus', 'pricing.lists'],
        'parties.bookings.index' => ['parties.bookings*'],
        'parties.calendar' => ['parties.calendar*'],
        'parties.invoices.index' => ['parties.invoices*'],
        'parties.orders.index' => ['parties.orders*'],
        'reports.index' => ['reports.index', 'reports.exports*'],
        'admin.branches' => ['admin.branches*', 'admin.stores*', 'admin.cash-drawers*'],
        'admin.authorization-baseline' => ['admin.authorization-baseline*', 'admin.users*'],
        'admin.roles' => ['admin.roles*'],
        'initial-setup' => ['initial-setup*'],
        'admin.settings' => ['admin.settings*', 'admin.printers*'],
        'admin.audit' => ['admin.audit*'],
        'admin.approvals' => ['admin.approvals*'],
    ];
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
                        $activeRoutes = $item['active_routes'] ?? self::ACTIVE_ROUTES[$item['route']] ?? [$item['route']];
                        $item['active'] = request()->routeIs(...$activeRoutes)
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
