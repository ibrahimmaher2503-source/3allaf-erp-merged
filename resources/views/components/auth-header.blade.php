@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <div class="flex items-center justify-center gap-2">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($description)
            <x-context-help :title="$title" :label="__('Open contextual help')"><p>{{ $description }}</p></x-context-help>
        @endif
    </div>
</div>
