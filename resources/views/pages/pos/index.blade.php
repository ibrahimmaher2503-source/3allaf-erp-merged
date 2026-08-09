<x-layouts::pos :title="__('POS')" :store="$store" :shift="$shift">
    @php
        $cartTotal = '0.00';
        foreach ($cart as $cartLine) {
            $product = $cartProducts->get((int) $cartLine['product_id']);
            $price = $product && $store ? app(\App\Modules\Pricing\Services\EffectivePriceResolver::class)->resolve($product->id, $store->id) : null;
            if ($price) {
                $cartTotal = bcadd($cartTotal, bcmul((string) $cartLine['quantity'], (string) $price->amount, 2), 2);
            }
        }
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3" data-guide="pos-header">
            <div>
                <flux:heading size="xl">{{ __('POS Checkout') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Local/Dev online checkout slice. Financial, tax, hardware, and offline policies remain PENDING.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button href="{{ route('pos.suspended') }}" variant="subtle" icon="pause">{{ __('Suspended') }} ({{ $suspendedCount }})</flux:button>
                <flux:button href="{{ route('sales.index') }}" variant="subtle" icon="receipt-percent">{{ __('Sales') }}</flux:button>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="grid gap-4 lg:grid-cols-[1.35fr_0.9fr]">
            {{-- `min-w-0`: a CSS Grid item's automatic minimum width defaults to its
                 content's min-content size, which the Cart table's `min-w-[520px]`
                 (line ~87, wrapped in its own `overflow-x-auto`) would otherwise force
                 up through this grid item, overflowing the page horizontally on narrow
                 (mobile) viewports even though the table's own overflow is contained. --}}
            <section class="flex min-h-0 min-w-0 flex-col gap-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-2" data-guide="pos-products-heading">
                        <div>
                            <flux:heading size="lg">{{ __('Products') }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Choose a priced active product. Barcode scanner input can use the same field later.') }}</flux:text>
                        </div>
                        <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $availableProducts->count() }} {{ __('available') }}</span>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($availableProducts as $product)
                            @php($price = $priceMap->get($product->id))
                            <div class="flex flex-col justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div>
                                    <div class="font-semibold">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ $product->item_code }}</div>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    @if ($price)
                                        <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ number_format((float) $price->amount, 2) }} {{ $store?->company?->currency_symbol ?? 'EGP' }}</span>
                                        <form method="POST" action="{{ route('pos.cart.add') }}">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <flux:button type="submit" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                                        </form>
                                    @else
                                        <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('No approved price') }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500 dark:border-zinc-700">{{ __('No products available.') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-2" data-guide="pos-cart-heading">
                        <div>
                            <flux:heading size="lg">{{ __('Cart') }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Items are re-priced and stock-checked on the server before posting.') }}</flux:text>
                        </div>
                        @if ($cart->isNotEmpty())
                            <form method="POST" action="{{ route('pos.cart.clear') }}">
                                @csrf
                                <flux:button type="submit" size="sm" variant="subtle">{{ __('Clear') }}</flux:button>
                            </form>
                        @endif
                    </div>
                    <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Cart') }}">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="border-b border-zinc-200 text-start text-xs text-zinc-500 dark:border-zinc-700">
                                <tr><th class="py-2 text-start">{{ __('Product') }}</th><th class="py-2 text-start">{{ __('Quantity') }}</th><th class="py-2 text-start">{{ __('Unit price') }}</th><th class="py-2 text-end">{{ __('Amount') }}</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($cart as $cartLine)
                                    @php($product = $cartProducts->get((int) $cartLine['product_id']))
                                    @php($price = $product && $store ? app(\App\Modules\Pricing\Services\EffectivePriceResolver::class)->resolve($product->id, $store->id) : null)
                                    @if ($product)
                                        <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                                            <td class="py-3"><div class="font-medium">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</div><div class="text-xs text-zinc-500">{{ $product->item_code }}</div></td>
                                            <td class="py-3">{{ $cartLine['quantity'] }}</td>
                                            <td class="py-3">{{ $price ? number_format((float) $price->amount, 2) : '—' }}</td>
                                            <td class="py-3 text-end font-semibold">{{ $price ? number_format((float) bcmul((string) $cartLine['quantity'], (string) $price->amount, 2), 2) : '—' }}</td>
                                            <td class="py-3 text-end"><form method="POST" action="{{ route('pos.cart.remove') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><flux:button type="submit" size="sm" variant="subtle" icon="x-mark" aria-label="{{ __('Remove') }}"></flux:button></form></td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="5" class="py-10 text-center text-zinc-500">{{ __('Cart is empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="flex flex-col gap-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" data-guide="pos-operational-context">
                    <flux:heading size="lg">{{ __('Operational context') }}</flux:heading>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Selling Store') }}</dt><dd class="font-semibold">{{ $store ? (app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en) : __('Not configured') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Cash Drawer') }}</dt><dd class="font-semibold">{{ $shift?->cashDrawer?->code ?? __('Not configured') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Shift') }}</dt><dd class="font-semibold">{{ $shift ? __('Open') : __('No active shift') }}</dd></div>
                    </dl>
                    @if (! $store || ! $shift)
                        <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('Checkout requires a visible selling store and an active local POS shift.') }}</flux:callout>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Summary') }}</flux:heading>
                    <div class="mt-4 space-y-3 border-y border-zinc-100 py-4 text-sm dark:border-zinc-800">
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Subtotal') }}</span><span>{{ number_format((float) $cartTotal, 2) }} {{ $store?->company?->currency_symbol ?? 'EGP' }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Discount') }}</span><span>0.00</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Tax') }}</span><span>{{ __('PENDING') }}</span></div>
                        <div class="flex justify-between border-t border-zinc-100 pt-3 text-base font-bold dark:border-zinc-800"><span>{{ __('Total') }}</span><span>{{ number_format((float) $cartTotal, 2) }} {{ $store?->company?->currency_symbol ?? 'EGP' }}</span></div>
                    </div>
                    <div class="mt-4 grid gap-2" data-guide="pos-summary-actions">
                        <form method="POST" action="{{ route('pos.checkout') }}">@csrf<flux:button type="submit" variant="primary" class="w-full" :disabled="$cart->isEmpty() || ! $shift" icon="check">{{ __('Checkout cash sale') }}</flux:button></form>
                        <form method="POST" action="{{ route('pos.suspend') }}">@csrf<flux:button type="submit" variant="subtle" class="w-full" :disabled="$cart->isEmpty() || ! $shift" icon="pause">{{ __('Suspend sale') }}</flux:button></form>
                    </div>
                    <flux:text class="mt-3 text-xs text-zinc-500">{{ __('Local Demo only. Gateway, offline mode, tax, final discount limits, and receipt policy remain PENDING.') }}</flux:text>
                </div>
            </aside>
        </div>
    </div>
</x-layouts::pos>
