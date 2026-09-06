@php
    $currency = $store?->company?->currency_code ?? $store?->branch?->currency ?? 'EGP';
    $checkoutBlocker = $cart->isEmpty()
        ? __('Add an item to begin checkout.')
        : (! $context->isReady()
            ? $context->disabledReason
            : (! $preview ? (($error ?? null) ?: __('Review the cart before checkout.')) : null));
    $selectedCustomer = $order?->customer;
    $cashDraft = collect($paymentLines)->first(fn ($line) => $cashMethod && (int) ($line['method_id'] ?? 0) === (int) $cashMethod->id);
    $quickCash = config('pos.cash_quick_denominations', [20, 50, 100, 200, 500, 1000]);
@endphp

<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" data-guide="pos-summary-actions" aria-labelledby="pos-payment-heading">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading id="pos-payment-heading" size="lg">{{ __('Payment') }}</flux:heading>
            <p class="mt-1 text-xs text-zinc-500">{{ __('The draft is saved to the active order when a field is completed.') }}</p>
        </div>
        @can('pos_sales.apply_tax')
            <form method="POST" action="{{ route('pos.cart.tax') }}">
                @csrf
                <input type="hidden" name="tax_applicable" value="{{ ($taxApplicable ?? false) ? 0 : 1 }}">
                <button type="submit" class="min-h-10 rounded-lg px-3 text-xs font-bold text-cyan-700 hover:bg-cyan-50 dark:text-cyan-300 dark:hover:bg-cyan-950/30">
                    {{ ($taxApplicable ?? false) ? __('Remove tax') : __('Add tax') }}
                </button>
            </form>
        @endcan
    </div>

    @error('paymentLines')<p class="mt-3 rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-800 dark:bg-rose-950/30 dark:text-rose-200">{{ $message }}</p>@enderror

    <dl class="mt-4 space-y-2 text-sm tabular-nums">
        <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Subtotal') }}</dt><dd><x-money :amount="$preview['subtotal'] ?? '0.00'" :currency="$currency" /></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Discounts') }}</dt><dd class="text-emerald-700 dark:text-emerald-300">− <x-money :amount="$preview['discount_total'] ?? '0.00'" :currency="$currency" /></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Tax') }}</dt><dd><x-money :amount="$preview['tax_total'] ?? '0.00'" :currency="$currency" /></dd></div>
        @if (bccomp((string) $cashAdjustment, '0', 2) !== 0)
            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Cash rounding') }}</dt><dd><x-money :amount="$cashAdjustment" :currency="$currency" /></dd></div>
        @endif
        <div class="flex items-end justify-between gap-4 border-t border-zinc-200 pt-3 dark:border-zinc-700"><dt class="font-extrabold">{{ __('Final amount') }}</dt><dd class="text-3xl font-black text-cyan-700 dark:text-cyan-300"><x-money :amount="$preview['total'] ?? '0.00'" :currency="$currency" /></dd></div>
    </dl>

    @if ($cashRoundingError)
        <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">{{ $cashRoundingError }}</p>
    @endif
    @if ($checkoutBlocker)
        <p id="pos-checkout-blocker" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">{{ $checkoutBlocker }}</p>
    @endif

    <div class="mt-4 grid gap-2 sm:grid-cols-2">
        @if ($cashMethod)
            <button type="button" wire:click="startCheckout('cash')" wire:loading.attr="disabled" @disabled($checkoutBlocker || $cashRoundingError) class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-cyan-700 px-4 text-sm font-extrabold text-white hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-zinc-300 dark:disabled:bg-zinc-700">
                <flux:icon name="banknotes" class="size-5" />{{ __('Cash checkout') }}
            </button>
        @endif
        @if ($electronicMethods->isNotEmpty())
            <button type="button" wire:click="startCheckout('card')" wire:loading.attr="disabled" @disabled($checkoutBlocker) class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 text-sm font-extrabold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:bg-zinc-300 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white dark:disabled:bg-zinc-700">
                <flux:icon name="credit-card" class="size-5" />{{ __('Card / electronic checkout') }}
            </button>
        @endif
        @if ($methods->count() > 1)
            <button type="button" wire:click="startCheckout('split')" wire:loading.attr="disabled" @disabled($checkoutBlocker) class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 text-sm font-bold hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800 sm:col-span-2">
                <flux:icon name="ellipsis-horizontal" class="size-5" />{{ __('Mixed split payment') }}
            </button>
        @endif
    </div>

    @can('pos_sales.create')<form method="POST" action="{{ route('pos.suspend') }}" class="mt-2" onsubmit="return window.confirm({{ \Illuminate\Support\Js::from(__('Hold this order without collecting payment?')) }})">@csrf<button type="submit" @disabled($checkoutBlocker) class="min-h-11 w-full rounded-xl border border-zinc-300 px-4 text-sm font-bold hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800">{{ __('Hold order') }}</button></form>@endcan
    <dialog
        class="m-auto max-h-[94vh] w-[min(96vw,48rem)] overflow-y-auto rounded-2xl bg-white p-0 text-start shadow-2xl backdrop:bg-zinc-950/70 dark:bg-zinc-900 dark:text-zinc-100"
        x-data="{
            payable: Number(@js($payable)),
            cashReceived: Number(@js($cashDraft['tendered'] ?? '0')),
            submitting: false,
            nonCashAllocated() {
                return [...this.$el.querySelectorAll('[data-noncash-amount]')].reduce((total, input) => total + (Number(input.value) || 0), 0);
            },
            cashDue() { return Math.max(0, this.payable - this.nonCashAllocated()); },
            changeDue() { return Math.max(0, this.cashReceived - this.cashDue()); },
        }"
        x-on:open-pos-checkout.window="$el.showModal()"
        x-on:close="submitting = false"
        aria-labelledby="pos-checkout-dialog-title"
    >
        <form method="POST" action="{{ route('pos.checkout') }}" enctype="multipart/form-data" x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $order?->checkout_token }}">
            <input type="hidden" name="open_order_uuid" value="{{ $order?->uuid }}">
            <input type="hidden" name="tax_applicable" value="{{ ($taxApplicable ?? false) ? 1 : 0 }}">
            <input type="hidden" name="payment_mode" value="{{ $mode }}">

            <header class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">{{ __('Secure checkout') }}</p>
                    <flux:heading id="pos-checkout-dialog-title" size="xl" class="mt-1">
                        {{ $mode === 'cash' ? __('Cash payment') : ($mode === 'card' ? __('Card / electronic payment') : __('Mixed split payment')) }}
                    </flux:heading>
                </div>
                <button type="button" x-on:click="$el.closest('dialog').close()" class="flex size-10 items-center justify-center rounded-lg hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-cyan-600 dark:hover:bg-zinc-800" aria-label="{{ __('Cancel checkout') }}"><flux:icon name="x-mark" class="size-5" /></button>
            </header>

            <div class="space-y-5 p-5">
                <section class="grid gap-2 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-950 sm:grid-cols-2" aria-label="{{ __('Order context') }}">
                    <div><span class="text-zinc-500">{{ __('Customer') }}</span><div class="mt-1 font-bold">{{ $selectedCustomer ? (app()->getLocale() === 'ar' ? $selectedCustomer->name_ar : $selectedCustomer->name_en) : __('Walk-in customer') }}</div></div>
                    <div><span class="text-zinc-500">{{ __('Selling outlet') }}</span><div class="mt-1 font-bold">{{ app()->getLocale() === 'ar' ? $store?->name_ar : $store?->name_en }}</div></div>
                    <div><span class="text-zinc-500">{{ __('Branch') }}</span><div class="mt-1 font-bold">{{ app()->getLocale() === 'ar' ? $context->branch?->name_ar : $context->branch?->name_en }}</div></div>
                    @if ($mode !== 'card')<div><span class="text-zinc-500">{{ __('Shift') }}</span><div class="mt-1 font-bold" dir="ltr">#{{ $shift?->id }}</div></div>@endif
                </section>

                <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-label="{{ __('Amount summary') }}">
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Subtotal') }}</dt><dd><x-money :amount="$preview['subtotal'] ?? '0.00'" :currency="$currency" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Discounts') }}</dt><dd><x-money :amount="$preview['discount_total'] ?? '0.00'" :currency="$currency" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Tax') }}</dt><dd><x-money :amount="$preview['tax_total'] ?? '0.00'" :currency="$currency" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Cash rounding') }}</dt><dd><x-money :amount="$cashAdjustment" :currency="$currency" /></dd></div>
                    </dl>
                    <div class="mt-3 flex items-end justify-between border-t border-zinc-200 pt-3 dark:border-zinc-700"><span class="font-extrabold">{{ __('Final payable amount') }}</span><span class="text-2xl font-black text-cyan-700 dark:text-cyan-300"><x-money :amount="$payable" :currency="$currency" /></span></div>
                </section>

                <section class="space-y-3" aria-labelledby="payment-portions-heading">
                    <div class="flex items-center justify-between gap-3"><h3 id="payment-portions-heading" class="font-extrabold">{{ count($paymentLines) > 1 ? __('Payment portions') : __('Single payment') }}</h3>@if(in_array($mode, ['card', 'split'], true))<button type="button" wire:click="addPaymentLine" class="min-h-10 rounded-lg px-3 text-sm font-bold text-cyan-700 hover:bg-cyan-50 dark:text-cyan-300 dark:hover:bg-cyan-950/30">{{ $mode === 'card' ? __('Add card portion') : __('Add payment portion') }}</button>@endif</div>

                    @foreach ($paymentLines as $index => $line)
                        @php
                            $selectedMethod = $methods->firstWhere('id', (int) ($line['method_id'] ?? 0));
                            $isCashLine = $selectedMethod?->isCash() ?? false;
                            $isGiftCardLine = (string) ($selectedMethod?->type ?? '') === 'gift_card';
                        @endphp
                        <fieldset class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <legend class="px-2 text-sm font-bold">{{ __('Payment portion :number', ['number' => $index + 1]) }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @if ($mode === 'cash' && $cashMethod)
                                    <input type="hidden" name="payments[{{ $index }}][method_id]" value="{{ $cashMethod->id }}">
                                    <div class="sm:col-span-2"><span class="text-sm text-zinc-500">{{ __('Payment method') }}</span><div class="mt-1 font-bold">{{ app()->getLocale() === 'ar' ? $cashMethod->name_ar : $cashMethod->name_en }}</div></div>
                                @else
                                    <flux:select name="payments[{{ $index }}][method_id]" wire:model.blur="paymentLines.{{ $index }}.method_id" :label="__('Payment method')" required>
                                        <flux:select.option value="">{{ __('Select a method') }}</flux:select.option>
                                        @foreach ($methods as $method)
                                            @if (! ($mode === 'card' && $method->isCash()))
                                                <flux:select.option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</flux:select.option>
                                            @endif
                                        @endforeach
                                    </flux:select>
                                @endif

                                @if ($isCashLine || ($mode === 'cash' && $cashMethod))
                                    <input type="hidden" name="payments[{{ $index }}][amount]" value="0">
                                    <div class="sm:col-span-2">
                                        <label for="cash-received-{{ $index }}" class="text-sm font-semibold">{{ __('Cash received') }}</label>
                                        <input id="cash-received-{{ $index }}" name="payments[{{ $index }}][tendered]" wire:model.blur="paymentLines.{{ $index }}.tendered" x-model.number="cashReceived" type="number" min="0" step="0.01" inputmode="decimal" autofocus required class="mt-1 block min-h-12 w-full rounded-xl border-zinc-300 bg-white text-lg font-extrabold focus:border-cyan-600 focus:ring-cyan-600 dark:border-zinc-700 dark:bg-zinc-950">
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button type="button" x-on:click="cashReceived = cashDue(); $nextTick(() => document.getElementById('cash-received-{{ $index }}').dispatchEvent(new Event('change', { bubbles: true })))" class="min-h-10 rounded-lg bg-cyan-50 px-3 text-sm font-bold text-cyan-800 dark:bg-cyan-950/30 dark:text-cyan-200">{{ __('Exact amount') }}</button>
                                            @foreach ($quickCash as $denomination)<button type="button" x-on:click="cashReceived = {{ (int) $denomination }}; $nextTick(() => document.getElementById('cash-received-{{ $index }}').dispatchEvent(new Event('change', { bubbles: true })))" class="min-h-10 rounded-lg border border-zinc-300 px-3 text-sm font-bold tabular-nums hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">{{ $denomination }}</button>@endforeach
                                        </div>
                                    </div>
                                @else
                                    <flux:input name="payments[{{ $index }}][amount]" wire:model.blur="paymentLines.{{ $index }}.amount" type="number" min="0.01" step="0.01" inputmode="decimal" :label="__('Allocated amount')" data-noncash-amount :autofocus="$index === 0" required />
                                    <flux:input name="payments[{{ $index }}][evidence_reference]" wire:model.blur="paymentLines.{{ $index }}.evidence_reference" :label="__('Safe transaction reference (optional)')" autocomplete="off" />
                                    @if ($isGiftCardLine)<flux:input name="payments[{{ $index }}][gift_card_identifier]" wire:model.blur="paymentLines.{{ $index }}.gift_card_identifier" :label="__('Gift Card identifier')" required />@endif
                                    @can('pos_sales.payment_evidence_upload')
                                        <div class="sm:col-span-2"><label for="payment-evidence-{{ $index }}" class="text-sm font-semibold">{{ __('Payment evidence (if required)') }}</label><input id="payment-evidence-{{ $index }}" name="payments[{{ $index }}][evidence]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1 block w-full text-sm"></div>
                                    @endcan
                                @endif
                            </div>
                            @if ($mode === 'split' && count($paymentLines) > 1)<button type="button" wire:click="removePaymentLine({{ $index }})" class="mt-3 min-h-9 rounded-lg px-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30">{{ __('Remove portion') }}</button>@endif
                        </fieldset>
                    @endforeach
                </section>

                @if ($mode !== 'card' && collect($paymentLines)->contains(fn ($line) => $cashMethod && (int) ($line['method_id'] ?? 0) === (int) $cashMethod->id))
                    <section class="rounded-xl bg-emerald-50 p-4 text-emerald-950 dark:bg-emerald-950/30 dark:text-emerald-100" aria-live="polite"><div class="flex items-end justify-between"><span class="font-bold">{{ __('Change due') }}</span><span class="text-3xl font-black" x-text="changeDue().toFixed(2)"></span></div><p class="mt-1 text-xs">{{ __('Change is calculated from the cash portion only.') }}</p></section>
                @endif

                <flux:textarea name="notes" wire:model.blur="notes" :label="__('Order notes (optional)')" rows="2" maxlength="1000" />
                <p class="text-xs text-zinc-500">{{ __('Never enter a card number, security code, magnetic-stripe data, or PIN.') }}</p>
            </div>

            <footer class="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-12 rounded-xl px-5 text-sm font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('Cancel') }}</button>
                <button type="submit" x-bind:disabled="submitting" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-cyan-700 px-6 text-base font-extrabold text-white hover:bg-cyan-800 disabled:cursor-wait disabled:opacity-60"><flux:icon name="check-circle" class="size-5" /><span x-show="!submitting">{{ __('Complete sale') }}</span><span x-show="submitting" x-cloak>{{ __('Completing sale…') }}</span></button>
            </footer>
        </form>
    </dialog>
</section>
