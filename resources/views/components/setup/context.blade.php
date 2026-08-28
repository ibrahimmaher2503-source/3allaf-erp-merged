@props(['currentKey'])

@if (request()->boolean('setup'))
@php
    $setupContext = app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $setupOrder = ['company', 'branches-stores', 'warehouses', 'cash-drawers', 'users-scopes', 'payment-methods', 'taxes', 'document-sequences', 'printers', 'print-templates', 'supplier-groups', 'suppliers', 'categories', 'brands', 'product-options', 'product-masters', 'product-import', 'prices', 'customer-groups', 'customers', 'party-readiness'];
    $authorizedSetupSteps = collect($setupOrder)->map(fn (string $key) => collect($setupContext['steps'])->firstWhere('key', $key))->filter(fn (?array $step) => $step && $step['can_access'])->values();
    $currentSetupIndex = $authorizedSetupSteps->search(fn (array $step) => $step['key'] === $currentKey);
    $currentSetupStep = $currentSetupIndex !== false ? $authorizedSetupSteps[$currentSetupIndex] : null;
    $previousSetupStep = $currentSetupIndex !== false && $currentSetupIndex > 0 ? $authorizedSetupSteps[$currentSetupIndex - 1] : null;
    $nextSetupStep = $currentSetupIndex !== false && $currentSetupIndex < $authorizedSetupSteps->count() - 1 ? $authorizedSetupSteps[$currentSetupIndex + 1] : null;
    $setupHref = static fn (array $step): string => $step['route'].(str_contains($step['route'], '?') ? '&' : '?').http_build_query(['setup' => 1, 'setup_step' => $step['key']]);
@endphp

@if ($currentSetupStep)
    <aside {{ $attributes->class('rounded-2xl border border-primary/25 bg-primary/5 p-4 shadow-sm') }} aria-label="{{ __('Setup guidance') }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <nav class="text-xs font-medium text-text-muted" aria-label="{{ __('Breadcrumb') }}">
                    <a href="{{ route('initial-setup') }}" class="text-primary hover:underline">{{ __('Setup Center') }}</a>
                    <span aria-hidden="true"> / </span><span>{{ $currentSetupStep['label'] }}</span>
                </nav>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-text-primary">{{ __('Step :current of :total', ['current' => $currentSetupIndex + 1, 'total' => $authorizedSetupSteps->count()]) }}</span>
                    <flux:badge size="sm" color="{{ $currentSetupStep['complete'] ? 'green' : ($currentSetupStep['status'] === 'blocked' ? 'red' : 'amber') }}">{{ $currentSetupStep['status_label'] }}</flux:badge>
                    <span class="text-xs text-text-muted">{{ $currentSetupStep['required'] ? __('Required') : __('Optional') }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($previousSetupStep)<flux:button :href="$setupHref($previousSetupStep)" size="sm" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Previous') }}</flux:button>@endif
                <flux:button :href="route('initial-setup')" size="sm" variant="subtle" icon="squares-2x2" wire:navigate>{{ __('Return to Setup Center') }}</flux:button>
                @if ($nextSetupStep)<flux:button :href="$setupHref($nextSetupStep)" size="sm" variant="primary" icon="arrow-right" wire:navigate>{{ __('Next') }}</flux:button>@endif
            </div>
        </div>
    </aside>
@endif
@endif
