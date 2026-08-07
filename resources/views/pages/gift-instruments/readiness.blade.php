@php
    $isArabic = app()->getLocale() === 'ar';
@endphp

<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="{{ $kind }}-readiness-header">
            <div>
                <flux:heading size="xl">{{ $title }}</flux:heading>
                <flux:text class="mt-1">{{ $description }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($kind === 'gift-receipts')
                    <flux:button href="{{ route('gift.cards') }}" variant="subtle">{{ __('Open Gift Cards') }}</flux:button>
                @else
                    <flux:button href="{{ route('gift.receipts') }}" variant="subtle">{{ __('Open Gift Receipts') }}</flux:button>
                @endif
                <flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle">{{ __('Pending value settings') }}</flux:button>
            </div>
        </div>

        <flux:callout variant="warning" icon="information-circle" data-guide="{{ $kind }}-readiness-boundary">
            <flux:heading size="sm">{{ __('Local/Dev readiness only') }}</flux:heading>
            <flux:text class="mt-1">{{ $boundary }}</flux:text>
        </flux:callout>

        <div class="grid gap-4 sm:grid-cols-2" data-guide="{{ $kind }}-readiness-summary">
            @foreach ($items as $item)
                <div @if ($loop->first) data-guide="{{ $kind }}-readiness-first-card" @endif class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $item['title'] }}</flux:heading>
                        <flux:badge color="amber">PENDING</flux:badge>
                    </div>
                    <flux:text class="mt-2">{{ $item['detail'] }}</flux:text>
                </div>
            @endforeach
        </div>

        <section class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-900/60" data-guide="{{ $kind }}-readiness-empty">
            <flux:heading size="lg">{{ $emptyTitle }}</flux:heading>
            <flux:text class="mt-2">{{ $emptyBody }}</flux:text>
        </section>

        <flux:callout variant="info" icon="lock-closed">
            {{ __('No card number, Gift Receipt reference, balance, price, holder data, payment, or print artifact is loaded in this readiness slice.') }}
        </flux:callout>
    </div>
</x-layouts::app>
