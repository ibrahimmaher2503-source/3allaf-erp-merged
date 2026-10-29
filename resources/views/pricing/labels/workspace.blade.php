<flux:card
    class="space-y-5"
    data-label-workspace
    x-data="labelWorkspace(@js($initial), @js(route('pricing.labels.search')), @js(route('pricing.labels.prices')))"
    x-init="init()"
>
    <form method="GET" action="{{ route('pricing.labels') }}" class="grid gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:grid-cols-2" data-barcode-document-source>
        <label class="text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'مصدر المستند' : 'Document source' }}<select name="source" class="mt-1 w-full rounded-lg border-zinc-300" required><option value="purchase_invoice" @selected(request('source') === 'purchase_invoice')>{{ str_starts_with(app()->getLocale(), 'ar') ? 'استدعاء فاتورة شراء' : 'Load purchase invoice' }}</option><option value="stock_transfer" @selected(request('source') === 'stock_transfer')>{{ str_starts_with(app()->getLocale(), 'ar') ? 'استدعاء إذن تحويل' : 'Load stock transfer' }}</option></select></label>
        <label class="text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'رقم المستند' : 'Document number' }}<input name="number" value="{{ request('number') }}" class="mt-1 w-full rounded-lg border-zinc-300" required></label>
        <label class="text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'جهة الاستلام' : 'Receiving destination' }}<select name="destination_store_id" class="mt-1 w-full rounded-lg border-zinc-300" required><option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختر الجهة' : 'Select destination' }}</option>@foreach($documentStores as $store)<option value="{{ $store->id }}" @selected((int) request('destination_store_id') === $store->id)>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'منفذ البيع لتحديد السعر' : 'Selling outlet for price' }}<select name="selling_store_id" class="mt-1 w-full rounded-lg border-zinc-300" required><option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختر المنفذ' : 'Select outlet' }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((int) request('selling_store_id') === $store->id)>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach</select></label>
        <button class="rounded-xl bg-sky-700 px-4 py-2 font-bold text-white sm:col-span-2">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحميل منتجات المستند' : 'Load document products' }}</button>
    </form>
    <div class="flex items-center gap-2">
        <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'البحث عن المنتجات واختيارها' : 'Find and select products' }}</flux:heading>
        <x-context-help :title="__('Find and select products')" :label="__('Open contextual help')"><p>{{ __('Nothing is loaded until you search. Selected products and print settings remain while you continue searching.') }}</p></x-context-help>
    </div>

    @include('pricing.labels.search')
    @include('pricing.labels.selected-products')
</flux:card>
