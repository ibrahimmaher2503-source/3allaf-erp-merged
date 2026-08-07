@php
    $isArabic = app()->getLocale() === 'ar';
@endphp

<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="returns-readiness-header">
            <div>
                <flux:heading size="xl">{{ $title }}</flux:heading>
                <flux:text class="mt-2 max-w-3xl">{{ $description }}</flux:text>
            </div>
            <flux:badge color="amber">{{ __('Local/Dev readiness only') }}</flux:badge>
        </div>

        <div class="grid gap-4 lg:grid-cols-2" data-guide="returns-readiness-boundary">
            <flux:card class="border-amber-200 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/20">
                <flux:heading size="sm">{{ __('No return mutation is enabled') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No source return, refund, exchange, restock, payment reversal, customer, wallet, or Gift Card record is created from this screen.') }}</flux:text>
            </flux:card>
            <flux:card>
                <flux:heading size="sm">{{ __('Source and scope boundary') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Invoice/Gift Receipt validation, tenant/store scope, quantity, condition, approval, and settlement remain PENDING.') }}</flux:text>
            </flux:card>
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-guide="returns-readiness-summary">
            @foreach ($items as $index => $item)
                <flux:card data-guide="returns-readiness-card-{{ $index + 1 }}">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="sm">{{ $item['title'] }}</flux:heading>
                        <flux:badge color="amber">PENDING</flux:badge>
                    </div>
                    <flux:text class="mt-2">{{ $item['body'] }}</flux:text>
                </flux:card>
            @endforeach
        </div>

        <flux:card data-guide="returns-readiness-empty" class="border-dashed">
            <flux:heading size="sm">{{ __('No return/exchange records yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('This empty state is intentional. Configure and approve the required return policy and source contracts before any return, refund, exchange, or stock action is introduced.') }}</flux:text>
            <div class="mt-4 flex flex-wrap gap-3">
                <flux:button :href="route('admin.settings.customer-loyalty')" variant="primary">{{ __('Open pending policy settings') }}</flux:button>
                <flux:button :href="route('sales.index')" variant="ghost">{{ __('Review sales sources') }}</flux:button>
            </div>
        </flux:card>
    </div>
</x-layouts::app>
