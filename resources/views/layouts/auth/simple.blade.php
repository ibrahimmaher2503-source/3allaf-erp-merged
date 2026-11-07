@props(['loginPage' => false])

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
        @if ($loginPage)
            <style>
                body.rajeh-login { background: radial-gradient(circle at 12% 16%, #d5e9ff 0, transparent 31%), radial-gradient(circle at 88% 82%, #d7f3e8 0, transparent 29%), linear-gradient(140deg, #f2f8ff, #f8fbfd 55%, #eef8f4); }
                body.rajeh-login::before { content: ''; position: fixed; inset: 0; pointer-events: none; background-image: radial-gradient(#18569e 0.7px, transparent 0.7px); background-size: 27px 27px; opacity: .055; }
                .rajeh-login-stage { position: relative; display: flex; min-height: 100svh; align-items: center; justify-content: center; padding: 4.5rem 1rem 3rem; }
                .rajeh-login-card { width: min(100%, 29rem); border: 1px solid rgba(25, 76, 124, .12); border-radius: 1.75rem; background: rgba(255, 255, 255, .96); box-shadow: 0 24px 70px rgba(23, 58, 91, .11), 0 3px 14px rgba(23, 58, 91, .04); padding: clamp(1.5rem, 4vw, 2.5rem); }
                .rajeh-login-logo { display: block; width: min(100%, 14.5rem); height: auto; max-height: 6.5rem; object-fit: contain; }
                .rajeh-login input[name="username"], .rajeh-login input[name="password"] { min-height: 3rem; border-radius: .8rem; }
                .rajeh-login input[name="username"]:focus, .rajeh-login input[name="password"]:focus { box-shadow: 0 0 0 3px rgba(24, 95, 174, .14); }
                .rajeh-login [data-test="login-button"] { min-height: 3.1rem; border-radius: .8rem; font-weight: 700; box-shadow: 0 8px 20px rgba(15, 87, 179, .18); }
                .dark body.rajeh-login { background: radial-gradient(circle at 12% 16%, #163352 0, transparent 34%), radial-gradient(circle at 88% 82%, #123c36 0, transparent 33%), #0b1421; }
                .dark .rajeh-login-card { background: rgba(20, 33, 50, .96); border-color: rgba(172, 209, 239, .16); box-shadow: 0 28px 70px rgba(0, 0, 0, .28); }
                @media (max-width: 480px) { .rajeh-login-stage { padding-inline: 1rem; } .rajeh-login-card { border-radius: 1.4rem; } }
            </style>
        @endif
    </head>
    <body class="{{ $loginPage ? 'rajeh-login' : 'min-h-screen bg-white dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900' }} relative antialiased">
        <div class="absolute top-4 right-4 rtl:right-auto rtl:left-4 z-10">
            <x-locale-switcher compact />
        </div>
        @if ($loginPage)
            <div class="rajeh-login-stage">
                <main class="rajeh-login-card">
                    <a href="{{ route('home') }}" class="mb-7 flex justify-center" wire:navigate>
                        <img src="{{ asset('images/rajeh-logo.png') }}" alt="{{ __('Rajeh ERP') }}" class="rajeh-login-logo">
                    </a>
                    {{ $slot }}
                </main>
            </div>
        @else
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="mb-1 flex items-center justify-center">
                        <img src="{{ asset('images/rajeh-logo.png') }}" alt="{{ __('Rajeh ERP') }}" class="h-12 w-auto max-w-44 rounded-md bg-white p-1 object-contain sm:h-14">
                    </span>
                    <span class="sr-only">{{ config('app.name', '3allaf | علاف') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @endif

        <x-app-footer />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
