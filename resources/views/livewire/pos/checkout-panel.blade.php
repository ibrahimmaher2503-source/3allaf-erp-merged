<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" aria-labelledby="live-pos-payment-heading"
    x-data="{ tender: '{{ $cashMethod ? 'cash' : ($electronicMethods->isNotEmpty() ? 'electronic' : 'gift') }}', cashReceived: '', total: {{ json_encode((float) $total) }}, useElectronic: true, useGift: false }">
    <div class="flex items-center justify-between gap-3">
        <flux:heading id="live-pos-payment-heading" size="lg">{{ __('Payment') }}</flux:heading>
        @can('pos_sales.apply_tax')<form method="POST" action="{{ route('pos.cart.tax') }}">@csrf<input type="hidden" name="tax_applicable" value="{{ ($taxApplicable ?? false) ? 0 : 1 }}"><button type="submit" class="min-h-9 rounded-lg px-2 text-xs font-bold text-cyan-700">{{ ($taxApplicable ?? false) ? __('Remove tax') : __('Add tax') }}</button></form>@endcan
    </div>
    @php($blocker = $cart->isEmpty() ? __('Add an item to begin checkout.') : (!$store ? __('No selling store is assigned.') : (!$shift ? __('Open a cashier shift before checkout.') : (($error ?? null) ?: (!$preview ? __('Review the cart before checkout.') : null)))))
    @if($preview)
        <fieldset class="mt-3">
            <legend class="sr-only">{{ __('Payment method') }}</legend>
            <div class="grid grid-cols-2 gap-2">
                @if($cashMethod)<label class="cursor-pointer"><input type="radio" value="cash" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 dark:peer-checked:bg-cyan-950/30"><flux:icon name="banknotes" class="size-4" />{{ __('Cash') }}</span></label>@endif
                @if($electronicMethods->isNotEmpty())<label class="cursor-pointer"><input type="radio" value="electronic" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 dark:peer-checked:bg-cyan-950/30"><flux:icon name="credit-card" class="size-4" />{{ __('Card / electronic') }}</span></label>@endif
                @if($giftCardMethods->isNotEmpty())<label class="cursor-pointer"><input type="radio" value="gift" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 dark:peer-checked:bg-cyan-950/30"><flux:icon name="gift" class="size-4" />{{ __('Gift Card') }}</span></label>@endif
                <label class="cursor-pointer"><input type="radio" value="split" x-model="tender" class="peer sr-only"><span class="flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm font-bold peer-checked:border-cyan-600 peer-checked:bg-cyan-50 dark:peer-checked:bg-cyan-950/30"><flux:icon name="ellipsis-horizontal" class="size-4" />{{ __('Split payment') }}</span></label>
            </div>
        </fieldset>
        <form method="POST" action="{{ route('pos.checkout') }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $token }}"><input type="hidden" name="tax_applicable" value="{{ ($taxApplicable ?? false) ? 1 : 0 }}">
            @if($cashMethod)
                <fieldset x-show="tender === 'cash' || tender === 'split'" class="grid gap-3">
                    <legend class="text-sm font-bold">{{ __('Cash') }}</legend>
                    <input type="hidden" name="payments[0][method_id]" value="{{ $cashMethod->id }}" x-bind:disabled="tender !== 'cash' && tender !== 'split'">
                    <input type="hidden" name="payments[0][amount]" value="0" x-bind:disabled="tender !== 'cash' && tender !== 'split'">
                    <flux:input name="payments[0][tendered]" x-model="cashReceived" type="number" min="0" step="0.01" :label="__('Cash received')" x-bind:disabled="tender !== 'cash' && tender !== 'split'" />
                    <div class="flex justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm dark:bg-emerald-950/25"><span>{{ __('Change') }}</span><strong x-text="Math.max(0, Number(cashReceived || 0) - total).toFixed(2)"></strong></div>
                </fieldset>
            @endif
            @if($electronicMethods->isNotEmpty())
                <fieldset x-show="tender === 'electronic' || tender === 'split'" class="mt-4 grid gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <legend class="text-sm font-bold">{{ __('Card / electronic') }}</legend>
                    <label x-show="tender === 'split'" class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="useElectronic"> {{ __('Include electronic payment') }}</label>
                    <flux:select name="payments[1][method_id]" :label="__('Payment method')" x-bind:disabled="tender !== 'electronic' && !(tender === 'split' && useElectronic)">
                        @foreach($electronicMethods as $method)<option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}@if($method->requires_evidence) · {{ __('Evidence required') }}@endif</option>@endforeach
                    </flux:select>
                    <flux:input name="payments[1][amount]" type="number" min="0.01" step="0.01" :label="__('Amount')" x-bind:disabled="tender !== 'electronic' && !(tender === 'split' && useElectronic)" />
                    @can('pos_sales.payment_evidence_upload')<flux:input name="payments[1][evidence]" type="file" :label="__('Payment evidence')" x-bind:disabled="tender !== 'electronic' && !(tender === 'split' && useElectronic)" />@endcan
                    <flux:input name="payments[1][evidence_reference]" :label="__('Evidence reference')" x-bind:disabled="tender !== 'electronic' && !(tender === 'split' && useElectronic)" />
                </fieldset>
            @endif
            @if($giftCardMethods->isNotEmpty())
                <fieldset x-show="tender === 'gift' || tender === 'split'" class="mt-4 grid gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <legend class="text-sm font-bold">{{ __('Gift Card') }}</legend>
                    <label x-show="tender === 'split'" class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="useGift"> {{ __('Include Gift Card payment') }}</label>
                    <flux:select name="payments[2][method_id]" :label="__('Payment method')" x-bind:disabled="tender !== 'gift' && !(tender === 'split' && useGift)">@foreach($giftCardMethods as $method)<option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</option>@endforeach</flux:select>
                    <flux:input name="payments[2][gift_card_identifier]" :label="__('Gift Card identifier')" x-bind:disabled="tender !== 'gift' && !(tender === 'split' && useGift)" />
                    <flux:input name="payments[2][amount]" type="number" min="0.01" step="0.01" :label="__('Gift Card amount')" x-bind:disabled="tender !== 'gift' && !(tender === 'split' && useGift)" />
                </fieldset>
            @endif
            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800"><div class="space-y-2 text-sm"><div class="flex justify-between"><span>{{ __('Subtotal') }}</span><x-money :amount="$preview['subtotal']" :currency="$store?->branch?->currency ?? 'EGP'" /></div><div class="flex justify-between"><span>{{ __('Discount') }}</span><x-money :amount="$preview['discount_total']" :currency="$store?->branch?->currency ?? 'EGP'" /></div><div class="flex justify-between"><span>{{ __('Tax') }}</span><x-money :amount="$preview['tax_total']" :currency="$store?->branch?->currency ?? 'EGP'" /></div></div><div class="mt-4 flex items-end justify-between border-t pt-4"><span class="text-sm font-extrabold">{{ __('Total') }}</span><span class="text-3xl font-black text-cyan-700"><x-money :amount="$total" :currency="$store?->branch?->currency ?? 'EGP'" /></span></div></div>
            @if($blocker)<p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">{{ $blocker }}</p>@endif
            <button type="submit" @disabled($blocker) class="mt-4 min-h-14 w-full rounded-xl bg-cyan-700 px-4 text-base font-extrabold text-white disabled:bg-zinc-300">{{ __('Complete sale') }} · {{ number_format((float)$total, 2) }}</button>
        </form>
    @else
        <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-center text-sm text-zinc-500 dark:bg-zinc-950">{{ $blocker }}</div>
    @endif
    <form method="POST" action="{{ route('pos.suspend') }}" class="mt-3">@csrf<button type="submit" @disabled($cart->isEmpty() || !$shift) class="min-h-11 w-full rounded-xl text-sm font-bold text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Hold sale') }}</button></form>
</section>
