<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Party bookings and invoices will appear here when the workflow is available.')"
        :cards="$items"
        :empty="__('No party bookings or working invoices are available yet.')"
        guide-prefix="party-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
