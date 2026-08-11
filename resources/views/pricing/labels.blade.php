<x-app.page
    :title="__('Price labels')"
    :description="__('Prepare and review price labels before printing.')"
    max-width="6xl"
    class="pricing-screen"
>
    <x-slot:actions>
        <flux:button href="{{ route('pricing.index') }}" variant="subtle" icon="arrow-left">
            {{ __('Back to pricing') }}
        </flux:button>
    </x-slot:actions>

    <x-state.capability-boundary
        :title="__('Price labels')"
        :description="__('Price label printing is not available in this workspace yet.')"
        :boundary="__('Price label jobs will appear here when printing is available.')"
        :empty-title="__('No price label jobs are available yet.')"
        :empty="__('Return to pricing to review approved prices.')"
        guide-prefix="price-labels"
    />
</x-app.page>
