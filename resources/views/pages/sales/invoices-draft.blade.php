@php($isArabic = str_starts_with(app()->getLocale(), 'ar'))
<x-layouts::app :title="$isArabic ? 'مسودة فاتورة مبيعات' : 'Sales invoice draft'">
    <x-app.page class="internal-page" :title="$isArabic ? 'مسودة فاتورة مبيعات' : 'Sales invoice draft'" :description="$isArabic ? 'محفوظة دون ترحيل مخزون أو تحصيل مدفوعات.' : 'Saved without posting inventory or payments.'" :breadcrumbs="$isArabic ? 'المبيعات / فواتير المبيعات / مسودة' : 'Sales / Sales invoices / Draft'">
        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap justify-between gap-3"><div><p class="text-sm text-zinc-500">{{ $isArabic ? 'المنفذ' : 'Store' }}</p><strong>{{ $isArabic ? $sale->store->name_ar : $sale->store->name_en }}</strong></div><x-status.badge status="draft" /></div>
            <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[480px] text-sm"><thead><tr class="border-b text-start"><th class="p-2 text-start">{{ $isArabic ? 'المنتج' : 'Product' }}</th><th class="p-2 text-start">{{ $isArabic ? 'الكمية' : 'Quantity' }}</th><th class="p-2 text-start">{{ $isArabic ? 'سعر الوحدة' : 'Unit price' }}</th><th class="p-2 text-start">{{ $isArabic ? 'الإجمالي' : 'Total' }}</th></tr></thead><tbody>@foreach($sale->lines as $line)<tr class="border-b"><td class="p-2">{{ $isArabic ? $line->name_ar : $line->name_en }}<small class="block text-zinc-500" dir="ltr">{{ $line->item_code }}</small></td><td class="p-2" dir="ltr"><x-product-quantity :value="$line->quantity" /></td><td class="p-2" dir="ltr"><x-money :amount="$line->unit_price" /></td><td class="p-2" dir="ltr"><x-money :amount="$line->net_amount" /></td></tr>@endforeach</tbody></table></div>
            <div class="mt-3 text-end font-bold">{{ $isArabic ? 'إجمالي المسودة' : 'Draft total' }}: <span dir="ltr"><x-money :amount="$sale->total" /> {{ $sale->currency_code }}</span></div>
        </section>
    </x-app.page>
</x-layouts::app>
