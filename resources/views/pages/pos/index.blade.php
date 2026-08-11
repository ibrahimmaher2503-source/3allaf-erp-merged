<x-layouts::pos :title="__('POS')" :store="$store" :shift="$shift">
    @php
        $currency = $store?->company?->currency_symbol ?: $store?->company?->currency_code;
        $cashAdjustment = $preview && $cashDenomination !== null
            ? app(\App\Modules\Retail\Services\PosCalculationService::class)->cashRoundingAdjustment($preview['total'])
            : null;
        $cashPayable = $cashAdjustment === null || ! $preview ? null : bcadd($preview['total'], $cashAdjustment, 2);
        $electronicIndex = $cashMethod ? 1 : 0;
        $giftCardIndex = $electronicIndex + 1;
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

        @can('customers.view')
            <section class="rounded-xl border border-cyan-200 bg-cyan-50/70 p-4 shadow-xs dark:border-cyan-900 dark:bg-cyan-950/20" data-guide="pos-customer-context" aria-labelledby="pos-customer-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading id="pos-customer-heading" size="lg">{{ __('Customer') }}</flux:heading>
                        <flux:text class="mt-1 text-xs">{{ __('Search by normalized phone or bilingual name. Customer data remains scoped to the assigned branch/store.') }}</flux:text>
                    </div>
                    @if ($selectedCustomer)
                        <div class="flex flex-wrap items-center gap-2" data-selected-customer>
                            <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-cyan-900 dark:bg-zinc-900 dark:text-cyan-200">{{ app()->getLocale() === 'ar' ? $selectedCustomer->name_ar : $selectedCustomer->name_en }} · {{ $selectedCustomer->phone_display }}</span>
                            <form method="POST" action="{{ route('pos.customer.clear') }}">@csrf<flux:button type="submit" size="sm" variant="subtle">{{ __('Clear customer') }}</flux:button></form>
                        </div>
                    @else
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">{{ __('No customer selected') }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('pos') }}" class="mt-4 flex flex-wrap items-end gap-2">
                    <div class="min-w-[14rem] flex-1">
                        <label for="pos-customer-search" class="block text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ __('Search customer') }}</label>
                        <input id="pos-customer-search" name="customer_q" value="{{ $customerQuery }}" autocomplete="off" dir="auto" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-900" placeholder="{{ __('Phone or name') }}">
                    </div>
                    <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button>
                </form>

                @if ($customerSearchResults->isNotEmpty())
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3" data-customer-search-results>
                        @foreach ($customerSearchResults as $candidate)
                            <form method="POST" action="{{ route('pos.customer.select') }}" class="flex items-center justify-between gap-2 rounded-lg border border-cyan-200 bg-white p-3 dark:border-cyan-900 dark:bg-zinc-900">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $candidate->id }}">
                                <div class="min-w-0"><div class="truncate text-sm font-semibold">{{ app()->getLocale() === 'ar' ? $candidate->name_ar : $candidate->name_en }}</div><div class="text-xs text-zinc-500">{{ $candidate->phone_display }}</div></div>
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Select') }}</flux:button>
                            </form>
                        @endforeach
                    </div>
                @elseif ($customerQuery !== '')
                    <div class="mt-3 rounded-lg border border-dashed border-cyan-300 bg-white p-3 text-sm text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">{{ __('No customer matched this search.') }}</div>
                @endif

                @can('customers.create')
                    @if ($customerPurposes !== [])
                        <details class="mt-4 rounded-lg border border-cyan-200 bg-white p-3 dark:border-cyan-900 dark:bg-zinc-900">
                            <summary class="cursor-pointer text-sm font-semibold text-cyan-900 dark:text-cyan-200">{{ __('Register a new customer') }}</summary>
                            <form method="POST" action="{{ route('pos.customer.create') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <flux:input name="phone" :label="__('Phone')" required autocomplete="tel" />
                                <flux:select name="consent_purpose" :label="__('Consent purpose')" required>
                                    @foreach ($customerPurposes as $purpose)<flux:select.option value="{{ $purpose }}">{{ $purpose }}</flux:select.option>@endforeach
                                </flux:select>
                                <flux:input name="name_ar" :label="__('Arabic name')" required dir="rtl" />
                                <flux:input name="name_en" :label="__('English name')" required dir="ltr" />
                                <div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Register and select') }}</flux:button></div>
                            </form>
                        </details>
                    @elseif ($customerPolicyError)
                        <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('New customer registration is blocked until the consent-purpose policy is configured.') }} · {{ $customerPolicyError }}</flux:callout>
                    @endif
                @endcan
            </section>
        @endcan

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
                    <form method="GET" action="{{ route('pos') }}" class="mt-4 flex flex-wrap items-end gap-2" data-pos-product-search>
                        <div class="min-w-[15rem] flex-1">
                            <label for="pos-product-search" class="block text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ __('Search or scan product') }}</label>
                            <input id="pos-product-search" name="product_q" value="{{ $productQuery }}" autocomplete="off" inputmode="search" dir="auto" autofocus class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-900" placeholder="{{ __('Barcode, item code, or name') }}">
                        </div>
                        <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button>
                        @if ($productQuery !== '')
                            <flux:button href="{{ route('pos') }}" variant="subtle">{{ __('Clear search') }}</flux:button>
                        @endif
                    </form>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($availableProducts as $product)
                            @php($price = $priceMap->get($product->id))
                            <div class="flex flex-col justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div>
                                    <div class="font-semibold">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ $product->item_code }}</div>
                                    @if ($product->barcodes->isNotEmpty())
                                        <div class="mt-1 text-xs text-zinc-500">{{ __('Barcode') }}: {{ $product->barcodes->first()->barcode }}</div>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    @if ($price)
                                        <span class="font-bold text-emerald-700 dark:text-emerald-300"><x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round((string) $price->amount)" :currency="$currency" /></span>
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
                    @if ($productQuery !== '' && $otherStoreAvailability->isNotEmpty())
                        <section class="mt-4 rounded-lg border border-cyan-200 bg-cyan-50/60 p-3 dark:border-cyan-900 dark:bg-cyan-950/20" aria-labelledby="other-store-availability-heading">
                            <flux:heading id="other-store-availability-heading" size="sm">{{ __('Other store availability') }}</flux:heading>
                            <flux:text class="mt-1 text-xs">{{ __('Read-only availability in other visible selling stores. It does not add the item to this sale.') }}</flux:text>
                            <div class="mt-3 space-y-2">
                                @foreach ($otherStoreAvailability as $availability)
                                    @php($availableQuantity = bcsub((string) $availability->on_hand, (string) $availability->reserved, 6))
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-cyan-100 bg-white px-3 py-2 text-sm dark:border-cyan-950 dark:bg-zinc-900">
                                        <span class="font-medium">{{ app()->getLocale() === 'ar' ? $availability->store?->name_ar : $availability->store?->name_en }} · {{ $availability->store?->branch?->code }}</span>
                                        <span class="text-zinc-600 dark:text-zinc-300">{{ __('Available') }}: {{ $availableQuantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @elseif ($productQuery !== '' && $availableProducts->isEmpty())
                        <div class="mt-4 rounded-lg border border-dashed border-zinc-300 p-3 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ __('No product matched in this store. If you have visibility, other store availability appears here.') }}</div>
                    @endif
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
                                        <div class="font-semibold"><x-money :amount="$computed['net_amount'] ?? '0.00'" :currency="$currency" /></div>
                                        <div class="text-xs text-zinc-500">{{ __('Unit') }} <x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round($line['unit_price'])" :currency="$currency" :muted="true" /></div>
                                    </div>
                                </div>

                                @can('pos_sales.create')
                                    <form method="POST" action="{{ route('pos.cart.quantity') }}" class="mt-3 flex flex-wrap items-end gap-2">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                                        <div class="min-w-[8rem] flex-1">
                                            <label for="cart-quantity-{{ $line['product']->id }}" class="block text-xs font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Quantity') }}</label>
                                            <input id="cart-quantity-{{ $line['product']->id }}" name="quantity" type="number" min="0.000001" max="999999" step="{{ $line['product']->fractional_quantity ? '0.000001' : '1' }}" value="{{ $line['quantity'] }}" required class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-zinc-700 dark:bg-zinc-900">
                                        </div>
                                        <flux:button type="submit" size="sm" variant="subtle">{{ __('Update quantity') }}</flux:button>
                                    </form>
                                @endcan

                                @if (filled($cartLine['discount_amount'] ?? null))
                                    @php($discountApprovalState = $cartLine['discount_approval_state'] ?? null)
                                    @if ($discountApprovalState === 'pending')
                                        <flux:callout class="mt-3" variant="warning" icon="clock">{{ __('Discount') }}: {{ $cartLine['discount_amount'] }} ({{ $cartLine['discount_type'] }})<div class="mt-1 text-xs font-medium">{{ __('Independent manager approval is pending. Checkout remains blocked until the decision is recorded.') }}</div></flux:callout>
                                    @elseif ($discountApprovalState === 'approved')
                                        <flux:callout class="mt-3" variant="success" icon="check-circle">{{ __('Approved discount') }}: {{ $cartLine['discount_amount'] }} ({{ $cartLine['discount_type'] }})<div class="mt-1 text-xs font-medium">{{ __('Manager approval is recorded and will be revalidated at checkout.') }}</div></flux:callout>
                                    @elseif ($discountApprovalState === 'rejected')
                                        <flux:callout class="mt-3" variant="danger" icon="x-circle">{{ __('Rejected discount') }}: {{ $cartLine['discount_amount'] }} ({{ $cartLine['discount_type'] }})<div class="mt-1 text-xs font-medium">{{ __('Request a new decision before checkout.') }}</div></flux:callout>
                                    @else
                                        <flux:callout class="mt-3" variant="success" icon="tag">{{ __('Discount') }}: {{ $cartLine['discount_amount'] }} ({{ $cartLine['discount_type'] }})</flux:callout>
                                    @endif
                                @endif
                                @if (filled($cartLine['open_price_amount'] ?? null))
                                    @php($approvalState = $cartLine['open_price_approval_state'] ?? null)
                                    @if ($approvalState === 'pending')
                                        <flux:callout class="mt-3" variant="warning" icon="clock">{{ __('Open price') }}: {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $cartLine['open_price_amount']) }} · {{ $cartLine['open_price_reason'] }}<div class="mt-1 text-xs font-medium">{{ __('Independent manager approval is pending. Checkout remains blocked until the decision is recorded.') }}</div></flux:callout>
                                    @elseif ($approvalState === 'approved')
                                        <flux:callout class="mt-3" variant="success" icon="check-circle">{{ __('Approved open price') }}: {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $cartLine['open_price_amount']) }} · {{ $cartLine['open_price_reason'] }}<div class="mt-1 text-xs font-medium">{{ __('Manager approval is recorded and will be revalidated at checkout.') }}</div></flux:callout>
                                    @elseif ($approvalState === 'rejected')
                                        <flux:callout class="mt-3" variant="danger" icon="x-circle">{{ __('Rejected open price') }}: {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $cartLine['open_price_amount']) }} · {{ $cartLine['open_price_reason'] }}<div class="mt-1 text-xs font-medium">{{ __('Request a new decision before checkout.') }}</div></flux:callout>
                                    @else
                                        <flux:callout class="mt-3" variant="warning" icon="pencil-square">{{ __('Open price') }}: {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $cartLine['open_price_amount']) }} · {{ $cartLine['open_price_reason'] }}</flux:callout>
                                    @endif
                                @endif

                                <div class="mt-3 grid gap-3 xl:grid-cols-2">
                                    @can('pos_sales.apply_discount')
                                        <form method="POST" action="{{ route('pos.cart.discount') }}" class="grid gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60" @if (filled($cartLine['discount_type'] ?? null)) onsubmit="return window.confirm({{ \Illuminate\Support\Js::from(__('Replace the existing discount? The previous discount will be removed.')) }})" @endif>
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                                            <input type="hidden" name="expected_revision" value="{{ (int) ($cartLine['discount_revision'] ?? 0) }}">
                                            <flux:select name="discount_type" :label="__('Discount source')" required>
                                                <flux:select.option value="line">{{ __('Manual discount') }}</flux:select.option>
                                                <flux:select.option value="customer_group">{{ __('Customer / group discount') }}</flux:select.option>
                                            </flux:select>
                                            <flux:input name="discount_amount" type="number" min="0.01" step="0.01" :label="__('Discount amount')" required />
                                            @if ($discountApprovalLimit !== null)
                                                <div class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ __('Independent manager approval is required above :percent% of the line amount.', ['percent' => $discountApprovalLimit]) }}</div>
                                            @endif
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
                                                <div class="text-xs text-zinc-500">{{ __('Reference') }} <x-money :amount="$line['price']->reference_amount ?? $line['price']->amount" :currency="$currency" :muted="true" /> · {{ __('Range') }} {{ $line['price']->open_price_minimum ?? __('Not configured') }}–{{ $line['price']->open_price_maximum ?? __('Not configured') }}</div>
                                                @if ($openPriceApprovalLimit !== null)
                                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ __('Manager approval is required when the requested price differs from the reference by more than :percent%.', ['percent' => $openPriceApprovalLimit]) }}</div>
                                                @endif
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
                        <div class="flex justify-between gap-4"><span class="text-zinc-500">{{ __('Gross subtotal') }}</span><x-money :amount="$preview['subtotal'] ?? '0.00'" :currency="$currency" /></div>
                        <div class="flex justify-between gap-4"><span class="text-zinc-500">{{ __('Discount total') }}</span><x-money :amount="$preview['discount_total'] ?? '0.00'" :currency="$currency" /></div>
                        <div class="flex justify-between gap-4"><span class="text-zinc-500">{{ __('Post-discount net') }}</span><x-money :amount="$preview['taxable_base'] ?? '0.00'" :currency="$currency" /></div>
                        <div class="flex justify-between gap-4"><span class="text-zinc-500">{{ __('Tax') }}</span><x-money :amount="$preview['tax_total'] ?? '0.00'" :currency="$currency" /></div>
                        <div class="flex justify-between gap-4 border-t border-zinc-100 pt-3 text-base font-bold dark:border-zinc-800"><span>{{ __('Final total') }}</span><x-money :amount="$preview['total'] ?? '0.00'" :currency="$currency" /></div>
                        @if ($cashAdjustment !== null)
                            <div class="flex justify-between gap-4"><span class="text-zinc-500">{{ __('Cash rounding') }}</span><x-money :amount="$cashAdjustment" :currency="$currency" /></div>
                            <div class="flex justify-between gap-4 font-semibold"><span>{{ __('Cash payable') }}</span><x-money :amount="$cashPayable" :currency="$currency" /></div>
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
                                    <div x-data="{ fileName: '' }" class="grid gap-2">
                                        <label for="payment-evidence-{{ $electronicIndex }}" class="text-sm font-medium">{{ __('Protected payment evidence') }}</label>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input id="payment-evidence-{{ $electronicIndex }}" name="payments[{{ $electronicIndex }}][evidence]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" x-on:change="fileName = $event.target.files[0]?.name || ''" />
                                            <label for="payment-evidence-{{ $electronicIndex }}" class="inline-flex min-h-9 cursor-pointer items-center rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-text-primary hover:bg-surface-muted focus-within:ring-2 focus-within:ring-primary">{{ __('Choose file') }}</label>
                                            <span class="text-xs text-text-muted" x-text="fileName || '{{ __('No file selected') }}'">{{ __('No file selected') }}</span>
                                        </div>
                                    </div>
                                @endcan
                                <flux:input name="payments[{{ $electronicIndex }}][evidence_reference]" :label="__('Terminal reference (optional)')" />
                            </fieldset>
                        @endif

                        @if ($giftCardMethods->isNotEmpty())
                            <fieldset class="grid gap-3 rounded-lg border border-violet-200 bg-violet-50/40 p-3 dark:border-violet-900 dark:bg-violet-950/20">
                                <legend class="px-1 text-sm font-semibold">{{ __('Gift Card tender') }}</legend>
                                <flux:select name="payments[{{ $giftCardIndex }}][method_id]" :label="__('Payment method')">
                                    <flux:select.option value="">{{ __('No Gift Card') }}</flux:select.option>
                                    @foreach ($giftCardMethods as $method)
                                        <flux:select.option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input name="payments[{{ $giftCardIndex }}][gift_card_identifier]" :label="__('Gift Card identifier')" :description="__('Enter the printed card identifier.')" />
                                <flux:input name="payments[{{ $giftCardIndex }}][amount]" type="number" min="0.01" step="0.01" :label="__('Gift Card amount')" :description="__('Only the entered amount is redeemed; any remaining balance stays on the card.')" />
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
