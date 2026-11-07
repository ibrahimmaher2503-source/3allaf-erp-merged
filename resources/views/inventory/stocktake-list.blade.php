<section class="min-w-0 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-filters-heading">
    <h2 id="stocktake-filters-heading" class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-3 text-sm font-bold text-zinc-800 dark:border-zinc-800 dark:bg-zinc-800/20 dark:text-white sm:px-5">{{ $t('البحث عن جلسة جرد', 'Find a stocktake session') }}</h2>
    <form method="GET" action="{{ route('inventory.counts') }}" class="grid min-w-0 items-end gap-3 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-4" data-stocktake-filters>
        <label class="min-w-0 text-xs font-semibold text-zinc-600">{{ $t('رقم الجرد', 'Reference') }}<input name="count_number" value="{{ request('count_number') }}" class="{{ $field }}" placeholder="{{ $t('رقم الجرد', 'Count number') }}"></label>
        <label class="min-w-0 text-xs font-semibold text-zinc-600">{{ $t('المخزن / المنفذ', 'Warehouse / store') }}<select name="count_store_id" class="{{ $field }}"><option value="">{{ $t('كل المواقع', 'All locations') }}</option>@foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected((int) request('count_store_id') === $store->id)>{{ \App\Support\HumanName::name($store) }}</option>@endforeach</select></label>
        <label class="min-w-0 text-xs font-semibold text-zinc-600">{{ $t('الحالة', 'Status') }}<select name="count_status" class="{{ $field }}"><option value="">{{ $t('كل الحالات', 'All statuses') }}</option>@foreach(['draft', 'in_progress', 'submitted', 'recount_required', 'reconciled', 'cancelled'] as $countStatus)<option value="{{ $countStatus }}" @selected(request('count_status') === $countStatus)>{{ $status($countStatus) }}</option>@endforeach</select></label>
        <label class="min-w-0 text-xs font-semibold text-zinc-600">{{ $t('من تاريخ', 'From date') }}<input type="date" name="count_from" value="{{ request('count_from') }}" class="{{ $field }}"></label>
        <label class="min-w-0 text-xs font-semibold text-zinc-600">{{ $t('إلى تاريخ', 'To date') }}<input type="date" name="count_to" value="{{ request('count_to') }}" class="{{ $field }}"></label>
        <div class="flex flex-wrap gap-2 xl:col-span-3 xl:justify-end"><button type="submit" class="{{ $button }} min-h-10 bg-sky-700 px-5 text-white">{{ $t('بحث', 'Search') }}</button><a href="{{ route('inventory.counts') }}" class="{{ $button }} inline-flex min-h-10 items-center border border-zinc-300 dark:border-zinc-700">{{ $t('إعادة ضبط', 'Reset') }}</a></div>
    </form>
</section>
<section class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-list-heading">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/20 sm:px-5"><h2 id="stocktake-list-heading" class="text-base font-bold">{{ $t('قائمة الجرد', 'Stocktake results') }}</h2><span class="text-xs text-zinc-500">{{ $counts->total() }} {{ $t('جلسة', 'sessions') }}</span></div>
    <div class="divide-y divide-zinc-100 dark:divide-zinc-800" data-stocktake-results>
        @forelse($counts as $item)
            @php $itemProgress = $item->total_lines_count ? round(100 * $item->counted_lines_count / $item->total_lines_count) : 0; @endphp
            <article class="grid min-w-0 gap-3 px-4 py-3 hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 sm:px-5 lg:grid-cols-[minmax(0,2fr)_minmax(8rem,1fr)_minmax(10rem,1fr)_minmax(12rem,1.3fr)] lg:items-center" data-stocktake-session-row>
                <div class="min-w-0"><strong class="block truncate text-sm text-zinc-900 dark:text-white">{{ \App\Support\HumanName::name($item->store) }}</strong><div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 text-xs text-zinc-500"><span class="font-mono" dir="ltr">{{ $item->count_number }}</span><span dir="ltr">{{ $item->created_at?->format('Y-m-d H:i') }}</span><span>{{ $item->creator?->name }}</span></div></div>
                <div class="flex flex-wrap items-center gap-2"><x-status.badge :status="$item->status" :label="$status($item->status)" /><span class="text-xs text-zinc-500">{{ $item->count_type === 'full' ? $t('جرد كلي', 'Full count') : ($item->scope_type === 'random' ? $t('جرد عشوائي', 'Random count') : $t('جرد جزئي', 'Partial count')) }}</span></div>
                <div class="min-w-0"><div class="flex items-center justify-between gap-2 text-xs"><span>{{ $t('تقدم العد', 'Count progress') }}</span><strong class="font-mono" dir="ltr">{{ $item->counted_lines_count }}/{{ $item->total_lines_count }}</strong></div><div class="mt-1.5 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $itemProgress }}%"></div></div><div class="mt-1 text-xs text-zinc-500">{{ $item->matched_lines_count }} {{ $t('مطابق', 'matched') }} · {{ $item->shortage_lines_count }} {{ $t('عجز', 'shortages') }} · {{ $item->surplus_lines_count }} {{ $t('زيادة', 'surpluses') }}</div></div>
                <div class="flex min-w-0 flex-wrap items-center gap-1.5 lg:justify-end" data-stocktake-row-actions>
                    @if(in_array($item->status, ['draft', 'in_progress'], true))
                        @can('stock_counts.edit')<x-actions.button semantic="edit" :label="__('Enter count')" :href="route('inventory.counts.entry', $item)">{{ $t('إدخال العد', 'Enter count') }}</x-actions.button>@endcan
                    @elseif($item->status === 'submitted')
                        @can('stock_counts.reconcile')<x-actions.button semantic="view" :label="__('Review')" :href="route('inventory.counts.reconcile-page', $item)">{{ $t('مراجعة', 'Review') }}</x-actions.button>@endcan
                    @elseif($item->status === 'reconciled')<a class="{{ $button }} border border-zinc-300" href="{{ route('inventory.counts.reconcile-page', $item) }}">{{ $t('عرض الجرد المكتمل', 'View settled count') }}</a>@endif
                    @if((in_array($item->status, ['draft', 'in_progress', 'submitted', 'recount_required'], true) && auth()->user()->can('stock_counts.cancel'))
                        || ($item->status === 'in_progress' && auth()->user()->can('stock_counts.submit'))
                        || ($item->status === 'submitted' && auth()->user()->can('stock_counts.reconcile')))
                        <details class="relative" data-stocktake-row-menu><summary class="{{ $button }} cursor-pointer list-none border border-zinc-300 dark:border-zinc-700" aria-label="{{ $t('إجراءات أخرى', 'More actions') }}">⋯</summary>
                            <div class="absolute end-0 z-20 mt-1 flex w-56 flex-col gap-2 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                @if($item->status === 'in_progress')@can('stock_counts.submit')<form method="POST" action="{{ route('inventory.counts.submit', $item) }}">@csrf<x-actions.button type="submit" semantic="confirm" :label="__('Submit')">{{ $t('إرسال', 'Submit') }}</x-actions.button></form>@endcan @endif
                                @if($item->status === 'submitted')@can('stock_counts.reconcile')<form method="POST" action="{{ route('inventory.counts.reconcile', $item) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Reconcile and approve')">{{ $t('مطابقة واعتماد', 'Reconcile and approve') }}</x-actions.button></form>@endcan @endif
                                @can('stock_counts.cancel')<form method="POST" action="{{ route('inventory.counts.cancel', $item) }}" onsubmit="return confirm(@js($t('إلغاء جلسة الجرد؟', 'Cancel this stocktake?')))">@csrf<label class="text-xs font-semibold">{{ $t('سبب الإلغاء', 'Cancellation reason') }}<input name="reason" required maxlength="1000" class="{{ $field }}"></label><button class="{{ $button }} text-rose-700">{{ $t('إلغاء الجرد', 'Cancel count') }}</button></form>@endcan
                            </div>
                        </details>
                    @endif
                </div>
            </article>
        @empty<div class="px-4 py-12 text-center text-sm text-zinc-500">{{ $t('لا توجد جلسات جرد ضمن الفلاتر الحالية.', 'No stocktake sessions match these filters.') }}</div>@endforelse
    </div>
    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $counts->links() }}</div>
</section>
