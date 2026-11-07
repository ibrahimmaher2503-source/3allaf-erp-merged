<x-layouts::app :title="__('Quotations')">
    @php($sellingStores = $stores->where('type', 'selling')->values())
    <x-app.page :title="__('Quotations')" :description="__('Create product sales quotations that are printable and shareable, with no stock, payment, reservation, wallet, or accounting effect.')" max-width="7xl">
        @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
        @if(session('share_url'))<flux:callout variant="info"><a class="underline" href="{{ session('share_url') }}">{{ __('Open the protected quotation link') }}</a></flux:callout>@endif
        <flux:callout variant="warning" icon="exclamation-triangle">{{ __('NON-POSTING: a quotation is an offer only. Phase 1 has no one-click conversion and it never changes inventory or financial records.') }}</flux:callout>
        @if(!($creating ?? false))
            @can('quotations.create')<div class="flex justify-end"><flux:button :href="route('quotations.create')" variant="primary">{{ __('+ Add Quotation') }}</flux:button></div>@endcan
        @endif
        @if($creating ?? false)
            <flux:button :href="route('quotations.index')" variant="subtle">{{ __('Back to quotations') }}</flux:button>
            <flux:card>
                <div class="flex items-center gap-2"><flux:heading size="lg">{{ __('New quotation') }}</flux:heading><x-context-help :title="__('New quotation')" :label="__('Open contextual help')"><p>{{ __('Use catalog products and manual quotation-only items together.') }}</p></x-context-help></div>
                <form method="POST" action="{{ route('quotations.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-product-line-editor data-autofocus="true" data-next-index="1">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                    <input type="hidden" name="activity_type" value="retail"><flux:input name="quotation_date" type="date" :value="old('quotation_date', now()->toDateString())" :label="__('Quotation date')" required />
                    <div class="relative sm:col-span-2" data-quotation-customer-picker>
                        <label for="quotation-customer-search" class="mb-1 block text-sm font-semibold">{{ __('Search and select customer') }}</label>
                        <input type="hidden" name="customer_id" value="{{ old('customer_id') }}" data-quotation-customer-id>
                        <div class="flex gap-2"><input id="quotation-customer-search" type="search" data-quotation-customer-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="quotation-customer-results" autocomplete="off" class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30 dark:border-zinc-600 dark:bg-zinc-900" placeholder="{{ __('Search by name or phone (3 characters)') }}" aria-label="{{ __('Search and select customer') }}"><button type="button" data-quotation-customer-clear class="rounded-lg border border-zinc-300 px-3 text-sm hover:bg-zinc-100 active:scale-95 dark:border-zinc-600 dark:hover:bg-zinc-800" aria-label="{{ __('Clear selected customer') }}" title="{{ __('Clear selected customer') }}">×</button></div>
                        <div id="quotation-customer-results" data-quotation-customer-results role="listbox" class="absolute inset-x-0 top-full z-30 mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"></div>
                        <p data-quotation-customer-help class="mt-1 text-xs text-zinc-500">{{ __('Type at least 3 letters or digits to search.') }}</p>
                        @error('customer_id')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">@can('customers.create') <details class="mt-2 rounded-lg border border-zinc-200 p-3"><summary class="cursor-pointer text-sm font-semibold">{{ __('Add new customer') }}</summary><div class="mt-3 grid gap-2 sm:grid-cols-2"><input data-new-first class="h-10 rounded-lg border px-3" placeholder="{{ __('Arabic first name') }}"><input data-new-last class="h-10 rounded-lg border px-3" placeholder="{{ __('Arabic last name') }}"><input data-new-phone class="h-10 rounded-lg border px-3" placeholder="{{ __('Phone') }}"><select data-new-governorate class="h-10 rounded-lg border px-3"><option value="">{{ __('Governorate') }}</option>@foreach($governorates as $governorate)<option value="{{ $governorate->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $governorate->name_ar : $governorate->name_en }}</option>@endforeach</select><button type="button" data-create-quotation-customer class="rounded-lg bg-cyan-700 px-4 py-2 text-white">{{ __('Create and select') }}</button><p data-customer-error class="text-sm text-rose-700" role="alert"></p></div></details>@endcan</div>
                    @if($sellingStores->count() === 1)<input type="hidden" name="store_id" value="{{ $sellingStores->first()->id }}">@else<flux:select name="store_id" :label="__('Store')" required><flux:select.option value="">{{ __('Choose store') }}</flux:select.option>@foreach($sellingStores as $store)<flux:select.option value="{{ $store->id }}">{{ $store->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</flux:select.option>@endforeach</flux:select>@endif
                    <flux:input name="valid_until" type="date" :value="old('valid_until', now()->addDays(7)->toDateString())" :label="__('Valid until')" required />
                    <section class="grid gap-3 rounded-lg border p-2 sm:col-span-2 lg:col-span-4 md:grid-cols-[minmax(6rem,1fr)_minmax(10rem,2fr)_minmax(8rem,1.5fr)_minmax(8rem,1.5fr)_5rem_6rem_6rem_3rem]" data-product-line>
                        <div class="md:col-span-2"><flux:select name="lines[0][line_type]" :label="__('Compatible line type')" required><flux:select.option value="product">{{ __('Retail product') }}</flux:select.option><flux:select.option value="manual">{{ __('Manual item') }}</flux:select.option></flux:select></div>
                        <div class="md:col-span-4"><x-product-line-lookup name="lines[0][product_id]" :label="__('Product')" /></div>
                        <div class="md:col-span-3"><flux:input name="lines[0][description_ar]" :value="old('lines.0.description_ar')" :label="__('Line description (Arabic)')" required /></div>
                        <div class="md:col-span-3"><flux:input name="lines[0][description_en]" :value="old('lines.0.description_en')" :label="__('Line description (English)')" required /></div>
                        <div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="lines[0][quantity]" data-line-quantity type="number" step="0.001" min="0.001" value="{{ old('lines.0.quantity', '1') }}" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div>
                        <div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Unit price') }}</label><input name="lines[0][unit_price]" data-line-price data-price-kind="price" type="number" step="0.01" min="0" value="{{ old('lines.0.unit_price') }}" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div>
                        <div class="md:col-span-3"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-zinc-50 px-3 font-mono" data-line-total>0.00</output></div>
                    </section>
                    <template data-line-template><section class="grid gap-3 rounded-lg border p-2 sm:col-span-2 lg:col-span-4 md:grid-cols-[minmax(6rem,1fr)_minmax(10rem,2fr)_minmax(8rem,1.5fr)_minmax(8rem,1.5fr)_5rem_6rem_6rem_3rem]" data-product-line><div class="md:col-span-2"><label class="text-xs font-semibold">{{ __('Compatible line type') }}</label><select name="lines[__INDEX__][line_type]" class="mt-1 h-10 w-full rounded-lg border-zinc-300"><option value="product">{{ __('Retail product') }}</option><option value="manual">{{ __('Manual item') }}</option></select></div><div class="md:col-span-4"><x-product-line-lookup name="lines[__INDEX__][product_id]" :label="__('Product')" /></div><div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Line description (Arabic)') }}</label><input name="lines[__INDEX__][description_ar]" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div><div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Line description (English)') }}</label><input name="lines[__INDEX__][description_en]" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div><div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="lines[__INDEX__][quantity]" data-line-quantity type="number" step="0.001" min="0.001" value="1" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div><div class="md:col-span-3"><label class="text-xs font-semibold">{{ __('Unit price') }}</label><span class="block text-xs text-zinc-500">{{ __('Reference consumer price') }}: <span data-reference-price>—</span></span><input name="lines[__INDEX__][unit_price]" data-line-price data-price-kind="price" type="number" step="0.01" min="0" class="mt-1 h-10 w-full rounded-lg border-zinc-300" required></div><div class="md:col-span-3"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-zinc-50 px-3 font-mono" data-line-total>0.00</output></div><button type="button" data-remove-line class="self-end text-sm text-rose-700 underline">{{ __('Remove') }}</button></section></template>
                    <div class="sm:col-span-2 lg:col-span-4"><button type="button" data-add-line class="text-sm font-semibold text-cyan-700 underline">{{ __('Add item') }}</button> <button type="button" data-add-manual class="ms-3 text-sm font-semibold text-cyan-700 underline">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة صنف يدوي' : 'Add Manual Item' }}</button></div>
                    <flux:select name="discount_type" :label="__('Discount type')"><flux:select.option value="">{{ __('No discount') }}</flux:select.option><flux:select.option value="fixed">{{ __('Fixed amount') }}</flux:select.option><flux:select.option value="percent">{{ __('Percentage') }}</flux:select.option></flux:select>
                    <flux:input name="discount_value" type="number" min="0" step="0.01" value="0" :label="__('Discount value')" />
                    <flux:input name="tax_rate" type="number" min="0" max="100" step="0.01" value="0" :label="__('Tax percentage (0 = no tax)')" />
                    <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800"><div>{{ __('Subtotal') }}: <output data-editor-total>0.00</output></div><div>{{ __('Discount') }}: <output data-discount-total>0.00</output></div><div>{{ __('Tax') }}: <output data-tax-total>0.00</output></div><div class="font-bold">{{ __('Final quotation total') }}: <output data-quotation-final>0.00</output></div></div>
                    <flux:textarea name="terms" :label="__('Terms')" rows="2">{{ old('terms') }}</flux:textarea>
                    <flux:textarea name="notes" :label="__('Notes')" rows="2">{{ old('notes') }}</flux:textarea>
                    <div class="flex justify-end sm:col-span-2 lg:col-span-4"><flux:button type="submit" variant="primary">{{ __('Save Quotation') }}</flux:button></div>
                </form>
            </flux:card>
        @endif
        @if(!($creating ?? false))
        <flux:card class="overflow-hidden p-0"><div class="overflow-x-auto"><table class="data-table min-w-[900px] w-full text-sm"><thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Branch / store') }}</th><th>{{ __('Items') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @forelse($quotations as $quotation)
                <tr class="border-t border-zinc-100 align-top dark:border-zinc-800"><td class="font-mono font-semibold">{{ $quotation->quotation_number }}</td><td>{{ $quotation->quotation_date?->locale(app()->getLocale())->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y' : 'M j, Y') ?? $quotation->created_at?->locale(app()->getLocale())->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y' : 'M j, Y') }}</td><td>{{ $quotation->customer ? (str_starts_with(app()->getLocale(), 'ar') ? $quotation->customer->name_ar : $quotation->customer->name_en) : __('Walk-in') }}</td><td>{{ $quotation->branch?->code }} / {{ $quotation->store?->code }}</td><td>{{ $quotation->lines->count() }}</td><td>{{ number_format((float)$quotation->total, 2) }} {{ $quotation->currency_code }}</td><td><x-status.badge :status="$quotation->status === 'draft' ? 'issued' : $quotation->status" :label="$quotation->status === 'draft' ? __('Active') : null" /></td><td class="w-32 whitespace-nowrap"><div class="flex items-center gap-1">
                    <a class="hidden size-8 items-center justify-center rounded-md text-cyan-700 sm:inline-flex hover:bg-cyan-50 focus-visible:ring-2 focus-visible:ring-cyan-600 active:scale-95 dark:hover:bg-zinc-800" href="{{ route('quotations.show', $quotation) }}" title="{{ __('View') }}" aria-label="{{ __('View') }}"><flux:icon name="eye" class="size-4" /></a>
                    @can('quotations.print')<a class="hidden size-8 items-center justify-center rounded-md text-cyan-700 sm:inline-flex hover:bg-cyan-50 focus-visible:ring-2 focus-visible:ring-cyan-600 active:scale-95 dark:hover:bg-zinc-800" href="{{ URL::signedRoute('quotations.print', ['quotation' => $quotation->id]) }}" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><flux:icon name="printer" class="size-4" /></a>@endcan
                    @php($canEditQuotation = in_array($quotation->status, ['draft', 'issued'], true) && auth()->user()->can('quotations.edit'))
                    @if($canEditQuotation)<button type="button" class="hidden size-8 items-center justify-center rounded-md text-cyan-700 sm:inline-flex hover:bg-cyan-50 focus-visible:ring-2 focus-visible:ring-cyan-600 active:scale-95 dark:hover:bg-zinc-800" title="{{ __('Edit Quotation') }}" aria-label="{{ __('Edit Quotation') }}" onclick="document.getElementById('quotation-edit-{{ $quotation->id }}').showModal()"><flux:icon name="pencil-square" class="size-4" /></button>@endif
                    <details class="relative" data-quotation-actions><summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-md text-cyan-700 hover:bg-cyan-50 focus-visible:ring-2 focus-visible:ring-cyan-600 active:scale-95 dark:hover:bg-zinc-800" title="{{ __('More actions') }}" aria-label="{{ __('More actions') }}"><flux:icon name="ellipsis-horizontal" class="size-4" /></summary><div class="fixed z-50 min-w-44 space-y-1 rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-900" data-quotation-action-menu>
                    <a class="block rounded px-2 py-1 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 sm:hidden" href="{{ route('quotations.show', $quotation) }}">{{ __('View') }}</a>
                    @can('quotations.print')<a class="block rounded px-2 py-1 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 sm:hidden" href="{{ URL::signedRoute('quotations.print', ['quotation' => $quotation->id]) }}">{{ __('Print') }}</a>@endcan
                    @if($canEditQuotation)<button type="button" class="block w-full rounded px-2 py-1 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 sm:hidden" onclick="document.getElementById('quotation-edit-{{ $quotation->id }}').showModal()">{{ __('Edit Quotation') }}</button>@endif
                    @can('quotations.share')<form method="POST" action="{{ route('quotations.share', $quotation) }}">@csrf<button type="submit" class="w-full rounded px-2 py-1 text-start text-sm hover:bg-zinc-100 active:scale-[.98] dark:hover:bg-zinc-800">{{ __('Share / regenerate link') }}</button></form>@endcan
                    @can('quotations.edit') @foreach($quotation->shareLinks ?? [] as $link) @if($link->revoked_at === null && $link->expires_at?->isFuture())<form method="POST" action="{{ route('quotations.share.revoke', [$quotation, $link]) }}">@csrf<button type="submit" class="w-full rounded px-2 py-1 text-start text-sm hover:bg-zinc-100 active:scale-[.98] dark:hover:bg-zinc-800">{{ __('Revoke link') }}</button></form>@endif @endforeach @endcan
                    @if(in_array($quotation->status, ['draft', 'issued'], true))@can('quotations.cancel')<form method="POST" action="{{ route('quotations.status', $quotation) }}">@csrf<input type="hidden" name="status" value="cancelled"><input type="hidden" name="reason" value="{{ __('Cancelled by authorized user.') }}"><button type="submit" class="w-full rounded px-2 py-1 text-start text-sm text-rose-700 hover:bg-rose-50 active:scale-[.98]">{{ __('Cancel') }}</button></form>@endcan
                    @endif
                    </div></details>
                    @if(in_array($quotation->status, ['draft', 'issued'], true))
                        @can('quotations.edit')<dialog id="quotation-edit-{{ $quotation->id }}" class="m-auto max-h-[90vh] w-[min(95vw,42rem)] overflow-y-auto rounded-xl border border-zinc-200 bg-white p-4 text-start shadow-xl backdrop:bg-zinc-950/60 dark:border-zinc-700 dark:bg-zinc-900"><div class="mb-3 flex justify-between gap-2"><h2 class="font-semibold">{{ __('Edit Quotation') }}</h2><button type="button" onclick="this.closest('dialog').close()" aria-label="{{ __('Close') }}">×</button></div><form method="POST" action="{{ route('quotations.update', $quotation) }}" class="mt-3 grid gap-3" data-product-line-editor>@csrf @method('PUT')
                            @foreach($quotation->lines->sortBy('line_number')->values() as $lineIndex => $quotationLine)
                                @php($quotationProduct = $products->firstWhere('id', (int) $quotationLine->product_id))
                                <section class="grid gap-3 rounded-lg border border-zinc-200 p-3" data-product-line data-manual-code="{{ $quotationLine->source_reference }}">
                                    <input type="hidden" name="lines[{{ $lineIndex }}][id]" value="{{ $quotationLine->id }}">
                                    <flux:select name="lines[{{ $lineIndex }}][line_type]" :label="__('Line type')"><flux:select.option value="product" :selected="$quotationLine->line_type === 'product'">{{ __('Retail product') }}</flux:select.option><flux:select.option value="manual" :selected="$quotationLine->line_type === 'manual'">{{ __('Manual item') }}</flux:select.option></flux:select>
                                    <x-product-line-lookup name="lines[{{ $lineIndex }}][product_id]" :value="$quotationLine->product_id" :display="$quotationProduct ? ((str_starts_with(app()->getLocale(), 'ar') ? $quotationProduct->name_ar : $quotationProduct->name_en).' · '.$quotationProduct->item_code) : ''" :label="__('Product')" />
                                    <flux:input name="lines[{{ $lineIndex }}][description_en]" :value="$quotationLine->description_en" :label="__('Line description (English)')" required />
                                    <flux:input name="lines[{{ $lineIndex }}][description_ar]" :value="$quotationLine->description_ar" :label="__('Line description (Arabic)')" required />
                                    <flux:input name="lines[{{ $lineIndex }}][quantity]" type="number" step="0.001" :value="$quotationLine->quantity" :label="__('Quantity')" required data-line-quantity />
                                    <flux:input name="lines[{{ $lineIndex }}][unit_price]" type="number" step="0.01" :value="$quotationLine->unit_price" :label="__('Unit price')" required data-line-price data-price-kind="price" />
                                    <div><flux:label>{{ __('Line total') }}</flux:label><output data-line-total class="ms-2 font-mono">0.00</output></div>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="removed_line_ids[]" value="{{ $quotationLine->id }}">{{ __('Remove') }}</label>
                                </section>
                            @endforeach
                            <flux:select name="customer_id" :label="__('Customer')" required>@foreach($customers as $customer)<flux:select.option value="{{ $customer->id }}" :selected="$quotation->customer_id === $customer->id">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en }} · {{ $customer->phone_display }}</flux:select.option>@endforeach</flux:select>
                            <flux:input name="quotation_date" type="date" :value="$quotation->quotation_date?->format('Y-m-d')" :label="__('Quotation date')" /><flux:input name="valid_until" type="date" :value="$quotation->valid_until?->format('Y-m-d')" :label="__('Valid until')" required /><flux:select name="discount_type" :label="__('Discount type')"><flux:select.option value="">{{ __('No discount') }}</flux:select.option><flux:select.option value="fixed" :selected="$quotation->discount_type === 'fixed'">{{ __('Fixed amount') }}</flux:select.option><flux:select.option value="percent" :selected="$quotation->discount_type === 'percent'">{{ __('Percentage') }}</flux:select.option></flux:select><flux:input name="discount_value" type="number" min="0" step="0.01" :value="$quotation->discount_value" :label="__('Discount value')" /><flux:input name="tax_rate" type="number" min="0" max="100" step="0.01" :value="$quotation->tax_rate" :label="__('Tax percentage')" /><flux:textarea name="terms" :label="__('Terms')" rows="2">{{ $quotation->terms }}</flux:textarea><flux:textarea name="notes" :label="__('Notes')" rows="2">{{ $quotation->notes }}</flux:textarea><flux:button type="submit" size="sm">{{ __('Save Quotation') }}</flux:button>
                        </form></dialog>@endcan
                    @endif
                </div></td></tr>
            @empty<tr><td colspan="8"><x-state.empty :title="__('No quotations yet.')" :description="__('Create a product quotation without posting stock or financial entries.')" /></td></tr>@endforelse
        </tbody></table></div><div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $quotations->links() }}</div></flux:card>
        @endif
    </x-app.page>
</x-layouts::app>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-product-line-editor]').forEach((editor) => {
            const decorate = (line) => {
                if (line.dataset.quotationDecorated) return;
                line.dataset.quotationDecorated = '1';
                const type = line.querySelector('[name$="[line_type]"]');
                const product = line.querySelector('[data-product-lookup]');
                if (!type || !product) return;
                const code = document.createElement('label');
                code.className = 'text-xs font-semibold';
                code.textContent = @json(__('Manual/reference code'));
                code.innerHTML += '<input name="' + type.name.replace('[line_type]', '[source_reference]') + '" maxlength="190" class="mt-1 block h-10 w-full rounded-lg border border-zinc-300 px-3 dark:bg-zinc-900">';
                product.after(code);
                code.querySelector('input').value = line.dataset.manualCode || '';
                const sync = () => {
                    const manual = type.value === 'manual';
                    product.hidden = manual;
                    product.querySelector('[data-product-id]').disabled = manual;
                    if (manual) product.querySelector('[data-product-id]').value = '';
                    code.hidden = !manual;
                };
                type.addEventListener('change', sync);
                sync();
            };
            const calculate = () => {
                let subtotal = 0;
                editor.querySelectorAll('[data-product-line]').forEach((line) => {
                    const quantity = Number(line.querySelector('[data-line-quantity]')?.value || 0);
                    const price = Number(line.querySelector('[data-line-price]')?.value || 0);
                    const lineTotal = Math.round(quantity * price * 100) / 100;
                    subtotal += lineTotal;
                    const output = line.querySelector('[data-line-total]');
                    if (output) output.textContent = lineTotal.toFixed(2);
                });
                const type = editor.querySelector('[name="discount_type"]')?.value;
                const value = Number(editor.querySelector('[name="discount_value"]')?.value || 0);
                const taxRate = Number(editor.querySelector('[name="tax_rate"]')?.value || 0);
                const discount = type === 'percent' ? subtotal * Math.min(100, value) / 100 : type === 'fixed' ? Math.min(subtotal, value) : 0;
                editor.querySelectorAll('[data-editor-total]').forEach((node) => node.textContent = subtotal.toFixed(2));
                editor.querySelectorAll('[data-discount-total]').forEach((node) => node.textContent = discount.toFixed(2));
                editor.querySelectorAll('[data-tax-total]').forEach((node) => node.textContent = ((subtotal - discount) * taxRate / 100).toFixed(2));
                editor.querySelectorAll('[data-quotation-final]').forEach((node) => node.textContent = (subtotal - discount + (subtotal - discount) * taxRate / 100).toFixed(2));
            };
            editor.querySelectorAll('[data-product-line]').forEach(decorate);
            editor.addEventListener('product-selected', (event) => {
                const line = event.target.closest('[data-product-line]');
                const reference = line?.querySelector('[data-reference-price]');
                if (reference) reference.textContent = Number(event.detail?.unit_price || 0).toFixed(2);
            });
            editor.addEventListener('input', calculate);
            editor.addEventListener('change', calculate);
            editor.addEventListener('click', (event) => {
                if (event.target.closest('[data-add-manual]')) {
                    event.stopImmediatePropagation();
                    editor.querySelector('[data-add-line]')?.click();
                    requestAnimationFrame(() => {
                        const line = [...editor.querySelectorAll('[data-product-line]')].at(-1);
                        decorate(line);
                        const type = line.querySelector('[name$="[line_type]"]');
                        type.value = 'manual';
                        type.dispatchEvent(new Event('change', {bubbles: true}));
                    });
                }
                requestAnimationFrame(() => { editor.querySelectorAll('[data-product-line]').forEach(decorate); calculate(); });
            });
            calculate();
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-quotation-actions]').forEach((details) => {
            details.addEventListener('toggle', () => {
                if (!details.open) return;
                const trigger = details.querySelector('summary').getBoundingClientRect();
                const menu = details.querySelector('[data-quotation-action-menu]');
                menu.style.top = Math.max(8, Math.min(trigger.bottom + 4, innerHeight - menu.offsetHeight - 8)) + 'px';
                menu.style.left = Math.max(8, Math.min(trigger.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8)) + 'px';
            });
        });
        const form = document.querySelector('form[action="{{ route('quotations.store') }}"]');
        if (!form) return;
        const search = form.querySelector('[data-quotation-customer-search]');
        const selectedId = form.querySelector('[data-quotation-customer-id]');
        const results = form.querySelector('[data-quotation-customer-results]');
        const help = form.querySelector('[data-quotation-customer-help]');
        let matches = [];
        let highlighted = -1;
        let customerSearchRequest = 0;
        let debounce;
        const closeResults = () => { results.classList.add('hidden'); search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant'); };
        const choose = (customer) => {
            selectedId.value = customer.id;
            search.setCustomValidity('');
            search.value = customer.name + ' · ' + customer.phone;
            help.textContent = search.value;
            closeResults();
        };
        const renderResults = () => {
            results.replaceChildren();
            matches.forEach((customer, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = 'quotation-customer-option-' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', String(index === highlighted));
                option.className = 'block w-full rounded-md px-3 py-2 text-start text-sm hover:bg-cyan-50 focus:bg-cyan-50 dark:hover:bg-zinc-800' + (index === highlighted ? ' bg-cyan-50 dark:bg-zinc-800' : '');
                option.textContent = customer.name + ' · ' + customer.phone;
                option.addEventListener('click', () => choose(customer));
                results.append(option);
            });
            results.classList.toggle('hidden', matches.length === 0);
            search.setAttribute('aria-expanded', String(matches.length > 0));
            if (highlighted >= 0) search.setAttribute('aria-activedescendant', 'quotation-customer-option-' + highlighted);
            else search.removeAttribute('aria-activedescendant');
        };
        search?.addEventListener('input', () => {
            selectedId.value = '';
            matches = []; highlighted = -1; closeResults();
            help.textContent = @json(__('Type at least 3 letters or digits to search.'));
            clearTimeout(debounce);
            const term = search.value.trim();
            const storeId = form.querySelector('[name="store_id"]').value;
            const requestId = ++customerSearchRequest;
            if ([...term].length < 3 || !storeId) { matches = []; closeResults(); return; }
            debounce = setTimeout(async () => {
                try {
                    const url = new URL(@json(route('quotations.customers.search')), location.origin);
                    url.searchParams.set('q', term);
                    url.searchParams.set('store_id', storeId);
                    const response = await fetch(url, {headers: {Accept: 'application/json'}});
                    if (!response.ok) return;
                    const result = await response.json();
                    if (requestId !== customerSearchRequest) return;
                    matches = result.data || [];
                    highlighted = matches.length === 1 ? 0 : -1;
                    renderResults();
                } catch { closeResults(); }
            }, 250);
        });
        search?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { closeResults(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                if (!matches.length) return;
                event.preventDefault();
                highlighted = (highlighted + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
                renderResults();
            }
            if (event.key === 'Enter' && matches.length && highlighted >= 0) { event.preventDefault(); choose(matches[highlighted]); }
        });
        form.querySelector('[data-quotation-customer-clear]')?.addEventListener('click', () => {
            selectedId.value = ''; search.value = ''; matches = []; closeResults();
            help.textContent = @json(__('Type at least 3 letters or digits to search.'));
            search.setCustomValidity(''); search.focus();
        });
        form.querySelector('[name="store_id"]')?.addEventListener('change', () => {
            selectedId.value = ''; search.value = ''; matches = []; closeResults();
        });
        document.addEventListener('click', (event) => { if (!form.querySelector('[data-quotation-customer-picker]').contains(event.target)) closeResults(); });
        form.addEventListener('submit', (event) => {
            if (selectedId.value) return;
            event.preventDefault();
            search.setCustomValidity(@json(__('Select customer')));
            search.reportValidity();
        });
        search.addEventListener('input', () => search.setCustomValidity(''));
        form.querySelector('[data-create-quotation-customer]')?.addEventListener('click', async () => {
            const error = form.querySelector('[data-customer-error]');
            error.textContent = '';
            const data = new FormData();
            data.set('store_id', form.querySelector('[name="store_id"]').value);
            data.set('idempotency_key', crypto.randomUUID());
            data.set('first_name_ar', form.querySelector('[data-new-first]').value);
            data.set('last_name_ar', form.querySelector('[data-new-last]').value);
            data.set('phone', form.querySelector('[data-new-phone]').value);
            data.set('governorate_id', form.querySelector('[data-new-governorate]').value);
            try {
                const response = await fetch(@json(route('quotations.customers.store')), {method: 'POST', headers: {'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value, Accept: 'application/json'}, body: data});
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Customer creation failed');
                choose(result);
                matches = [];
                form.querySelector('[data-create-quotation-customer]').closest('details').open = false;
            } catch (exception) { error.textContent = exception.message; }
        });
    });
</script>
