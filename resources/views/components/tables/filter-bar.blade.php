<div {{ $attributes->class('table-filter-bar flex min-w-0 flex-col gap-3 rounded-lg bg-surface-muted/35 p-3 sm:flex-row sm:items-end sm:justify-between') }} data-table-filter-bar>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @if (isset($actions))
        <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">{{ $actions }}</div>
    @endif
</div>
