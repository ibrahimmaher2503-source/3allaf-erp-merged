@props([
    'title',
    'description' => null,
    'badge' => null,
    'badgeColor' => 'zinc',
    'requestId' => null,
    'breadcrumbs' => null,
])

<div {{ $attributes->merge(['class' => 'w-full space-y-3 mb-6 border-b border-zinc-200 pb-5 dark:border-zinc-800']) }}>
    @if ($breadcrumbs || isset($breadcrumbs))
        <div class="text-xs text-zinc-500 dark:text-zinc-400">
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
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">
                    {{ $title }}
                </flux:heading>

                @if ($badge)
                    <flux:badge size="sm" :color="$badgeColor">
                        {{ $badge }}
                    </flux:badge>
                @endif
            </div>

            @if ($description)
                <flux:subheading class="text-sm text-zinc-500 dark:text-zinc-400">
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
