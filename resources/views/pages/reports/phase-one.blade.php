@php
    $ar = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $arabic, string $english): string => $ar ? $arabic : $english;
    $title = match ($type) {
        'sales' => $t('تقرير المبيعات حسب الأصناف', 'Product Sales Report'),
        'inventory' => $t('تقرير المخزون الحالي', 'Current Inventory Report'),
        default => $t('ملخص المبيعات اليومية حسب الفرع', 'Daily Sales Summary by Branch'),
    };
    $qty = static fn ($value): string => \App\Support\ProductQuantity::format((string) $value);
    $money = static fn ($value): string => number_format((float) $value, 2);
@endphp
<x-layouts.app :title="$title">
    <main class="mx-auto max-w-[1500px] space-y-4 px-3 py-4 sm:px-6" data-phase-one-report="{{ $type }}">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b pb-3"><h1 class="text-xl font-bold">{{ $title }}</h1><div class="flex flex-wrap gap-2 text-sm"><a class="text-primary underline" href="{{ route('reports.phase-one.sales') }}">{{ $t('المبيعات حسب الأصناف', 'Product sales') }}</a><a class="text-primary underline" href="{{ route('reports.phase-one.inventory') }}">{{ $t('المخزون الحالي', 'Current inventory') }}</a><a class="text-primary underline" href="{{ route('reports.phase-one.daily') }}">{{ $t('المبيعات اليومية', 'Daily sales') }}</a></div></header>
        @if($errors->any())<div role="alert" class="rounded border border-rose-300 bg-rose-50 p-2 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="GET" class="grid gap-2 rounded-lg border bg-white p-3 text-xs sm:grid-cols-2 lg:grid-cols-6" data-report-filters>
            @if($type !== 'inventory')<label>{{ $t('من', 'From') }}<input name="date_from" type="date" value="{{ $filters['date_from'] }}" required class="mt-1 block w-full rounded border-zinc-300 text-sm"></label><label>{{ $t('إلى', 'To') }}<input name="date_to" type="date" value="{{ $filters['date_to'] }}" required class="mt-1 block w-full rounded border-zinc-300 text-sm"></label>@endif
            @if($type === 'daily')
                <label>{{ $t('الفرع', 'Branch') }}<select name="branch_id" required class="mt-1 block w-full rounded border-zinc-300 text-sm">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ \App\Support\HumanName::name($branch) }} · {{ $branch->code }}</option>@endforeach</select></label>
            @elseif($type === 'inventory')
                <label>{{ $t('المخزن / المتجر', 'Store / warehouse') }}<select name="store_id" class="mt-1 block w-full rounded border-zinc-300 text-sm"><option value="">{{ $t('كل المخازن', 'All stores') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((int) ($filters['store_id'] ?? 0) === $store->id)>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach</select></label>
            @else
                <label>{{ $t('الفروع / المتاجر', 'Branches / stores') }}<select name="store_ids[]" multiple size="3" class="mt-1 block w-full rounded border-zinc-300 text-sm" aria-label="{{ $t('اختر عدة متاجر أو اتركها فارغة للكل', 'Select multiple stores or leave empty for all') }}">@foreach($stores as $store)<option value="{{ $store->id }}" @selected(in_array($store->id, array_map('intval', $filters['store_ids'] ?? []), true))>{{ $store->branch ? \App\Support\HumanName::name($store->branch).' / ' : '' }}{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>@endforeach</select></label>
            @endif
            @if($type !== 'daily')
                <label>{{ $t('الموردون', 'Suppliers') }}<select name="supplier_ids[]" multiple size="3" class="mt-1 block w-full rounded border-zinc-300 text-sm" aria-label="{{ $t('اختر عدة موردين أو اتركها فارغة للكل', 'Select multiple suppliers or leave empty for all') }}">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(in_array($supplier->id, array_map('intval', $filters['supplier_ids'] ?? []), true))>{{ \App\Support\HumanName::name($supplier) }}</option>@endforeach</select></label>
                <label>{{ $t('الفئات', 'Categories') }}<select name="category_ids[]" multiple size="3" class="mt-1 block w-full rounded border-zinc-300 text-sm" aria-label="{{ $t('اختر عدة فئات أو اتركها فارغة للكل', 'Select multiple categories or leave empty for all') }}">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(in_array($category->id, array_map('intval', $filters['category_ids'] ?? []), true))>{{ \App\Support\HumanName::name($category) }}</option>@endforeach</select></label>
            @endif
            <div class="flex items-end"><button class="w-full rounded bg-primary px-4 py-2 font-bold text-white">{{ $t('تطبيق', 'Apply') }}</button></div>
        </form>
        <div class="overflow-x-auto rounded-lg border bg-white" data-report-results><table class="w-full min-w-[680px] text-start text-xs"><thead class="bg-zinc-100"><tr>
            @if($type === 'daily')<th class="p-2">{{ $t('التاريخ', 'Date') }}</th>@foreach(['gross' => $t('إجمالي المبيعات', 'Gross sales'), 'cash' => $t('نقدي', 'Cash'), 'electronic' => $t('بطاقة / إلكتروني', 'Card / electronic'), 'discounts' => $t('خصومات', 'Discounts'), 'refunds' => $t('مرتجعات', 'Refunds'), 'net' => $t('صافي المبيعات', 'Net sales')] as $label)<th class="p-2 text-end">{{ $label }}</th>@endforeach
            @else<th class="p-2">{{ $t('الكود', 'Code') }}</th><th class="p-2">{{ $t('الصنف', 'Product') }}</th><th class="p-2 text-end">{{ $t('الكمية', 'Quantity') }}</th><th class="p-2 text-end">{{ $t('تكلفة الوحدة', 'Unit cost') }}</th><th class="p-2 text-end">{{ $type === 'sales' ? $t('سعر البيع الفعلي', 'Actual sale price') : $t('السعر الحالي الأساسي', 'Current base price') }}</th><th class="p-2 text-end">{{ $t('القيمة بالتكلفة', 'Cost value') }}</th><th class="p-2 text-end">{{ $type === 'sales' ? $t('قيمة المبيعات', 'Sales value') : $t('قيمة البيع', 'Selling value') }}</th>@endif
        </tr></thead><tbody>
            @forelse($report['rows'] as $row)
                <tr class="border-t" data-report-row>@if($type === 'daily')<td class="p-2" dir="ltr">{{ $row['day'] }}</td>@foreach(['gross','cash','electronic','discounts','refunds','net'] as $column)<td class="p-2 text-end tabular-nums" dir="ltr">{{ $money($row[$column]) }}</td>@endforeach
                @else
                    @php
                        $quantity = $type === 'sales' ? $row->sold_quantity : $row->quantity;
                        $cost = $type === 'sales' ? $row->total_cost : $row->cost_value;
                        $value = $type === 'sales' ? $row->total_sales : null;
                    @endphp
<td class="p-2 font-mono" dir="ltr">{{ $row->item_code }}</td><td class="p-2">{{ $ar ? $row->name_ar : ($row->name_en ?: $row->name_ar) }}</td><td class="p-2 text-end tabular-nums">{{ $qty($quantity) }}</td><td class="p-2 text-end tabular-nums">{{ (float) $quantity != 0 ? $money((float) $cost / (float) $quantity) : '—' }}</td><td class="p-2 text-end tabular-nums">{{ $type === 'sales' ? ((float) $quantity != 0 ? $money((float) $value / (float) $quantity) : '—') : (isset($filters['store_id']) ? ($row->current_price === null ? '—' : $money($row->current_price)) : $money($row->sale_price)) }}</td><td class="p-2 text-end tabular-nums">{{ $money($cost) }}</td><td class="p-2 text-end tabular-nums">{{ $value === null ? '—' : $money($value) }}</td>@endif</tr>
            @empty<tr><td colspan="7" class="p-6 text-center text-zinc-500">{{ $t('لا توجد بيانات مرحلة ضمن النطاق.', 'No posted data in this scope.') }}</td></tr>@endforelse
        </tbody><tfoot><tr class="border-t-2 bg-zinc-50 font-bold"><th class="p-2" colspan="{{ $type === 'daily' ? 1 : 2 }}">{{ $t('الإجمالي العام', 'Grand total') }}</th>@if($type === 'daily')@foreach(['gross','cash','electronic','discounts','refunds','net'] as $column)<td class="p-2 text-end tabular-nums">{{ $money($report['totals'][$column]) }}</td>@endforeach @else<td class="p-2 text-end">{{ $qty($report['totals']->quantity) }}</td><td colspan="2"></td><td class="p-2 text-end">{{ $money($report['totals']->cost) }}</td><td class="p-2 text-end">{{ $type === 'sales' ? $money($report['totals']->sales) : '—' }}</td>@endif</tr></tfoot></table></div>
        <div>{{ $report['rows']->links() }}</div>
    </main>
</x-layouts.app>
