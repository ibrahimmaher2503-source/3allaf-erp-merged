@props([
    'status' => 'draft',
    'label' => null,
    'size' => 'sm',
    'variant' => 'subtle',
])

@php
    $normalized = strtolower(trim((string)$status));

    $colorMap = [
        'active' => 'green',
        'open' => 'green',
        'healthy' => 'green',
        'approved' => 'green',
        'completed' => 'green',
        'received' => 'green',
        'reconciled' => 'green',
        'success' => 'green',
        'online' => 'green',

        'draft' => 'zinc',
        'inactive' => 'zinc',
        'closed' => 'zinc',
        'archived' => 'zinc',
        'cancelled' => 'zinc',
        'void' => 'zinc',

        'pending' => 'amber',
        'submitted' => 'amber',
        'pending_approval' => 'amber',
        'difference_review' => 'amber',
        'suspended' => 'amber',
        'degraded' => 'amber',
        'warning' => 'amber',
        'review' => 'amber',
        'offline' => 'amber',
        'reserved' => 'amber',
        'maintenance' => 'amber',

        'rejected' => 'red',
        'damaged' => 'red',
        'expired' => 'red',
        'down' => 'red',
        'failed' => 'red',
        'error' => 'red',
        'disabled' => 'red',

        'processing' => 'blue',
        'queued' => 'blue',
        'assigned' => 'blue',
        'in_transit' => 'blue',
        'in_progress' => 'blue',
        'partially_received' => 'blue',
        'sent' => 'blue',

        'override' => 'purple',
        'special' => 'purple',
    ];

    $color = $colorMap[$normalized] ?? 'zinc';

    $defaultLabels = [
        'active' => __('Active'),
        'open' => __('Open'),
        'healthy' => __('Healthy'),
        'approved' => __('Approved'),
        'completed' => __('Completed'),
        'received' => __('Received'),
        'reconciled' => __('Reconciled'),
        'success' => __('Success'),
        'online' => __('Online'),
        'draft' => __('Draft'),
        'inactive' => __('Inactive'),
        'closed' => __('Closed'),
        'archived' => __('Archived'),
        'cancelled' => __('Cancelled'),
        'void' => __('Voided'),
        'pending' => __('Pending'),
        'submitted' => __('Submitted'),
        'pending_approval' => __('Pending approval'),
        'difference_review' => __('Difference review'),
        'suspended' => __('Suspended'),
        'degraded' => __('Degraded'),
        'warning' => __('Warning'),
        'review' => __('In Review'),
        'offline' => __('Offline'),
        'reserved' => __('Reserved'),
        'maintenance' => __('Maintenance'),
        'rejected' => __('Rejected'),
        'damaged' => __('Damaged'),
        'expired' => __('Expired'),
        'down' => __('Down'),
        'failed' => __('Failed'),
        'error' => __('Error'),
        'disabled' => __('Disabled'),
        'processing' => __('Processing'),
        'queued' => __('Queued'),
        'assigned' => __('Assigned'),
        'in_transit' => __('In transit'),
        'in_progress' => __('In progress'),
        'partially_received' => __('Partially received'),
        'sent' => __('Sent'),
        'override' => __('Override'),
    ];

    $displayLabel = $label ?? $defaultLabels[$normalized] ?? __(ucfirst((string) $status));
@endphp

<flux:badge :size="$size" :color="$color" :variant="$variant" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-medium']) }}>
    <span class="size-1.5 rounded-full bg-current opacity-75"></span>
    <span>{{ $displayLabel }}</span>
</flux:badge>
