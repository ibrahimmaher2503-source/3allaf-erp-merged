<x-layouts::app :title="__('Returns & Exchanges')">
    <x-app.page :title="__('Returns & Exchanges')" :description="__('Create source-linked return documents; the original sale is never edited.')" max-width="7xl">
        @if(session('success'))
            <flux:callout variant="success">{{ session('success') }}</flux:callout>
        @endif
        @if(isset($errors) && $errors->any())
            <flux:callout variant="danger">{{ $errors->first() }}</flux:callout>
        @endif

        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Choose one source only: the completed sale or its active Gift Receipt. A return is not complete until inspection, approval, settlement, and stock disposition are recorded.') }}
        </flux:callout>

        @can('returns.create')
            @php($exchangeLines = array_values(array_filter(is_array(old('exchange_lines')) ? old('exchange_lines') : [['product_id' => '', 'quantity' => 1]], 'is_array')))
            <flux:card class="mt-6">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('New return or exchange') }}</flux:heading>
                    <x-context-help :title="__('New return or exchange')" :label="__('Open contextual help')">
                        <p>{{ __('For an exchange, add every replacement line below. Any difference requires an active payment method when the approved return is completed.') }}</p>
                    </x-context-help>
                </div>

                <form method="GET" action="{{ route('returns.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    <flux:input name="source_search" :label="__('Search invoice, customer, phone or Gift Receipt')" :value="request('source_search')" maxlength="100" />
                    <flux:input name="source_date" type="date" :label="__('Sale date')" :value="request('source_date')" />
                    <flux:button type="submit" variant="subtle">{{ __('Search') }}</flux:button>
                </form>
                <div class="mt-4 overflow-x-auto">
                    <table class="data-table w-full text-sm"><thead><tr><th>{{ __('Source') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Sale date') }}</th><th>{{ __('Branch / outlet') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
                        @foreach($sales as $sale)
                            <tr><td>{{ $sale->document_number ?: '#'.$sale->id }}</td><td>{{ $sale->customer?->name_ar ?: $sale->customer?->name_en ?: __('Walk-in customer') }}</td><td>{{ $sale->approved_at?->format('Y-m-d H:i') }}</td><td>{{ $sale->branch?->name_ar ?: $sale->branch?->name_en }} · {{ $sale->store?->name_ar ?: $sale->store?->name_en }}</td><td>{{ number_format((float) $sale->payable_total, 2) }} {{ $sale->currency_code }}</td><td><a class="text-teal-700 underline" href="{{ route('returns.index', ['source_sale_id' => $sale->id]) }}">{{ __('Use this source') }}</a></td></tr>
                        @endforeach
                        @foreach($receipts as $receipt)
                            <tr><td>{{ __('Gift Receipt') }} {{ $receipt->reference }} · {{ $receipt->sale?->document_number }}</td><td>{{ $receipt->sale?->customer?->name_ar ?: $receipt->sale?->customer?->name_en ?: __('Walk-in customer') }}</td><td>{{ $receipt->sale?->approved_at?->format('Y-m-d H:i') }}</td><td>{{ $receipt->branch?->name_ar ?: $receipt->branch?->name_en }} · {{ $receipt->store?->name_ar ?: $receipt->store?->name_en }}</td><td>—</td><td><a class="text-teal-700 underline" href="{{ route('returns.index', ['source_gift_receipt_id' => $receipt->id]) }}">{{ __('Use this source') }}</a></td></tr>
                        @endforeach
                        @if($sales->isEmpty() && $receipts->isEmpty())<tr><td colspan="6">{{ __('No matching sources.') }}</td></tr>@endif
                    </tbody></table>
                </div>
                <div class="mt-3">{{ $sales->links() }} {{ $receipts->links() }}</div>

                <form method="POST" action="{{ route('returns.store') }}" novalidate class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ settlement: @js(old('settlement_type', 'cash_refund')), lineId: @js((string) old('sale_line_id', '')), condition: @js(old('condition', '')), disposition: @js(old('disposition', 'restock')), quantities: @js($lineQuantities) }" data-product-line-editor data-next-index="{{ count($exchangeLines) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="source_sale_id" value="{{ $sourceSaleId ?: '' }}">
                    <input type="hidden" name="source_gift_receipt_id" value="{{ $sourceGiftReceiptId ?: '' }}">
                    <div class="sm:col-span-2 lg:col-span-4">
                        <p class="font-semibold">{{ __('Source') }}: {{ $selectedReceipt?->reference ?: $selectedSale?->document_number ?: __('Choose a source from the search results.') }}</p>
                        <flux:error name="source_sale_id" /><flux:error name="source_gift_receipt_id" />
                    </div>
                    <flux:select name="sale_line_id" label="{{ __('Source line') }}" required x-model="lineId">
                        <option value="">{{ __('Choose item from the selected source') }}</option>
                        @foreach($sourceLines as $line)
                            <option value="{{ $line->id }}" @selected((int) old('sale_line_id') === $line->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? ($line->name_ar ?: $line->name_en) : ($line->name_en ?: $line->name_ar) }} · {{ $line->item_code }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input name="quantity" type="number" min="1" step="1" value="{{ old('quantity', 1) }}" label="{{ __('Quantity') }}" x-bind:max="quantities[lineId]?.remaining" required />
                    <div class="sm:col-span-2 lg:col-span-4">
                        @foreach($sourceLines as $line)
                            <p x-show="lineId === '{{ $line->id }}'" x-cloak data-sale-line="{{ $line->id }}" data-sold="{{ $lineQuantities[$line->id]['sold'] }}" data-returned="{{ $lineQuantities[$line->id]['returned'] }}" data-remaining="{{ $lineQuantities[$line->id]['remaining'] }}">{{ __('Sold quantity') }}: <x-product-quantity :value="$lineQuantities[$line->id]['sold']" /> · {{ __('Previously returned / reserved quantity') }}: <x-product-quantity :value="$lineQuantities[$line->id]['returned']" /> · {{ __('Remaining returnable quantity') }}: <x-product-quantity :value="$lineQuantities[$line->id]['remaining']" /></p>
                        @endforeach
                    </div>

                    <flux:select name="settlement_type" label="{{ __('Settlement') }}" required x-model="settlement">
                        <option value="cash_refund" @selected(old('settlement_type', 'cash_refund') === 'cash_refund')>{{ __('Cash refund record') }}</option>
                        <option value="original_tender" @selected(old('settlement_type') === 'original_tender')>{{ __('Original tender reversal record') }}</option>
                        <option value="gift_card" @selected(old('settlement_type') === 'gift_card')>{{ __('Gift Card') }}</option>
                        <option value="exchange" @selected(old('settlement_type') === 'exchange')>{{ __('Exchange') }}</option>
                    </flux:select>
                    <flux:select name="condition" label="{{ __('Inspected condition') }}" required x-model="condition" x-on:change="if (condition !== 'sellable') disposition = 'quarantine'">
                        <option value="">{{ __('Select inspection result') }}</option>
                        <option value="sellable" @selected(old('condition') === 'sellable')>{{ __('Sellable') }}</option>
                        <option value="non_sellable" @selected(old('condition') === 'non_sellable')>{{ __('Non-sellable') }}</option>
                        <option value="damaged" @selected(old('condition') === 'damaged')>{{ __('Damaged') }}</option>
                        <option value="manager_review" @selected(old('condition') === 'manager_review')>{{ __('Manager review') }}</option>
                    </flux:select>
                    <flux:select name="disposition" label="{{ __('Stock disposition') }}" required x-model="disposition">
                        <option value="restock" x-bind:disabled="condition !== 'sellable'" @selected(old('disposition', 'restock') === 'restock')>{{ __('Restock sellable') }}</option>
                        <option value="quarantine" @selected(old('disposition') === 'quarantine')>{{ __('Quarantine to the damaged store') }}</option>
                    </flux:select>
                    <flux:textarea name="inspection_notes" label="{{ __('Inspection notes / evidence reference') }}">{{ old('inspection_notes') }}</flux:textarea>

                    <fieldset x-show="settlement === 'exchange'" x-bind:disabled="settlement !== 'exchange'" @disabled(old('settlement_type', 'cash_refund') !== 'exchange') x-cloak class="sm:col-span-2 lg:col-span-4 rounded-lg border border-teal-200 p-4 dark:border-teal-900">
                        <legend class="px-1 text-sm font-semibold">{{ __('Replacement lines') }}</legend>
                        <p class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Replacement stock leaves the selling store only when this return is completed.') }}</p>
                        @foreach($exchangeLines as $index => $replacement)
                        <div class="grid gap-3 sm:grid-cols-12" data-product-line>
                            <div class="sm:col-span-5"><x-product-line-lookup name="exchange_lines[{{ $index }}][product_id]" :value="$replacement['product_id'] ?? ''" :display="filled($replacement['product_id'] ?? null) ? __('Product').' #'.$replacement['product_id'] : ''" :label="__('Product')" /></div>
                            <div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="exchange_lines[{{ $index }}][quantity]" data-line-quantity type="number" min="1" step="1" value="{{ $replacement['quantity'] ?? 1 }}" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div>
                            <div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Unit price') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3 text-xs">{{ __('Calculated at completion') }}</span></div>
                            <div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3">—</span></div>
                        </div>
                        @endforeach
                        <template data-line-template><div class="mt-3 grid gap-3 sm:grid-cols-12" data-product-line><div class="sm:col-span-5"><x-product-line-lookup name="exchange_lines[__INDEX__][product_id]" :label="__('Product')" /></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="exchange_lines[__INDEX__][quantity]" data-line-quantity type="number" min="1" step="1" value="1" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Unit price') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3 text-xs">{{ __('Calculated at completion') }}</span></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3">—</span></div><button type="button" data-remove-line class="self-end text-sm text-red-700 underline">{{ __('Remove') }}</button></div></template>
                        <button type="button" data-add-line class="mt-3 text-sm text-teal-700 underline">{{ __('Add item') }}</button>
                    </fieldset>

                    <flux:textarea name="reason" label="{{ __('Reason') }}" required class="sm:col-span-2">{{ old('reason') }}</flux:textarea>
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <flux:button type="submit" variant="primary">{{ __('Create draft') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @endcan

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[960px] w-full text-sm">
                    <thead><tr><th>{{ __('Return') }}</th><th>{{ __('Source') }}</th><th>{{ __('Lines') }}</th><th>{{ __('Settlement') }}</th><th>{{ __('Value') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr class="border-t border-zinc-100 align-top dark:border-zinc-800">
                                <td class="font-mono font-semibold">{{ $return->return_number ?: '#'.$return->id }}</td>
                                <td>{{ $return->sourceSale?->document_number ?: ($return->sourceGiftReceipt?->reference ?: __('Source unavailable')) }}</td>
                                <td>{{ $return->lines->count() }}</td><td>{{ str_replace('_', ' ', ucfirst($return->settlement_type)) }}</td>
                                <td>{{ number_format((float) $return->settlement_value, 2) }} {{ $return->currency_code }}</td><td><x-status.badge :status="$return->status" /></td>
                                <td><x-actions.button semantic="view" :label="__('Open')" :href="route('returns.show', $return)">{{ __('Open') }}</x-actions.button> <x-actions.button semantic="print" :label="__('Print')" :href="route('returns.print', $return)">{{ __('Print') }}</x-actions.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-state.empty :title="__('No returns yet')" :description="__('Start from a completed sale or a valid Gift Receipt.')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $returns->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
