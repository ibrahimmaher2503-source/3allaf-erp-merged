<x-layouts::pos :title="__('Resume suspended sale')" :store="$sale->store" :shift="$preview['shift']">
    @php
        $currency = $sale->currency_code;
        $electronicIndex = $cashMethod ? 1 : 0;
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Resume suspended sale') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Prices, tax, discounts, open-price limits, shift context, and stock will be checked again before payment is posted.') }}</flux:text>
            </div>
            <flux:button href="{{ route('pos.suspended') }}" variant="subtle" icon="arrow-left">{{ __('Back to suspended sales') }}</flux:button>
        </div>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </flux:callout>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Revalidated cart') }}</flux:heading>
                    <flux:text>{{ __('Shift') }} #{{ $preview['shift']->id }} · {{ __('Drawer') }} {{ $preview['shift']->cashDrawer?->code }}</flux:text>
                </div>
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Revalidated suspended cart') }}">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-950/40">
                            <tr><th class="p-3 text-start">{{ __('Item') }}</th><th class="p-3 text-end">{{ __('Quantity') }}</th><th class="p-3 text-end">{{ __('Unit price') }}</th><th class="p-3 text-end">{{ __('Discount') }}</th><th class="p-3 text-end">{{ __('Net') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['lines'] as $index => $line)
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="p-3"><span class="font-semibold">{{ app()->getLocale() === 'ar' ? $line['product']->name_ar : $line['product']->name_en }}</span><div class="text-xs text-zinc-500">{{ $line['product']->item_code }}{{ $line['is_open_price'] ? ' · '.__('Open price revalidated') : '' }}</div></td>
                                    <td class="p-3 text-end">{{ $line['quantity'] }}</td>
                                    <td class="p-3 text-end">{{ $line['unit_price'] }} {{ $currency }}</td>
                                    <td class="p-3 text-end">{{ $preview['totals']['lines'][$index]['discount_amount'] }} {{ $currency }}</td>
                                    <td class="p-3 text-end font-semibold">{{ $preview['totals']['lines'][$index]['net_amount'] }} {{ $currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payment') }}</flux:heading>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Subtotal') }}</dt><dd>{{ $preview['totals']['subtotal'] }} {{ $currency }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Discount') }}</dt><dd>{{ $preview['totals']['discount_total'] }} {{ $currency }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Tax') }}</dt><dd>{{ $preview['totals']['tax_total'] }} {{ $currency }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-zinc-200 pt-2 font-bold dark:border-zinc-700"><dt>{{ __('Invoice total') }}</dt><dd>{{ $preview['totals']['total'] }} {{ $currency }}</dd></div>
                </dl>

                @if ($cashDenomination === null)
                    <flux:callout class="mt-3" variant="warning" icon="exclamation-triangle">{{ __('Cash is unavailable until the cash-rounding denomination is configured.') }}</flux:callout>
                @endif

                <form method="POST" action="{{ route('pos.suspended.finalize', $sale) }}" enctype="multipart/form-data" class="mt-4 grid gap-4">
                    @csrf
                    <input type="hidden" name="resume_token" value="{{ $resumeToken }}">

                    @if ($electronicMethods->isNotEmpty())
                        <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <legend class="px-1 text-sm font-semibold">{{ __('Manual electronic payment') }}</legend>
                            <flux:select name="payments[{{ $electronicIndex }}][method_id]" :label="__('Payment method')">
                                <flux:select.option value="">{{ __('No electronic payment') }}</flux:select.option>
                                @foreach ($electronicMethods as $method)
                                    <flux:select.option value="{{ $method->id }}">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}{{ $method->requires_evidence ? ' · '.__('evidence required') : '' }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input name="payments[{{ $electronicIndex }}][amount]" type="number" min="0.01" step="0.01" :label="__('Electronic amount')" />
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
                            <flux:input name="payments[0][tendered]" type="number" min="0" step="0.01" :label="__('Cash tendered')" />
                        </fieldset>
                    @endif

                    <flux:button type="submit" variant="primary" icon="check" class="w-full">{{ __('Revalidate, settle, and complete') }}</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::pos>
