<article class="rounded-xl border border-border p-4">
    <input type="hidden" :name="`products[${product.id}][product_id]`" :value="product.id">
    <input type="hidden" :name="`products[${product.id}][selected]`" value="1">

    <div class="flex justify-between gap-3">
        <span class="min-w-0">
            <strong class="block truncate" x-text="name(product)"></strong>
            <small dir="ltr" class="text-text-muted" x-text="product.item_code + (product.model_number ? ' · ' + product.model_number : '')"></small>
        </span>
        <button type="button" class="min-h-11 text-red-700" x-on:click="remove(product.id)">
            {{ __('Remove') }}
        </button>
    </div>
    <p x-show="product.document_quantity" class="mt-2 text-sm text-zinc-600">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كمية المستند' : 'Document quantity' }}: <span dir="ltr" x-text="product.document_quantity"></span></p>
    <p x-show="product.resolved_price" class="mt-2 text-sm font-semibold text-emerald-800">{{ str_starts_with(app()->getLocale(), 'ar') ? 'سعر البيع في الوجهة' : 'Destination selling price' }}: <span dir="ltr" x-text="Number(product.resolved_price).toFixed(2)"></span></p>

    <template x-if="primary(product)">
        <div class="mt-3 grid grid-cols-2 gap-3">
            <label>
                {{ __('Barcode') }}
                <input type="hidden" :name="`products[${product.id}][barcode_id]`" :value="primary(product)?.id">
                <span class="mt-1 block rounded-lg border border-border bg-surface px-3 py-2 font-mono" dir="ltr" x-text="primary(product)?.barcode"></span>
            </label>
            <label>
                {{ __('Label copies') }}
                <input
                    :name="`products[${product.id}][copies]`"
                    x-model="product.copies"
                    type="number"
                    min="1"
                    max="500"
                    step="1"
                    required
                    class="mt-1 min-h-11 w-full rounded-lg border border-border bg-surface px-3"
                >
            </label>
        </div>
    </template>

    <template x-if="!primary(product)">
        @include('pricing.labels.missing-barcode')
    </template>
</article>
