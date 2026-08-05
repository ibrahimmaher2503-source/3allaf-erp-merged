@props([
    'title',
    'description' => null,
    'badge' => null,
    'badgeColor' => 'zinc',
    'requestId' => null,
    'breadcrumbs' => null,
])

<div {{ $attributes->merge(['class' => 'app-page-header mb-8 w-full space-y-4 border-b border-border pb-6']) }}>
    @if ($breadcrumbs || isset($breadcrumbs))
        <div class="text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-text-muted">
            @if (isset($breadcrumbs))
                {{ $breadcrumbs }}
            @else
                {{ $breadcrumbs }}
            @endif
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <flux:heading size="xl" level="1" class="font-bold tracking-[-0.03em] text-text-primary">
                    {{ $title }}
                </flux:heading>

                @if ($badge)
                    @if ($badgeColor === 'primary')
                        <span class="inline-flex items-center rounded-md bg-primary-soft px-2 py-1 text-xs font-medium text-primary ring-1 ring-primary/20">
                            {{ $badge }}
                        </span>
                    @else
                        <flux:badge size="sm" :color="$badgeColor">
                            {{ $badge }}
                        </flux:badge>
                    @endif
                @endif
            </div>

            @if ($description)
                <flux:subheading class="max-w-2xl text-sm leading-6 text-text-muted">
                    {{ $description }}
                </flux:subheading>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            @if ($requestId)
                <flux:badge size="sm" variant="outline" icon="finger-print" class="font-mono text-xs" title="{{ __('Correlation ID') }}">
                    {{ $requestId }}
                </flux:badge>
            @endif

            @if (isset($actions))
                {{ $actions }}
            @elseif ($slot->isNotEmpty())
                {{ $slot }}
            @endif
        </div>
    </div>
</div>
