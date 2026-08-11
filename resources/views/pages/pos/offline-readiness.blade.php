<x-layouts::app :title="__('Offline sales')">
    <x-state.capability-boundary
        :title="__('Offline sales')"
        :description="__('Offline selling is not available in this workspace yet. Continue selling from POS while connected.')"
        guide-prefix="offline-sales"
    >
        <x-slot:actions>
            <flux:button href="{{ route('pos') }}" variant="primary" icon="arrow-left">{{ __('Back to POS') }}</flux:button>
        </x-slot:actions>
    </x-state.capability-boundary>
</x-layouts::app>
