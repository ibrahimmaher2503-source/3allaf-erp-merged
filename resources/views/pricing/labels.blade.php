<x-app.page
    :title="app()->getLocale() === 'ar' ? 'الباركود وملصقات الأسعار' : __('Barcodes & price labels')"
    :description="app()->getLocale() === 'ar' ? 'راجع هوية المنتج وسياق الطباعة والطابور قبل إنشاء المخرجات.' : __('Review product identity, print context, and the queue before producing output.')"
    :breadcrumbs="app()->getLocale() === 'ar' ? 'المنتجات والمخزون' : 'Products & inventory'"
    max-width="6xl"
    class="pricing-screen"
    data-guide="price-labels-workspace"
>
    <x-slot:actions>
        <flux:button href="{{ route('pricing.index') }}" variant="subtle" icon="arrow-left">
            {{ __('Back to pricing') }}
        </flux:button>
    </x-slot:actions>

    <flux:callout variant="info" icon="information-circle" title="{{ __('Printer and template selection') }}">
        {{ __('A printer profile describes the destination. A print template key describes the layout. Each queued label keeps its branch/store, printer profile, and template key together so an authorized print workflow can use the saved default or an explicitly selected compatible profile.') }}
    </flux:callout>

    @if ($printerCount === 0)
        <flux:callout variant="warning" icon="printer" title="{{ __('No active printer profiles available') }}">
            {{ __('Create an active printer profile and choose its compatible print-template key before preparing label output.') }}
            <x-slot:actions>
                <flux:button href="{{ route('admin.settings', ['tab' => 'printers']) }}" variant="primary" icon="cog-6-tooth">
                    {{ __('Configure printer profiles') }}
                </flux:button>
            </x-slot:actions>
        </flux:callout>
    @endif

    @can('pricing_labels.create')
    <flux:card class="space-y-5" data-label-workspace>
        <div><flux:heading size="lg">{{ __('Create barcode labels') }}</flux:heading><flux:subheading>{{ __('Search products, choose their actual barcode and output context, then preview or download exact labels.') }}</flux:subheading></div>
        <form method="GET" action="{{ route('pricing.labels') }}" class="flex flex-col gap-3 sm:flex-row"><flux:input name="search" :value="$search" :label="__('Product search')" :placeholder="__('Arabic/English name, item code, model, or exact barcode')" class="flex-1"/><div class="flex items-end"><flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button></div></form>
        <form method="POST" action="{{ route('pricing.labels.preview') }}" target="_blank" class="space-y-4">@csrf
            <div class="grid gap-3 md:grid-cols-3"><flux:select name="store_id" :label="__('Selling outlet')" required><flux:select.option value="">{{ __('Select outlet') }}</flux:select.option>@foreach($stores as $store)<flux:select.option value="{{ $store->id }}">{{ app()->getLocale()==='ar'?$store->name_ar:$store->name_en }} · {{ $store->code }}</flux:select.option>@endforeach</flux:select><flux:select name="template_id" :label="__('Print template')" required><flux:select.option value="">{{ __('Select template') }}</flux:select.option>@foreach($templates as $template)<flux:select.option value="{{ $template->id }}">{{ app()->getLocale()==='ar'?$template->name_ar:$template->name_en }} · {{ __(strtoupper($template->paper_size)) }}</flux:select.option>@endforeach</flux:select><flux:select name="printer_id" :label="__('Printer profile')"><flux:select.option value="">{{ __('PDF / browser print') }}</flux:select.option>@foreach($printers as $printer)<flux:select.option value="{{ $printer->id }}">{{ $printer->name }} · {{ __(strtoupper($printer->paper_size)) }}</flux:select.option>@endforeach</flux:select></div>
            <div class="grid gap-3 lg:grid-cols-2">@forelse($products as $index=>$product)<article class="rounded-xl border border-border p-3"><label class="flex items-start gap-3"><input type="checkbox" name="selected[]" value="{{ $index }}" class="mt-1"><input type="hidden" name="products[{{ $index }}][id]" value="{{ $product->id }}"><span class="min-w-0"><strong>{{ app()->getLocale()==='ar'?$product->name_ar:$product->name_en }}</strong><small class="block text-text-muted" dir="ltr">{{ $product->item_code }} · {{ $product->model_number }}</small></span></label><div class="mt-3 grid grid-cols-2 gap-2"><flux:select name="products[{{ $index }}][barcode_id]" :label="__('Barcode')">@foreach($product->barcodes as $barcode)<flux:select.option value="{{ $barcode->id }}" :selected="$barcode->is_primary">{{ $barcode->barcode }}{{ $barcode->is_primary?' · '.__('Default'):'' }}</flux:select.option>@endforeach</flux:select><flux:input name="products[{{ $index }}][copies]" type="number" min="1" max="500" step="1" value="1" :label="__('Copies')"/></div></article>@empty<x-state.empty :title="__('No active products match the search')"/>@endforelse</div>
            <div class="flex flex-wrap justify-end gap-2"><flux:button type="submit" name="output" value="html" variant="primary" icon="eye">{{ __('Preview labels') }}</flux:button><flux:button type="submit" name="output" value="pdf" variant="subtle" icon="arrow-down-tray">{{ __('Download PDF') }}</flux:button></div>
        </form>
    </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Generated label queue') }}</flux:heading>
                <flux:subheading>{{ __('Review the saved output context before an authorized print action. This screen does not test hardware.') }}</flux:subheading>
            </div>
            <flux:badge size="sm" color="zinc">{{ $queues->count() }} {{ __('queued jobs') }}</flux:badge>
        </div>

        @if ($queues->isEmpty())
            <x-state.empty
                :title="__('No label jobs are queued yet')"
                :description="__('Approve a price and return here to prepare a label job. A job is not created just because a printer profile exists.')"
                icon="printer"
            >
                <x-slot:action><flux:button href="{{ route('pricing.index') }}" variant="subtle" icon="arrow-left">{{ __('Review approved prices') }}</flux:button></x-slot:action>
            </x-state.empty>
        @else
            <flux:table class="responsive-resource-table" aria-label="{{ __('Label queue readiness') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column>{{ __('Branch / store') }}</flux:table.column>
                    <flux:table.column>{{ __('Printer profile') }}</flux:table.column>
                    <flux:table.column>{{ __('Print template key') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($queues as $queue)
                        <flux:table.row key="label-queue-{{ $queue->id }}" data-label-queue="{{ $queue->id }}">
                            <flux:table.cell data-primary>
                                <div class="font-medium">{{ $queue->product?->item_code ?? __('Unknown product') }}</div>
                                <div class="text-xs text-zinc-500">{{ $queue->product?->name_en ?? $queue->product?->name_ar ?? __('Product name unavailable') }}</div>
                            </flux:table.cell>
                            <flux:table.cell data-label="{{ __('Branch / store') }}">{{ $queue->store?->code ?? __('Store unavailable') }}</flux:table.cell>
                            <flux:table.cell data-label="{{ __('Printer profile') }}">{{ $queue->printer?->name ?? __('Not assigned') }}</flux:table.cell>
                            <flux:table.cell data-label="{{ __('Print template key') }}" class="font-mono text-xs">{{ $queue->template_name ?: ($queue->printer?->template_name ?? __('Not assigned')) }}</flux:table.cell>
                            <flux:table.cell data-label="{{ __('Status') }}"><flux:badge size="sm" color="zinc">{{ \App\Modules\Platform\Support\UiLabel::status($queue->status) }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-app.page>
