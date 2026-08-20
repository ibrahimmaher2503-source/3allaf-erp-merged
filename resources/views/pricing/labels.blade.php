<x-app.page
    :title="__('Price labels')"
    :description="__('Prepare and review price labels before printing.')"
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

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Label queue readiness') }}</flux:heading>
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
            <flux:table aria-label="{{ __('Label queue readiness') }}">
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
                            <flux:table.cell>
                                <div class="font-medium">{{ $queue->product?->item_code ?? __('Unknown product') }}</div>
                                <div class="text-xs text-zinc-500">{{ $queue->product?->name_en ?? $queue->product?->name_ar ?? __('Product name unavailable') }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $queue->store?->code ?? __('Store unavailable') }}</flux:table.cell>
                            <flux:table.cell>{{ $queue->printer?->name ?? __('Not assigned') }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $queue->template_name ?: ($queue->printer?->template_name ?? __('Not assigned')) }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ __(str_replace('_', ' ', ucfirst((string) $queue->status))) }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-app.page>
