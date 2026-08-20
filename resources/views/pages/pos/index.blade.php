<x-layouts::pos :title="__('POS Checkout')" :store="$store" :shift="$shift">
    @php
        $currency = $store?->branch?->currency ?? 'EGP';
        $cashAdjustment = $cashMethod && $preview && $cashDenomination !== null
            ? app(\App\Modules\Retail\Services\PosCalculationService::class)->cashRoundingAdjustment($preview['total'], $cashDenomination)
            : null;
        $cashPayable = $cashAdjustment === null || ! $preview ? null : bcadd($preview['total'], $cashAdjustment, 2);
        $electronicIndex = $cashMethod ? 1 : 0;
        $giftCardIndex = $electronicIndex + 1;
        $checkoutBlocker = $cart->isEmpty()
            ? __('Add an item to begin checkout.')
            : (! $store
                ? __('Checkout is unavailable because no selling store is assigned.')
                : (! $shift
                    ? __('Checkout is unavailable because there is no active shift for this cashier.')
                    : (! $preview ? ($previewError ?: __('Review the cart before checkout.')) : null)));
        $totalValue = (string) ($cashPayable ?? ($preview['total'] ?? '0.00'));
        $initialTender = old('payment_mode')
            ?: ($cashMethod ? 'cash' : ($electronicMethods->isNotEmpty() ? 'electronic' : ($giftCardMethods->isNotEmpty() ? 'gift_card' : '')));
        $supportedTenderCount = ($cashMethod ? 1 : 0) + ($electronicMethods->isNotEmpty() ? 1 : 0) + ($giftCardMethods->isNotEmpty() ? 1 : 0);
    @endphp

    <div class="mx-auto flex w-full max-w-[118rem] flex-col gap-3" data-pos-workstation>
        <header class="flex min-h-12 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 pb-3 dark:border-zinc-800" data-guide="pos-header">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cyan-700 text-white shadow-sm">
                    <flux:icon name="shopping-cart" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="leading-none">{{ __('New sale') }}</flux:heading>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Scan, build the order, and complete payment.') }}</p>
                </div>
            </div>
            <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('POS tools') }}">
                <flux:button href="{{ route('pos.suspended') }}" size="sm" variant="subtle" icon="pause" wire:navigate>{{ __('Held sales') }} <span class="tabular-nums">{{ $suspendedCount }}</span></flux:button>
                <flux:button href="{{ route('sales.index') }}" size="sm" variant="subtle" icon="receipt-percent" wire:navigate>{{ __('Sales') }}</flux:button>
                @if (auth()->user()->hasPermission('offline_queue_conflicts.view'))
                    <flux:button href="{{ route('pos.offline.queue') }}" size="sm" variant="subtle" icon="signal-slash" wire:navigate>{{ __('offline.queue_title') }}</flux:button>
                @endif
            </nav>
        </header>

        @if (! $context->isReady())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ $context->disabledReason }}</span>
                    @can('shifts_cash_movements.view')<flux:button href="{{ route('pos.shift') }}" size="sm" variant="primary" wire:navigate>{{ __('Open shift') }}</flux:button>@endcan
                </div>
            </flux:callout>
        @endif

        @if (session('success'))
            <div x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 2600)" x-show="visible" x-transition.opacity role="status" aria-live="polite" class="fixed end-5 top-20 z-50 flex max-w-sm items-center gap-2 rounded-xl bg-zinc-900 px-4 py-3 text-sm font-medium text-white shadow-xl dark:bg-zinc-100 dark:text-zinc-900">
                <flux:icon name="check-circle" class="size-5 text-emerald-400" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle"><ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
        @elseif ($previewError)
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $previewError }}</flux:callout>
        @endif

        <main class="grid min-w-0 items-start gap-3 xl:grid-cols-[minmax(0,1.55fr)_minmax(19rem,.78fr)_minmax(21rem,.82fr)]" aria-label="{{ __('Point of sale workspace') }}">
            <livewire:pos.product-browser />
            <livewire:pos.cart />
            <section class="hidden flex min-h-[42rem] min-w-0 flex-col overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" aria-labelledby="pos-products-heading">
                <div class="p-4 pb-3">
                    <div class="flex items-center justify-between gap-3">
                        <div><flux:heading id="pos-products-heading" size="lg">{{ __('Products') }}</flux:heading><p class="mt-0.5 text-xs text-zinc-500">{{ __('Scan a barcode or choose a product.') }}</p></div>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $availableProducts->count() }}</span>
                    </div>

                    <form method="GET" action="{{ route('pos') }}" class="mt-3" data-pos-product-search x-data="{ searching: false }" x-on:submit="searching = true">
                        @if ($categoryId !== null)<input type="hidden" name="category" value="{{ $categoryId }}">@endif
                        <label for="pos-product-search" class="sr-only">{{ __('Scan or search by name, SKU or barcode') }}</label>
                        <div class="relative">
                            <flux:icon name="magnifying-glass" class="pointer-events-none absolute start-4 top-1/2 z-10 size-5 -translate-y-1/2 text-zinc-400" />
                            <input id="pos-product-search" name="product_q" value="{{ $productQuery }}" autocomplete="off" inputmode="search" dir="auto" autofocus class="block min-h-12 w-full rounded-xl border border-zinc-300 bg-zinc-50 ps-12 pe-28 text-base shadow-xs outline-none transition focus:border-cyan-600 focus:bg-white focus:ring-2 focus:ring-cyan-600/15 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:bg-zinc-900" placeholder="{{ __('Scan or search by name, SKU or barcode') }}">
                            <button type="submit" x-bind:disabled="searching" class="absolute end-1.5 top-1.5 inline-flex min-h-9 items-center rounded-lg bg-cyan-700 px-3 text-sm font-semibold text-white hover:bg-cyan-800 disabled:opacity-60">
                                <span x-show="!searching">{{ __('Search') }}</span><span x-show="searching" x-cloak>{{ __('Searching…') }}</span>
                            </button>
                        </div>
                    </form>

                    @if ($availableCategories->isNotEmpty())
                        <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('Product categories') }}">
                            <a href="{{ route('pos', array_filter(['product_q' => $productQuery ?: null])) }}" wire:navigate class="inline-flex min-h-9 shrink-0 items-center rounded-lg px-3 text-sm font-semibold {{ $categoryId === null ? 'bg-cyan-700 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' }}">{{ __('All') }}</a>
                            @foreach ($availableCategories as $category)
                                <a href="{{ route('pos', array_filter(['product_q' => $productQuery ?: null, 'category' => $category->id])) }}" wire:navigate class="inline-flex min-h-9 shrink-0 items-center rounded-lg px-3 text-sm font-semibold {{ $categoryId === $category->id ? 'bg-cyan-700 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' }}">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</a>
                            @endforeach
                        </nav>
                    @endif
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto border-t border-zinc-100 p-4 dark:border-zinc-800 xl:max-h-[calc(100vh-12rem)]">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-4" data-product-grid>
                        @forelse ($availableProducts as $product)
                            @php
                                $price = $priceMap->get($product->id);
                                $stock = $stockByProduct->get($product->id);
                                $availableStock = $stock ? bcsub((string) $stock->on_hand, (string) $stock->reserved, 3) : null;
                                $isInStock = $availableStock !== null && bccomp($availableStock, '0', 3) === 1;
                                $needsOptions = $product->product_type !== 'standard' || $product->fractional_quantity || filled($product->size) || filled($product->colour) || filled($product->target_age) || filled($product->suitable_gender);
                                $dialogId = 'pos-options-'.$product->id;
                            @endphp
                            <article class="group flex min-w-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md motion-reduce:transform-none dark:border-zinc-800 dark:bg-zinc-950" data-product-card="{{ $product->id }}">
                                <div class="relative flex h-28 items-center justify-center overflow-hidden bg-zinc-100 text-zinc-400 dark:bg-zinc-800/80 dark:text-zinc-500">
                                    <div class="absolute inset-0 opacity-50 [background-image:radial-gradient(circle_at_center,rgba(8,145,178,.12),transparent_65%)]"></div>
                                    <flux:icon name="cube" class="relative size-10" />
                                    <span class="absolute start-2 top-2 rounded-md px-2 py-1 text-[11px] font-bold {{ $isInStock ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200' }}">{{ $isInStock ? __('In stock') : __('Stock check') }}</span>
                                </div>
                                <div class="flex flex-1 flex-col p-3">
                                    <h3 class="line-clamp-2 min-h-10 text-sm font-bold leading-5 text-zinc-900 dark:text-zinc-100">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</h3>
                                    <p class="mt-1 truncate text-xs text-zinc-500">{{ $product->item_code }}</p>
                                    <div class="mt-auto pt-3">
                                        @if ($price)
                                            <div class="text-base font-extrabold tabular-nums text-zinc-950 dark:text-white"><x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round((string) $price->amount)" :currency="$currency" /></div>
                                            @if ($needsOptions)
                                                <button type="button" onclick="document.getElementById('{{ $dialogId }}').showModal()" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-cyan-700 px-3 text-sm font-bold text-white hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-700">{{ __('Select options') }}</button>
                                            @else
                                                <form method="POST" action="{{ route('pos.cart.add') }}" class="mt-3">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="quantity" value="1"><button type="submit" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-cyan-700 px-3 text-sm font-bold text-white hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-700"><flux:icon name="plus" class="size-4" />{{ __('Add to cart') }}</button></form>
                                            @endif
                                        @else
                                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ __('No approved price') }}</p>
                                            <button type="button" disabled class="mt-3 min-h-10 w-full rounded-lg bg-zinc-100 text-sm font-semibold text-zinc-400 dark:bg-zinc-800">{{ __('Unavailable') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </article>

                            @if ($price && $needsOptions)
                                <dialog id="{{ $dialogId }}" class="w-[min(92vw,34rem)] rounded-2xl bg-white p-0 text-start shadow-2xl backdrop:bg-zinc-950/55 dark:bg-zinc-900 dark:text-zinc-100">
                                    <form method="POST" action="{{ route('pos.cart.add') }}" class="p-5" x-data="{ quantity: 1 }">@csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div class="flex items-start gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-800">
                                            <div class="flex size-20 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800"><flux:icon name="cube" class="size-8" /></div>
                                            <div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">{{ __('Configure item') }}</p><flux:heading size="lg" class="mt-1">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</flux:heading><p class="mt-1 text-xs text-zinc-500">{{ $product->item_code }}</p><div class="mt-2 font-extrabold"><x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round((string) $price->amount)" :currency="$currency" /></div></div>
                                            <button type="button" onclick="this.closest('dialog').close()" class="flex size-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Close') }}"><flux:icon name="x-mark" class="size-5" /></button>
                                        </div>
                                        <div class="grid gap-4 py-5 sm:grid-cols-2">
                                            @foreach ([__('Size') => $product->size, __('Colour') => $product->colour, __('Target age') => $product->target_age, __('Gender') => $product->suitable_gender] as $label => $value)
                                                @if (filled($value))<fieldset><legend class="text-sm font-bold">{{ $label }}</legend><div class="mt-2"><span class="inline-flex min-h-10 items-center rounded-lg border-2 border-cyan-600 bg-cyan-50 px-3 text-sm font-semibold text-cyan-900 dark:bg-cyan-950/30 dark:text-cyan-100">{{ __((string) $value) }}</span></div></fieldset>@endif
                                            @endforeach
                                            @if ($product->product_type !== 'standard')<div><div class="text-sm font-bold">{{ __('Product type') }}</div><span class="mt-2 inline-flex min-h-10 items-center rounded-lg bg-zinc-100 px-3 text-sm font-semibold dark:bg-zinc-800">{{ __(ucfirst($product->product_type)) }}</span></div>@endif
                                        </div>
                                        <div class="flex items-center justify-between gap-4 border-t border-zinc-200 py-4 dark:border-zinc-800">
                                            <label class="text-sm font-bold" for="quantity-{{ $product->id }}">{{ __('Quantity') }}</label>
                                            <div class="inline-grid grid-cols-[2.75rem_4rem_2.75rem] overflow-hidden rounded-xl border border-zinc-300 dark:border-zinc-700"><button type="button" x-on:click="quantity = Math.max({{ $product->fractional_quantity ? '0.001' : '1' }}, quantity - {{ $product->fractional_quantity ? '0.001' : '1' }})" class="min-h-11 text-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">−</button><input id="quantity-{{ $product->id }}" name="quantity" x-model="quantity" type="number" min="{{ $product->fractional_quantity ? '0.001' : '1' }}" step="{{ $product->fractional_quantity ? '0.001' : '1' }}" class="w-full border-x border-y-0 border-zinc-300 bg-transparent text-center font-bold dark:border-zinc-700"><button type="button" x-on:click="quantity = Number(quantity) + {{ $product->fractional_quantity ? '0.001' : '1' }}" class="min-h-11 text-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">+</button></div>
                                        </div>
                                        <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800"><button type="button" onclick="this.closest('dialog').close()" class="min-h-11 rounded-lg px-4 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Cancel') }}</button><button type="submit" class="min-h-11 rounded-lg bg-cyan-700 px-5 text-sm font-bold text-white hover:bg-cyan-800">{{ __('Add to cart') }}</button></div>
                                    </form>
                                </dialog>
                            @endif
                        @empty
                            <div class="col-span-full flex min-h-64 flex-col items-center justify-center text-center"><flux:icon name="magnifying-glass" class="size-9 text-zinc-300" /><p class="mt-3 font-semibold">{{ __('No products available.') }}</p><p class="mt-1 text-sm text-zinc-500">{{ __('Try another name, SKU, barcode, or category.') }}</p></div>
                        @endforelse
                    </div>

                    @if ($productQuery !== '' && $otherStoreAvailability->isNotEmpty())
                        <section class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800"><flux:heading size="sm">{{ __('Other store availability') }}</flux:heading><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach ($otherStoreAvailability as $availability) @php $availableQuantity = bcsub((string) $availability->on_hand, (string) $availability->reserved, 3); @endphp <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800"><span>{{ app()->getLocale() === 'ar' ? $availability->store?->name_ar : $availability->store?->name_en }}</span><span class="font-bold tabular-nums">{{ rtrim(rtrim($availableQuantity, '0'), '.') }}</span></div>@endforeach</div></section>
                    @endif
                </div>
            </section>

            <section class="hidden flex min-h-[42rem] min-w-0 flex-col overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:sticky xl:top-3 xl:max-h-[calc(100vh-5rem)]" aria-labelledby="pos-cart-heading">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 p-4 dark:border-zinc-800"><div><flux:heading id="pos-cart-heading" size="lg">{{ __('Cart') }} <span class="text-zinc-400">({{ $cart->count() }})</span></flux:heading><p class="mt-0.5 text-xs text-zinc-500">{{ __('Current order') }}</p></div>@if ($cart->isNotEmpty())<form method="POST" action="{{ route('pos.cart.clear') }}" onsubmit="return window.confirm({{ \Illuminate\Support\Js::from(__('Clear every item from this cart?')) }})">@csrf<button type="submit" class="min-h-10 rounded-lg px-3 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20">{{ __('Clear cart') }}</button></form>@endif</div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                    @forelse ($previewLines as $line)
                        @php
                            $cartLine = $line['cart'];
                            $computed = $preview['lines'][$loop->index] ?? null;
                            $quantityStep = $line['product']->fractional_quantity ? '0.001' : '1';
                            $minimumQuantity = $line['product']->fractional_quantity ? '0.001' : '1';
                            $incrementedQuantity = bcadd($line['quantity'], $quantityStep, 6);
                            $decrementedQuantity = bcsub($line['quantity'], $quantityStep, 6);
                            $canDecreaseQuantity = bccomp($decrementedQuantity, '0', 6) === 1;
                            $quantityDisplay = $line['product']->fractional_quantity ? rtrim(rtrim(number_format((float) $line['quantity'], 3, '.', ''), '0'), '.') : number_format((float) $line['quantity'], 0, '.', '');
                        @endphp
                        <article class="rounded-xl bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/60 dark:ring-zinc-800" data-cart-line="{{ $line['product']->id }}">
                            <div class="flex gap-3"><div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-zinc-200 text-zinc-400 dark:bg-zinc-800"><flux:icon name="cube" class="size-6" /></div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h3 class="line-clamp-2 text-sm font-bold">{{ app()->getLocale() === 'ar' ? $line['product']->name_ar : $line['product']->name_en }}</h3><p class="mt-0.5 text-xs text-zinc-500">{{ $line['product']->item_code }}</p></div><div class="shrink-0 text-end text-sm font-extrabold tabular-nums"><x-money :amount="$computed['net_amount'] ?? '0.00'" :currency="$currency" /></div></div>
                                @if (filled($line['product']->size) || filled($line['product']->colour))<p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">@if(filled($line['product']->size)){{ __('Size') }}: {{ $line['product']->size }}@endif @if(filled($line['product']->size) && filled($line['product']->colour))<span aria-hidden="true">·</span>@endif @if(filled($line['product']->colour)){{ __('Colour') }}: {{ $line['product']->colour }}@endif</p>@endif
                            </div></div>
                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                <div class="inline-grid grid-cols-[2.5rem_3.5rem_2.5rem] overflow-hidden rounded-lg border border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                                    <form method="POST" action="{{ route('pos.cart.quantity') }}">@csrf<input type="hidden" name="product_id" value="{{ $line['product']->id }}"><input type="hidden" name="quantity" value="{{ $decrementedQuantity }}"><button type="submit" class="min-h-10 w-full text-lg hover:bg-zinc-100 disabled:opacity-35 dark:hover:bg-zinc-800" @disabled(! $canDecreaseQuantity) aria-label="{{ __('Decrease quantity') }}">−</button></form>
                                    <span class="flex min-h-10 items-center justify-center border-x border-zinc-300 text-sm font-extrabold tabular-nums dark:border-zinc-700">{{ $quantityDisplay }}</span>
                                    <form method="POST" action="{{ route('pos.cart.quantity') }}">@csrf<input type="hidden" name="product_id" value="{{ $line['product']->id }}"><input type="hidden" name="quantity" value="{{ $incrementedQuantity }}"><button type="submit" class="min-h-10 w-full text-lg hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Increase quantity') }}">+</button></form>
                                </div>
                                <form method="POST" action="{{ route('pos.cart.remove') }}">@csrf<input type="hidden" name="product_id" value="{{ $line['product']->id }}"><button type="submit" class="flex min-h-10 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20"><flux:icon name="trash" class="size-4" />{{ __('Remove') }}</button></form>
                            </div>
                            @if (filled($cartLine['discount_amount'] ?? null) || filled($cartLine['open_price_amount'] ?? null))
                                <div class="mt-3 flex flex-wrap gap-2">@if(filled($cartLine['discount_amount'] ?? null))<span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ __('Discount') }}: {{ $cartLine['discount_amount'] }}</span>@endif @if(filled($cartLine['open_price_amount'] ?? null))<span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">{{ __('Open price') }}: {{ $cartLine['open_price_amount'] }}</span>@endif</div>
                            @endif
                            @if ($line['price']->open_price_allowed || auth()->user()->can('pos_sales.apply_discount'))
                                <details class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-800" data-cart-adjustments><summary class="cursor-pointer text-xs font-bold text-cyan-700 dark:text-cyan-300">{{ __('Adjust price or discount') }}</summary><div class="mt-3 grid gap-3">
                                    @can('pos_sales.apply_discount')<form method="POST" action="{{ route('pos.cart.discount') }}" class="grid gap-2">@csrf<input type="hidden" name="product_id" value="{{ $line['product']->id }}"><input type="hidden" name="expected_revision" value="{{ (int) ($cartLine['discount_revision'] ?? 0) }}"><flux:select name="discount_type" :label="__('Discount source')" required><flux:select.option value="line">{{ __('Manual discount') }}</flux:select.option><flux:select.option value="customer_group">{{ __('Customer / group discount') }}</flux:select.option></flux:select><div class="grid grid-cols-2 gap-2"><flux:input name="discount_amount" type="number" min="0.01" step="0.01" :label="__('Discount amount')" required /><flux:input name="reason" :label="__('Reason')" /></div><flux:button type="submit" size="sm" variant="subtle">{{ __('Apply discount') }}</flux:button></form>@endcan
                                    @can('pos_sales.open_price')@if($line['price']->open_price_allowed)<form method="POST" action="{{ route('pos.cart.open-price') }}" class="grid gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">@csrf<input type="hidden" name="product_id" value="{{ $line['product']->id }}"><input type="hidden" name="expected_revision" value="{{ (int) ($cartLine['pricing_revision'] ?? 0) }}"><div class="grid grid-cols-2 gap-2"><flux:input name="amount" type="number" min="0.0001" step="0.0001" :label="__('Open price amount')" required /><flux:input name="reason" :label="__('Required reason')" required /></div><flux:button type="submit" size="sm" variant="subtle">{{ __('Set open price') }}</flux:button></form>@endif@endcan
                                </div></details>
                            @endif
                        </article>
                    @empty
                        <div class="flex min-h-64 flex-col items-center justify-center px-6 text-center"><div class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800"><flux:icon name="shopping-cart" class="size-7" /></div><p class="mt-4 font-bold">{{ __('Cart is empty') }}</p><p class="mt-1 text-sm text-zinc-500">{{ __('Scan a barcode or add a product to begin.') }}</p></div>
                    @endforelse
                </div>
                @if ($cart->isNotEmpty())<div class="border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950"><div class="flex items-center justify-between"><span class="text-sm font-semibold text-zinc-500">{{ __('Cart total') }}</span><span class="text-xl font-extrabold tabular-nums"><x-money :amount="$preview['total'] ?? '0.00'" :currency="$currency" /></span></div></div>@endif
            </section>

            <aside class="min-w-0 space-y-3 xl:sticky xl:top-3 xl:max-h-[calc(100vh-5rem)] xl:overflow-y-auto" aria-label="{{ __('Customer, payment and order summary') }}">
                @can('customers.view')
                    <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" data-guide="pos-customer-context" aria-labelledby="pos-customer-heading">
                        <div class="flex items-center justify-between gap-3"><flux:heading id="pos-customer-heading" size="lg">{{ __('Customer') }}</flux:heading>@can('customers.create')@if($customerPurposes !== [])<button type="button" onclick="document.getElementById('pos-new-customer').showModal()" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2.5 text-sm font-bold text-cyan-700 hover:bg-cyan-50 dark:text-cyan-300 dark:hover:bg-cyan-950/30"><flux:icon name="user-plus" class="size-4" />{{ __('Add New Customer') }}</button>@endif@endcan</div>
                        @if ($selectedCustomer)
                            <div class="mt-3 rounded-xl bg-cyan-50 p-3 ring-1 ring-cyan-200 dark:bg-cyan-950/25 dark:ring-cyan-900" data-selected-customer><div class="flex items-start justify-between gap-3"><div><div class="font-bold text-cyan-950 dark:text-cyan-100">{{ app()->getLocale() === 'ar' ? $selectedCustomer->name_ar : $selectedCustomer->name_en }}</div><div class="mt-1 text-sm text-cyan-800 dark:text-cyan-200">{{ $selectedCustomer->phone_display }}</div></div><form method="POST" action="{{ route('pos.customer.clear') }}">@csrf<button type="submit" class="min-h-9 rounded-lg px-2 text-xs font-bold text-rose-600 hover:bg-white/70">{{ __('Remove') }}</button></form></div></div>
                        @endif
                        <form method="GET" action="{{ route('pos') }}" class="mt-3" x-data="{ searching: false }" x-on:submit="searching = true"><label for="pos-customer-search" class="sr-only">{{ __('Search customer by name or phone') }}</label><div class="relative"><flux:icon name="magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 z-10 size-4 -translate-y-1/2 text-zinc-400" /><input id="pos-customer-search" name="customer_q" value="{{ $customerQuery }}" autocomplete="off" dir="auto" class="block min-h-11 w-full rounded-xl border-zinc-300 bg-zinc-50 ps-10 pe-20 text-sm focus:border-cyan-600 focus:ring-cyan-600 dark:border-zinc-700 dark:bg-zinc-950" placeholder="{{ __('Search by name or phone') }}"><button type="submit" x-bind:disabled="searching" class="absolute end-1.5 top-1.5 min-h-8 rounded-lg px-2 text-xs font-bold text-cyan-700 hover:bg-cyan-50 dark:text-cyan-300">{{ __('Search') }}</button></div></form>
                        @if ($customerSearchResults->isNotEmpty())<div class="mt-2 divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800" data-customer-search-results>@foreach($customerSearchResults as $candidate)<form method="POST" action="{{ route('pos.customer.select') }}" class="flex items-center justify-between gap-2 p-2.5">@csrf<input type="hidden" name="customer_id" value="{{ $candidate->id }}"><div class="min-w-0"><div class="truncate text-sm font-bold">{{ app()->getLocale() === 'ar' ? $candidate->name_ar : $candidate->name_en }}</div><div class="text-xs text-zinc-500">{{ $candidate->phone_display }}</div></div><button type="submit" class="min-h-9 rounded-lg bg-cyan-50 px-3 text-xs font-bold text-cyan-700 hover:bg-cyan-100 dark:bg-cyan-950/30 dark:text-cyan-300">{{ __('Select') }}</button></form>@endforeach</div>@elseif($customerQuery !== '')<p class="mt-2 text-xs text-zinc-500">{{ __('No customer matched this search.') }}</p>@endif
                    </section>

                    @can('customers.create')@if($customerPurposes !== [])
                        <dialog id="pos-new-customer" class="w-[min(92vw,38rem)] rounded-2xl bg-white p-0 text-start shadow-2xl backdrop:bg-zinc-950/55 dark:bg-zinc-900 dark:text-zinc-100"><form method="POST" action="{{ route('pos.customer.create') }}" class="p-5">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><div class="flex items-start justify-between gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-800"><div><flux:heading size="lg">{{ __('Add New Customer') }}</flux:heading><p class="mt-1 text-sm text-zinc-500">{{ __('Create and select the customer without leaving this sale.') }}</p></div><button type="button" onclick="this.closest('dialog').close()" class="flex size-10 items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('Close') }}"><flux:icon name="x-mark" class="size-5" /></button></div><div class="mt-4 grid gap-3 sm:grid-cols-2"><flux:input name="phone" :label="__('Phone')" :value="old('phone')" :placeholder="__('e.g. 01012345678 or +20 1012345678')" :description="__('Egyptian numbers accept local, +20, 0020, spaces, and Arabic numerals.')" required autocomplete="tel" dir="ltr" /><flux:select name="consent_purpose" :label="__('Consent purpose')" required>@foreach($customerPurposes as $purpose)<flux:select.option value="{{ $purpose }}">{{ __($purpose) }}</flux:select.option>@endforeach</flux:select><flux:input name="name_ar" :label="__('Arabic name')" required dir="rtl" /><flux:input name="name_en" :label="__('English name')" required dir="ltr" /></div><div class="mt-5 flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800"><button type="button" onclick="this.closest('dialog').close()" class="min-h-11 rounded-lg px-4 text-sm font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('Cancel') }}</button><button type="submit" class="min-h-11 rounded-lg bg-cyan-700 px-5 text-sm font-bold text-white hover:bg-cyan-800">{{ __('Create and select') }}</button></div></form></dialog>
                    @endif@endcan
                @endcan

                <livewire:pos.checkout-panel />
                <section class="hidden rounded-2xl bg-white p-4 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" data-guide="pos-summary-actions" aria-labelledby="pos-payment-heading" x-data="{ tender: '{{ $initialTender }}', cashReceived: '{{ old('payments.0.tendered', '') }}', total: {{ json_encode((float) $totalValue) }} }">
                    <div class="flex items-center justify-between gap-3"><flux:heading id="pos-payment-heading" size="lg">{{ __('Payment') }}</flux:heading>@can('pos_sales.apply_tax')<form method="POST" action="{{ route('pos.cart.tax') }}">@csrf<input type="hidden" name="tax_applicable" value="{{ $taxApplicable ? 0 : 1 }}"><button type="submit" class="min-h-9 rounded-lg px-2 text-xs font-bold text-cyan-700 hover:bg-cyan-50 dark:text-cyan-300">{{ $taxApplicable ? __('Remove tax') : __('Add tax') }}</button></form>@endcan</div>

                    @if ($cart->isNotEmpty() && $preview)
                        <fieldset class="mt-3"><legend class="sr-only">{{ __('Payment method') }}</legend><div class="grid grid-cols-2 gap-2">
                            @if($cashMethod)<label class="cursor-pointer"><input type="radio" name="payment_mode_selector" value="cash" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 peer-checked:text-cyan-900 dark:border-zinc-700 dark:peer-checked:bg-cyan-950/30 dark:peer-checked:text-cyan-100"><flux:icon name="banknotes" class="size-4" />{{ __('Cash') }}</span></label>@endif
                            @if($electronicMethods->isNotEmpty())<label class="cursor-pointer"><input type="radio" name="payment_mode_selector" value="electronic" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 peer-checked:text-cyan-900 dark:border-zinc-700 dark:peer-checked:bg-cyan-950/30 dark:peer-checked:text-cyan-100"><flux:icon name="credit-card" class="size-4" />{{ __('Card / electronic') }}</span></label>@endif
                            @if($giftCardMethods->isNotEmpty())<label class="cursor-pointer"><input type="radio" name="payment_mode_selector" value="gift_card" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 peer-checked:text-cyan-900 dark:border-zinc-700 dark:peer-checked:bg-cyan-950/30 dark:peer-checked:text-cyan-100"><flux:icon name="gift" class="size-4" />{{ __('Gift Card') }}</span></label>@endif
                            @if($supportedTenderCount > 1)<label class="cursor-pointer"><input type="radio" name="payment_mode_selector" value="split" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 peer-checked:text-cyan-900 dark:border-zinc-700 dark:peer-checked:bg-cyan-950/30 dark:peer-checked:text-cyan-100"><flux:icon name="ellipsis-horizontal" class="size-4" />{{ __('Split payment') }}</span></label>@endif
                        </div></fieldset>

                        <form method="POST" action="{{ route('pos.checkout') }}" enctype="multipart/form-data" class="mt-4" data-guide="pos-payment-form">@csrf<input type="hidden" name="checkout_token" value="{{ $checkoutToken }}"><input type="hidden" name="tax_applicable" value="{{ $taxApplicable ? 1 : 0 }}"><input type="hidden" name="payment_mode" x-bind:value="tender">
                            @if($cashMethod)<fieldset x-show="tender === 'cash' || tender === 'split'" x-cloak class="grid gap-3"><legend class="text-sm font-bold">{{ __('Cash') }}</legend><input type="hidden" name="payments[0][method_id]" value="{{ $cashMethod->id }}" x-bind:disabled="tender !== 'cash' && tender !== 'split'"><input type="hidden" name="payments[0][amount]" value="0" x-bind:disabled="tender !== 'cash' && tender !== 'split'"><div><div class="flex items-end gap-2"><div class="flex-1"><label for="pos-cash-received" class="mb-1 block text-sm font-semibold">{{ __('Cash received') }}</label><input id="pos-cash-received" name="payments[0][tendered]" x-model="cashReceived" x-bind:disabled="tender !== 'cash' && tender !== 'split'" type="number" min="0" step="0.01" class="block min-h-11 w-full rounded-xl border-zinc-300 bg-zinc-50 text-base font-bold tabular-nums focus:border-cyan-600 focus:ring-cyan-600 dark:border-zinc-700 dark:bg-zinc-950"></div><button type="button" x-on:click="cashReceived = total.toFixed(2)" class="min-h-11 rounded-xl bg-zinc-100 px-3 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">{{ __('Exact amount') }}</button></div><div class="mt-2 flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm dark:bg-emerald-950/25"><span class="font-semibold text-emerald-800 dark:text-emerald-200">{{ __('Change') }}</span><strong class="tabular-nums text-emerald-900 dark:text-emerald-100" x-text="Math.max(0, Number(cashReceived || 0) - total).toFixed(2) + ' {{ $currency }}'">0.00 {{ $currency }}</strong></div></div></fieldset>@endif

                            @if($electronicMethods->isNotEmpty())<fieldset x-show="tender === 'electronic' || tender === 'split'" x-cloak class="grid gap-3 {{ $cashMethod ? 'mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800' : '' }}"><legend class="text-sm font-bold">{{ __('Card / electronic') }}</legend><flux:select name="payments[{{ $electronicIndex }}][method_id]" :label="__('Payment method')" x-bind:disabled="tender !== 'electronic' && tender !== 'split'"><flux:select.option value="">{{ __('Select a method') }}</flux:select.option>@foreach($electronicMethods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) old('payments.'.$electronicIndex.'.method_id') === (string) $method->id">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}{{ $method->requires_evidence ? ' · '.__('evidence required') : '' }}</flux:select.option>@endforeach</flux:select><flux:input name="payments[{{ $electronicIndex }}][amount]" type="number" min="0.01" step="0.01" :value="old('payments.'.$electronicIndex.'.amount')" :label="__('Amount')" x-bind:disabled="tender !== 'electronic' && tender !== 'split'" />@can('pos_sales.payment_evidence_upload')<div x-data="{ fileName: '' }"><label for="payment-evidence-{{ $electronicIndex }}" class="text-sm font-semibold">{{ __('Payment evidence') }}</label><input id="payment-evidence-{{ $electronicIndex }}" name="payments[{{ $electronicIndex }}][evidence]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1 block w-full text-sm" x-on:change="fileName = $event.target.files[0]?.name || ''" x-bind:disabled="tender !== 'electronic' && tender !== 'split'"></div>@endcan<flux:input name="payments[{{ $electronicIndex }}][evidence_reference]" :value="old('payments.'.$electronicIndex.'.evidence_reference')" :label="__('Reference')" x-bind:disabled="tender !== 'electronic' && tender !== 'split'" /></fieldset>@endif

                            @if($giftCardMethods->isNotEmpty())<fieldset x-show="tender === 'gift_card' || tender === 'split'" x-cloak class="grid gap-3 mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800"><legend class="text-sm font-bold">{{ __('Gift Card') }}</legend><flux:select name="payments[{{ $giftCardIndex }}][method_id]" :label="__('Payment method')" x-bind:disabled="tender !== 'gift_card' && tender !== 'split'"><flux:select.option value="">{{ __('Select a Gift Card method') }}</flux:select.option>@foreach($giftCardMethods as $method)<flux:select.option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</flux:select.option>@endforeach</flux:select><flux:input name="payments[{{ $giftCardIndex }}][gift_card_identifier]" :label="__('Gift Card identifier')" x-bind:disabled="tender !== 'gift_card' && tender !== 'split'" /><flux:input name="payments[{{ $giftCardIndex }}][amount]" type="number" min="0.01" step="0.01" :label="__('Gift Card amount')" x-bind:disabled="tender !== 'gift_card' && tender !== 'split'" /></fieldset>@endif

                            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800"><div class="space-y-2 text-sm tabular-nums"><div class="flex justify-between"><span class="text-zinc-500">{{ __('Subtotal') }}</span><x-money :amount="$preview['subtotal'] ?? '0.00'" :currency="$currency" /></div><div class="flex justify-between"><span class="text-zinc-500">{{ __('Discount') }}</span><x-money :amount="$preview['discount_total'] ?? '0.00'" :currency="$currency" /></div><div class="flex justify-between"><span class="text-zinc-500">{{ __('Tax') }}</span><x-money :amount="$preview['tax_total'] ?? '0.00'" :currency="$currency" /></div></div><div class="mt-4 flex items-end justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="text-sm font-extrabold uppercase tracking-wide text-zinc-500">{{ __('Total') }}</span><span class="text-3xl font-black tracking-tight text-cyan-700 dark:text-cyan-300"><x-money :amount="$totalValue" :currency="$currency" /></span></div></div>
                            @if($checkoutBlocker)<p id="pos-checkout-blocker" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">{{ $checkoutBlocker }}</p>@endif
                            <button type="submit" @disabled($checkoutBlocker !== null) class="mt-4 inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-cyan-700 px-4 text-base font-extrabold text-white shadow-lg shadow-cyan-950/15 hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:shadow-none dark:disabled:bg-zinc-700" @if($checkoutBlocker) aria-describedby="pos-checkout-blocker" @endif><flux:icon name="check-circle" class="size-5" />{{ __('Complete sale') }} <span aria-hidden="true">·</span> <x-money :amount="$totalValue" :currency="$currency" /></button>
                        </form>
                    @else
                        <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-center text-sm text-zinc-500 dark:bg-zinc-950">{{ __('Payment appears after an item is added.') }}</div>
                    @endif
                    <form method="POST" action="{{ route('pos.suspend') }}" class="mt-3">@csrf<button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-bold text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-800" @disabled($cart->isEmpty() || ! $shift)><flux:icon name="pause" class="size-4" />{{ __('Hold sale') }}</button></form>
                </section>
            </aside>
        </main>
    </div>
</x-layouts::pos>
