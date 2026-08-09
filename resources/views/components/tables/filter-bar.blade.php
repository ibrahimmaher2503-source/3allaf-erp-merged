<div {{ $attributes->class('table-filter-bar flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between') }} data-table-filter-bar>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
