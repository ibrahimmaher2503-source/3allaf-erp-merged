@props(['currentKey'])

@php
    $setupContext = app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $setupGroups = [
        'basics' => ['label' => __('Basics'), 'keys' => ['company', 'branches-stores', 'warehouses', 'cash-drawers', 'users-scopes']],
        'settings' => ['label' => __('Settings'), 'keys' => ['payment-methods', 'taxes', 'document-sequences', 'printers', 'print-templates']],
        'operations' => ['label' => __('Operational readiness'), 'keys' => ['supplier-groups', 'suppliers', 'categories', 'brands', 'product-options', 'product-masters', 'product-import', 'prices', 'customer-groups', 'customers', 'party-readiness']],
    ];
    $authoritativeSteps = collect($setupContext['steps']);
    $authorizedSetupSteps = $authoritativeSteps->filter(fn (array $step) => $step['can_access'])->values();
    $currentSetupIndex = $authorizedSetupSteps->search(fn (array $step) => $step['key'] === $currentKey);
    $currentSetupStep = $currentSetupIndex !== false ? $authorizedSetupSteps[$currentSetupIndex] : null;
    $currentGroup = collect($setupGroups)->first(fn (array $group) => in_array($currentKey, $group['keys'], true));
    $authorizedGroupSteps = $currentGroup ? $authorizedSetupSteps->filter(fn (array $step) => in_array($step['key'], $currentGroup['keys'], true))->values() : collect();
    $groupIndex = $authorizedGroupSteps->search(fn (array $step) => $step['key'] === $currentKey);
    $continueStep = $authorizedSetupSteps->first(fn (array $step) => $step['required'] && ! $step['complete']);
    $setupHref = static fn (array $step): string => $step['route'].(str_contains($step['route'], '?') ? '&' : '?').http_build_query(['setup' => 1, 'setup_step' => $step['key']]);
@endphp

@if ($currentSetupStep)
    <aside {{ $attributes->class('setup-context rounded-2xl border border-primary/25 bg-primary/5 p-4 shadow-sm') }} aria-label="{{ __('Setup guidance') }}" data-setup-step="{{ $currentKey }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-2">
                <nav class="text-xs font-medium text-text-muted" aria-label="{{ __('Breadcrumb') }}">
                    <a href="{{ route('initial-setup') }}" class="text-primary hover:underline" wire:navigate>{{ __('Setup Center') }}</a>
                    <span aria-hidden="true"> / </span><span>{{ $currentGroup['label'] ?? __('Setup') }}</span>
                    <span aria-hidden="true"> / </span><span>{{ $currentSetupStep['label'] }}</span>
                </nav>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-text-primary">{{ __('Step :current of :total', ['current' => $currentSetupIndex + 1, 'total' => $authorizedSetupSteps->count()]) }}</span>
                    @if ($groupIndex !== false)<span class="text-sm text-text-muted">{{ $currentGroup['label'] }} — {{ __('Step :current of :total', ['current' => $groupIndex + 1, 'total' => $authorizedGroupSteps->count()]) }}</span>@endif
                    <flux:badge size="sm" color="{{ $currentSetupStep['complete'] ? 'green' : ($currentSetupStep['status'] === 'blocked' ? 'red' : 'amber') }}">{{ $currentSetupStep['status_label'] }}</flux:badge>
                    <span class="text-xs text-text-muted">{{ $currentSetupStep['required'] ? __('Required') : __('Optional') }}</span>
                </div>
                <div class="h-1.5 max-w-xl overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $setupContext['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"><span class="block h-full rounded-full bg-primary" style="width: {{ $setupContext['progress_percent'] }}%"></span></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button :href="route('initial-setup')" size="sm" variant="subtle" icon="squares-2x2" wire:navigate>{{ __('Return to Setup Center') }}</flux:button>
                @if ($continueStep && $continueStep['key'] !== $currentKey)
                    <flux:button :href="$setupHref($continueStep)" size="sm" variant="primary" icon="arrow-right" wire:navigate>{{ __('Continue Setup') }}</flux:button>
                @endif
            </div>
        </div>
    </aside>
@endif
