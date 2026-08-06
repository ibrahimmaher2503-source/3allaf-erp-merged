<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Purchase Invoice') }} {{ $invoice->invoice_number ?: __('Draft') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 2rem; }
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1.5rem 0; }
        .muted { color: #6b7280; font-size: .85rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border-bottom: 1px solid #d1d5db; padding: .6rem; text-align: start; }
        .totals { margin-inline-start: auto; max-width: 22rem; margin-top: 1.5rem; }
        .totals div { display: flex; justify-content: space-between; padding: .35rem 0; }
        .total { border-top: 2px solid #111827; font-weight: 700; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ __('Purchase Invoice') }}</strong>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>
    <h1>{{ $invoice->invoice_number ?: __('Draft invoice') }}</h1>
    <div class="grid">
        <div><div class="muted">{{ __('Supplier') }}</div>{{ $invoice->supplier?->name_en ?: $invoice->supplier?->name_ar }} ({{ $invoice->supplier?->code }})</div>
        <div><div class="muted">{{ __('Receiving store') }}</div>{{ $invoice->store?->name_en ?: $invoice->store?->name_ar }} ({{ $invoice->store?->code }})</div>
        <div><div class="muted">{{ __('Status') }}</div>{{ $invoice->status }}<br>{{ $invoice->invoice_date?->format('Y-m-d') }}</div>
    </div>
    <table>
        <thead><tr><th>#</th><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit cost') }}</th><th>{{ __('Discount') }}</th><th>{{ __('Tax') }}</th><th>{{ __('Total') }}</th></tr></thead>
        <tbody>
        @foreach ($invoice->lines as $index => $line)
            <tr><td>{{ $index + 1 }}</td><td>{{ $line->product?->item_code }} — {{ $line->product?->name_en ?: $line->product?->name_ar }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit_cost }}</td><td>{{ $line->discount_amount }}</td><td>{{ $line->tax_amount }}</td><td>{{ $line->line_total }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div><span>{{ __('Subtotal') }}</span><span>{{ $invoice->subtotal }}</span></div>
        <div><span>{{ __('Discount') }}</span><span>{{ $invoice->discount_amount }}</span></div>
        <div><span>{{ __('Tax') }}</span><span>{{ $invoice->tax_amount }}</span></div>
        <div class="total"><span>{{ __('Total') }}</span><span>{{ $invoice->total_amount }}</span></div>
    </div>
    @if ($invoice->notes)<p><span class="muted">{{ __('Notes') }}:</span> {{ $invoice->notes }}</p>@endif
</body>
</html>
