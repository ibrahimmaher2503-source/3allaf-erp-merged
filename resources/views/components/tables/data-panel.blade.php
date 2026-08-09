@props([
    'title',
    'description' => null,
])

<x-cards.section-card :title="$title" :description="$description" {{ $attributes->merge(['data-table-panel' => true]) }}>
    @if (isset($toolbar))
        <div class="table-panel-toolbar" data-table-toolbar>{{ $toolbar }}</div>
    @endif

    <div class="app-table-frame table-panel-surface" data-table-surface>
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="table-panel-footer">{{ $footer }}</div>
    @endif
</x-cards.section-card>
