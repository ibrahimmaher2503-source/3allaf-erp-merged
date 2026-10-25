@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $lines = array_values(old('lines', [['identifier' => '', 'quantity' => '1']]));
@endphp
<x-layouts::app :title="$isArabic ? 'فاتورة مبيعات جديدة' : 'New sales invoice'">
    <x-app.page class="internal-page" :title="$isArabic ? 'فاتورة مبيعات جديدة' : 'New sales invoice'" :description="$isArabic ? 'أنشئ مسودة فاتورة مبيعات عادية مستقلة عن نقطة البيع. لا تُرحّل الكميات أو المدفوعات عند حفظ المسودة.' : 'Create an ordinary sales invoice draft independently of POS. Saving does not post stock or payments.'" :breadcrumbs="$isArabic ? 'المبيعات / فواتير المبيعات / فاتورة جديدة' : 'Sales / Sales invoices / New invoice'">
        <x-slot:actions>@can('pos_sales.view')<flux:button href="{{ route('sales.invoices') }}" variant="subtle" wire:navigate>{{ $isArabic ? 'فواتير المبيعات' : 'Sales invoices' }}</flux:button>@endcan</x-slot:actions>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">{{ $errors->first() }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('sales.invoices.drafts.store') }}" class="space-y-4" x-data="{ next: {{ count($lines) }} }">
            @csrf
            <input type="hidden" name="creation_token" value="{{ old('creation_token', $creationToken) }}">
            <section class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select name="store_id" :label="$isArabic ? 'منفذ البيع' : 'Selling store'" required>
                        <flux:select.option value="">{{ $isArabic ? 'اختر منفذ البيع' : 'Choose a selling store' }}</flux:select.option>
                        @foreach ($stores as $store)
                            <flux:select.option value="{{ $store->id }}" :selected="(string) old('store_id', $defaultStoreId) === (string) $store->id">{{ $isArabic ? $store->name_ar : $store->name_en }} · {{ $store->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="self-end text-sm text-zinc-600 dark:text-zinc-300">{{ $isArabic ? 'رقم الفاتورة يُخصّص بعد الاعتماد. تحفظ هذه الخطوة مسودة فقط.' : 'The invoice number is assigned on approval. This step saves a draft only.' }}</div>
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="font-bold">{{ $isArabic ? 'المنتجات' : 'Products' }}</h2><flux:button type="button" variant="subtle" icon="plus" x-on:click="$refs.rows.insertAdjacentHTML('beforeend', $refs.lineTemplate.innerHTML.replaceAll('__INDEX__', next++))">{{ $isArabic ? 'إضافة سطر' : 'Add line' }}</flux:button></div>
                <p class="mb-3 text-sm text-zinc-500">{{ $isArabic ? 'أدخل كود المنتج أو الباركود والكمية المطلوبة. سيُتحقق من المنتج والسعر ضمن منفذ البيع المختار.' : 'Enter the product code or barcode and quantity. Product and price are checked for the selected selling store.' }}</p>
                <div class="space-y-2" x-ref="rows">
                    @foreach ($lines as $index => $line)
                        <div class="grid min-w-0 gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]" data-invoice-line>
                            <label class="min-w-0 text-sm font-medium">{{ $isArabic ? 'كود المنتج / الباركود' : 'Product code / barcode' }}<input required name="lines[{{ $index }}][identifier]" value="{{ $line['identifier'] ?? '' }}" class="mt-1 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" autocomplete="off"></label>
                            <label class="min-w-0 text-sm font-medium">{{ $isArabic ? 'الكمية' : 'Quantity' }}<input required name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" type="number" min="1" step="1" dir="ltr" class="mt-1 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
                            <button type="button" class="self-end rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700" aria-label="{{ $isArabic ? 'حذف السطر' : 'Remove line' }}" x-on:click="if ($refs.rows.querySelectorAll('[data-invoice-line]').length > 1) $el.closest('[data-invoice-line]').remove()">×</button>
                        </div>
                    @endforeach
                </div>
                <template x-ref="lineTemplate"><div class="grid min-w-0 gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]" data-invoice-line>
                    <label class="min-w-0 text-sm font-medium">{{ $isArabic ? 'كود المنتج / الباركود' : 'Product code / barcode' }}<input required name="lines[__INDEX__][identifier]" class="mt-1 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" autocomplete="off"></label>
                    <label class="min-w-0 text-sm font-medium">{{ $isArabic ? 'الكمية' : 'Quantity' }}<input required name="lines[__INDEX__][quantity]" value="1" type="number" min="1" step="1" dir="ltr" class="mt-1 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
                    <button type="button" class="self-end rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700" aria-label="{{ $isArabic ? 'حذف السطر' : 'Remove line' }}" x-on:click="$el.closest('[data-invoice-line]').remove()">×</button>
                </div></template>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:textarea name="notes" :label="$isArabic ? 'ملاحظات' : 'Notes'">{{ old('notes') }}</flux:textarea>
            </section>
            <div class="flex justify-end"><flux:button type="submit" variant="primary" icon="document-check">{{ $isArabic ? 'حفظ مسودة الفاتورة' : 'Save invoice draft' }}</flux:button></div>
        </form>
    </x-app.page>
</x-layouts::app>
