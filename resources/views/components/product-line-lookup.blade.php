@props([
    'name' => null,
    'value' => '',
    'label' => __('Product'),
    'placeholder' => __('Scan barcode or search product'),
    'required' => false,
    'valueField' => 'id',
    'display' => '',
])

<div {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }} data-product-lookup data-search-url="{{ route('transaction-products.search') }}" data-value-field="{{ $valueField }}">
    <label class="mb-1 block text-xs font-semibold" data-product-label>{{ $label }}</label>
    <input
        type="hidden"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        data-product-id
        {{ $attributes->whereStartsWith('wire:model') }}
    >
    <input
        type="search"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        autocomplete="off"
        dir="auto"
        @if($required) required @endif
        placeholder="{{ $placeholder }}"
        value="{{ $display }}"
        class="block h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/15 dark:border-zinc-700 dark:bg-zinc-900"
        data-product-search
        @if(filled($value)) data-selected="true" @endif
    >
    <div class="absolute z-40 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900" role="listbox" data-product-results></div>
    <p class="mt-1 hidden text-xs font-semibold text-rose-600" role="alert" data-product-error>{{ __('Select a product from the search results.') }}</p>
</div>
