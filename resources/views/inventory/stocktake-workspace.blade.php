@php
    $stocktakeTitle = match ($focus) {
        'count-create' => $t('جرد جديد', 'New stocktake'),
        'count-entry' => $t('جرد المخزون', 'Stocktake'),
        'count-reconcile' => $t('مراجعة الجرد', 'Stocktake review'),
        default => $t('عمليات الجرد', 'Stocktake sessions'),
    };
    $progressPercent = $count && $countProgress['total'] > 0 ? round(100 * $countProgress['counted'] / $countProgress['total']) : 0;
@endphp
<style>
    @media (min-width: 768px) and (max-width: 1199px) {
        .app-topbar { grid-template-columns: minmax(9rem, auto) minmax(0, 1fr); }
        .app-topbar__actions { grid-column: 1 / -1; flex-wrap: wrap; justify-content: flex-end; }
    }
</style>
<div class="min-w-0 space-y-4" data-guide="inventory-counts" data-stocktake-screen="{{ $focus }}">
    <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white dark:bg-transparent px-1 py-3 dark:border-zinc-800 sm:px-2" data-stocktake-page-header>
        <div class="min-w-0">
            <nav class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500" aria-label="{{ $t('مسار الجرد', 'Stocktake breadcrumb') }}">
                <a href="{{ route('inventory.index') }}" class="hover:text-primary">{{ $t('المخزون', 'Inventory') }}</a><span>/</span>
                @if($focus === 'counts')<span class="text-primary">{{ $t('الجرد', 'Stocktake') }}</span>
                @else<a href="{{ route('inventory.counts') }}" class="hover:text-primary">{{ $t('الجرد', 'Stocktake') }}</a><span>/</span><span class="text-primary">{{ $stocktakeTitle }}</span>@endif
            </nav>
            <div class="mt-1 flex min-w-0 flex-wrap items-center gap-2"><h1 class="text-xl font-bold text-zinc-900 dark:text-white sm:text-2xl">{{ $stocktakeTitle }} @if($count)<span class="font-mono text-base" dir="ltr">#{{ $count->count_number }}</span>@endif</h1>
                @if($count)<x-status.badge :status="$count->status" :label="$status($count->status)" />@endif
            </div>
        </div>
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            @if($focus === 'counts')
                @can('stock_counts.create')<a href="{{ route('inventory.counts.create') }}" class="{{ $button }} inline-flex min-h-10 items-center bg-emerald-700 text-white">{{ $t('جرد جديد', 'New stocktake') }}</a>@endcan
            @elseif($focus === 'count-create')
                <a href="{{ route('inventory.counts') }}" class="{{ $button }} inline-flex min-h-10 items-center border border-zinc-300 dark:border-zinc-700">{{ $t('كل الجلسات', 'All sessions') }}</a>
            @else
                <a href="{{ route('inventory.counts') }}" class="{{ $button }} inline-flex min-h-10 items-center border border-zinc-300 dark:border-zinc-700">{{ $t('كل الجلسات', 'All sessions') }}</a>
                @if($focus === 'count-entry')
                    @if($count->status === 'in_progress')@can('stock_counts.edit')<a href="#stocktake-manual-add" onclick="document.getElementById('stocktake-manual-add').open = true; requestAnimationFrame(() => document.querySelector('[data-product-line-editor] [data-product-search]')?.focus())" class="{{ $button }} inline-flex min-h-10 items-center bg-emerald-700 text-white">{{ $t('إضافة عنصر', 'Add item') }}</a>@endcan @endif
                    <a href="{{ route('inventory.counts.reconcile-page', $count) }}" class="{{ $button }} inline-flex min-h-10 items-center border border-sky-300 text-sky-800 dark:text-sky-300">{{ $t('مراجعة الجرد', 'Review count') }}</a>
                    @if($count->status === 'in_progress')@can('stock_counts.submit')<form method="POST" action="{{ route('inventory.counts.submit', $count) }}" class="inline-flex">@csrf<button type="submit" class="{{ $button }} inline-flex min-h-10 items-center bg-emerald-700 text-white">{{ $t('إرسال الجرد للمراجعة', 'Submit count for review') }}</button></form>@endcan @endif
                @elseif(in_array($count->status, ['draft', 'in_progress'], true))@can('stock_counts.edit')<a href="{{ route('inventory.counts.entry', $count) }}" class="{{ $button }} inline-flex min-h-10 items-center border border-sky-300 text-sky-800 dark:text-sky-300">{{ $t('متابعة العد', 'Resume count') }}</a>@endcan @endif
            @endif
        </div>
    </header>
    @if(session('success'))<div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($count)
        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-1 py-2 text-sm dark:border-zinc-800 sm:px-2" data-stocktake-session-bar>
            <div class="min-w-0"><strong class="text-zinc-900 dark:text-zinc-100">{{ \App\Support\HumanName::name($count->store) }}</strong><span class="mx-2 text-zinc-400">·</span><span class="font-mono text-xs" dir="ltr">{{ $count->store?->code }}</span></div>
            <div class="flex items-center gap-3"><span>{{ $t('تم عد', 'Counted') }} <strong class="font-mono" dir="ltr">{{ $countProgress['counted'] }}/{{ $countProgress['total'] }}</strong></span><span class="rounded-full bg-emerald-50 px-2 py-1 font-mono font-bold text-emerald-800 dark:bg-emerald-950/30" dir="ltr">{{ $progressPercent }}%</span></div>
        </div>
    @endif
    @if($focus === 'counts')@include('inventory.stocktake-list')
    @elseif($focus === 'count-create')@include('inventory.stocktake-create')
    @elseif($focus === 'count-entry')@include('inventory.stocktake-entry')
    @elseif($focus === 'count-reconcile')@include('inventory.stocktake-review')@endif
</div>
