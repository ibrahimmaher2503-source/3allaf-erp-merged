<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <style>
        @include('pages.exports.partials.cairo-pdf-styles')
        @page { margin: 24pt; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        body {
            direction: ltr;
            font-family: {{ str_starts_with(app()->getLocale(), 'ar') ? "'Cairo PDF Arabic'" : "'Cairo PDF Latin'" }};
            font-size: 9px;
            font-weight: 400;
            line-height: 1.65;
            color: #17233c;
            text-align: {{ str_starts_with(app()->getLocale(), 'ar') ? 'right' : 'left' }};
        }
        h1 { margin: 0 0 12px; font-family: inherit; font-size: 16px; font-weight: 800; line-height: 1.5; }
        p, table, thead, tbody, tr, th, td, span { font-family: inherit; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; line-height: 1.35; }
        thead { display: table-header-group; background: #e8f2f4; }
        th, td {
            border: 1px solid #bccbd2;
            padding: 6px 7px;
            text-align: {{ str_starts_with(app()->getLocale(), 'ar') ? 'right' : 'left' }};
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        th { font-weight: 700; font-size: 8px; }
        td { font-weight: 400; }
        tr { page-break-inside: avoid; }
        .latin { direction: ltr; unicode-bidi: embed; font-family: 'Cairo PDF Latin'; text-align: left; }
        .arabic { direction: ltr; unicode-bidi: embed; font-family: 'Cairo PDF Arabic'; text-align: right; }
    </style>
</head>
<body>
@php
    $segments = static fn (mixed $value): array => preg_split('/(\p{Arabic}[^A-Za-z]*)/u', (string) $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [(string) $value];
    $columnWeights = array_map(static fn (mixed $header): int => max(8, min(24, mb_strlen((string) $header))), $headers);
    $totalWeight = array_sum($columnWeights);
@endphp
<h1 class="{{ str_starts_with(app()->getLocale(), 'ar') ? 'arabic' : 'latin' }}">{{ $title }}</h1>
<table>
    <thead><tr>@foreach($headers as $index => $header)<th style="width: {{ number_format($columnWeights[$index] * 100 / $totalWeight, 4, '.', '') }}%" class="{{ preg_match('/\p{Arabic}/u', (string) $header) ? 'arabic' : 'latin' }}">{{ $header }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            @foreach($row as $cell)
                <td>@foreach($segments($cell) as $segment)<span class="{{ preg_match('/\p{Arabic}/u', $segment) ? 'arabic' : 'latin' }}">{{ $segment }}</span>@endforeach</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($headers) }}" class="{{ str_starts_with(app()->getLocale(), 'ar') ? 'arabic' : 'latin' }}">{{ __('No records found') }}</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
