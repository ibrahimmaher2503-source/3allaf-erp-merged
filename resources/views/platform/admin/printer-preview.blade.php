<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Printer Configuration Preview') }}</title>
    @vite(['resources/css/app.css'])
    <style>@media print {.no-print{display:none!important} body{background:#fff!important}.print-container{border:0!important;box-shadow:none!important}}</style>
</head>
<body class="bg-zinc-100 p-4 sm:p-8">
    <div class="no-print mx-auto mb-4 flex max-w-3xl justify-end"><button type="button" onclick="window.print()" class="rounded bg-zinc-900 px-4 py-2 text-sm text-white">{{ __('Print') }}</button></div>
    <main class="print-container mx-auto max-w-3xl space-y-4 rounded-xl border border-zinc-200 bg-white p-8">
        <div class="flex items-start justify-between border-b border-zinc-300 pb-4">
            <div>
                <h1 class="text-xl font-bold">{{ $printer->name }}</h1>
                <p class="text-sm text-zinc-600">{{ __('Configuration preview') }}</p>
            </div>
            <div class="text-end text-xs font-mono">
                <div>{{ strtoupper($printer->printer_type) }}</div>
                <div>{{ $printer->paper_size }}</div>
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <dt class="font-semibold">{{ __('Template') }}</dt><dd>{{ $printer->template_name }}</dd>
            <dt class="font-semibold">{{ __('Connection') }}</dt><dd>{{ $printer->connection_type }}</dd>
            <dt class="font-semibold">{{ __('Status') }}</dt><dd>{{ $printer->status }}</dd>
        </dl>
        <div class="border border-dashed border-zinc-400 p-6 text-center text-sm text-zinc-600">
            {{ __('This is a configuration preview. No document or payment data is printed.') }}
        </div>
    </main>
</body>
</html>
