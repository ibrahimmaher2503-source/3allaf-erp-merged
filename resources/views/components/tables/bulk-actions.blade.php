@props([
    'pageIds' => [],
    'selectedCount' => 0,
    'selectedIds' => [],
    'pageCount' => 0,
    'maxSelection' => 100,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-border bg-surface-muted/40 p-3 sm:flex-row sm:items-center sm:justify-between']) }} role="region" aria-label="{{ __('Bulk operations') }}">
    <div class="flex min-w-0 items-start gap-3">
        <input
            type="checkbox"
            class="mt-1 size-4 rounded border-border text-primary focus:ring-primary"
            wire:click="toggleBulkPage(@js($pageIds))"
            @checked($pageIds !== [] && count(array_diff($pageIds, $selectedIds)) === 0)
            aria-label="{{ __('Select all records on this page') }}"
        />
        <div class="min-w-0 text-sm">
            <div class="font-medium text-text-primary">
                {{ trans_choice(':count selected', $selectedCount, ['count' => $selectedCount]) }}
            </div>
            <div class="text-xs text-text-muted">
                {{ __('This selects the current page (:count records). Cross-page actions require queued processing.', ['count' => $pageCount]) }}
                {{ __('Limit: :limit.', ['limit' => $maxSelection]) }}
            </div>
        </div>
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
            @if ($selectedCount > 0)
                <flux:button type="button" size="sm" variant="ghost" wire:click="clearBulkSelection">
                    {{ __('Clear selection') }}
                </flux:button>
            @endif
        </div>
    @endif
</div>
