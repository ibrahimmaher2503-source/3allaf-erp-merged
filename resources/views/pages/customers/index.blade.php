<x-layouts::app :title="__('Customers')">
    @php
        $pageTitle = match ($mode) {
            'history' => __('Customer transaction history'),
            'loyalty' => __('Loyalty & points'),
            default => __('Customer master'),
        };
        $pageDescription = match ($mode) {
            'history' => __('Find a customer, then open the permission-scoped unified transaction history.'),
            'loyalty' => __('Find a customer, then open the immutable loyalty points ledger.'),
            default => __('Search and maintain customer profiles by phone or name.'),
        };
    @endphp
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header data-guide="customer-list-header" :title="$pageTitle" :description="$pageDescription">
            <x-slot:actions>
                @if ($mode === 'loyalty')
                    @can('company_settings.view')<flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="primary" icon="cog-6-tooth">{{ __('Customer Policy Settings') }}</flux:button>@endcan
                    @can('dashboard_reports.view')<flux:button href="{{ route('reports.customers') }}" variant="subtle" icon="chart-bar">{{ __('Customer & loyalty reports') }}</flux:button>@endcan
                @else
                    @can('customers.create')<flux:button data-guide="customer-create-action" href="{{ route('customers.create') }}" variant="primary" icon="plus">{{ __('New customer') }}</flux:button>@endcan
                @endif
                @can('customers.view')<flux:button href="{{ route('customers.groups.index') }}" variant="subtle" icon="folder">{{ __('Customer groups') }}</flux:button>@endcan
                @can('customers.export')<flux:button data-guide="customer-export-action" href="{{ route('customers.export', ['q' => $term]) }}" variant="subtle" icon="arrow-down-tray">{{ __('Export') }}</flux:button>@endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <section data-guide="customer-search" class="rounded-xl border border-border bg-surface p-4 shadow-card" aria-labelledby="customer-search-heading">
            <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <div class="min-w-[16rem] flex-1">
                    <label for="customer-search" class="block text-sm font-semibold">{{ __('Phone or name') }}</label>
                    <input id="customer-search" name="q" value="{{ $term }}" dir="auto" autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-950" placeholder="{{ __('Search by normalized phone or Arabic/English name') }}">
                </div>
                <div class="min-w-[15rem]">
                    <label for="customer-group-filter" class="block text-sm font-semibold">{{ __('Customer group') }}</label>
                    <select id="customer-group-filter" name="group_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-950">
                        <option value="">{{ __('All groups') }}</option>
                        @foreach ($groupOptions as $group)
                            <option value="{{ $group->id }}" @selected($groupId === $group->id)>{{ $group->parent ? '↳ ' : '' }}{{ app()->getLocale() === 'ar' ? $group->name_ar : $group->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button>
                @if ($term !== '' || $groupId !== null)<flux:button href="{{ route('customers.index', ['mode' => $mode]) }}" variant="ghost">{{ __('Reset') }}</flux:button>@endif
            </form>
        </section>

        <x-tables.data-panel data-guide="customer-table" :title="__('Profiles')" :description="__('Customer records in your authorized scope.')">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $customers->total() }} {{ __('records') }}</flux:badge></x-slot:actions>
            <table class="data-table data-table--mobile-summary min-w-full text-start text-sm">
                    <thead><tr><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Customer group') }}</th><th scope="col">{{ __('Phone') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Consent records') }}</th><th scope="col">{{ __('Children') }}</th><th scope="col" class="text-end">{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr data-customer-row>
                                <td><div class="font-semibold text-text-primary">{{ app()->getLocale() === 'ar' ? $customer->name_ar : $customer->name_en }}</div><div class="mt-1 text-xs text-text-muted" dir="auto">{{ app()->getLocale() === 'ar' ? $customer->name_en : $customer->name_ar }}</div></td>
                                <td><div class="font-medium">{{ $customer->group ? (app()->getLocale() === 'ar' ? $customer->group->name_ar : $customer->group->name_en) : __('No group assigned') }}</div>@if ($customer->group?->parent)<div class="mt-1 text-xs text-text-muted">{{ app()->getLocale() === 'ar' ? $customer->group->parent->name_ar : $customer->group->parent->name_en }}</div>@endif</td>
                                <td class="font-mono text-xs" dir="ltr">{{ $customer->phone_display }}</td>
                                <td><x-status.badge :status="$customer->status" /></td>
                                <td class="tabular-nums">{{ $customer->consents_count }}</td>
                                <td class="tabular-nums">{{ $customer->children_count }}</td>
                                <td class="text-end">
                                    @if ($mode === 'loyalty')
                                        <flux:button href="{{ route('customers.loyalty', $customer) }}" size="sm" variant="subtle">{{ __('Open loyalty ledger') }}</flux:button>
                                    @elseif ($mode === 'history')
                                        <flux:button href="{{ route('customers.show', $customer) }}" size="sm" variant="subtle">{{ __('Open transaction history') }}</flux:button>
                                    @else
                                        <flux:button href="{{ route('customers.show', $customer) }}" size="sm" variant="subtle">{{ __('Open profile') }}</flux:button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-state.empty :title="__('No customer profiles found.')" :description="__('Create a profile or broaden the search.')"><x-slot:action>@if ($mode === 'loyalty') @can('company_settings.view')<flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle">{{ __('Review customer policy') }}</flux:button>@endcan @else @can('customers.create')<flux:button href="{{ route('customers.create') }}" variant="primary" icon="plus">{{ __('Create customer profile') }}</flux:button>@endcan @endif</x-slot:action></x-state.empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            <x-slot:footer>@if ($customers->hasPages()){{ $customers->links() }}@endif</x-slot:footer>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
