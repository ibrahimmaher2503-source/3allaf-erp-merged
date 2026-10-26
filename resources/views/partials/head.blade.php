@include('partials.theme-bootstrap')

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'TOY & JOY') : config('app.name', 'TOY & JOY') }}
</title>

<link rel="icon" href="{{ asset('images/rajeh-logo-mark.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/rajeh-logo-mark.png') }}">
<link rel="manifest" href="/manifest.json">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0d9488">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
