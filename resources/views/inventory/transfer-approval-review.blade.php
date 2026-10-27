@php
    $ar = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $arabic, string $english): string => $ar ? $arabic : $english;
    $qty = static fn ($value): string => \App\Support\ProductQuantity::format($value);
    $field = 'mt-1 w-full rounded-xl border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900';
@endphp
<x-layouts.app :title="$t('المراجعة النهائية للتحويل', 'Final transfer review')">
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6" data-transfer-approval-review>
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-bold">{{ $t('المراجعة النهائية للتحويل', 'Final transfer review') }}</h1><p class="text-sm text-zinc-600">{{ $transfer->transfer_number }}</p></div>
            <a href="{{ route('inventory.transfers') }}" class="text-sm font-semibold text-sky-700">{{ $t('العودة إلى التحويلات', 'Back to transfers') }}</a>
        </header>
        @if(session('error') || $errors->any())<div role="alert" class="rounded-xl bg-rose-50 p-3 text-rose-800">{{ session('error') ?: $errors->first() }}</div>@endif
        <p class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">{{ $t('يمكن تعديل التحويل هنا. الاعتماد يحفظ التعديلات ويرسل الكميات من المصدر فورًا، ثم تصبح الوجهات جاهزة للاستلام الفعلي.', 'Edit the transfer here. Approval saves the final values, dispatches stock from the source immediately, and enables destination receiving.') }}</p>
        <form method="POST" action="{{ route('inventory.transfers.approve', $transfer) }}" class="space-y-5" x-data="{ source: @js((string) old('source_store_id', $transfer->source_store_id)) }" data-product-line-editor data-next-index="{{ max(1, count($reviewLines)) }}">
            @csrf
            <input type="hidden" name="review_version" value="{{ old('review_version', $transfer->lock_version) }}">
            <section class="grid gap-4 rounded-xl border bg-white p-4 dark:bg-zinc-900 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ $t('المخزن المصدر', 'Source warehouse') }}
                    <select name="source_store_id" class="{{ $field }}" required data-transfer-source id="transfer-review-source" x-model="source">
                        @foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected((int) old('source_store_id', $transfer->source_store_id) === (int) $store->id)>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">{{ $t('الوجهة الأساسية', 'Primary destination') }}
                    <select name="destination_store_id" class="{{ $field }}" required>
                        @foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected((int) old('destination_store_id', $transfer->destination_store_id) === (int) $store->id)>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">{{ $t('سبب التحويل', 'Transfer reason') }}<input name="reason_code" value="{{ old('reason_code', $transfer->reason_code) }}" class="{{ $field }}" maxlength="100" required></label>
                <label class="text-sm font-semibold">{{ $t('مرجع الفاتورة', 'Invoice reference') }}<input name="invoice_reference" value="{{ old('invoice_reference', $transfer->invoice_reference) }}" class="{{ $field }}" maxlength="120"></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ $t('ملاحظات', 'Notes') }}<textarea name="reason_notes" class="{{ $field }}" maxlength="1000" rows="2">{{ old('reason_notes', $transfer->notes) }}</textarea></label>
            </section>
            <section class="space-y-3 rounded-xl border bg-white p-4 dark:bg-zinc-900">
                <h2 class="font-bold">{{ $t('المنتجات والوجهات', 'Products and destinations') }}</h2>
                @foreach($reviewLines as $index => $line)
                    @php $product = $reviewProducts->get((int) ($line['product_id'] ?? 0)); @endphp
                    <div class="grid gap-3 rounded-xl border p-3 sm:grid-cols-2" data-product-line>
                        <x-product-line-lookup :name="'lines['.$index.'][product_id]'" :value="$line['product_id'] ?? ''" :display="$product ? (\App\Support\HumanName::name($product).' · '.$product->item_code) : ''" :label="$t('المنتج', 'Product')" :required="true" :transfer="true" source-store-selector="#transfer-review-source" />
                        <label class="text-sm font-semibold">{{ $t('الكمية المطلوبة', 'Requested quantity') }}<input name="lines[{{ $index }}][quantity_requested]" value="{{ $qty($line['quantity_requested'] ?? 0) }}" type="number" min="1" step="1" required inputmode="numeric" class="{{ $field }}" data-line-quantity></label>
                        <div class="grid gap-2 sm:col-span-2 sm:grid-cols-3">
                            @foreach($visibleStores as $store)
                                    <label class="text-xs font-semibold">{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}<input name="lines[{{ $index }}][allocations][{{ $store->id }}]" value="{{ $qty($line['allocations'][$store->id] ?? ($transfer->lines->get($index)?->allocations->isEmpty() && (int) $store->id === (int) $transfer->destination_store_id ? ($line['quantity_requested'] ?? 0) : 0)) }}" type="number" min="0" step="1" inputmode="numeric" class="{{ $field }}" :disabled="source === @js((string) $store->id)"></label>
                            @endforeach
                        </div>
                        <button type="button" data-remove-line class="w-fit text-sm font-semibold text-rose-700">{{ $t('حذف البند', 'Remove line') }}</button>
                    </div>
                @endforeach
                <template data-line-template>
                    <div class="grid gap-3 rounded-xl border p-3 sm:grid-cols-2" data-product-line>
                        <x-product-line-lookup name="lines[__INDEX__][product_id]" :label="$t('المنتج', 'Product')" :required="true" :transfer="true" source-store-selector="#transfer-review-source" />
                        <label class="text-sm font-semibold">{{ $t('الكمية المطلوبة', 'Requested quantity') }}<input name="lines[__INDEX__][quantity_requested]" value="1" type="number" min="1" step="1" required inputmode="numeric" class="{{ $field }}" data-line-quantity></label>
                        <div class="grid gap-2 sm:col-span-2 sm:grid-cols-3">@foreach($visibleStores as $store)<label class="text-xs font-semibold">{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}<input name="lines[__INDEX__][allocations][{{ $store->id }}]" value="0" type="number" min="0" step="1" inputmode="numeric" class="{{ $field }}" :disabled="source === @js((string) $store->id)"></label>@endforeach</div>
                        <button type="button" data-remove-line class="w-fit text-sm font-semibold text-rose-700">{{ $t('حذف البند', 'Remove line') }}</button>
                    </div>
                </template>
                <button type="button" data-add-line class="text-sm font-semibold text-sky-700 underline">{{ $t('إضافة بند', 'Add line') }}</button>
            </section>
            <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('inventory.transfers') }}" class="rounded-xl border px-5 py-2 font-semibold">{{ $t('إلغاء', 'Cancel') }}</a><button class="rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white">{{ $t('اعتماد التحويل', 'Approve transfer') }}</button></div>
        </form>
    </main>
</x-layouts.app>
