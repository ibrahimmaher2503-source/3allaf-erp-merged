@include('partials.theme-bootstrap')

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', '3allaf | علاف') : config('app.name', '3allaf | علاف') }}
</title>

<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="/manifest.json">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0d9488">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
