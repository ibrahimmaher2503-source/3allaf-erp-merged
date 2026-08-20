@php
    $setup ??= app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $nextStep = collect($setup['steps'])->first(static fn (array $step): bool => $step['required'] && ! $step['complete']);
    $statusClasses = ['not_started' => ['border-zinc-300/70', 'bg-zinc-500/10'], 'incomplete' => ['border-amber-500/35', 'bg-amber-500/10'], 'ready' => ['border-sky-500/35', 'bg-sky-500/10'], 'blocked' => ['border-rose-500/35', 'bg-rose-500/10'], 'completed' => ['border-emerald-500/30', 'bg-emerald-500/10']];
    $stepGroups = [
        'foundation' => ['label' => __('Foundation'), 'description' => __('Set the company context and the places where work happens.'), 'keys' => ['company', 'branches-stores', 'warehouses', 'pos-selling-location', 'cash-drawers', 'users-scopes']],
        'configuration' => ['label' => __('Configuration'), 'description' => __('Save the financial, numbering, printer-profile, and template-assignment rules used by operations.'), 'keys' => ['payment-methods', 'taxes', 'document-sequences', 'printers', 'print-templates']],
        'master-data' => ['label' => __('Master data'), 'description' => __('Prepare catalog definitions, customers, suppliers, products, prices, and opening inventory in dependency order.'), 'keys' => ['categories', 'brands', 'customer-groups', 'customers', 'party-readiness', 'supplier-groups', 'suppliers', 'product-masters', 'product-import', 'prices', 'opening-configuration']],
    ];
@endphp

<x-layouts::app :title="__('Initial setup')">
    <x-app.page :title="__('Initial setup progress')" :description="__('Finish setup and master data definitions before daily operations and transactions.')" max-width="7xl" class="space-y-6" data-guide="initial-setup-header">
        <x-slot:actions>
            <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><flux:button type="submit" variant="subtle" icon="language">{{ app()->getLocale() === 'ar' ? __('Switch to English') : __('Switch to Arabic') }}</flux:button></form>
            <flux:button :href="route('dashboard')" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
        </x-slot:actions>
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]" data-guide="initial-setup-summary">
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-card sm:p-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Setup / master data') }}</div><flux:heading size="lg" class="mt-2">{{ __('Configuration status') }}</flux:heading><flux:text class="mt-1">{{ __('Each status below comes from persisted data and its readiness rule.') }}</flux:text></div><div class="text-start sm:text-end"><div class="text-2xl font-semibold tracking-tight text-primary"><span dir="ltr">{{ $setup['completed_count'] }} / {{ $setup['required_count'] }}</span></div><div class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('Required complete') }}</div></div></div><progress class="mt-5 h-2.5 w-full accent-primary" value="{{ $setup['progress_percent'] }}" max="100" aria-label="{{ __('Setup progress') }}">{{ $setup['progress_percent'] }}%</progress><div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-text-muted"><span>{{ __('Not started') }}</span><span>{{ __('Incomplete') }}</span><span>{{ __('Ready') }}</span><span>{{ __('Blocked') }}</span><span>{{ __('Completed') }}</span></div></div>
            @if ($nextStep)<div class="flex flex-col justify-between gap-5 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 shadow-sm sm:p-6" data-guide="initial-setup-next-step"><div><div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-800 dark:text-amber-200">{{ __('Next action') }}</div><flux:heading size="base" class="mt-3">{{ $nextStep['label'] }}</flux:heading><flux:text class="mt-1">{{ $nextStep['reason'] }}</flux:text></div>@if ($nextStep['route'] && $nextStep['can_access'])<flux:button :href="$nextStep['route']" data-setup-route="{{ $nextStep['route_name'] }}" variant="primary" icon="arrow-left" wire:navigate>{{ $nextStep['cta_label'] }}</flux:button>@endif</div>@else<flux:callout variant="success" icon="check-circle" title="{{ __('All required setup steps are complete') }}">{{ __('Review the saved definitions before opening daily operations.') }}</flux:callout>@endif
        </section>
        <flux:callout variant="info" icon="information-circle" title="{{ __('Definitions first, transactions later') }}">{{ __('Use Setup / Master Data for company, branch, catalog, and policy definitions. Daily Operations / Transactions remains separate for sales, purchase orders, inventory movements, parties, settlements, and returns.') }}</flux:callout>
        <section aria-labelledby="owner-decisions-heading" data-guide="initial-setup-owner-decisions">
            <div class="mb-4"><div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">{{ __('Owner decisions') }}</div><flux:heading id="owner-decisions-heading" size="lg" class="mt-2">{{ __('Open decisions with an entry or review surface') }}</flux:heading><flux:text class="mt-1 max-w-4xl text-sm leading-6">{{ __('Each card stays pending until the owner confirms the policy. Use the linked screen to enter or review the decision; this page never records an approval by itself.') }}</flux:text></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($setup['owner_decisions'] as $decision)
                    <article class="flex h-full flex-col gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 shadow-sm sm:p-6" data-owner-decision="{{ $decision['key'] }}">
                        <div class="flex items-start justify-between gap-3"><flux:heading size="base" class="min-w-0">{{ $decision['title'] }}</flux:heading><flux:badge size="sm" color="amber" class="shrink-0">{{ $decision['status_label'] }}</flux:badge></div>
                        <flux:text class="text-sm leading-6 text-text-muted">{{ $decision['description'] }}</flux:text>
                        <div class="mt-auto border-t border-amber-500/20 pt-4">
                            @if ($decision['can_access'])
                                <flux:button :href="$decision['route']" data-setup-route="{{ $decision['route_name'] }}" variant="subtle" size="sm" icon="arrow-left" wire:navigate>{{ $decision['cta_label'] }}</flux:button>
                            @else
                                <span class="text-xs font-medium text-text-muted">{{ __('Permission required') }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        <section aria-label="{{ __('Initial setup steps') }}" data-guide="initial-setup-steps"><div class="mb-4"><div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Owner checklist') }}</div><flux:heading size="lg" class="mt-2">{{ __('Initial setup steps') }}</flux:heading><flux:text class="mt-1 max-w-4xl text-sm leading-6">{{ __('Follow the sections in order. Each action opens the internal screen that owns the data, and returning here refreshes the readiness status.') }}</flux:text></div>
            @php($stepNumber = 0)
            @foreach ($stepGroups as $groupKey => $group)
                <div class="mt-7 first:mt-0" data-setup-section="{{ $groupKey }}"><div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><flux:heading size="base">{{ $group['label'] }}</flux:heading><flux:text class="text-sm">{{ $group['description'] }}</flux:text></div><span class="text-xs font-medium text-text-muted">{{ count($group['keys']) }} {{ __('areas') }}</span></div><div class="grid gap-4 md:grid-cols-2">
                    @foreach (collect($setup['steps'])->whereIn('key', $group['keys']) as $step)
                        @php($stepNumber++)
                        @php($classes = $statusClasses[$step['status']] ?? $statusClasses['incomplete'])
                        <article class="group flex h-full flex-col gap-4 rounded-2xl border {{ $classes[0] }} bg-surface p-5 shadow-sm sm:p-6" data-guide="initial-setup-step-{{ $step['key'] }}" data-setup-destination="{{ $step['destination_key'] }}"><div class="flex items-start justify-between gap-4"><div class="flex min-w-0 items-start gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $classes[1] }} text-sm font-semibold">{{ str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><flux:heading size="base">{{ $step['label'] }}</flux:heading><flux:text class="mt-1 text-sm leading-6">{{ $step['description'] }}</flux:text></div></div><flux:badge size="sm" color="{{ $step['status'] === 'completed' ? 'green' : ($step['status'] === 'blocked' ? 'red' : ($step['status'] === 'ready' ? 'blue' : 'amber')) }}">{{ $step['status_label'] }}</flux:badge></div><div class="mt-auto border-t border-border pt-4"><p class="text-sm leading-6 text-text-muted">{{ $step['reason'] }}</p><div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs font-medium text-text-muted">{{ $step['required'] ? __('Required') : __('Optional') }}</span>@if ($step['route'] && $step['can_access'])<flux:button :href="$step['route']" data-setup-route="{{ $step['route_name'] }}" data-setup-destination="{{ $step['destination_key'] }}" variant="{{ $step['complete'] ? 'subtle' : 'primary' }}" size="sm" icon="arrow-left" wire:navigate>{{ $step['cta_label'] }}</flux:button>@elseif ($step['route'])<span class="text-xs font-medium text-text-muted">{{ __('Permission required') }}</span>@else<span class="text-xs font-medium text-rose-700 dark:text-rose-300">{{ __('No configuration surface') }}</span>@endif</div></div></article>
                    @endforeach
                </div></div>
            @endforeach
        </section>
        <flux:callout variant="warning" icon="shield-check" title="{{ __('Readiness is not approval') }}">{{ __('A saved row is counted only when the current readiness rule is met. Financial approvals, production devices, and owner/UAT decisions remain separate gates.') }}</flux:callout>
    </x-app.page>
</x-layouts::app>
