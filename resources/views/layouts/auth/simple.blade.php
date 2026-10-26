<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900 relative">
        <div class="absolute top-4 right-4 rtl:right-auto rtl:left-4 z-10">
            <x-locale-switcher compact />
        </div>
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="mb-1 flex items-center justify-center">
                        <img src="{{ asset('images/rajeh-logo.png') }}" alt="{{ __('Rajeh ERP') }}" class="h-12 w-auto max-w-44 rounded-md bg-white p-1 object-contain sm:h-14">
                    </span>
                    <span class="sr-only">{{ config('app.name', 'TOY & JOY') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-app-footer />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
