@props([
    'name' => null,
    'value' => '',
    'label' => __('Product'),
    'placeholder' => __('Scan barcode or search product'),
    'required' => false,
    'valueField' => 'id',
    'display' => '',
    'purchasing' => false,
    'supplierId' => '',
    'supplierOnly' => true,
    'currencyCode' => '',
    'companyId' => '',
    'purchaseInvoiceId' => '',
    'disabled' => false,
    'resultPresentation' => 'full',
    'transfer' => false,
    'sourceStoreSelector' => '',
    'labelVisuallyHidden' => false,
])

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }}
    data-product-lookup
    data-search-url="{{ route('transaction-products.search') }}"
    data-value-field="{{ $valueField }}"
    data-context="{{ $transfer ? 'transfer' : ($purchasing ? 'purchasing' : 'transaction') }}"
    data-source-store-selector="{{ $sourceStoreSelector }}"
    data-supplier-id="{{ $supplierId }}"
    data-supplier-only="{{ $supplierOnly ? '1' : '0' }}"
    data-currency-code="{{ $currencyCode }}"
    data-company-id="{{ $companyId }}"
    data-purchase-invoice-id="{{ $purchaseInvoiceId }}"
    data-result-presentation="{{ $resultPresentation }}"
    data-message-empty="{{ __('No matching products.') }}"
    data-message-loading="{{ __('Searching products…') }}"
    data-message-error="{{ __('Product search could not be completed. Try again.') }}"
    data-message-supplier-required="{{ __('Select a supplier before adding products.') }}"
    data-message-source-required="{{ __('Select a source store before adding products.') }}"
    data-message-supplier-empty="{{ __('This supplier has no associated products. Uncheck “Use supplier products only” to search all authorized products.') }}"
    data-label-unit="{{ __('Unit') }}"
    data-label-supplier-code="{{ __('Supplier product code') }}"
    data-label-last-price="{{ __('Last supplier price') }}"
    data-label-fallback-price="{{ __('Current product cost') }}"
    data-label-no-price="{{ __('No current product cost') }}"
>
    <label class="{{ $labelVisuallyHidden ? 'sr-only' : 'mb-1 block text-xs font-semibold' }}" data-product-label>{{ $label }}</label>
    <input
        type="hidden"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        data-product-id
        {{ $attributes->whereStartsWith('wire:model') }}
    >
    <div class="flex items-stretch gap-1.5 {{ $labelVisuallyHidden ? 'mt-1' : '' }}">
        <div class="relative min-w-0 flex-1">
            <input
                type="search"
                role="combobox"
                aria-label="{{ $label }}"
                aria-autocomplete="list"
                aria-expanded="false"
                autocomplete="off"
                dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}"
                @if($required) required @endif
                @if($disabled) disabled @endif
                placeholder="{{ $disabled ? __('Select a supplier before adding products.') : $placeholder }}"
                value="{{ $display }}"
                class="block h-10 w-full appearance-none rounded-lg border border-zinc-300 bg-white px-3 {{ str_starts_with(app()->getLocale(), 'ar') ? 'text-right' : 'text-left' }} text-sm [unicode-bidi:plaintext] outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/15 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                data-product-search
                @if(filled($value)) data-selected="true" @endif
            >
        </div>
    </div>
    <div class="absolute z-40 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900" role="listbox" aria-live="polite" data-product-results></div>
    <p class="mt-1 hidden text-xs font-semibold text-rose-600" role="alert" data-product-error>{{ __('Select a product from the search results.') }}</p>
</div>
