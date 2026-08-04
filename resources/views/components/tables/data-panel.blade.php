@props([
    'title',
    'description' => null,
])

<x-cards.section-card :title="$title" :description="$description" {{ $attributes }}>
    @if (isset($toolbar))
        <div class="border-y border-border py-3">{{ $toolbar }}</div>
    @endif

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="border-t border-border pt-3">{{ $footer }}</div>
    @endif
</x-cards.section-card>
