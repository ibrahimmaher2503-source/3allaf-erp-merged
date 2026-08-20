<x-layouts::app :title="__('Customer groups')">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Customer groups')" :description="__('Build a company-scoped hierarchy and use it to organize and filter customer profiles.')">
            <x-slot:actions><flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button></x-slot:actions>
        </x-page-header>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        @can('customers.edit')
            <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20" aria-labelledby="customer-group-create-heading">
                <flux:heading id="customer-group-create-heading" size="lg">{{ __('Create customer group') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Parent is optional. A parent and all descendants must belong to the same company.') }}</flux:text>
                <flux:text class="mt-1 text-sm text-text-muted">{{ __('Use groups to filter customer lists and reports. Leave Parent empty for a root group; nested groups remain under their selected parent.') }}</flux:text>
                <form method="POST" action="{{ route('customers.groups.store') }}" novalidate class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <flux:input name="name_ar" :label="__('Arabic name')" :value="old('name_ar')" required dir="rtl" />
                    <flux:input name="name_en" :label="__('English name')" :value="old('name_en')" required dir="ltr" />
                    <div>
                        <label for="new-group-parent" class="block text-sm font-semibold">{{ __('Parent group (optional)') }}</label>
                        <select id="new-group-parent" name="parent_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">{{ __('No parent — root group') }}</option>
                            @foreach ($parentOptions as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->parent_id ? '↳ ' : '' }}{{ app()->getLocale() === 'ar' ? $parent->name_ar : $parent->name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex justify-end"><flux:button type="submit" variant="primary">{{ __('Create group') }}</flux:button></div>
                </form>
            </section>
        @endcan

        <section class="rounded-xl border border-border bg-surface p-4 shadow-card">
            <form method="GET" action="{{ route('customers.groups.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[16rem] flex-1">
                    <label for="customer-group-search" class="block text-sm font-semibold">{{ __('Search groups') }}</label>
                    <input id="customer-group-search" name="q" value="{{ $term }}" dir="auto" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-950" placeholder="{{ __('Arabic or English group name') }}">
                </div>
                <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button>
                @if ($term !== '')<flux:button href="{{ route('customers.groups.index') }}" variant="ghost">{{ __('Reset') }}</flux:button>@endif
            </form>
        </section>

        <x-tables.data-panel :title="__('Company customer-group hierarchy')" :description="__('The list is paginated and filtered on the server.')">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $groups->total() }} {{ __('groups') }}</flux:badge></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="data-table min-w-full text-start text-sm">
                    <thead><tr><th>{{ __('Group') }}</th><th>{{ __('Parent') }}</th><th>{{ __('Customers') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td><div class="font-semibold">{{ $group->parent_id ? '↳ ' : '' }}{{ app()->getLocale() === 'ar' ? $group->name_ar : $group->name_en }}</div><div class="mt-1 text-xs text-text-muted" dir="auto">{{ app()->getLocale() === 'ar' ? $group->name_en : $group->name_ar }}</div></td>
                                <td>{{ $group->parent ? (app()->getLocale() === 'ar' ? $group->parent->name_ar : $group->parent->name_en) : __('Root group') }}</td>
                                <td class="tabular-nums">{{ $group->active_customers_count }}</td>
                                <td><x-status.badge :status="$group->status" /></td>
                                <td class="text-end">
                                    @can('customers.edit')
                                        <details class="inline-block text-start">
                                            <summary class="cursor-pointer rounded-lg border border-border px-3 py-2 text-xs font-semibold">{{ __('Edit') }}</summary>
                                            <form method="POST" action="{{ route('customers.groups.update', $group->id) }}" novalidate class="mt-2 w-72 space-y-3 rounded-xl border border-border bg-surface p-3 shadow-lg">
                                                @csrf @method('PUT')
                                                <flux:input name="name_ar" :label="__('Arabic name')" :value="$group->name_ar" required dir="rtl" />
                                                <flux:input name="name_en" :label="__('English name')" :value="$group->name_en" required dir="ltr" />
                                                <div><label class="block text-sm font-semibold">{{ __('Parent group') }}</label><select name="parent_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950"><option value="">{{ __('No parent — root group') }}</option>@foreach ($parentOptions as $parent)<option value="{{ $parent->id }}" @selected($group->parent_id === $parent->id || ($group->parent_id === null && $parent->id === 0))>{{ $parent->parent_id ? '↳ ' : '' }}{{ app()->getLocale() === 'ar' ? $parent->name_ar : $parent->name_en }}</option>@endforeach</select></div>
                                                <flux:select name="status" :label="__('Status')"><flux:select.option value="active" :selected="$group->status === 'active'">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive" :selected="$group->status === 'inactive'">{{ __('Inactive') }}</flux:select.option></flux:select>
                                                <flux:button type="submit" variant="primary" size="sm">{{ __('Save group') }}</flux:button>
                                            </form>
                                        </details>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-state.empty :title="__('No customer groups found.')" :description="__('Create a root group or broaden the search.')"><x-slot:action>@can('customers.edit')<flux:button href="#customer-group-create-heading" variant="primary" icon="plus">{{ __('Create root group') }}</flux:button>@endcan</x-slot:action></x-state.empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-slot:footer>@if ($groups->hasPages()){{ $groups->links() }}@endif</x-slot:footer>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
