<x-layouts::app :title="__('Purchase invoices')">
    <x-app.page
        :title="__('Purchase invoices')"
        :description="__('Review purchase invoices and receiving when the related workflow is available.')"
        max-width="6xl"
        class="purchasing-screen"
    >
        <x-slot:actions>
            @can('company_settings.view')
                <flux:button href="{{ route('purchasing.invoices.settings') }}" variant="subtle" icon="adjustments-horizontal">
                    {{ __('Invoice settings') }}
                </flux:button>
            @endcan
            <flux:button href="{{ route('purchasing.orders') }}" variant="subtle" icon="arrow-left">
                {{ __('Back to purchase orders') }}
            </flux:button>
        </x-slot:actions>

        <x-state.capability-boundary
            :title="__('Purchase invoices')"
            :description="__('Purchase invoice and receiving workflows are not available in this workspace yet.')"
            :boundary="__('Invoice and receiving records will appear here when available.')"
            :empty-title="__('No purchase invoices are available yet.')"
            :empty="__('Return to purchase orders to review existing procurement records.')"
            guide-prefix="purchase-invoices"
        />
    </x-app.page>
</x-layouts::app>
