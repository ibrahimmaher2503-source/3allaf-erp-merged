@if($count && $focus === 'count-reconcile')
<style>
    @media (max-width: 639px) {
        [data-stocktake-review-scroll] { overflow: visible; }
        [data-stocktake-review-table] { display: block; min-width: 0; }
        [data-stocktake-review-table] thead { display: none; }
        [data-stocktake-review-table] tbody { display: grid; gap: .625rem; padding: .75rem; }
        [data-stocktake-review-table] tbody tr { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; padding: .75rem; border: 1px solid #e4e4e7; border-radius: .75rem; }
        [data-stocktake-review-table] td { display: block; min-width: 0; padding: 0; overflow-wrap: anywhere; text-align: start; }
        [data-stocktake-review-col="product"], [data-stocktake-review-col="empty"] { grid-column: 1 / -1; }
        [data-stocktake-review-col="expected"], [data-stocktake-review-col="counted"], [data-stocktake-review-col="difference"] { padding: .5rem; border: 1px solid #e4e4e7; border-radius: .625rem; background: #fafafa; }
        [data-stocktake-review-col="counted"] { border-color: #fcd34d; background: #fffbeb; }
        [data-stocktake-review-col="movements"] { grid-column: 2 / 4; }
        [data-stocktake-review-col="audit"] { grid-column: 1 / 3; }
        .stocktake-review-mobile-label { display: block; margin-bottom: .25rem; font-size: .6875rem; font-weight: 700; line-height: 1.25; color: #71717a; }
        .dark [data-stocktake-review-table] tbody tr, .dark [data-stocktake-review-col="expected"], .dark [data-stocktake-review-col="difference"] { border-color: #3f3f46; }
        .dark [data-stocktake-review-col="expected"], .dark [data-stocktake-review-col="difference"] { background: #27272a; }
        .dark [data-stocktake-review-col="counted"] { border-color: #92400e; background: #451a03; }
    }
    @media (min-width: 640px) { .stocktake-review-mobile-label { display: none; } }
</style>
<section class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-review-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800 sm:px-5"><div><h2 id="stocktake-review-heading" class="text-base font-bold">{{ $t('مراجعة الفروقات والمطابقة', 'Review and reconcile') }}</h2><p class="text-xs text-zinc-500">{{ $t('راجع الكميات والحركات قبل أي تسوية.', 'Review quantities and movements before settlement.') }}</p></div><span class="font-mono text-xs text-zinc-500" dir="ltr">{{ $count->count_number }}</span></div>
    <div class="grid min-w-0 grid-cols-2 gap-px border-b border-zinc-200 bg-zinc-200 dark:border-zinc-800 dark:bg-zinc-800 sm:grid-cols-4" data-stocktake-review-summary>
        @foreach([[$t('المنتجات', 'Products'), $countProgress['total']], [$t('معدود', 'Counted'), $countProgress['counted']], [$t('مطابق', 'Matched'), $countProgress['matched']], [$t('بفارق', 'Variance'), $countProgress['different']]] as [$label, $value])
            <div class="min-w-0 bg-white px-4 py-3 dark:bg-zinc-900"><span class="block text-xs text-zinc-500">{{ $label }}</span><strong class="mt-0.5 block font-mono text-lg" dir="ltr">{{ $value }}</strong></div>
        @endforeach
    </div>
    <form method="GET" action="{{ route('inventory.counts.reconcile-page', $count) }}" class="grid min-w-0 gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800 sm:grid-cols-[minmax(0,2fr)_minmax(9rem,1fr)_auto] sm:items-end sm:px-5">
        <label class="min-w-0 text-xs font-semibold">{{ $t('بحث عن منتج أو باركود', 'Search product or barcode') }}<input name="count_search" value="{{ request('count_search') }}" class="{{ $field }}" placeholder="{{ $t('اسم أو رمز المنتج', 'Product name or code') }}"></label>
        <label class="min-w-0 text-xs font-semibold">{{ $t('حالة المراجعة', 'Review filter') }}<select name="count_filter" class="{{ $field }}"><option value="all">{{ $t('الكل', 'All') }}</option>@foreach(['not_counted' => $t('غير معدود', 'Uncounted'), 'matched' => $t('مطابق', 'Matched'), 'shortage' => $t('عجز', 'Shortage'), 'excess' => $t('زيادة', 'Excess'), 'movements' => $t('مع حركات', 'With movements')] as $value => $label)<option value="{{ $value }}" @selected(request('count_filter') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="{{ $button }} min-h-10 bg-sky-700 text-white">{{ $t('تصفية', 'Filter') }}</button>
    </form>
    <div class="min-w-0 max-w-full overflow-x-auto" data-stocktake-table-scroll data-stocktake-review-scroll><table class="w-full min-w-[960px] border-collapse text-sm" data-stocktake-review-table>
        <thead class="bg-zinc-50 text-xs text-zinc-600 dark:bg-zinc-800/60"><tr><th class="px-3 py-3 text-start">{{ $t('المنتج / الرمز', 'Product / code') }}</th><th class="px-3 py-3 text-end">{{ $t('المتوقع وقت العد', 'Expected at count') }}</th><th class="px-3 py-3 text-end">{{ $t('المعدود', 'Counted') }}</th><th class="px-3 py-3 text-end">{{ $t('الفرق', 'Difference') }}</th><th class="px-3 py-3 text-start">{{ $t('الحالة', 'Status') }}</th><th class="px-3 py-3 text-start">{{ $t('الحركات', 'Movements') }}</th><th class="px-3 py-3 text-start">{{ $t('العد بواسطة / الوقت', 'Counted by / at') }}</th><th class="px-3 py-3 text-start">{{ $t('ملاحظة', 'Note') }}</th></tr></thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">@forelse($countLines as $line)
            @php $liveValues = $lineCalculations[$line->id] ?? null; $reviewDifference = $liveValues['variance_quantity'] ?? null; @endphp
            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30">
                <td data-stocktake-review-col="product" class="min-w-48 px-3 py-2"><strong class="block">{{ \App\Support\HumanName::name($line->product) }}</strong><small class="block font-mono text-zinc-500" dir="ltr">{{ $line->product?->item_code }}</small></td>
                <td data-stocktake-review-col="expected" class="px-3 py-2 text-end font-mono"><span class="stocktake-review-mobile-label">{{ $t('المتوقع', 'Expected') }}</span><span dir="ltr">{{ $liveValues ? $qty(bcsub($liveValues['expected_quantity'], $liveValues['movement_after_completion'], 6)) : '—' }}</span></td>
                <td data-stocktake-review-col="counted" class="px-3 py-2 text-end font-mono"><span class="stocktake-review-mobile-label">{{ $t('المعدود', 'Counted') }}</span><span dir="ltr">{{ $line->is_counted ? $qty($line->counted_quantity) : '—' }}</span></td>
                <td data-stocktake-review-col="difference" class="px-3 py-2 text-end font-mono"><span class="stocktake-review-mobile-label">{{ $t('الفرق', 'Difference') }}</span><span dir="ltr">{{ $reviewDifference !== null ? $qty($reviewDifference) : '—' }}</span></td>
                <td data-stocktake-review-col="status" class="px-3 py-2 text-xs"><span class="stocktake-review-mobile-label">{{ $t('الحالة', 'Status') }}</span>{{ ! $liveValues ? $t('غير معدود', 'Uncounted') : (bccomp($reviewDifference, '0', 6) === 0 ? $t('مطابق', 'Matched') : (bccomp($reviewDifference, '0', 6) < 0 ? $t('عجز', 'Shortage') : $t('زيادة', 'Excess'))) }}</td>
                <td data-stocktake-review-col="movements" class="px-3 py-2"><span class="stocktake-review-mobile-label">{{ $t('الحركات', 'Movements') }}</span><a class="text-xs font-semibold text-sky-700 underline" href="{{ route('inventory.counts.movements', [$count, $line]) }}">{{ $t('عرض الحركات', 'View movements') }}</a></td>
                <td data-stocktake-review-col="audit" class="px-3 py-2 text-xs"><span class="stocktake-review-mobile-label">{{ $t('العد بواسطة / الوقت', 'Counted by / at') }}</span>{{ $line->counter?->name ?? '—' }}<span class="block font-mono text-zinc-500" dir="ltr">{{ $line->counted_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                <td data-stocktake-review-col="note" class="min-w-32 px-3 py-2 text-xs"><span class="stocktake-review-mobile-label">{{ $t('ملاحظة', 'Note') }}</span>{{ $line->notes ?: '—' }}</td>
            </tr>
        @empty<tr><td data-stocktake-review-col="empty" colspan="8" class="px-4 py-12 text-center text-zinc-500">{{ $t('لا توجد منتجات ضمن هذه النتائج.', 'No products match these filters.') }}</td></tr>@endforelse</tbody>
    </table></div>
    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $countLines->links() }}</div>
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-t border-zinc-100 bg-zinc-50/60 px-4 py-3 text-xs dark:border-zinc-800 dark:bg-zinc-800/20 sm:px-5"><p class="text-zinc-600 dark:text-zinc-300">{{ $t('راجع العناصر غير المعدودة قبل الاعتماد. لا تتحول إلى صفر تلقائياً.', 'Review uncounted items before approval. They are never silently converted to zero.') }}</p>
        @if($count->status === 'submitted')@can('stock_counts.reconcile')@can('stock_counts.approve')@can('stock_counts.post_adjustment')<form method="POST" action="{{ route('inventory.counts.reconcile', $count) }}" onsubmit="return confirm(@js($t('اعتماد الفروقات وترحيل تسويات المخزون؟', 'Approve variances and post inventory adjustments?')))">@csrf<button class="{{ $button }} min-h-10 bg-emerald-700 text-white">{{ $t('اعتماد وترحيل التسوية', 'Approve and post settlement') }}</button></form>@endcan @endcan @endcan @endif
    </div>
</section>
@endif
