<flux:card
    class="space-y-5"
    data-label-workspace
    x-data="labelWorkspace(@js($initial), @js(route('pricing.labels.search')), @js(route('pricing.labels.prices')))"
    x-init="init()"
>
    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 space-y-3" data-barcode-document-source>
        <label class="block text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'طريقة الطباعة' : 'Print mode' }}
            <select class="mt-1 w-full rounded-lg border-zinc-300" x-on:change="window.location.href = @js(route('pricing.labels')) + ($event.target.value === 'product' ? '' : '?source=' + $event.target.value)">
                <option value="purchase_invoice" @selected(request('source') === 'purchase_invoice')>{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود منتجات فاتورة شراء' : 'Purchase Invoice items' }}</option>
                <option value="stock_transfer" @selected(request('source') === 'stock_transfer')>{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود منتجات تحويل' : 'Transfer items' }}</option>
                <option value="product" @selected(! in_array(request('source'), ['purchase_invoice', 'stock_transfer'], true))>{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود منتج فردي' : 'Individual Product' }}</option>
            </select>
        </label>
        @if(in_array(request('source'), ['purchase_invoice', 'stock_transfer'], true))
            <form method="GET" action="{{ route('pricing.labels') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                <input type="hidden" name="source" value="{{ request('source') }}">
                <label class="text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'رقم المستند' : 'Document number' }}<input name="number" value="{{ request('number') }}" class="mt-1 w-full rounded-lg border-zinc-300" required></label>
                <button class="rounded-xl bg-sky-700 px-4 py-2 font-bold text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحميل المنتجات' : 'Load products' }}</button>
            </form>
            @if($documentDestinations->count() > 1)
                <div class="grid gap-2 sm:grid-cols-2" aria-label="{{ __('Transfer destinations') }}">
                    @foreach($documentDestinations as $receiptDestination)
                        <a class="rounded-lg border border-sky-300 bg-white px-3 py-2 text-sm font-semibold text-sky-800" href="{{ route('pricing.labels', ['source' => 'stock_transfer', 'number' => request('number'), 'receipt_destination_id' => $receiptDestination->id]) }}">{{ \App\Support\HumanName::name($receiptDestination) }} · {{ $receiptDestination->code }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
    <div class="flex items-center gap-2">
        <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'البحث عن المنتجات واختيارها' : 'Find and select products' }}</flux:heading>
        <x-context-help :title="__('Find and select products')" :label="__('Open contextual help')"><p>{{ __('Nothing is loaded until you search. Selected products and print settings remain while you continue searching.') }}</p></x-context-help>
    </div>

    @unless(in_array(request('source'), ['purchase_invoice', 'stock_transfer'], true))
        @include('pricing.labels.search')
    @endunless
    @include('pricing.labels.selected-products')
</flux:card>
