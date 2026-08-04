<x-layouts::app :title="__('PWA Shell & Status')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4 sm:p-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('PWA Shell & Status') }}</flux:heading>
                <flux:subheading>{{ __('Local application shell capabilities, network connectivity state, and non-sensitive static PWA cache policy.') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                <div x-data="{ online: navigator.onLine }"
                     x-on:online.window="online = true"
                     x-on:offline.window="online = false"
                     class="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-1 text-xs font-medium dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
                    <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-700 dark:text-zinc-300"></span>
                </div>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950 dark:text-teal-400">
                        <flux:icon name="signal" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="sm">{{ __('Connectivity Status') }}</flux:heading>
                        <flux:text class="text-xs">{{ __('Browser-standard network status') }}</flux:text>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <span class="text-xs text-zinc-500">{{ __('Status') }}</span>
                    <div x-data="{ online: navigator.onLine }" x-on:online.window="online = true" x-on:offline.window="online = false">
                        <flux:badge color="zinc" size="sm">
                            <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'"></span>
                        </flux:badge>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                        <flux:icon name="shield-check" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="sm">{{ __('Cache Policy') }}</flux:heading>
                        <flux:text class="text-xs">{{ __('Private response protection') }}</flux:text>
                    </div>
                </div>
                <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <flux:text class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        {{ __('No sensitive or authenticated responses are cached offline.') }}
                    </flux:text>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950 dark:text-purple-400">
                        <flux:icon name="device-phone-mobile" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="sm">{{ __('Installable Shell') }}</flux:heading>
                        <flux:text class="text-xs">{{ __('Manifest & Static Service Worker') }}</flux:text>
                    </div>
                </div>
                <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __('PWA shell is configured for fast local navigation and offline indicator.') }}
                    </flux:text>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="md" class="mb-2">{{ __('Current Locale & Direction') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div>
                    <span class="text-zinc-500">{{ __('Locale') }}:</span>
                    <flux:badge size="sm" color="zinc">{{ app()->getLocale() }}</flux:badge>
                </div>
                <div>
                    <span class="text-zinc-500">{{ __('Direction') }}:</span>
                    <flux:badge size="sm" color="zinc">{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'RTL' : 'LTR' }}</flux:badge>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
