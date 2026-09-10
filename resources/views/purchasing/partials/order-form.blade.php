@if (! $fullPage)
    <div>
        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Edit Draft Purchase Order') }}</h2>
        <p class="mt-1 text-xs text-zinc-500">{{ __('Enter supplier, destination store, order dates, and item lines.') }}</p>
    </div>
@endif

<form wire:submit.prevent="saveOrder" class="space-y-6" data-product-line-editor data-autofocus="true">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <flux:select wire:model.live="orderForm.supplier_id" :label="__('Supplier') . ' *'">
            <option value="">{{ __('Select Supplier') }}</option>
            @foreach ($suppliers as $sup)
                <option value="{{ $sup->id }}">{{ app()->getLocale() === 'ar' ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
            @endforeach
        </flux:select>

        <flux:select wire:model="orderForm.store_id" :label="__('Receiving Store / Warehouse')">
            <option value="">{{ __('Select Store (Optional)') }}</option>
            @foreach ($stores as $st)
                <option value="{{ $st->id }}">{{ app()->getLocale() === 'ar' ? $st->name_ar : $st->name_en }} ({{ $st->code }})</option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model="orderForm.order_date" :label="__('Order Date') . ' *'" />
        <flux:input type="date" wire:model="orderForm.expected_delivery_date" :label="__('Expected Delivery Date')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <flux:input wire:model.live="orderForm.payment_terms" :label="__('Payment Terms')" :placeholder="__('e.g. Net 30 days')" />
            <p class="mt-1 text-xs text-zinc-500">{{ __('Selecting a supplier fills its saved default. You may replace it explicitly for this draft; later supplier changes will not overwrite your edit.') }}</p>
        </div>
        <flux:input wire:model="orderForm.notes" :label="__('Order Notes')" :placeholder="__('Internal procurement reference notes...')" />
    </div>

    <div class="space-y-3 border-t border-border pt-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Order Line Items') }}</h3>
            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                <flux:checkbox wire:model.live="supplierProductsOnly" :label="__('Use supplier products only')" :disabled="blank($orderForm['supplier_id'])" />
                <flux:button type="button" size="xs" variant="subtle" icon="plus" wire:click="addLine" data-add-line :disabled="blank($orderForm['supplier_id'])">{{ __('Add item') }}</flux:button>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($lineItems as $index => $item)
                <div class="grid grid-cols-12 items-end gap-2 rounded-lg border border-border bg-zinc-50 p-3 dark:bg-zinc-800/40" data-product-line wire:key="order-line-{{ $index }}">
                    <div class="col-span-12 lg:col-span-5">
                        @php($selectedProduct = $products->firstWhere('id', (int) ($item['product_id'] ?? 0)))
                        <x-product-line-lookup wire:model.live="lineItems.{{ $index }}.product_id" :value="$item['product_id'] ?? ''" :display="$selectedProduct ? ((app()->getLocale() === 'ar' ? $selectedProduct->name_ar : ($selectedProduct->name_en ?: $selectedProduct->name_ar)).' · '.$selectedProduct->item_code) : ''" :required="true" :purchasing="true" :supplier-id="$orderForm['supplier_id']" :supplier-only="$supplierProductsOnly" :disabled="blank($orderForm['supplier_id'])" x-on:product-selected="$wire.selectOrderProduct({{ $index }}, $event.detail.id)" />
                    </div>

                    <div class="col-span-5 sm:col-span-3 lg:col-span-2">
                        <label class="text-xs font-semibold">{{ __('Quantity') }}</label>
                        <input data-line-quantity type="number" step="1" min="1" wire:model.live="lineItems.{{ $index }}.quantity_ordered" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                    </div>

                    <div class="col-span-7 sm:col-span-4 lg:col-span-2">
                        <label class="text-xs font-semibold">{{ __('Unit cost') }}</label>
                        <input data-line-price data-price-kind="cost" type="number" step="0.0001" min="0" wire:model.live="lineItems.{{ $index }}.unit_cost" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                        <p class="mt-1 text-[11px] text-zinc-500" data-price-source>{{ match($item['price_source'] ?? 'none') { 'last_supplier_price' => __('Last supplier price'), 'fallback_cost' => __('Fallback product cost'), 'saved_draft_cost' => __('Saved draft cost'), default => __('No saved supplier price or fallback cost') } }}@if(filled($item['price_date'] ?? '')) · {{ $item['price_date'] }}@endif @if(filled($item['price_currency'] ?? '')) · {{ $item['price_currency'] }}@endif</p>
                    </div>

                    <div class="col-span-10 sm:col-span-4 lg:col-span-2">
                        <span class="block text-xs font-semibold">{{ __('Line total') }}</span>
                        <output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-white px-3 font-mono text-sm dark:bg-zinc-900" data-line-total>0.00</output>
                    </div>

                    <div class="col-span-2 text-end lg:col-span-1">
                        @if (count($lineItems) > 1)
                            <flux:button type="button" size="xs" variant="ghost" icon="trash" class="text-rose-600" wire:click="removeLine({{ $index }})" />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end pt-2">
            <div class="rounded-lg bg-zinc-100 px-4 py-2 text-end dark:bg-zinc-800">
                <span class="text-xs text-zinc-500">{{ __('Estimated Subtotal') }}: </span>
                <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($formSubtotal, 2) }}</span>
                <span class="mt-0.5 block text-xs text-zinc-400">{{ __('Tax is not configured') }}</span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
        @if ($fullPage)
            <flux:button variant="ghost" href="{{ route('purchasing.orders') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
        @else
            <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
        @endif
        <flux:button variant="primary" type="submit">{{ __('Save Draft') }}</flux:button>
    </div>
</form>
