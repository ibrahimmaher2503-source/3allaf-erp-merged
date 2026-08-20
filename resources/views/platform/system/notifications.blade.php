<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component
{
    public function refresh(): void
    {
        // Delivery sources are not configured in this local baseline. The explicit
        // refresh state keeps the screen ready for a scoped notification source
        // without claiming any unseen alerts were delivered.
    }
}; ?>

<x-app.page :title="__('Notifications')" :description="__('Role-scoped operational alerts appear here when a configured source delivers them.')" max-width="5xl" class="space-y-6" data-guide="notifications-header">
    <x-slot:actions><flux:button icon="arrow-path" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh">{{ __('Refresh') }}</flux:button></x-slot:actions>
    <div wire:loading.flex wire:target="refresh" role="status" aria-live="polite" class="items-center gap-2 text-sm text-text-muted"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Checking notifications…') }}</div>
    <flux:card wire:loading.remove wire:target="refresh"><x-state.empty :title="__('No notifications')" :description="__('There are no delivered operational alerts in your current authorized scope.')" icon="bell" /></flux:card>
</x-app.page>
