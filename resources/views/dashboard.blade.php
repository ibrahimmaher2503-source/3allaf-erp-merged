<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
        <header class="flex flex-col gap-3 border-b border-zinc-200 pb-6 dark:border-zinc-700" data-guide="dashboard-header">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800 ring-1 ring-inset ring-teal-700/15 dark:bg-teal-950 dark:text-teal-200">
                    {{ __('DM 1.1 in progress') }}
                </span>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Platform foundation') }}</span>
            </div>

            <div>
                <flux:heading size="xl">{{ __('Operations workspace') }}</flux:heading>
                <flux:text class="mt-2 max-w-3xl">
                    {{ __('The shared application foundation is ready for authentication, operational modules, and role-scoped workflows.') }}
                </flux:text>
            </div>
        </header>

        <section aria-labelledby="foundation-heading" class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" data-guide="dashboard-foundation">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
                <flux:heading id="foundation-heading" size="lg">{{ __('Foundation status') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Verified building blocks available in the current development environment.') }}</flux:text>
            </div>

            <dl class="divide-y divide-zinc-200 dark:divide-zinc-700" data-guide="dashboard-foundation-list">
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <dt class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Application shell') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Responsive sidebar, mobile navigation, and shared page structure.') }}</dd>
                    </div>
                    <span class="w-fit text-sm font-semibold text-teal-700 dark:text-teal-300">{{ __('Available') }}</span>
                </div>

                <div class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <dt class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Authentication') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Sign in, registration, password reset, verification, passkeys, and account security.') }}</dd>
                    </div>
                    <span class="w-fit text-sm font-semibold text-teal-700 dark:text-teal-300">{{ __('Available') }}</span>
                </div>

                <div class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <dt class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Language direction') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Locale-aware document direction supports Arabic RTL and English LTR layouts.') }}</dd>
                    </div>
                    <span class="w-fit text-sm font-semibold text-teal-700 dark:text-teal-300">{{ __('Configured') }}</span>
                </div>
            </dl>
        </section>

        <section class="flex flex-col gap-4 rounded-xl bg-zinc-50 p-5 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between sm:p-6" data-guide="dashboard-setup-section">
            <div>
                <flux:heading size="lg">{{ __('Continue platform setup') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Account settings are available while roles, audit controls, and infrastructure decisions remain in the active milestone.') }}</flux:text>
            </div>
            <flux:button :href="route('profile.edit')" variant="primary" wire:navigate data-guide="dashboard-profile-action">
                {{ __('Open account settings') }}
            </flux:button>
        </section>
    </div>
</x-layouts::app>
