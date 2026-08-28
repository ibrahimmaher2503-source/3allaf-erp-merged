@php
    $routeName = request()->route()?->getName();
    $currentKey = match (true) {
        $routeName === 'admin.branches' => 'branches-stores',
        $routeName === 'admin.stores' => 'warehouses',
        $routeName === 'admin.cash-drawers' => 'cash-drawers',
        $routeName === 'admin.authorization-baseline' => 'users-scopes',
        $routeName === 'catalog.categories' => 'categories',
        $routeName === 'catalog.brands' => 'brands',
        $routeName === 'catalog.product-options' => 'product-options',
        in_array($routeName, ['catalog.products', 'catalog.products.create', 'catalog.products.edit', 'catalog.products.show'], true) => 'product-masters',
        $routeName === 'catalog.products.import' => 'product-import',
        $routeName === 'pricing.index' => 'prices',
        $routeName === 'inventory.adjustments.create' => 'opening-configuration',
        $routeName === 'customers.groups.index' => 'customer-groups',
        in_array($routeName, ['customers.index', 'customers.create', 'customers.show'], true) => 'customers',
        $routeName === 'party.readiness' => 'party-readiness',
        in_array($routeName, ['catalog.suppliers', 'suppliers.index'], true) && request('section') === 'supplier-groups' => 'supplier-groups',
        in_array($routeName, ['catalog.suppliers', 'suppliers.index'], true) => 'suppliers',
        default => null,
    };
@endphp

@if ($currentKey && ! in_array($routeName, ['admin.branches', 'admin.stores', 'admin.cash-drawers'], true))
    <div class="mx-auto mb-5 w-full max-w-7xl"><x-setup.context :current-key="$currentKey" :enabled="request()->boolean('setup')" /></div>
@endif
