<x-layouts::app :title="__('Dashboard')">
    <x-app.page
        :title="__('Operations workspace')"
        :description="__('Monitor daily operations and open the workspaces available to your role.')"
        :eyebrow="__('Operations')"
        :badge="__('Operational overview')"
        badge-color="primary"
        max-width="7xl"
        data-guide="dashboard-header"
    >
        @if (($setup['needs_attention'] ?? false) === true)
            <section class="flex flex-col gap-5 rounded-2xl border border-amber-500/25 bg-amber-500/5 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6" data-guide="dashboard-initial-setup">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">{{ __('First launch setup') }}</div>
                    <flux:heading class="mt-2" size="lg">{{ __('Complete initial setup') }}</flux:heading>
                    <flux:text class="mt-1">{{ $setup['completed_count'] }} / {{ $setup['required_count'] }} {{ __('required steps completed') }}. {{ __('Enter owner-provided data before opening operations.') }}</flux:text>
                </div>
                <flux:button :href="route('initial-setup')" variant="primary" wire:navigate data-guide="dashboard-initial-setup-action">
                    {{ __('Open setup dashboard') }}
                </flux:button>
            </section>
        @endif

        <section aria-labelledby="foundation-heading" class="dashboard-status-card overflow-hidden rounded-2xl border border-border bg-surface/95 shadow-card" data-guide="dashboard-foundation">
            <div class="border-b border-border bg-surface-muted/35 px-5 py-5 sm:px-6">
                <flux:heading id="foundation-heading" data-guide="dashboard-foundation-heading" size="lg">{{ __('Operational areas') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Use these areas to move from daily work to review and administration.') }}</flux:text>
            </div>

            {{--
                Not a <dl>: the HTML5 <dl> content model requires every <div>
                child to contain *only* dt/dd groups, and each row here is a
                three-part label/description/status item, not a pure
                term/definition pair — a status <span> alongside dt/dd fails
                axe-core's `definition-list` rule (WCAG 1.3.1) regardless of
                nesting depth. A plain list conveys "N items" to assistive
                tech without forcing an ill-fitting semantic.
            --}}
            <ul class="divide-y divide-border" data-guide="dashboard-foundation-list">
                <li class="dashboard-status-row grid gap-2 px-5 py-5 transition-colors sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6" data-guide="dashboard-foundation-first-row">
                    <div>
                        <p class="font-semibold text-text-primary">{{ __('Daily operations') }}</p>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('Point of sale, sales history, shifts, and cash movements are available from the workspace.') }}</p>
                    </div>
                    <span class="w-fit text-sm font-semibold text-primary">{{ __('Available') }}</span>
                </li>

                <li class="dashboard-status-row grid gap-2 px-5 py-5 transition-colors sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <p class="font-semibold text-text-primary">{{ __('Catalog and inventory') }}</p>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('Review products, prices, purchasing, stock balances, and movement history.') }}</p>
                    </div>
                    <span class="w-fit text-sm font-semibold text-primary">{{ __('Available') }}</span>
                </li>

                <li class="dashboard-status-row grid gap-2 px-5 py-5 transition-colors sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <p class="font-semibold text-text-primary">{{ __('Administration and review') }}</p>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('Manage customers, permissions, approvals, reports, and system settings within your role.') }}</p>
                    </div>
                    <span class="w-fit text-sm font-semibold text-primary">{{ __('Configured') }}</span>
                </li>
            </ul>
        </section>

        <section class="dashboard-next-card flex flex-col gap-5 rounded-2xl border border-primary/15 bg-primary-soft/35 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6" data-guide="dashboard-setup-section">
            <div>
            <flux:heading data-guide="dashboard-setup-heading" size="lg">{{ __('Complete business setup') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Finish the owner-provided setup values before opening every operational workflow.') }}</flux:text>
            </div>
            <flux:button :href="route('profile.edit')" variant="primary" wire:navigate data-guide="dashboard-profile-action">
                {{ __('Open account settings') }}
            </flux:button>
        </section>
    </x-app.page>
</x-layouts::app>
