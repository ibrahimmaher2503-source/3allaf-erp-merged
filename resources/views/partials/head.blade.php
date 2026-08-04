@php
    use App\Modules\Platform\Models\UserUiPreference;
@endphp

@php($uiPreference = auth()->user()?->uiPreference)
@php($uiPreferences = array_merge(UserUiPreference::defaults(), $uiPreference?->only(array_keys(UserUiPreference::defaults())) ?? []))

<script>
    window.__toyJoyUiPreferences = @js($uiPreferences);
    (() => {
        const prefs = window.__toyJoyUiPreferences;
        const root = document.documentElement;
        const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
        const appearance = prefs.appearance === 'system' ? (systemDark ? 'dark' : 'light') : prefs.appearance;
        root.classList.toggle('dark', appearance === 'dark');
        root.dataset.appearance = prefs.appearance;
        root.dataset.accent = prefs.accent_color;
        root.dataset.sidebarMode = prefs.sidebar_mode;
        root.dataset.contentWidth = prefs.content_width;
        root.dataset.fontScale = prefs.font_scale;
        root.dataset.tableDensity = prefs.table_density;
        root.dataset.reducedMotion = prefs.reduced_motion ? 'true' : 'false';
    })();
</script>

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'TOY & JOY') : config('app.name', 'TOY & JOY') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0d9488">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
