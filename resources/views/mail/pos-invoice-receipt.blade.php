<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('Sales invoice') }} {{ $sale->document_number }}</title></head>
<body style="font-family:Arial,sans-serif;color:#172033;line-height:1.5">
    <h1>{{ str_starts_with(app()->getLocale(), 'ar') ? ($sale->store->company->name_ar ?: $sale->store->company->name_en) : $sale->store->company->name_en }}</h1>
    <p>{{ __('Sales invoice') }} <strong>{{ $sale->document_number }}</strong></p>
    <p>{{ __('Customer') }}: {{ $sale->customer?->name_ar ?? __('Walk-in customer') }}<br>{{ __('Branch') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? $sale->branch->name_ar : $sale->branch->name_en }}</p>
    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse"><thead><tr><th align="start">{{ __('Product') }}</th><th align="end">{{ __('Quantity') }}</th><th align="end">{{ __('Line total') }}</th></tr></thead><tbody>
    @foreach($sale->lines as $line)<tr style="border-top:1px solid #ddd"><td>{{ str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en }}<small>{{ $line->item_code }}</small></td><td align="end"><x-product-quantity :value="$line->quantity" /></td><td align="end"><x-money :amount="$line->net_amount" /> {{ $sale->currency_code }}</td></tr>@endforeach
    </tbody></table>
    <p><strong>{{ __('Invoice total') }}: <x-money :amount="$sale->total" /> {{ $sale->currency_code }}</strong></p>
    <p>{{ __('Tax') }}: <x-money :amount="$sale->tax_total" /> {{ $sale->currency_code }}<br>{{ __('Payable total') }}: <x-money :amount="$sale->payable_total" /> {{ $sale->currency_code }}</p>
</body></html>
