<x-layouts::pos :title="__('POS')">
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Left 2 columns: Barcode / Items Selection & Cart Table -->
        <div class="lg:col-span-2 flex flex-col gap-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 flex gap-3">
                <flux:input icon="magnifying-glass" placeholder="{{ __('Search or scan barcode...') }}" class="flex-1" autofocus />
                <flux:button variant="primary" icon="plus">{{ __('Search') }}</flux:button>
            </div>

            <div class="flex-1 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-center items-center text-center">
                <div class="size-12 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 mb-2">
                    <flux:icon name="shopping-cart" class="size-6" />
                </div>
                <flux:heading size="md" class="text-zinc-600 dark:text-zinc-400">{{ __('Cart is empty') }}</flux:heading>
                <flux:text class="text-xs text-zinc-400 mt-1">{{ __('Scan item barcode or search products to begin checkout.') }}</flux:text>
            </div>
        </div>

        <!-- Right 1 column: Totals & Checkout Actions -->
        <div class="flex flex-col gap-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Summary') }}</flux:heading>

                <div class="space-y-2 text-sm border-t border-b border-zinc-100 py-3 dark:border-zinc-800">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                        <span class="font-medium">{{ __('Not available yet') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Tax') }}</span>
                        <span class="font-medium">{{ __('Configured at checkout') }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <span>{{ __('Total') }}</span>
                        <span class="text-zinc-500">{{ __('Not available yet') }}</span>
                    </div>
                </div>

                <flux:button variant="primary" class="w-full h-12" disabled>
                    {{ __('Checkout') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-layouts::pos>
