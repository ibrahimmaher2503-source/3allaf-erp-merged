<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('Print template preview') }}</title>@vite(['resources/css/app.css'])<style>@page{margin:8mm}.sheet-58mm{width:58mm}.sheet-80mm{width:80mm}.sheet-a5{max-width:148mm}.sheet-a4{max-width:210mm}.sheet-label{width:50mm;min-height:30mm}@media print{.no-print{display:none!important}}</style></head>
<body class="bg-zinc-100 p-4 text-zinc-900">
<div class="no-print mx-auto mb-4 flex max-w-3xl justify-end"><button onclick="window.print()" class="rounded bg-zinc-900 px-4 py-2 text-white">{{ __('Test print') }}</button></div>
<main class="mx-auto rounded-xl border bg-white p-6 shadow-sm {{ match($template->paper_size){'58mm'=>'sheet-58mm','80mm'=>'sheet-80mm','a5'=>'sheet-a5','a4'=>'sheet-a4',default=>'sheet-label'} }}">
<header class="border-b pb-3 text-center"><h1 class="font-bold">{{ app()->getLocale()==='ar' ? $template->name_ar : $template->name_en }}</h1><p class="text-xs">{{ $template->header_text }}</p></header>
<section class="space-y-2 py-4 text-sm"><div class="flex justify-between"><span>{{ __('Document type') }}</span><span>{{ __(Illuminate\Support\Str::headline($template->document_type)) }}</span></div><div class="flex justify-between"><span>{{ __('Paper size') }}</span><span>{{ $template->paper_size }}</span></div><div class="flex justify-between"><span>{{ __('Language') }}</span><span>{{ __($template->language) }}</span></div><div class="border-y border-dashed py-4 text-center">{{ __('Sample document content — no customer, payment, or production data is used.') }}</div></section>
<footer class="border-t pt-3 text-center text-xs">{{ $template->footer_text }}</footer>
</main></body></html>
