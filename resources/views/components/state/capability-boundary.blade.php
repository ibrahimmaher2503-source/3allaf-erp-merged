@props([
    'title',
    'description' => null,
    'boundary' => null,
    'cards' => [],
    'empty' => null,
    'emptyTitle' => null,
    'guidePrefix' => 'capability-review',
    'statusLabel' => null,
    'cardStatusLabel' => null,
])

@php
    $displayDescription = $description ?: __('Use this area when the related records are available.');
@endphp

<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-guide="{{ $guidePrefix }}-header">
    <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex min-w-0 max-w-3xl flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ $title }}</h1>
            <x-context-help :title="$title" :label="__('Open contextual help')">
                <p>{{ $displayDescription }}</p>
            </x-context-help>
        </div>
        @if (isset($actions))
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
        @endif
    </header>

    @if ($boundary)
        <section class="rounded-2xl border border-amber-300/40 bg-amber-50/70 p-4 text-sm leading-6 text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/20 dark:text-amber-100" data-guide="{{ $guidePrefix }}-boundary">
            {{ $boundary }}
        </section>
    @endif

    @if ($cards)
        <section class="space-y-4" data-guide="{{ $guidePrefix }}-summary" aria-label="{{ $statusLabel ?: __('Readiness summary') }}">
            @if ($statusLabel)
                <p class="text-sm font-semibold text-text-muted">{{ $statusLabel }}</p>
            @endif
            <div class="grid gap-4 md:grid-cols-2" data-guide="{{ $guidePrefix }}-cards">
                @foreach ($cards as $card)
                    <article class="rounded-2xl border border-border bg-surface p-5 shadow-sm" @if ($loop->first) data-guide="{{ $guidePrefix }}-first-card" @endif>
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-semibold text-text-primary">{{ $card['title'] }}</h2>
                            @if ($cardStatusLabel)
                                <x-status.badge tone="warning">{{ $cardStatusLabel }}</x-status.badge>
                            @endif
                        </div>
                        <p class="mt-2 text-sm leading-6 text-text-muted">{{ $card['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="max-w-2xl" data-guide="{{ $guidePrefix }}-empty" aria-labelledby="{{ $guidePrefix }}-empty-title" @if ($cards) hidden @endif>
        <x-state.empty
            :title="$emptyTitle ?: __('No records are available yet.')"
            :description="$empty ?: __('There is nothing to show yet.')"
            icon="document-text"
        />
    </section>
</div>
