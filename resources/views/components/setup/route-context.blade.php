@php
    $routeName = request()->route()?->getName();
    $routeSetupKey = match (true) {
        $routeName === 'admin.branches' => 'branches-stores',
        $routeName === 'admin.stores' => 'warehouses',
        $routeName === 'admin.cash-drawers' => 'cash-drawers',
        str_starts_with((string) $routeName, 'admin.authorization') || in_array($routeName, ['admin.users', 'admin.roles'], true) => 'users-scopes',
        str_starts_with((string) $routeName, 'catalog.categories') => 'categories',
        str_starts_with((string) $routeName, 'catalog.brands') => 'brands',
        str_starts_with((string) $routeName, 'catalog.product-options') => 'product-options',
        str_starts_with((string) $routeName, 'catalog.products.import') => 'product-import',
        str_starts_with((string) $routeName, 'catalog.products') => 'product-masters',
        str_starts_with((string) $routeName, 'pricing.') => 'prices',
        str_starts_with((string) $routeName, 'inventory.adjustments') && request('setup_step') === 'opening-configuration' => 'opening-configuration',
        str_starts_with((string) $routeName, 'customers.groups.') => 'customer-groups',
        str_starts_with((string) $routeName, 'customers.') => 'customers',
        $routeName === 'party.readiness' => 'party-readiness',
        in_array($routeName, ['catalog.suppliers', 'suppliers.index'], true) && request('section') === 'supplier-groups' => 'supplier-groups',
        in_array($routeName, ['catalog.suppliers', 'suppliers.index'], true) => 'suppliers',
        default => null,
    };
    $requestedSetupKey = request()->string('setup_step')->toString();
    $currentKey = $routeSetupKey ?? ($requestedSetupKey !== '' ? $requestedSetupKey : null);
@endphp

@if ($currentKey && ! in_array($routeName, ['admin.branches', 'admin.stores', 'admin.cash-drawers'], true))
    <div class="mx-auto mb-5 w-full max-w-7xl"><x-setup.context :current-key="$currentKey" :enabled="request()->boolean('setup')" /></div>
@endif
