@if($count && $focus === 'count-entry')
<style>
    @media (max-width: 639px) {
        [data-stocktake-table-scroll] { overflow: visible; }
        [data-stocktake-count-table] { display: block; min-width: 0; }
        [data-stocktake-count-table] thead { display: none; }
        [data-stocktake-count-table] tbody { display: grid; gap: .625rem; padding: .75rem; }
        [data-stocktake-count-table] tbody tr { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; padding: .75rem; border: 1px solid #e4e4e7; border-radius: .75rem; }
        [data-stocktake-count-table] td { display: block; min-width: 0; padding: 0; overflow-wrap: anywhere; }
        [data-stocktake-col="product"] { grid-column: 1 / -1; }
        [data-stocktake-col="location"] { grid-column: 1 / -1; font-size: .75rem; color: #71717a; }
        [data-stocktake-col="expected"], [data-stocktake-col="actual"], [data-stocktake-col="difference"] { min-width: 0; padding: .5rem; border: 1px solid #e4e4e7; border-radius: .625rem; background: #fafafa; text-align: start; }
        [data-stocktake-col="actual"] { border-color: #fcd34d; background: #fffbeb; }
        [data-stocktake-col="status"] { grid-column: 1 / -1; }
        [data-stocktake-col="note"] { grid-column: 1 / -1; }
        [data-stocktake-col="audit"] { grid-column: 1 / 3; }
        [data-stocktake-col="action"] { grid-column: 3; text-align: end; }
        [data-stocktake-col="empty"] { grid-column: 1 / -1; }
        .stocktake-mobile-label { display: block; margin-bottom: .25rem; font-size: .6875rem; font-weight: 700; line-height: 1.25; color: #71717a; }
        [data-stocktake-col="actual"] [data-count-quantity] { width: 100%; min-width: 0; }
        .dark [data-stocktake-count-table] tbody tr, .dark [data-stocktake-col="expected"], .dark [data-stocktake-col="difference"] { border-color: #3f3f46; }
        .dark [data-stocktake-col="expected"], .dark [data-stocktake-col="difference"] { background: #27272a; }
        .dark [data-stocktake-col="actual"] { border-color: #92400e; background: #451a03; }
    }
    @media (min-width: 640px) { .stocktake-mobile-label { display: none; } }
</style>
<section class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-entry-heading">
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800 sm:px-5">
        <div><h2 id="stocktake-entry-heading" class="text-base font-bold">{{ $t('منتجات الجرد', 'Stocktake products') }}</h2><p class="text-xs text-zinc-500">{{ $countProgress['total'] }} {{ $t('منتج ضمن الجلسة', 'products in session') }} · {{ $countProgress['counted'] }} {{ $t('تم عدها', 'counted') }}</p></div>
        <div class="flex flex-wrap items-center gap-2">
            @if($count->status === 'draft')@can('stock_counts.open')<form method="POST" action="{{ route('inventory.counts.open', $count) }}">@csrf<button class="{{ $button }} min-h-10 bg-emerald-700 text-white">{{ $t('فتح الجلسة وتثبيت اللقطات', 'Open session and freeze snapshots') }}</button></form>@endcan @endif
        </div>
    </div>
    @if($count->status === 'in_progress')
        <div class="flex min-w-0 flex-wrap items-end justify-between gap-2 border-b border-zinc-100 bg-zinc-50/50 px-4 py-2 dark:border-zinc-800 dark:bg-zinc-800/20 sm:px-5" data-stocktake-action-bar>
            @can('stock_counts.edit')<div class="flex flex-wrap items-center gap-2"><a href="#stocktake-manual-add" onclick="event.preventDefault(); document.querySelector('[data-product-line-editor] [data-product-search]')?.focus()" class="{{ $button }} inline-flex min-h-10 items-center border border-emerald-600 text-emerald-800 dark:text-emerald-300">{{ $t('إضافة منتج', 'Add product') }}</a><a href="#stocktake-scan-controls" onclick="event.preventDefault(); document.querySelector('[data-scan-input]')?.focus()" class="{{ $button }} inline-flex min-h-10 items-center border border-zinc-300 dark:border-zinc-700">{{ $t('مسح باركود', 'Scan barcode') }}</a></div><form method="POST" action="{{ route('inventory.counts.add-all', $count) }}" class="flex min-w-0 flex-wrap items-end gap-2">@csrf<label class="min-w-0 flex-1 text-xs font-semibold">{{ $t('إضافة كل منتجات المخزن', 'Add all warehouse products') }}<select name="store_id" class="{{ $field }} min-w-32">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }}</option>@endforeach</select></label><button class="{{ $button }} min-h-10 border border-emerald-700 text-emerald-800 dark:text-emerald-300">{{ $t('إضافة الكل', 'Add all') }}</button></form>@endcan
        </div>
    @endif
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800 sm:px-5"><h3 class="text-sm font-bold">{{ $t('قائمة المنتجات والعد', 'Products and counts') }}</h3><span class="text-xs text-zinc-500">{{ $countLines->total() }} {{ $t('نتيجة', 'results') }}</span></div>
    <form method="GET" action="{{ route('inventory.counts.entry', $count) }}" class="grid min-w-0 gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800 sm:grid-cols-[minmax(0,2fr)_minmax(9rem,1fr)_auto] sm:items-end sm:px-5">
        <label class="min-w-0 text-xs font-semibold">{{ $t('بحث عن منتج أو باركود', 'Search product or barcode') }}<input name="count_search" value="{{ request('count_search') }}" class="{{ $field }}" placeholder="{{ $t('اسم أو رمز المنتج', 'Product name or code') }}"></label>
        <label class="min-w-0 text-xs font-semibold">{{ $t('حالة العد', 'Count status') }}<select name="count_filter" class="{{ $field }}"><option value="all">{{ $t('الكل', 'All') }}</option>@foreach(['not_counted' => $t('غير معدود', 'Uncounted'), 'matched' => $t('مطابق', 'Matched'), 'shortage' => $t('عجز', 'Shortage'), 'excess' => $t('زيادة', 'Excess'), 'movements' => $t('مع حركات', 'With movements')] as $value => $label)<option value="{{ $value }}" @selected(request('count_filter') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="{{ $button }} min-h-10 bg-sky-700 text-white">{{ $t('تصفية', 'Filter') }}</button>
    </form>
    <div class="min-w-0 max-w-full overflow-x-auto" data-stocktake-table-scroll><table class="w-full min-w-[1020px] border-collapse text-sm" data-stocktake-count-table>
        <thead class="bg-zinc-50 text-xs text-zinc-600 dark:bg-zinc-800/60"><tr><th class="px-3 py-3 text-start">{{ $t('المنتج / الرمز', 'Product / code') }}</th><th class="px-3 py-3 text-start">{{ $t('الموقع', 'Location') }}</th><th class="px-3 py-3 text-end">{{ $t('المتوقع', 'Expected') }}</th><th class="px-3 py-3 text-start">{{ $t('الكمية الفعلية', 'Actual count') }}</th><th class="px-3 py-3 text-end">{{ $t('الفرق', 'Difference') }}</th><th class="px-3 py-3 text-start">{{ $t('الحالة', 'Status') }}</th><th class="px-3 py-3 text-start">{{ $t('الملاحظة', 'Note') }}</th><th class="px-3 py-3 text-start">{{ $t('العد بواسطة / الوقت', 'Counted by / at') }}</th><th class="px-3 py-3 text-start">{{ $t('إجراء', 'Action') }}</th></tr></thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">@forelse($countLines as $line)
            @php $entryValues = $lineCalculations[$line->id] ?? null; $entryDifference = $entryValues['variance_quantity'] ?? null; @endphp
            <tr class="align-middle hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30" @if($count->status === 'in_progress')@can('stock_counts.participate') data-stocktake-quantity data-endpoint="{{ route('inventory.counts.quantity', $count) }}" data-count-id="{{ $count->id }}" data-user-id="{{ auth()->id() }}" data-store-id="{{ $line->store_id }}" data-lookup="{{ $line->product?->item_code }}" @endcan @endif>
                <td data-stocktake-col="product" class="min-w-48 px-3 py-2"><strong class="block">{{ \App\Support\HumanName::name($line->product) }}</strong><small class="block font-mono text-zinc-500" dir="ltr">{{ $line->product?->item_code }}</small></td>
                <td data-stocktake-col="location" class="px-3 py-2 text-xs">{{ \App\Support\HumanName::name($line->store) }}</td>
                <td data-stocktake-col="expected" class="px-3 py-2 text-end font-mono"><span class="stocktake-mobile-label">{{ $t('المتوقع', 'Expected') }}</span><span dir="ltr">{{ $entryValues ? $qty(bcsub($entryValues['expected_quantity'], $entryValues['movement_after_completion'], 6)) : $qty($line->reference_on_hand) }}</span></td>
                <td data-stocktake-col="actual" class="min-w-32 px-3 py-2"><span class="stocktake-mobile-label">{{ $t('الفعلي', 'Actual') }}</span>@if($count->status === 'in_progress')@can('stock_counts.participate')<input data-count-quantity type="number" min="0" step="1" inputmode="numeric" value="{{ $line->is_counted ? $qty($line->counted_quantity) : '' }}" class="{{ $field }} mt-0 bg-amber-50 font-mono dark:bg-amber-950/20" aria-label="{{ $t('الكمية الفعلية', 'Actual quantity') }}">@else<span dir="ltr" class="font-mono">{{ $line->is_counted ? $qty($line->counted_quantity) : '—' }}</span>@endcan @else<span dir="ltr" class="font-mono">{{ $line->is_counted ? $qty($line->counted_quantity) : '—' }}</span>@endif</td>
                <td data-stocktake-col="difference" class="px-3 py-2 text-end font-mono"><span class="stocktake-mobile-label">{{ $t('الفرق', 'Difference') }}</span><span dir="ltr">{{ $entryDifference !== null ? $qty($entryDifference) : '—' }}</span></td>
                <td data-stocktake-col="status" class="px-3 py-2 text-xs"><span class="stocktake-mobile-label">{{ $t('حالة العد', 'Count status') }}</span><span class="inline-flex rounded-full bg-zinc-100 px-2 py-1 font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ ! $entryValues ? $t('غير معدود', 'Uncounted') : (bccomp($entryDifference, '0', 6) === 0 ? $t('مطابق', 'Matched') : (bccomp($entryDifference, '0', 6) < 0 ? $t('عجز', 'Shortage') : $t('زيادة', 'Excess'))) }}</span></td>
                <td data-stocktake-col="note" class="min-w-40 px-3 py-2">@if($count->status === 'in_progress')@can('stock_counts.participate')<input data-count-note value="{{ $line->notes }}" class="{{ $field }} mt-0 bg-amber-50 dark:bg-amber-950/20" placeholder="{{ $t('ملاحظة', 'Note') }}">@else<span class="text-xs">{{ $line->notes ?: '—' }}</span>@endcan @else<span class="text-xs">{{ $line->notes ?: '—' }}</span>@endif</td>
                <td data-stocktake-col="audit" class="px-3 py-2 text-xs text-zinc-500">{{ $line->counter?->name ?? '—' }}<span class="block font-mono" dir="ltr">{{ $line->counted_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                <td data-stocktake-col="action" class="px-3 py-2 text-xs">@if($count->status === 'in_progress')@can('stock_counts.participate')<span data-count-save-state role="status" class="block text-zinc-500">{{ $t('محفوظ', 'Saved') }}</span>@endcan @if(! $line->completed_at)<form method="POST" action="{{ route('inventory.counts.complete-line', $line) }}">@csrf<button class="mt-1 font-semibold text-primary">{{ $t('تأكيد العد', 'Complete count') }}</button></form>@endif @endif</td>
            </tr>
        @empty<tr><td data-stocktake-col="empty" colspan="9" class="px-4 py-12 text-center text-zinc-500">{{ $t('لا توجد منتجات ضمن هذه النتائج.', 'No products match these filters.') }}</td></tr>@endforelse</tbody>
    </table></div>
    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $countLines->links() }}</div>
    @if($count->status === 'in_progress')
        <div id="stocktake-scan-controls" data-stocktake-scan data-count-id="{{ $count->id }}" data-user-id="{{ auth()->id() }}" data-endpoint="{{ route('inventory.counts.contribute', $count) }}" data-quantity-endpoint="{{ route('inventory.counts.quantity', $count) }}" class="min-w-0 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800 sm:px-5" aria-label="{{ $t('وضع المسح السريع', 'Quick scan mode') }}">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-bold">{{ $t('المسح السريع', 'Quick scan') }}</h3><span data-scan-save-state class="text-xs text-zinc-600" role="status">{{ $t('جاهز للمسح', 'Ready to scan') }}</span></div>
            <div class="grid min-w-0 gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(9rem,.8fr)_minmax(12rem,2fr)_auto_auto] xl:items-end">
                <label class="min-w-0 text-xs font-semibold">{{ $t('موقع العد', 'Count location') }}<select data-scan-store class="{{ $field }}">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }} — {{ $location->store?->code }}</option>@endforeach</select></label>
                <label class="min-w-0 text-xs font-semibold">{{ $t('باركود / رمز المنتج', 'Barcode / product code') }}<input data-scan-input type="text" inputmode="none" autocomplete="off" class="{{ $field }} min-w-0" placeholder="{{ $t('امسح الرمز ثم Enter', 'Scan code then Enter') }}"></label>
                <button type="button" data-scan-submit class="{{ $button }} min-h-10 bg-emerald-700 text-white">{{ $t('إضافة مسح', 'Add scan') }}</button><button type="button" data-scan-camera class="{{ $button }} min-h-10 border border-emerald-700 text-emerald-800 dark:text-emerald-300">{{ $t('فتح الكاميرا', 'Open camera') }}</button>
            </div>
            <div data-scan-camera-panel hidden class="mt-3 space-y-2"><video data-scan-video autoplay muted playsinline class="aspect-video max-h-72 w-full rounded-xl bg-zinc-950 object-contain"></video><button type="button" data-scan-stop class="{{ $button }} border border-zinc-400">{{ $t('إيقاف الكاميرا', 'Stop camera') }}</button></div>
            <p data-scan-feedback role="status" aria-live="polite" class="mt-2 text-xs text-zinc-700"></p>
            <div data-scan-last hidden class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs"><strong data-scan-last-name></strong><span data-scan-last-sku dir="ltr" class="mx-2 font-mono"></span><span data-scan-last-quantity dir="ltr" class="font-bold"></span></div>
            <div class="mt-2 grid min-w-0 gap-2 sm:grid-cols-[8rem_minmax(0,1fr)_auto]"><input data-scan-override type="number" min="0" step="1" inputmode="numeric" class="{{ $field }} mt-0" placeholder="{{ $t('الكمية الفعلية', 'Actual quantity') }}" aria-label="{{ $t('تعديل كمية آخر منتج', 'Override last product quantity') }}"><input data-scan-note class="{{ $field }} mt-0" placeholder="{{ $t('ملاحظة اختيارية', 'Optional note') }}"><button type="button" data-scan-override-save class="{{ $button }} min-h-10 border border-emerald-700">{{ $t('حفظ الكمية', 'Save quantity') }}</button></div>
        </div>
        <form id="stocktake-manual-add" method="POST" action="{{ route('inventory.counts.contribute', $count) }}" data-product-line-editor data-autofocus="false" class="flex min-w-0 flex-wrap items-end gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800 sm:px-5">@csrf
            <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="device_id" value="browser-{{ auth()->id() }}">
            <div data-product-line class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(9rem,1fr)_minmax(12rem,2fr)_minmax(7rem,.6fr)_minmax(9rem,1fr)] xl:items-end">
                <label class="min-w-0 text-xs font-semibold">{{ $t('الموقع الدقيق', 'Exact location') }}<select name="store_id" class="{{ $field }}">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }} — {{ $location->store?->code }}</option>@endforeach</select></label>
                <div class="min-w-0"><x-product-line-lookup name="lookup" value-field="item_code" :label="$t('المنتج', 'Product')" :required="true" /></div>
                <label class="text-xs font-semibold">{{ $t('الكمية', 'Quantity') }}<input data-line-quantity name="quantity" type="number" value="{{ old('quantity', '1') }}" min="0" step="1" inputmode="numeric" class="{{ $field }}" required></label>
                <label class="text-xs font-semibold">{{ $t('طريقة الإدخال', 'Input method') }}<select name="input_method" class="{{ $field }}"><option value="scan">{{ $t('مسح وحدة واحدة', 'Single scan') }}</option><option value="batch">{{ $t('إضافة كمية دفعة', 'Add batch quantity') }}</option><option value="manual">{{ $t('إدخال يدوي', 'Manual entry') }}</option></select></label>
            </div>
            <button type="submit" data-add-line class="{{ $button }} min-h-10 bg-emerald-700 text-white">{{ $t('إضافة بند', 'Add item') }}</button>
        </form>
    @endif
</section>
@endif
