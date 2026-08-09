<x-layouts::pos :title="__('POS')" :store="$store" :shift="$shift">
    @php
        $currency = $store?->company?->currency_symbol ?: $store?->company?->currency_code;
        $cashAdjustment = $preview && $cashDenomination !== null
            ? app(\App\Modules\Retail\Services\PosCalculationService::class)->cashRoundingAdjustment($preview['total'])
            : null;
        $cashPayable = $cashAdjustment === null || ! $preview ? null : bcadd($preview['total'], $cashAdjustment, 2);
        $electronicIndex = $cashMethod ? 1 : 0;
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3" data-guide="pos-header">
            <div>
                <flux:heading size="xl">{{ __('POS Checkout') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Barcode-first retail checkout with server-calculated totals, scoped payments, and protected evidence.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button href="{{ route('pos.suspended') }}" variant="subtle" icon="pause">{{ __('Suspended') }} ({{ $suspendedCount }})</flux:button>
                <flux:button href="{{ route('sales.index') }}" variant="subtle" icon="receipt-percent">{{ __('Sales') }}</flux:button>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif
        @if ($previewError)
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $previewError }}</flux:callout>
        @endif

        <div class="grid min-w-0 gap-4 lg:grid-cols-[1.35fr_0.9fr]">
            <section class="flex min-w-0 flex-col gap-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <flux:heading size="lg">{{ __('Products') }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Only active products with an approved effective store price can enter the basket.') }}</flux:text>
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
                                        <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ \App\Modules\Retail\Support\DecimalMoney::round((string) $price->amount) }} {{ $currency }}</span>
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
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <flux:heading size="lg">{{ __('Cart') }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Prices and stock are locked and checked again in the posting transaction.') }}</flux:text>
                        </div>
                        @if ($cart->isNotEmpty())
                            <form method="POST" action="{{ route('pos.cart.clear') }}">@csrf<flux:button type="submit" size="sm" variant="subtle">{{ __('Clear') }}</flux:button></form>
                        @endif
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($previewLines as $line)
                            @php($cartLine = $line['cart'])
                            @php($computed = $preview['lines'][$loop->index] ?? null)
                            <article class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-cart-line="{{ $line['product']->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold">{{ app()->getLocale() === 'ar' ? $line['product']->name_ar : $line['product']->name_en }}</div>
                                        <div class="text-xs text-zinc-500">{{ $line['product']->item_code }} · {{ __('Qty') }} {{ $line['quantity'] }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="font-semibold">{{ $computed['net_amount'] ?? '0.00' }} {{ $currency }}</div>
                                        <div class="text-xs text-zinc-500">{{ __('Unit') }} {{ \App\Modules\Retail\Support\DecimalMoney::round($line['unit_price']) }}</div>
                                    </div>
                                </div>

                                @if (filled($cartLine['discount_amount'] ?? null))
                                    <flux:callout class="mt-3" variant="success" icon="tag">{{ __('Discount') }}: {{ $cartLine['discount_amount'] }} ({{ $cartLine['discount_type'] }})</flux:callout>
                                @endif
                                @if (filled($cartLine['open_price_amount'] ?? null))
                                    <flux:callout class="mt-3" variant="warning" icon="pencil-square">{{ __('Open price') }}: {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $cartLine['open_price_amount']) }} · {{ $cartLine['open_price_reason'] }}</flux:callout>
                                @endif

                                <div class="mt-3 grid gap-3 xl:grid-cols-2">
                                    @can('pos_sales.apply_discount')
                                        <form method="POST" action="{{ route('pos.cart.discount') }}" class="grid gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                                            <input type="hidden" name="expected_revision" value="{{ (int) ($cartLine['discount_revision'] ?? 0) }}">
                                            <flux:select name="discount_type" :label="__('Discount source')" required>
                                                <flux:select.option value="line">{{ __('Manual discount') }}</flux:select.option>
                                                <flux:select.option value="customer_group">{{ __('Customer / group discount') }}</flux:select.option>
                                            </flux:select>
                                            <flux:input name="discount_amount" type="number" min="0.01" step="0.01" :label="__('Discount amount')" required />
                                            <flux:input name="reason" :label="__('Reason')" :description="filled($cartLine['discount_type'] ?? null) ? __('Required because this replaces the previous discount.') : __('Recorded with the discount audit event.')" />
                                            <flux:button type="submit" size="sm" variant="subtle">{{ filled($cartLine['discount_type'] ?? null) ? __('Replace discount') : __('Apply discount') }}</flux:button>
                                        </form>
                                    @endcan

                                    @can('pos_sales.open_price')
                                        @if ($line['price']->open_price_allowed)
                                            <form method="POST" action="{{ route('pos.cart.open-price') }}" class="grid gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                                                <input type="hidden" name="expected_revision" value="{{ (int) ($cartLine['pricing_revision'] ?? 0) }}">
                                                <div class="text-xs text-zinc-500">{{ __('Reference') }} {{ $line['price']->reference_amount ?? $line['price']->amount }} · {{ __('Range') }} {{ $line['price']->open_price_minimum ?? __('Not configured') }}–{{ $line['price']->open_price_maximum ?? __('Not configured') }}</div>
                                                <flux:input name="amount" type="number" min="0.0001" step="0.0001" :label="__('Open price amount')" required />
                                                <flux:input name="reason" :label="__('Required reason')" required />
                                                <flux:button type="submit" size="sm" variant="subtle">{{ __('Set open price') }}</flux:button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>

                                <form method="POST" action="{{ route('pos.cart.remove') }}" class="mt-3 text-end">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                                    <flux:button type="submit" size="sm" variant="subtle" icon="x-mark">{{ __('Remove') }}</flux:button>
                                </form>
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-10 text-center text-zinc-500 dark:border-zinc-700">{{ __('Cart is empty') }}</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <aside class="flex min-w-0 flex-col gap-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Operational context') }}</flux:heading>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Selling Store') }}</dt><dd class="font-semibold">{{ $store ? (app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en) : __('Not configured') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Cash Drawer') }}</dt><dd class="font-semibold">{{ $shift?->cashDrawer?->code ?? __('Not configured') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Shift') }}</dt><dd class="font-semibold">{{ $shift ? __('Open') : __('No active shift') }}</dd></div>
                    </dl>
                    @if (! $store || ! $shift)
                        <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('Checkout requires a visible selling store and an active shift.') }}</flux:callout>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading size="lg">{{ __('Totals') }}</flux:heading>
                        @can('pos_sales.apply_tax')
                            <form method="POST" action="{{ route('pos.cart.tax') }}">
                                @csrf
                                <input type="hidden" name="tax_applicable" value="{{ $taxApplicable ? 0 : 1 }}">
                                <flux:button type="submit" size="sm" variant="subtle">{{ $taxApplicable ? __('Disable tax') : __('Enable tax') }}</flux:button>
                            </form>
                        @endcan
                    </div>
                    <div class="mt-4 space-y-3 border-y border-zinc-100 py-4 text-sm dark:border-zinc-800">
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Gross subtotal') }}</span><span>{{ $preview['subtotal'] ?? '0.00' }} {{ $currency }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Discount total') }}</span><span>{{ $preview['discount_total'] ?? '0.00' }} {{ $currency }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Post-discount net') }}</span><span>{{ $preview['taxable_base'] ?? '0.00' }} {{ $currency }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">{{ __('Tax') }}</span><span>{{ $preview['tax_total'] ?? '0.00' }} {{ $currency }}</span></div>
                        <div class="flex justify-between border-t border-zinc-100 pt-3 text-base font-bold dark:border-zinc-800"><span>{{ __('Final total') }}</span><span>{{ $preview['total'] ?? '0.00' }} {{ $currency }}</span></div>
                        @if ($cashAdjustment !== null)
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Cash rounding') }}</span><span>{{ $cashAdjustment }} {{ $currency }}</span></div>
                            <div class="flex justify-between font-semibold"><span>{{ __('Cash payable') }}</span><span>{{ $cashPayable }} {{ $currency }}</span></div>
                        @endif
                    </div>
                    @if ($taxApplicable)
                        <flux:callout class="mt-3" variant="success" icon="check-circle">{{ __('Tax enabled') }} · {{ $taxSetting?->code }} · {{ $taxSetting?->rate }}%</flux:callout>
                    @endif
                    @if ($cashDenomination === null)
                        <flux:callout class="mt-3" variant="warning" icon="exclamation-triangle">{{ __('Cash is blocked because the Initial Setup cash-rounding denomination is not configured.') }}</flux:callout>
                    @endif

                    <form method="POST" action="{{ route('pos.checkout') }}" enctype="multipart/form-data" class="mt-4 grid gap-4" data-guide="pos-payment-form">
                        @csrf
                        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
                        <input type="hidden" name="tax_applicable" value="{{ $taxApplicable ? 1 : 0 }}">

                        @if ($electronicMethods->isNotEmpty())
                            <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <legend class="px-1 text-sm font-semibold">{{ __('Manual electronic payment') }}</legend>
                                <flux:select name="payments[{{ $electronicIndex }}][method_id]" :label="__('Payment method')">
                                    <flux:select.option value="">{{ __('No electronic payment') }}</flux:select.option>
                                    @foreach ($electronicMethods as $method)
                                        <flux:select.option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}{{ $method->requires_evidence ? ' · '.__('evidence required') : '' }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input name="payments[{{ $electronicIndex }}][amount]" type="number" min="0.01" step="0.01" :label="__('Electronic amount')" :description="__('Enter the electronic amount explicitly.')" />
                                @can('pos_sales.payment_evidence_upload')
                                    <flux:input name="payments[{{ $electronicIndex }}][evidence]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" :label="__('Protected payment evidence')" />
                                @endcan
                                <flux:input name="payments[{{ $electronicIndex }}][evidence_reference]" :label="__('Terminal reference (optional)')" />
                            </fieldset>
                        @endif

                        @if ($cashMethod)
                            <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <legend class="px-1 text-sm font-semibold">{{ __('Cash residual') }}</legend>
                                <flux:checkbox name="payments[0][method_id]" value="{{ $cashMethod->id }}" :label="__('Use cash for the remaining residual')" :disabled="$cashDenomination === null" />
                                <input type="hidden" name="payments[0][amount]" value="0">
                                <flux:input name="payments[0][tendered]" type="number" min="0" step="0.01" :label="__('Cash tendered')" :description="__('Cash is applied to the exact residual; any excess becomes change.')" />
                            </fieldset>
                        @endif

                        <flux:button type="submit" variant="primary" class="w-full" :disabled="$cart->isEmpty() || ! $shift || ! $preview" icon="check">{{ __('Settle and complete sale') }}</flux:button>
                    </form>

                    <form method="POST" action="{{ route('pos.suspend') }}" class="mt-2">@csrf<flux:button type="submit" variant="subtle" class="w-full" :disabled="$cart->isEmpty() || ! $shift" icon="pause">{{ __('Suspend sale') }}</flux:button></form>
                </div>
            </aside>
        </div>
    </div>
</x-layouts::pos>
