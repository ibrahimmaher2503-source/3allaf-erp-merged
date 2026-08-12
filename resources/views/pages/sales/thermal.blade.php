<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('Receipt') }} {{ $sale->document_number }}</title><style>@page{size:80mm auto;margin:4mm}*{box-sizing:border-box}body{width:72mm;margin:0 auto;font-family:Arial,sans-serif;color:#111;font-size:11px}h1,p{margin:0}.center{text-align:center}.row{display:flex;justify-content:space-between;gap:8px}.rule{border-top:1px dashed #333;margin:8px 0}.line{margin:7px 0}.bold{font-weight:700}.small{font-size:9px;color:#444}@media print{body{width:auto}}</style></head>
<body>
    <header class="center"><h1>{{ $sale->store->company->name_en }}</h1><p>{{ app()->getLocale() === 'ar' ? $sale->store->name_ar : $sale->store->name_en }}</p><p class="small">{{ $sale->document_number }} · {{ $sale->approved_at?->format('Y-m-d H:i') }}</p></header>
    <div class="rule"></div>
    @foreach ($sale->lines as $line)
        <div class="line">
            <div class="bold">{{ app()->getLocale() === 'ar' ? $line->name_ar : $line->name_en }}</div>
            <div class="small">{{ $line->item_code }}</div>
            <x-variant-snapshot :snapshot="$line->variant_snapshot" class="small" />
            <div class="row"><span>{{ $line->quantity }} × {{ __('Selling price') }} {{ \App\Modules\Retail\Support\DecimalMoney::round((string) $line->unit_price) }}</span><span>{{ $line->gross_amount }}</span></div>
            @if ($line->is_open_price)<div class="small">{{ __('Reference') }} {{ \App\Modules\Retail\Support\DecimalMoney::round((string) ($line->reference_price ?? $line->unit_price)) }} · {{ __('Open price') }} · {{ $line->open_price_reason }}</div>@endif
            @if (bccomp((string) $line->discount_amount, '0', 2) > 0)<div class="row small"><span>{{ __('Discount') }}</span><span>-{{ $line->discount_amount }}</span></div>@endif
            <div class="row"><span>{{ __('Line total') }}</span><span class="bold">{{ $line->net_amount }}</span></div>
        </div>
    @endforeach
    <div class="rule"></div>
    <div class="row"><span>{{ __('Invoice subtotal') }}</span><span>{{ $sale->subtotal }}</span></div><div class="row"><span>{{ __('Discount') }}</span><span>{{ $sale->discount_total }}</span></div><div class="row"><span>{{ __('Tax') }}</span><span>{{ $sale->tax_total }}</span></div><div class="row bold"><span>{{ __('Invoice total') }}</span><span>{{ $sale->total }} {{ $sale->currency_code }}</span></div><div class="row"><span>{{ __('Cash rounding') }}</span><span>{{ $sale->cash_rounding_amount }}</span></div><div class="row bold"><span>{{ __('Payable total') }}</span><span>{{ $sale->payable_total }} {{ $sale->currency_code }}</span></div><div class="row"><span>{{ __('Applied total') }}</span><span>{{ $sale->paid_total }}</span></div><div class="row"><span>{{ __('Change total') }}</span><span>{{ $sale->change_total }}</span></div>
    <div class="rule"></div><p class="bold">{{ __('Payments') }}</p>
    @foreach ($sale->payments as $payment)<div class="line"><div class="row"><span class="bold">{{ $payment->method_code }}</span><span>{{ __('Applied') }} {{ $payment->amount }}</span></div>@if ($payment->tendered_amount !== null)<div class="row small"><span>{{ __('Tendered') }}</span><span>{{ $payment->tendered_amount }}</span></div>@endif@if (bccomp((string) $payment->change_amount, '0', 2) > 0)<div class="row small"><span>{{ __('Change') }}</span><span>{{ $payment->change_amount }}</span></div>@endif</div>@endforeach
    <div class="rule"></div><p class="center small">{{ __('Thank you') }}</p><script>window.addEventListener('load',()=>window.print())</script>
</body></html>
