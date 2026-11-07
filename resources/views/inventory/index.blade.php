@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $ar, string $en): string => $isArabic ? $ar : $en;
    $status = static fn (?string $value): string => \App\Modules\Platform\Support\UiLabel::status($value);
    $movementLabel = static fn (?string $value): string => \App\Modules\Inventory\Support\InventoryMovementLabel::label($value);
    $field = 'mt-1 w-full rounded-xl border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900';
    $button = 'rounded-xl px-3 py-2 text-xs font-bold transition focus:outline-none focus:ring-2 focus:ring-sky-500';
    $isBalancesFocus = $focus === 'balances';
    $isStocktakeFocus = in_array($focus, ['counts', 'count-create', 'count-entry', 'count-reconcile'], true);
    $isDedicatedInventoryDocument = in_array($focus, ['addition', 'deduction'], true);
    $inventoryRoute = $isBalancesFocus ? 'inventory.balances' : ($focus === 'movements' ? 'inventory.movements' : 'inventory.index');
    $movementTypes = \App\Modules\Inventory\Support\InventoryMovementLabel::options();
    $qty = static fn ($value): string => \App\Support\ProductQuantity::format($value);
    $countLines = null;
    $countProgress = null;
    if ($count && in_array($focus, ['count-entry', 'count-reconcile'], true)) {
        $countProgress = ['total' => $count->lines()->count(), 'counted' => $count->lines()->where('is_counted', true)->count(), 'matched' => $count->lines()->where('is_counted', true)->where('variance_quantity', 0)->count(), 'different' => $count->lines()->where('variance_quantity', '!=', 0)->count()];
        $lineQuery = $count->lines()->with(['product', 'store', 'counter']);
        $countSearch = trim((string) request('count_search', ''));
        if ($countSearch !== '') {
            $like = '%'.addcslashes($countSearch, '%_\\').'%';
            $lineQuery->whereHas('product', fn ($query) => $query->where('item_code', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', 'like', $like)));
        }
        if ($focus === 'count-entry' && request()->integer('count_category_id') > 0) {
            $lineQuery->whereHas('product', fn ($query) => $query->where('category_id', request()->integer('count_category_id')));
        }
        $lineFilter = (string) request('count_filter', 'all');
        if ($lineFilter === 'differences') $lineQuery->where(fn ($query) => $query->where('is_counted', false)->orWhere('variance_quantity', '!=', 0));
        if ($lineFilter === 'not_counted') $lineQuery->where('is_counted', false);
        if ($lineFilter === 'matched') $lineQuery->where('is_counted', true)->where('variance_quantity', 0);
        if ($lineFilter === 'shortage') $lineQuery->where('variance_quantity', '<', 0);
        if ($lineFilter === 'excess') $lineQuery->where('variance_quantity', '>', 0);
        if ($lineFilter === 'movements') {
            $lineQuery->whereExists(function ($query) use ($count) {
                $query->selectRaw('1')->from('stock_movements')
                    ->whereColumn('stock_movements.product_id', 'stock_count_lines.product_id')
                    ->whereColumn('stock_movements.store_id', 'stock_count_lines.store_id')
                    ->where(function ($boundary) use ($count) {
                        $boundary->where(function ($ids) {
                            $ids->whereNotNull('stock_count_lines.reference_movement_id')
                                ->whereColumn('stock_movements.id', '>', 'stock_count_lines.reference_movement_id');
                        })->orWhere(function ($legacy) use ($count) {
                            $legacy->whereNull('stock_count_lines.reference_movement_id')
                                ->where('stock_movements.posted_at', '>', $count->reference_at);
                        });
                    });
            });
        }
        $countLines = $lineQuery->orderBy('id')->paginate(30, ['*'], 'lines_page')->withQueryString();
        $lineCalculations = app(\App\Modules\Inventory\Support\StockCountReconciliation::class)->calculateMany($count, $countLines->getCollection());
    }
@endphp

<x-layouts.app :title="$isStocktakeFocus ? $t('الجرد المخزني', 'Stocktake') : ($isDedicatedInventoryDocument ? ($focus === 'addition' ? $t('إذن إضافة مخزون', 'Stock Addition / Entry Permit') : $t('إذن خصم مخزون', 'Stock Deduction / Exit Permit')) : $t('المخزون', 'Inventory'))">
    <div class="mx-auto {{ $isStocktakeFocus ? 'max-w-[1600px] space-y-4 py-4' : 'max-w-7xl space-y-6 py-6' }} px-4 sm:px-6 lg:px-8" data-inventory-demo data-inventory-focus="{{ $focus ?: 'workspace' }}">
        @unless($isStocktakeFocus)
        @if($isBalancesFocus)
            <header class="flex items-center gap-2 border-b border-border pb-5" data-guide="inventory-hero"><h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ __('Inventory balances') }}</h1><x-context-help :title="__('Inventory balances')" :label="__('Open contextual help')"><p>{{ __('Scoped balances, availability, and reorder by product and permitted store.') }}</p></x-context-help></header>
        @elseif($focus === 'transfers')
        <header class="flex items-end justify-between gap-3 border-b border-border pb-5"><div class="flex items-center gap-2"><h1 class="text-2xl font-bold">{{ $t('سجل تحويلات المخزون', 'Stock transfer register') }}</h1><x-context-help :title="$t('سجل تحويلات المخزون', 'Stock transfer register')" :label="__('Open contextual help')"><p>{{ $t('المراجع والمصادر والوجهات والحالات والكميات ضمن نطاقك.', 'References, sources, destinations, statuses, and quantities in your scope.') }}</p></x-context-help></div><div class="flex gap-2">@can('transfers.receive')<a href="{{ route('inventory.transfers.receiving-inbox') }}" class="{{ $button }} border border-sky-700 text-sky-700">{{ $t('الأذون الواردة للاستلام', 'Incoming receipts') }}</a>@endcan @can('transfers.create')<a href="{{ route('inventory.transfers.create') }}" class="{{ $button }} bg-sky-700 text-white">{{ $t('تحويل جديد', 'New transfer') }}</a>@endcan</div></header>
        @elseif($isDedicatedInventoryDocument)
        <header class="border-b border-border pb-5"><h1 class="text-2xl font-bold">{{ $focus === 'addition' ? $t('إذن إضافة مخزون', 'Stock Addition / Entry Permit') : $t('إذن خصم مخزون', 'Stock Deduction / Exit Permit') }}</h1></header>
        @elseif($focus === 'counts')
        <header class="flex items-end justify-between gap-3 border-b border-border pb-5"><div class="flex items-center gap-2"><h1 class="text-2xl font-bold">{{ $t('سجل الجرد والتسويات', 'Counts and adjustments register') }}</h1><x-context-help :title="$t('سجل الجرد والتسويات', 'Counts and adjustments register')" :label="__('Open contextual help')"><p>{{ $t('خطط الجرد والفروقات والتسويات ضمن المتاجر المصرح بها.', 'Count plans, variances, and adjustments in permitted stores.') }}</p></x-context-help></div>@can('stock_counts.create')<a href="{{ route('inventory.counts.create') }}" class="{{ $button }} bg-emerald-700 text-white">{{ $t('جرد جديد', 'New count') }}</a>@endcan</header>
        @else
        <header class="flex flex-col gap-3 border-b border-border pb-5 md:flex-row md:items-end md:justify-between" data-guide="inventory-hero">
            <div class="flex items-center gap-2"><h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ $t('المخزون', 'Inventory') }}</h1><x-context-help :title="$t('المخزون', 'Inventory')" :label="__('Open contextual help')"><p>{{ $t('تابع الرصيد والحركة والتحويل والجرد ضمن نطاقك.', 'Track balances, movements, transfers, and counts within your scope.') }}</p></x-context-help></div>
            <div class="flex w-full flex-wrap gap-2 md:w-auto md:justify-end">
                @can('transfers.create')<a href="{{ route('inventory.transfers.create') }}" class="{{ $button }} bg-sky-700 text-white">{{ $t('تحويل جديد', 'New transfer') }}</a>@endcan
            </div>
        </header>
        @endif
        @endunless

        @if($focus === null)
        <section class="space-y-3" aria-labelledby="inventory-overview-heading">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-semibold text-primary">{{ $t('لوحة الوحدة', 'Module dashboard') }}</p><div class="mt-1 flex items-center gap-2"><h2 id="inventory-overview-heading" class="text-xl font-bold">{{ $t('نظرة تشغيلية على المخزون', 'Inventory operational overview') }}</h2><x-context-help :title="$t('نظرة تشغيلية على المخزون', 'Inventory operational overview')" :label="__('Open contextual help')"><p>{{ $t('مؤشرات حقيقية ضمن المتاجر المصرح بها والفلاتر الحالية.', 'Real indicators within authorized stores and current filters.') }}</p></x-context-help></div></div><div class="module-quick-actions">
                <flux:button :href="route('inventory.balances')" size="sm" variant="subtle" icon="archive-box" wire:navigate>{{ $t('الأرصدة', 'Balances') }}</flux:button>
                <flux:button :href="route('inventory.movements')" size="sm" variant="subtle" icon="queue-list" wire:navigate>{{ $t('الحركات', 'Movements') }}</flux:button>
                @can('transfers.view')<flux:button :href="route('inventory.transfers')" size="sm" variant="subtle" icon="arrows-right-left" wire:navigate>{{ $t('التحويلات', 'Transfers') }}</flux:button>@endcan
                <flux:button :href="route('inventory.counts')" size="sm" variant="subtle" icon="clipboard-document-list" wire:navigate>{{ $t('الجرد', 'Counts') }}</flux:button>
                <flux:button :href="route('inventory.adjustments')" size="sm" variant="subtle" icon="adjustments-horizontal" wire:navigate>{{ $t('التسويات', 'Adjustments') }}</flux:button>
                @can('pricing_labels.view')<flux:button :href="route('pricing.labels')" size="sm" variant="subtle" icon="qr-code" wire:navigate>{{ $t('الباركود', 'Barcodes') }}</flux:button>@endcan
            </div></div>
            <div class="module-overview-grid" tabindex="0" aria-label="{{ $t('مؤشرات المخزون، مرر أفقياً على الشاشات الصغيرة', 'Inventory indicators; scroll horizontally on small screens') }}">
                <a href="{{ route('catalog.products') }}" class="dashboard-kpi dashboard-kpi--priority"><div><p class="dashboard-kpi__label">{{ $t('المنتجات والمتغيرات النشطة', 'Active products & variants') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['active_products'] + $inventoryOverview['active_variants'] }}</p><p class="dashboard-kpi__state">{{ $inventoryOverview['active_products'] }} {{ $t('منتج', 'products') }} · {{ $inventoryOverview['active_variants'] }} {{ $t('متغير', 'variants') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.cube-transparent class="size-5" /></span></a>
                <a href="{{ route('inventory.balances') }}" class="dashboard-kpi dashboard-kpi--priority"><div><p class="dashboard-kpi__label">{{ $t('المنتجات المرئية', 'Visible products') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['visible_products'] }}</p><p class="dashboard-kpi__state">{{ $t('لها رصيد ضمن النطاق', 'with balances in scope') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.cube class="size-5" /></span></a>
                <a href="{{ route('inventory.balances') }}" class="dashboard-kpi dashboard-kpi--priority"><div><p class="dashboard-kpi__label">{{ $t('إجمالي الرصيد الفعلي', 'Total stock on hand') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $qty($inventoryOverview['stock_on_hand']) }}</p><p class="dashboard-kpi__state">{{ $t('عبر المتاجر المرئية', 'across visible stores') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.archive-box class="size-5" /></span></a>
                @if($inventoryOverview['inventory_value'] !== null)<a href="{{ route('inventory.balances') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('قيمة المخزون', 'Inventory value') }}</p><p class="dashboard-kpi__value" dir="ltr"><x-money :amount="$inventoryOverview['inventory_value']" currency="EGP" /></p><p class="dashboard-kpi__state">{{ $t('من قيمة الأرصدة المعتمدة', 'authoritative balance value') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.banknotes class="size-5" /></span></a>@endif
                <a href="{{ route('inventory.balances') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('منخفض المخزون', 'Low stock') }}</p><p class="dashboard-kpi__value text-amber-700" dir="ltr">{{ $inventoryOverview['low_stock'] }}</p><p class="dashboard-kpi__state">{{ $t('من وحدة إلى خمس', 'one to five available') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.exclamation-triangle class="size-5" /></span></a>
                <a href="{{ route('inventory.balances') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('نافد المخزون', 'Out of stock') }}</p><p class="dashboard-kpi__value text-rose-700" dir="ltr">{{ $inventoryOverview['out_of_stock'] }}</p><p class="dashboard-kpi__state">{{ $t('المتاح صفر أو أقل', 'zero or less available') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.archive-box-x-mark class="size-5" /></span></a>
                <a href="{{ route('inventory.balances', ['stock_state' => 'negative']) }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('رصيد سالب', 'Negative stock') }}</p><p class="dashboard-kpi__value text-rose-700" dir="ltr">{{ $inventoryOverview['negative_stock'] }}</p><p class="dashboard-kpi__state">{{ $t('استثناء يتطلب المراجعة', 'exception requiring review') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.exclamation-circle class="size-5" /></span></a>
                <a href="{{ route('inventory.movements') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('حركات اليوم', "Today's movements") }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['today_movements'] }}</p><p class="dashboard-kpi__state">{{ $t('حركات مخزون مرحلة', 'posted stock movements') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.queue-list class="size-5" /></span></a>
                <a href="{{ route('catalog.products') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('بيانات كتالوج ناقصة', 'Catalog exceptions') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['missing_barcodes'] + $inventoryOverview['missing_catalog_data'] }}</p><p class="dashboard-kpi__state">{{ $inventoryOverview['missing_barcodes'] }} {{ $t('دون باركود', 'without barcode') }} · {{ $inventoryOverview['missing_catalog_data'] }} {{ $t('بيانات ناقصة', 'missing data') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.qr-code class="size-5" /></span></a>
                @can('transfers.view')<a href="{{ route('inventory.transfers') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('تحويلات معلقة', 'Pending transfers') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['pending_transfers'] }}</p><p class="dashboard-kpi__state">{{ $t('قبل الإغلاق النهائي', 'before final completion') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.arrows-right-left class="size-5" /></span></a>@endcan
                <a href="{{ route('inventory.counts') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('جرد نشط', 'Active counts') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['active_counts'] }}</p><p class="dashboard-kpi__state">{{ $t('مسودة أو قيد التنفيذ', 'draft, active, or submitted') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.clipboard-document-list class="size-5" /></span></a>
                <a href="{{ route('inventory.adjustments') }}" class="dashboard-kpi"><div><p class="dashboard-kpi__label">{{ $t('تسويات معلقة', 'Pending adjustments') }}</p><p class="dashboard-kpi__value" dir="ltr">{{ $inventoryOverview['pending_adjustments'] }}</p><p class="dashboard-kpi__state">{{ $t('مسودة أو مقدمة', 'draft or submitted') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.adjustments-horizontal class="size-5" /></span></a>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="dashboard-panel"><header class="dashboard-panel__header"><flux:heading size="lg">{{ $t('توزيع المخزون حسب المتجر', 'Stock distribution by store') }}</flux:heading><flux:icon.building-storefront class="size-5 text-primary" /></header><div class="dashboard-list">@forelse($inventoryOverview['stock_distribution'] as $store)<a href="{{ route('inventory.balances', ['store_id' => $store->id]) }}" class="dashboard-list__row"><span><strong class="block">{{ $store->code }}</strong><small class="text-text-muted">{{ $isArabic ? $store->name_ar : ($store->name_en ?: $store->name_ar) }}</small></span><strong class="tabular-nums">{{ $qty($store->on_hand) }}</strong></a>@empty<x-state.empty :title="$t('لا توجد أرصدة موزعة', 'No distributed balances')" icon="archive-box" />@endforelse</div></section>
                <section class="dashboard-panel"><header class="dashboard-panel__header"><flux:heading size="lg">{{ $t('المنتجات الأعلى حركة خلال 7 أيام', 'Top-moving products · 7 days') }}</flux:heading><flux:icon.arrow-trending-up class="size-5 text-primary" /></header><div class="dashboard-list">@forelse($inventoryOverview['top_moving_products'] as $product)<a href="{{ route('inventory.stock-card', $product->id) }}" class="dashboard-list__row"><span class="min-w-0"><strong class="block truncate">{{ $isArabic ? $product->name_ar : ($product->name_en ?: $product->name_ar) }}</strong><small class="font-mono text-text-muted">{{ $product->item_code }}</small></span><strong class="tabular-nums">{{ $qty($product->moved_quantity) }}</strong></a>@empty<x-state.empty :title="$t('لا توجد حركات خلال الفترة', 'No movements in this period')" icon="queue-list" />@endforelse</div></section>
            </div>
        </section>
        @endif

        @unless($isStocktakeFocus)
            @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @endunless

        @if($isStocktakeFocus)
            @include('inventory.stocktake-workspace')
        @else
        @if($focus !== 'transfer-create' && ! $isDedicatedInventoryDocument)
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="inventory-filters-heading">
            <div class="mb-4 flex flex-col gap-3 border-b border-zinc-200 pb-3 dark:border-zinc-800 md:flex-row md:items-center md:justify-between"><div><h2 id="inventory-filters-heading" class="text-base font-bold text-text-primary">{{ $t('تصفية العرض', 'Filter inventory') }}</h2><p class="mt-1 text-sm text-text-muted">{{ $t('حدّد المنتج أو المتجر أو نوع الحركة.', 'Narrow results by product, store, or movement type.') }}</p></div></div>
            <form method="GET" action="{{ route($inventoryRoute) }}" class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-[minmax(16rem,2fr)_minmax(10rem,1fr)_repeat(3,minmax(9rem,1fr))_auto]" data-inventory-filter>
                <div><label for="inventory-q" class="text-xs font-semibold">{{ $t('بحث عن منتج', 'Product search') }}</label><input id="inventory-q" name="q" value="{{ $search }}" placeholder="{{ $t('اسم أو كود أو باركود', 'Name, item code, or barcode') }}" class="{{ $field }}"></div>
                <label class="flex min-h-10 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm dark:border-zinc-700"><input type="checkbox" name="include_zero" value="1" @checked($includeZero)><span>{{ $t('إظهار المواقع ذات الرصيد الصفري', 'Include zero-balance locations') }}</span></label>
                <div><label for="inventory-store" class="text-xs font-semibold">{{ $t('المتجر', 'Store') }}</label><select id="inventory-store" name="store_id" class="{{ $field }}"><option value="">{{ $t('كل المتاجر المسموحة', 'All permitted stores') }}</option>@foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected($filterStoreId === $store->id)>{{ $store->code }} · {{ $isArabic ? $store->name_ar : $store->name_en }}</option>@endforeach</select></div>
                <div><label for="inventory-stock-state" class="text-xs font-semibold">{{ $t('حالة المخزون', 'Stock state') }}</label><select id="inventory-stock-state" name="stock_state" class="{{ $field }}"><option value="">{{ $t('كل الحالات', 'All states') }}</option><option value="available" @selected($stockState === 'available')>{{ $t('متاح', 'Available') }}</option><option value="low" @selected($stockState === 'low')>{{ $t('منخفض', 'Low stock') }}</option><option value="out" @selected($stockState === 'out')>{{ $t('نافد', 'Out of stock') }}</option><option value="negative" @selected($stockState === 'negative')>{{ $t('سالب', 'Negative') }}</option></select></div>
                <div><label for="inventory-movement-type" class="text-xs font-semibold">{{ $t('نوع الحركة', 'Movement type') }}</label><select id="inventory-movement-type" name="movement_type" class="{{ $field }}"><option value="">{{ $t('كل أنواع الحركة', 'All movement types') }}</option>@foreach($movementTypes as $type => $label)<option value="{{ $type }}" @selected($movementType === $type)>{{ $label }}</option>@endforeach</select></div>
                <div class="flex w-full gap-2 md:col-span-2 md:justify-end xl:col-span-1 xl:w-auto xl:flex-nowrap"><button type="submit" class="{{ $button }} min-h-10 flex-1 bg-zinc-900 text-white xl:flex-none">{{ $t('تطبيق', 'Apply') }}</button><a href="{{ route($inventoryRoute) }}" class="{{ $button }} min-h-10 flex-1 border border-zinc-300 text-center dark:border-zinc-700 xl:flex-none">{{ __('Reset filters') }}</a></div>
            </form>
        </section>

        <dl class="grid divide-y divide-zinc-200 rounded-2xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900 sm:grid-cols-2 sm:divide-y-0 xl:grid-cols-5" data-guide="inventory-summary">
            @foreach([[$t('الأرصدة', 'Balances'), $inventorySummary['balances']], [$t('الحركات', 'Movements'), $inventorySummary['movements']], [$t('التحويلات', 'Transfers'), $inventorySummary['transfers']], [$t('التسويات', 'Adjustments'), $inventorySummary['adjustments']], [$t('خطط الجرد', 'Stock counts'), $inventorySummary['counts']]] as [$label, $value])<div @class(['flex items-baseline justify-between gap-3 px-4 py-3', 'xl:border-s xl:border-zinc-200 xl:dark:border-zinc-800' => ! $loop->first])><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="font-mono text-lg font-bold text-text-primary">{{ $value }}</dd></div>@endforeach
        </dl>
        @endif

        @if(in_array($focus, [null, 'balances', 'stock-card'], true))
        <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="balances-heading" data-guide="inventory-balances">
            <div class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 id="balances-heading" class="text-lg font-bold">{{ $t('الأرصدة المتاحة وإعادة الطلب', 'Available balances and reorder') }}</h2><x-context-help :title="$t('الأرصدة المتاحة وإعادة الطلب', 'Available balances and reorder')" :label="__('Open contextual help')"><p>{{ $t('التوفر = الرصيد الفعلي ناقص المحجوز. الكميات قيد النقل منفصلة حتى الاستلام.', 'Available = on-hand minus reserved. In-transit remains separate until receipt.') }}</p><p>{{ $t('الاستعلام عبر الفروع للمعلومة فقط ولا يغير متجر البيع أو السلة.', 'Cross-branch lookup is informational only and never changes the selling store or cart.') }}</p></x-context-help></div>
            <x-tables.table-shell :label="$t('أرصدة المخزون', 'Inventory balances')"><div class="overflow-x-auto"><table class="data-table responsive-resource-table w-full text-start text-sm" data-stock-balance-table><thead><tr><th>{{ $t('المنتج', 'Product') }}</th><th>{{ $t('المتجر / الفرع', 'Store / branch') }}</th><th>{{ $t('فعلي', 'On-hand') }}</th><th>{{ $t('محجوز', 'Reserved') }}</th><th>{{ $t('متاح', 'Available') }}</th><th>{{ $t('قيد النقل', 'In transit') }}</th>@if($canViewCost)<th>{{ $t('التكلفة', 'Cost') }}</th>@endif<th>{{ $t('إعادة الطلب', 'Reorder') }}</th></tr></thead><tbody>
                @forelse($balances as $balance)
                    @php $available = (float) $balance->on_hand - (float) $balance->reserved; $reorder = (float) ($balance->product?->reorder_threshold ?? 0); $branch = $balance->store?->branch; @endphp
                    <tr data-stock-balance-row><td data-primary><strong>{{ \App\Support\HumanName::name($balance->product) }}</strong><div class="text-xs text-zinc-500" dir="ltr">{{ $balance->product?->item_code }} @if($balance->product?->model_number)· {{ $balance->product->model_number }}@endif @if($balance->product?->barcodes->first())· {{ $balance->product->barcodes->first()->barcode }}@endif</div></td><td data-label="{{ $t('الموقع والفرع', 'Location and branch') }}">@can('branches_stores.view')<a class="font-bold text-primary" href="{{ route('admin.stores',['search'=>$balance->store?->code]) }}">{{ \App\Support\HumanName::name($balance->store) }}</a>@else<span class="font-bold">{{ \App\Support\HumanName::name($balance->store) }}</span>@endcan<div class="text-xs text-zinc-500" dir="ltr">{{ $balance->store?->code }}</div><div class="text-xs">{{ $balance->store?->type==='selling'?$t('منفذ بيع','Sales outlet'):$t('مخزن','Warehouse') }} · {{ $t('الفرع','Branch') }}: {{ \App\Support\HumanName::name($branch) }} <small dir="ltr">{{ $branch?->code }}</small></div></td><td data-label="{{ $t('فعلي', 'On-hand') }}" class="font-mono" dir="ltr">{{ $qty($balance->on_hand) }}</td><td data-label="{{ $t('محجوز', 'Reserved') }}" class="font-mono" dir="ltr">{{ $qty($balance->reserved) }}</td><td data-label="{{ $t('متاح', 'Available') }}" class="font-mono font-bold {{ $available <= $reorder ? 'text-rose-700' : 'text-emerald-700' }}" dir="ltr">{{ $qty($available) }}</td><td data-label="{{ $t('قيد النقل', 'In transit') }}" class="font-mono" dir="ltr">{{ $qty($balance->in_transit) }}</td>@if($canViewCost)<td data-label="{{ $t('التكلفة', 'Cost') }}"><x-money :amount="$balance->average_cost" currency="EGP" /></td>@endif<td data-label="{{ $t('إعادة الطلب', 'Reorder') }}" class="font-mono" dir="ltr">{{ $available <= $reorder ? $t('مطلوب','Required') : $t('سليم','Healthy') }} · {{ $qty($reorder) }}</td></tr>
                @empty<tr><td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-5 py-10 text-center text-zinc-500">{{ $t('لا توجد أرصدة ضمن النطاق.', 'No balances in this scope.') }}</td></tr>@endforelse
            </tbody></table></div><div class="border-t border-zinc-200 px-5 py-3"><x-tables.pagination :paginator="$balances" :page-sizes="[25]" /></div></x-tables.table-shell>
        </section>

        @endif
        @unless($isBalancesFocus)
        @if($focus === 'transfer-create' || $transfer?->status === 'draft')
            <section class="rounded-3xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-indigo-50/70 p-5 shadow-sm dark:border-sky-800 dark:from-sky-950/30 dark:via-zinc-900 dark:to-indigo-950/20 sm:p-6" aria-labelledby="transfer-editor-heading">
                <div class="flex flex-col gap-2 border-b border-sky-200/80 pb-4 dark:border-sky-800/70 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center gap-2">
                        <h2 id="transfer-editor-heading" class="text-xl font-bold text-sky-950 dark:text-sky-100">{{ $transfer ? $t('تعديل تحويل مسودة', 'Edit draft transfer') : $t('إنشاء تحويل مخزون', 'Create stock transfer') }}</h2>
                        <x-context-help :title="$transfer ? $t('تعديل تحويل مسودة', 'Edit draft transfer') : $t('إنشاء تحويل مخزون', 'Create stock transfer')" :label="__('Open contextual help')"><p>{{ $t('حدد المتجر المصدر والوجهة، ثم اختر المنتج والكمية لإنشاء مسودة التحويل.', 'Choose the source and destination stores, then select the product and quantity to create the transfer draft.') }}</p></x-context-help>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800 dark:bg-sky-900/60 dark:text-sky-200">{{ $transfer ? $t('مسودة قيد التعديل', 'Draft in progress') : $t('مسودة جديدة', 'New draft') }}</span>
                </div>
                <form
                    class="mt-4 space-y-4"
                    method="POST"
                    action="{{ $transfer ? route('inventory.transfers.update', $transfer) : route('inventory.transfers.store') }}"
                    x-data="{ attempted: false, source: @js((string) old('source_store_id', $transfer?->source_store_id ?? '')) }"
                    x-on:submit="attempted = true; if (!source || !$el.querySelector('[data-destination-choice] input:checked') || $el.querySelector('[data-line-allocation-error]:not([hidden])')) { $event.preventDefault(); } else { $el.querySelector('button[type=submit]').disabled = true; }"
                    data-transfer-allocation-editor data-product-line-editor data-autofocus="true" data-next-index="{{ max(1, ...array_map('intval', array_keys((array) old('lines', $transfer?->lines?->all() ?: [null])))) + 1 }}"
                >
                    @csrf
                    @php
                        $selectedDestinationIds = old('destination_store_ids', $transfer ? $transfer->lines->flatMap(fn ($line) => $line->allocations->pluck('destination_store_id'))->push($transfer->destination_store_id)->unique()->all() : []);
                        $submittedLines = old('lines');
                        $transferFormLines = $transfer?->lines?->all() ?: [null];
                        if (is_array($submittedLines)) {
                            $submittedProducts = \App\Modules\Catalog\Models\Product::query()->whereIn('id', collect($submittedLines)->pluck('product_id')->filter()->map(fn ($id) => (int) $id))->get()->keyBy('id');
                            $transferFormLines = [];
                            foreach ($submittedLines as $index => $submittedLine) {
                                if (! is_array($submittedLine)) continue;
                                $draftLine = new \App\Modules\Inventory\Models\StockTransferLine(['product_id' => (int) ($submittedLine['product_id'] ?? 0), 'quantity_requested' => $submittedLine['quantity_requested'] ?? '']);
                                $draftLine->setRelation('product', $submittedProducts->get($draftLine->product_id));
                                $draftLine->setRelation('allocations', collect((array) ($submittedLine['allocations'] ?? []))->map(fn ($quantity, $destinationId) => new \App\Modules\Inventory\Models\StockTransferAllocation(['destination_store_id' => (int) $destinationId, 'quantity' => $quantity])));
                                $transferFormLines[$index] = $draftLine;
                            }
                            if ($transferFormLines === []) $transferFormLines = [null];
                        }
                    @endphp
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-sky-200/80 bg-white/75 p-4 shadow-sm dark:border-sky-800/70 dark:bg-zinc-900/60">
                            <label for="transfer-source" class="text-xs font-semibold">{{ $t('المتجر المصدر', 'Source store') }}</label>
                            <select id="transfer-source" name="source_store_id" class="{{ $field }}" x-model="source" x-on:invalid="attempted = true" :aria-invalid="attempted && !source ? 'true' : 'false'" aria-describedby="transfer-source-error" data-transfer-source required>
                                <option value="" disabled>{{ __('Select source store') }}</option>
                                @foreach($visibleStores->where('status', 'active') as $store)
                                    <option value="{{ $store->id }}">{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</option>
                                @endforeach
                            </select>
                            <p id="transfer-source-error" class="mt-1 text-xs text-red-700" x-cloak x-show="attempted && !source">{{ __('Select source store') }}</p>
                            @error('source_store_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="rounded-2xl border border-indigo-200/80 bg-white/75 p-4 shadow-sm dark:border-indigo-800/70 dark:bg-zinc-900/60">
                            <span class="text-xs font-semibold">{{ $t('المخازن المحول إليها', 'Destination warehouses') }}</span>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($visibleStores->where('status', 'active') as $store)<label class="flex items-center gap-2 text-sm" data-destination-choice><input type="checkbox" name="destination_store_ids[]" value="{{ $store->id }}" @checked(in_array($store->id, array_map('intval', (array) $selectedDestinationIds), true))>{{ \App\Support\HumanName::name($store) }} · {{ $store->code }}</label>@endforeach</div>
                            <input type="hidden" name="destination_store_id" value="{{ old('destination_store_id', $transfer?->destination_store_id) }}">
                            @error('destination_store_ids')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-800 dark:bg-amber-950/20" x-data="{ invoice: @js((string) old('invoice_reference', $transfer?->invoice_reference ?? '')), invoiceStore: '', loading: false, message: '', mismatch: false, async loadInvoice() { const source = document.querySelector('#transfer-source')?.value; if (!this.invoice || !source) { this.message = @js(__('اختر مخزن المصدر وأدخل رقم الفاتورة أولًا.')); return; } this.loading = true; this.message = ''; const response = await fetch(@js(url('/inventory/transfers')).replace('/transfers', '/transfers/purchase-invoices/'+encodeURIComponent(this.invoice.trim())+'/products')+'?source_store_id='+source, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const data = await response.json(); this.loading = false; if (!response.ok) { this.message = @js(__('تعذر تحميل فاتورة الشراء المعتمدة.')); return; } this.mismatch = data.warehouse_mismatch; this.invoiceStore = data.invoice_store_id; this.message = data.message || (data.lines.length ? @js(__('تم تحميل منتجات الفاتورة في بنود التحويل.')) : @js(__('لا تحتوي الفاتورة على بنود.'))); document.querySelector('[data-transfer-allocation-editor]')?.dispatchEvent(new CustomEvent('purchase-invoice-loaded', { bubbles: true, detail: { lines: data.lines } })); } }">
                        <div class="flex flex-wrap items-end gap-3"><div class="min-w-48 flex-1"><label for="approved-invoice" class="text-xs font-semibold">{{ $t('إضافة من فاتورة شراء معتمدة', 'Add from Approved Purchase Invoice') }}</label><input id="approved-invoice" name="invoice_reference" x-model="invoice" class="{{ $field }}" placeholder="{{ $t('رقم أو معرف الفاتورة', 'Invoice number or ID') }}"></div><button type="button" class="{{ $button }} border border-amber-600 text-amber-800" x-on:click="loadInvoice" :disabled="loading">{{ $t('تحميل المنتجات', 'Load products') }}</button></div>
                        <p class="mt-2 text-xs text-amber-900" x-show="message" x-text="message"></p><button type="button" class="mt-2 text-xs font-bold text-amber-900 underline" x-show="mismatch" x-on:click="document.querySelector('#transfer-source').value = invoiceStore; document.querySelector('#transfer-source').dispatchEvent(new Event('change', { bubbles: true })); loadInvoice()">{{ $t('استخدام مخزن الفاتورة كمصدر', 'Use invoice warehouse as source') }}</button>
                    </div>
                    <div class="space-y-3">
                        @foreach($transferFormLines as $lineIndex => $transferLine)
                            @php
                                $sourceId = (int) old('source_store_id', $transfer?->source_store_id ?? 0);
                                $balance = $transferLine ? \App\Modules\Inventory\Models\StockBalance::query()->where('store_id', $sourceId)->where('product_id', $transferLine->product_id)->first() : null;
                                $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : null;
                                $selling = $transferLine && $sourceId ? app(\App\Modules\Pricing\Services\EffectivePriceResolver::class)->resolve((int) $transferLine->product_id, $sourceId)?->amount : null;
                            @endphp
                            <div class="grid min-w-0 gap-x-2 gap-y-1.5 rounded-lg border border-zinc-200 bg-white/75 px-3 py-2 odd:bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-900/70 lg:grid-cols-[minmax(12rem,2fr)_repeat(4,minmax(0,1fr))_2.5rem]" data-product-line>
                                <div><x-product-line-lookup :name="'lines['.$lineIndex.'][product_id]'" :value="old('lines.'.$lineIndex.'.product_id', $transferLine?->product_id)" :display="$transferLine?->product ? ((str_starts_with(app()->getLocale(), 'ar') ? $transferLine->product->name_ar : $transferLine->product->name_en).' · '.$transferLine->product->item_code) : ''" :label="$t('المنتج', 'Product')" :required="true" :transfer="true" source-store-selector="#transfer-source" /></div>
                                <div><span class="block text-xs font-semibold">{{ $t('الكمية المتاحة', 'Available quantity') }}</span><output data-line-available dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">{{ $available === null ? '—' : $qty($available) }}</output></div>
                                <div><span class="block text-xs font-semibold">{{ $t('سعر التكلفة', 'Cost price') }}</span><output data-line-cost dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">@if($balance)<x-money :amount="$balance->average_cost" />@else—@endif</output></div>
                                <div><span class="block text-xs font-semibold">{{ $t('سعر البيع', 'Selling price') }}</span><output data-line-selling-price dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">@if($selling !== null)<x-money :amount="$selling" />@else—@endif</output></div>
                                <div><span class="text-xs font-semibold">{{ $t('إجمالي الكمية الموزعة', 'Allocated total') }}</span><output data-line-transfer-total dir="ltr" class="mt-1 flex h-9 items-center justify-center rounded-md bg-zinc-50 px-2 font-mono">{{ $qty(old('lines.'.$lineIndex.'.quantity_requested', $transferLine?->quantity_requested ?? 0)) }}</output></div>
                                <div data-transfer-allocations>
                                    @foreach($visibleStores->where('status', 'active') as $destination)
                                        <label class="text-xs font-semibold" data-destination-id="{{ $destination->id }}" hidden>
                                            <span>{{ \App\Support\HumanName::name($destination) }}</span>
                                            <input dir="ltr" name="lines[{{ $lineIndex }}][allocations][{{ $destination->id }}]" value="{{ ($allocationValue = old('lines.'.$lineIndex.'.allocations.'.$destination->id, $transferLine?->allocations?->firstWhere('destination_store_id', $destination->id)?->quantity ?? '')) === '' ? '' : $qty($allocationValue) }}" class="{{ $field }}" inputmode="decimal" min="0" step="any" placeholder="0" data-allocation-input>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-rose-700" data-line-allocation-error role="status" hidden>{{ $t('الكمية الموزعة تتجاوز الكمية المتاحة في المصدر.', 'Allocated quantity exceeds available source stock.') }}</p>
                                <button type="button" data-remove-line class="inline-flex size-10 items-center justify-center self-end rounded-lg text-rose-700 hover:bg-rose-50" title="{{ $t('حذف البند', 'Delete item') }}" aria-label="{{ $t('حذف البند', 'Delete item') }}"><svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 11v5m4-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                            </div>
                        @endforeach
                        <template data-line-template>
                            <div class="grid min-w-0 gap-x-2 gap-y-1.5 rounded-lg border border-zinc-200 bg-white/75 px-3 py-2 odd:bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-900/70 lg:grid-cols-[minmax(12rem,2fr)_repeat(4,minmax(0,1fr))_2.5rem]" data-product-line>
                                <div><x-product-line-lookup name="lines[__INDEX__][product_id]" :label="$t('المنتج', 'Product')" :required="true" :transfer="true" source-store-selector="#transfer-source" /></div>
                                <div><span class="block text-xs font-semibold">{{ $t('الكمية المتاحة', 'Available quantity') }}</span><output data-line-available dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">—</output></div>
                                <div><span class="block text-xs font-semibold">{{ $t('سعر التكلفة', 'Cost price') }}</span><output data-line-cost dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">—</output></div>
                                <div><span class="block text-xs font-semibold">{{ $t('سعر البيع', 'Selling price') }}</span><output data-line-selling-price dir="ltr" class="mt-1 flex h-9 items-center rounded-md bg-zinc-50 px-2 font-mono">—</output></div>
                                <div><span class="text-xs font-semibold">{{ $t('إجمالي الكمية الموزعة', 'Allocated total') }}</span><output data-line-transfer-total dir="ltr" class="mt-1 flex h-9 items-center justify-center rounded-md bg-zinc-50 px-2 font-mono">0</output></div>
                                <div data-transfer-allocations>
                                    @foreach($visibleStores->where('status', 'active') as $destination)
                                        <label class="text-xs font-semibold" data-destination-id="{{ $destination->id }}" hidden>
                                            <span>{{ \App\Support\HumanName::name($destination) }}</span>
                                            <input dir="ltr" name="lines[__INDEX__][allocations][{{ $destination->id }}]" value="" class="{{ $field }}" inputmode="decimal" min="0" step="any" placeholder="0" data-allocation-input>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-rose-700" data-line-allocation-error role="status" hidden>{{ $t('الكمية الموزعة تتجاوز الكمية المتاحة في المصدر.', 'Allocated quantity exceeds available source stock.') }}</p>
                                <button type="button" data-remove-line class="inline-flex size-10 items-center justify-center self-end rounded-lg text-rose-700 hover:bg-rose-50" title="{{ $t('حذف البند', 'Delete item') }}" aria-label="{{ $t('حذف البند', 'Delete item') }}"><svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 11v5m4-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                            </div>
                        </template>
                        <button type="button" data-add-line class="text-sm font-semibold text-sky-700 underline">{{ $t('إضافة بند', 'Add item') }}</button>
                    </div>
                    <div><label for="transfer-reason" class="text-xs font-semibold">{{ $t('السبب', 'Reason') }}</label><input id="transfer-reason" name="reason_code" value="{{ old('reason_code', $transfer?->reason_code) }}" class="{{ $field }}" required></div>
                    <div><label for="transfer-notes" class="text-xs font-semibold">{{ $t('ملاحظات', 'Notes') }}</label><textarea id="transfer-notes" name="reason_notes" class="{{ $field }}" rows="2">{{ old('reason_notes', $transfer?->notes) }}</textarea></div>
                    <button type="submit" x-on:click="attempted = true" class="{{ $button }} min-h-11 px-5 text-sm shadow-sm bg-sky-700 text-white">{{ $t('حفظ المسودة', 'Save draft') }}</button>
                </form>
            </section>
        @endif

        @if($focus === null || $focus === 'transfers')
        <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="transfers-heading" data-guide="inventory-transfers"><div class="border-b border-zinc-200 px-5 py-4"><h2 id="transfers-heading" class="text-lg font-bold">{{ $t('التحويلات وفروق الاستلام', 'Transfers and receipt differences') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('تابع مسار التحويل من المسودة حتى الاستلام.', 'Follow each transfer from draft through receipt.') }}</p></div><div class="space-y-4 p-5">
            @forelse($transfers as $item)
                @php
                    $sourceVisible = in_array((int) $item->source_store_id, $visibleStoreIds, true);
                    $visibleAllocation = $item->lines
                        ->flatMap(fn ($line) => $line->allocations->map(fn ($allocation) => ['line' => $line, 'allocation' => $allocation]))
                        ->first(fn ($row) => in_array((int) $row['allocation']->destination_store_id, $visibleStoreIds, true));
                    $previewLine = ! $sourceVisible && $visibleAllocation ? $visibleAllocation['line'] : $item->lines->first();
                    $previewDestination = ! $sourceVisible && $visibleAllocation ? $visibleAllocation['allocation']->destination : $item->destinationStore;
                    $previewQuantity = ! $sourceVisible && $visibleAllocation ? $visibleAllocation['allocation']->quantity : $previewLine?->quantity_requested;
                    $canReceiveHere = $item->lines->contains(fn ($line) => $line->allocations->isNotEmpty())
                        ? $visibleAllocation !== null
                        : in_array((int) $item->destination_store_id, $visibleStoreIds, true);
                @endphp
                <div class="rounded-2xl border border-zinc-200 p-4" data-transfer-row><div class="flex flex-col gap-3 lg:flex-row lg:justify-between"><div><strong>{{ $item->transfer_number }}</strong><span class="ms-2 rounded-full bg-amber-100 px-2 py-1 text-xs">{{ $status($item->status) }}</span><p class="mt-2 text-sm text-zinc-600">{{ $item->sourceStore?->code }} → {{ $previewDestination?->code }} · {{ $previewLine?->product?->item_code }} · {{ $qty($previewQuantity) }}</p></div><div class="flex flex-wrap gap-2">@if($item->status === 'draft') @can('transfers.edit')<x-actions.button semantic="edit" :label="__('Edit')" :href="route('inventory.transfers.edit', $item)">{{ $t('تعديل', 'Edit') }}</x-actions.button>@endcan @can('transfers.submit')<form method="POST" action="{{ route('inventory.transfers.submit', $item) }}">@csrf<x-actions.button type="submit" semantic="confirm" :label="__('Submit')">{{ $t('إرسال', 'Submit') }}</x-actions.button></form>@endcan @elseif($item->status === 'submitted') @can('transfers.approve')<x-actions.button semantic="approve" :label="__('Review and approve')" :href="route('inventory.transfers.approval-review', $item)">{{ $t('مراجعة واعتماد', 'Review and approve') }}</x-actions.button>@endcan @elseif($item->status === 'approved')<span class="text-xs text-amber-800">{{ $t('تحويل قديم معتمد ينتظر معالجة منفصلة', 'Legacy approved transfer awaiting separate resolution') }}</span> @elseif($item->status === 'in_transit') @can('transfers.receive') @if($canReceiveHere)<x-actions.button semantic="receive" :label="__('Receive')" :href="route('inventory.transfers.receiving-inbox')">{{ $t('استلام', 'Receive') }}</x-actions.button>@endif @endcan @elseif($item->status === 'difference_review') @can('transfers.difference')<x-actions.button semantic="view" :label="__('Review difference')" :href="route('inventory.transfers.differences', $item)">{{ $t('مراجعة الفرق', 'Review difference') }}</x-actions.button>@endcan @endif</div></div>

                @if($item->status === 'received' && $item->difference_status === 'recorded')
                    <details class="mt-2 rounded-lg border border-amber-200 bg-amber-50/50 p-2 text-xs" data-transfer-variance><summary class="cursor-pointer font-semibold">{{ $t('فروق الاستلام المسجلة', 'Recorded receipt variances') }}</summary>
                        @foreach($item->lines as $line)
                            @foreach($line->allocations as $allocation)
                                @php
                                    $variance = bcsub((string) $allocation->quantity, (string) $allocation->quantity_received, 6);
                                @endphp
                                @if(bccomp($variance, '0', 6) > 0)
                                    @php
                                        $receiptDraft = $item->receivingDrafts->firstWhere('destination_store_id', $allocation->destination_store_id);
                                    @endphp
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-amber-200 pt-2" data-transfer-variance-row>
                                        <strong>{{ \App\Support\HumanName::text($line->product) }}</strong><span>{{ \App\Support\HumanName::text($allocation->destination) }}</span>
                                        <span>{{ $t('مخصص', 'Allocated') }}: <x-product-quantity :value="$allocation->quantity" /></span><span>{{ $t('فعلي', 'Actual') }}: <x-product-quantity :value="$allocation->quantity_received" /></span><span>{{ $t('فرق', 'Variance') }}: <x-product-quantity :value="$variance" /></span>
                                        <span>{{ $receiptDraft?->difference_type }} · {{ $receiptDraft?->difference_reason }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </details>
                @endif
                @if($item->status === 'difference_review')<form class="mt-4 grid gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 sm:grid-cols-3" method="POST" action="{{ route('inventory.transfers.differences.resolve', $item) }}">@csrf<div><label for="resolve-difference-{{ $item->id }}" class="text-xs font-semibold">{{ $t('نوع الفرق', 'Difference type') }}</label><select id="resolve-difference-{{ $item->id }}" name="difference_type" class="{{ $field }}" required><option value="shortage">{{ $t('عجز', 'Shortage') }}</option><option value="damage">{{ $t('تالف', 'Damage') }}</option><option value="refusal">{{ $t('رفض', 'Refusal') }}</option></select></div><div><label for="resolve-reason-{{ $item->id }}" class="text-xs font-semibold">{{ $t('سبب الإغلاق', 'Resolution reason') }}</label><input id="resolve-reason-{{ $item->id }}" name="difference_reason" class="{{ $field }}" required></div><div class="flex items-end"><x-actions.button type="submit" semantic="confirm" :label="__('Resolve difference')" class="w-full">{{ $t('إغلاق الفرق', 'Resolve difference') }}</x-actions.button></div></form>@endif
            </div>@empty<div class="py-4 text-sm text-zinc-500">{{ $t('لا توجد تحويلات في النطاق.', 'No transfers in this scope.') }}</div>@endforelse
        </div></section>
        @endif

        @if($focus === 'adjustment-create' || $isDedicatedInventoryDocument || $adjustment?->status === 'draft')
            <section class="rounded-3xl border border-violet-200 bg-violet-50/50 p-5" aria-labelledby="adjustment-editor-heading" data-inventory-intent="{{ $isDedicatedInventoryDocument ? $focus : 'adjustment' }}"><h2 id="adjustment-editor-heading" class="text-xl font-bold">{{ $adjustment ? $t('تعديل مستند مسودة', 'Edit draft movement') : ($focus === 'addition' ? $t('إذن إضافة جديد', 'New addition') : ($focus === 'deduction' ? $t('إذن خصم جديد', 'New deduction') : $t('إدخال مخزون افتتاحي', 'Opening inventory entry'))) }}</h2>@if($adjustment)<p class="mt-1 font-mono text-sm font-bold text-violet-900" dir="ltr" data-inventory-document-number>{{ $adjustment->adjustment_number }}</p>@endif @if (! $adjustment && ! $isDedicatedInventoryDocument)<p class="mt-1 text-sm text-zinc-600">{{ $t('اختر نوع إدخال ورمز سبب افتتاحي معتمد فقط. لا تعدل الأرصدة مباشرة ولا ترحل الحركة من هذه الصفحة.', 'Choose an entry type and an approved opening reason only. Do not edit balances directly or post the movement from this page.') }}</p>@endif
                <form class="mt-4 space-y-4" method="POST" action="{{ $adjustment ? route('inventory.adjustments.update', $adjustment) : ($isDedicatedInventoryDocument ? route('inventory.documents.store', ['documentType' => $focus === 'addition' ? 'entry' : 'exit']) : route('inventory.adjustments.store')) }}" data-product-line-editor data-autofocus="true" data-next-index="{{ max(1, $adjustment?->lines?->count() ?? 1) }}">@csrf
                    <div class="grid gap-3 sm:grid-cols-3"><div><label for="adjustment-store" class="text-xs font-semibold">{{ $t('المتجر', 'Store') }}</label><select id="adjustment-store" name="store_id" class="{{ $field }}" required>@foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected($adjustment?->store_id === $store->id)>{{ \App\Support\HumanName::name($store) }} — {{ $store->code }}</option>@endforeach</select></div>@if($isDedicatedInventoryDocument)<input type="hidden" name="adjustment_type" value="{{ $focus === 'addition' ? 'entry' : 'exit' }}">@else<div><label for="adjustment-type" class="text-xs font-semibold">{{ $t('نوع الحركة', 'Movement type') }}</label><select id="adjustment-type" name="adjustment_type" class="{{ $field }}" required>@foreach(['entry','exit','exchange','adjustment'] as $type)<option value="{{ $type }}" @selected(old('adjustment_type', $adjustment?->adjustment_type ?? request('type')) === $type)>{{ $type === 'entry' ? $t('إذن إضافة', 'Stock addition') : ($type === 'exit' ? $t('إذن خصم', 'Stock deduction') : __($type)) }}</option>@endforeach</select></div>@endif<div><label for="adjustment-reason" class="text-xs font-semibold">{{ $t('السبب', 'Reason') }}</label><input id="adjustment-reason" name="reason_code" value="{{ $adjustment?->reason_code }}" class="{{ $field }}" required></div></div>
                    <div class="space-y-3">@foreach(($adjustment?->lines?->all() ?: [null]) as $lineIndex => $adjustmentLine)<div class="grid gap-3 rounded-xl border bg-white/70 p-3 sm:grid-cols-12" data-product-line><div class="sm:col-span-4"><x-product-line-lookup :name="'lines['.$lineIndex.'][product_id]'" :value="old('lines.'.$lineIndex.'.product_id', $adjustmentLine?->product_id)" :display="$adjustmentLine?->product ? ((str_starts_with(app()->getLocale(), 'ar') ? $adjustmentLine->product->name_ar : $adjustmentLine->product->name_en).' · '.$adjustmentLine->product->item_code) : ''" :label="$t('المنتج', 'Product')" :required="true" /></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ $t('الكمية', 'Quantity') }}</label><input data-line-quantity name="lines[{{ $lineIndex }}][quantity_delta]" value="{{ filled($adjustmentLine?->quantity_delta) ? $qty($adjustmentLine?->quantity_delta) : '1' }}" class="{{ $field }}" inputmode="decimal" step="any" required></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ $t('تكلفة الوحدة', 'Unit cost') }}</label><input data-line-price data-price-kind="cost" name="lines[{{ $lineIndex }}][unit_cost]" value="{{ $adjustmentLine?->unit_cost }}" class="{{ $field }}" inputmode="decimal" min="0" step="0.0001"></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ $t('إجمالي البند', 'Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-zinc-50 px-3 font-mono" data-line-total>0.00</output></div>@if($lineIndex > 0)<button type="button" data-remove-line class="text-sm text-rose-700 underline">{{ $t('حذف', 'Remove') }}</button>@endif</div>@endforeach
                        <template data-line-template><div class="grid gap-3 rounded-xl border bg-white/70 p-3 sm:grid-cols-12" data-product-line><div class="sm:col-span-4"><x-product-line-lookup name="lines[__INDEX__][product_id]" :label="$t('المنتج', 'Product')" :required="true" /></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ $t('الكمية', 'Quantity') }}</label><input data-line-quantity name="lines[__INDEX__][quantity_delta]" value="1" class="{{ $field }}" inputmode="decimal" step="any" required></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ $t('تكلفة الوحدة', 'Unit cost') }}</label><input data-line-price data-price-kind="cost" name="lines[__INDEX__][unit_cost]" class="{{ $field }}" inputmode="decimal" min="0" step="0.0001"></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ $t('إجمالي البند', 'Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-zinc-50 px-3 font-mono" data-line-total>0.00</output></div><button type="button" data-remove-line class="text-sm text-rose-700 underline">{{ $t('حذف', 'Remove') }}</button></div></template>
                        <button type="button" data-add-line class="text-sm font-semibold text-violet-700 underline">{{ $t('إضافة بند', 'Add item') }}</button></div>
                    <label class="flex gap-2 text-sm"><input type="checkbox" name="allow_negative" value="1" @checked($adjustment?->allow_negative)> <span>{{ $t('السماح بالرصيد السالب مع الصلاحية والسبب', 'Allow negative stock with permission and reason') }}</span></label><textarea name="reason_notes" class="{{ $field }}" rows="2" placeholder="{{ $t('سبب التجاوز عند الحاجة', 'Override reason when required') }}">{{ $adjustment?->reason_notes }}</textarea><button type="submit" class="{{ $button }} bg-violet-700 text-white">{{ $t('حفظ المسودة', 'Save draft') }}</button></form></section>
        @endif

        @if($focus === null || str_starts_with((string) $focus, 'count') || str_starts_with((string) $focus, 'adjustment'))
        <div class="grid gap-6 {{ $isDedicatedInventoryDocument ? 'grid-cols-1' : 'xl:grid-cols-2' }}">
            <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="adjustments-heading" data-guide="inventory-adjustments"><div class="border-b border-zinc-200 px-5 py-4"><h2 id="adjustments-heading" class="text-xl font-bold">{{ $isDedicatedInventoryDocument ? ($focus === 'addition' ? $t('مستندات الإضافة', 'Addition documents') : $t('مستندات الخصم', 'Deduction documents')) : $t('الإدخال والإخراج والتسويات', 'Entries, exits, and adjustments') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('الرصيد السالب محظور افتراضيا، والتجاوز يتطلب صلاحية وسببا وتدقيقا.', 'Negative stock is blocked by default; overrides require permission, reason, and audit.') }}</p></div><div class="space-y-3 p-5">@forelse($adjustments as $item)<div class="rounded-2xl border border-zinc-200 p-4"><div class="flex items-center justify-between gap-3"><div><strong>{{ $item->adjustment_number }}</strong><div class="mt-1 text-xs text-zinc-500">{{ \App\Support\HumanName::name($item->store) }} — {{ $item->store?->code }} · {{ $item->adjustment_type }} · {{ $item->reason_code }}</div></div><span class="rounded-full bg-zinc-100 px-2 py-1 text-xs">{{ $status($item->status) }}</span></div><div class="mt-3 flex flex-wrap gap-2">@if($item->status === 'draft') @can('inventory_stock_card.edit')<x-actions.button semantic="edit" :label="__('Edit')" :href="route('inventory.adjustments.edit', $item)">{{ $t('تعديل', 'Edit') }}</x-actions.button>@endcan @can('inventory_stock_card.approve')@if(in_array($item->adjustment_type, ['entry', 'exit'], true))<form method="POST" action="{{ route('inventory.adjustments.approve', $item) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Approve')">{{ $t('اعتماد', 'Approve') }}</x-actions.button></form>@endif@endcan @if(!in_array($item->adjustment_type, ['entry', 'exit'], true))@can('inventory_stock_card.submit')<form method="POST" action="{{ route('inventory.adjustments.submit', $item) }}">@csrf<x-actions.button type="submit" semantic="confirm" :label="__('Submit')">{{ $t('إرسال', 'Submit') }}</x-actions.button></form>@endcan @endif @elseif($item->status === 'submitted') @can('inventory_stock_card.approve')@if((int) $item->created_by === (int) auth()->id() && ! auth()->user()?->canBypassApproval())<span class="text-sm text-amber-800">{{ $t('لا يمكن لمنشئ المستند اعتماده. اطلب مراجعاً آخر.', 'The creator cannot approve this adjustment. Ask another reviewer.') }}</span>@else<form method="POST" action="{{ route('inventory.adjustments.approve', $item) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Approve and post')">{{ in_array($item->adjustment_type, ['entry', 'exit'], true) ? $t('اعتماد', 'Approve') : $t('اعتماد وترحيل', 'Approve and post') }}</x-actions.button></form>@endif @endcan @elseif($item->status === 'approved' && ! $item->reversal_of_id) @can('inventory_stock_card.reverse')<form method="POST" action="{{ route('inventory.adjustments.reverse', $item) }}">@csrf<input type="hidden" name="reason" value="{{ $t('تصحيح مستند معتمد', 'Corrective reversal') }}"><x-actions.button type="submit" semantic="cancel" :label="__('Reverse')">{{ $t('عكس', 'Reverse') }}</x-actions.button></form>@endcan @endif</div></div>@empty<p class="text-sm text-zinc-500">{{ $t('لا توجد مستندات حركة.', 'No inventory documents.') }}</p>@endforelse</div></section>

            @unless($isDedicatedInventoryDocument)
            <section class="min-w-0 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="counts-heading" data-guide="inventory-counts"><div class="border-b border-zinc-200 px-5 py-4"><h2 id="counts-heading" class="text-lg font-bold">{{ $t('الجرد والمطابقة', 'Stock counts and reconciliation') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('الحركات بعد المرجع تدخل في المطابقة، والعناصر غير المعدودة لا تصفر تلقائيا.', 'Movements after the reference are included; uncounted items are never auto-zeroed.') }}</p></div><div class="space-y-3 p-5">
                @if($focus === 'count-create')
                    @include('inventory.stocktake-create')
                @endif
                @if($focus === 'counts')
                    <form method="GET" action="{{ route('inventory.counts') }}" class="grid gap-2 rounded-xl border border-zinc-200 bg-zinc-50 p-3 sm:grid-cols-2 lg:grid-cols-5">
                        <input name="count_number" value="{{ request('count_number') }}" class="{{ $field }}" placeholder="{{ $t('رقم الجرد', 'Count number') }}">
                        <select name="count_store_id" class="{{ $field }}"><option value="">{{ $t('كل المخازن', 'All warehouses') }}</option>@foreach($visibleStores as $store)<option value="{{ $store->id }}" @selected((int) request('count_store_id') === $store->id)>{{ \App\Support\HumanName::name($store) }}</option>@endforeach</select>
                        <select name="count_status" class="{{ $field }}"><option value="">{{ $t('كل الحالات', 'All statuses') }}</option>@foreach(['draft','in_progress','submitted','reconciled','cancelled'] as $countStatus)<option value="{{ $countStatus }}" @selected(request('count_status') === $countStatus)>{{ $status($countStatus) }}</option>@endforeach</select>
                        <input type="date" name="count_from" value="{{ request('count_from') }}" class="{{ $field }}" aria-label="{{ $t('من تاريخ', 'From date') }}">
                        <input type="date" name="count_to" value="{{ request('count_to') }}" class="{{ $field }}" aria-label="{{ $t('إلى تاريخ', 'To date') }}">
                        <button class="{{ $button }} bg-sky-700 text-white lg:col-span-5">{{ $t('تصفية الجرد', 'Filter counts') }}</button>
                    </form>
                @endif
                @forelse($counts as $item)<div class="rounded-2xl border border-zinc-200 p-4"><div class="flex items-center justify-between gap-3"><div><strong>{{ $item->count_number }}</strong><div class="mt-1 text-xs text-zinc-500">{{ $item->store?->code }} · {{ $item->count_type }} · {{ $item->counted_lines_count }}/{{ $item->total_lines_count }} {{ $t('معدود', 'counted') }} · {{ max(0, $item->total_lines_count - $item->counted_lines_count) }} {{ $t('متبقٍ', 'remaining') }} · {{ $item->creator?->name }} · {{ $item->matched_lines_count }} {{ $t('مطابق', 'matched') }} · {{ $item->different_lines_count }} {{ $t('بفارق', 'with variance') }}</div></div><span class="rounded-full bg-zinc-100 px-2 py-1 text-xs">{{ $status($item->status) }}</span></div><div class="mt-3 flex flex-wrap gap-2">@if(in_array($item->status, ['in_progress', 'submitted'], true)) @can('stock_counts.view')<x-actions.button semantic="view" :label="$t('عرض فروقات الجرد', 'View stocktake differences')" :href="route('inventory.counts.reconcile-page', [$item, 'count_filter' => 'differences'])">{{ $t('عرض فروقات الجرد', 'View stocktake differences') }}</x-actions.button>@endcan @endif @if(in_array($item->status, ['draft','in_progress'], true)) @can('stock_counts.edit')<x-actions.button semantic="edit" :label="__('Enter count')" :href="route('inventory.counts.entry', $item)">{{ $t('إدخال العد', 'Enter count') }}</x-actions.button>@endcan @if($item->status === 'in_progress') @can('stock_counts.submit')<form method="POST" action="{{ route('inventory.counts.submit', $item) }}">@csrf<x-actions.button type="submit" semantic="confirm" :label="__('Submit')">{{ $t('إرسال', 'Submit') }}</x-actions.button></form>@endcan @endif @elseif($item->status === 'submitted') @can('stock_counts.reconcile')<x-actions.button semantic="view" :label="__('Review')" :href="route('inventory.counts.reconcile-page', $item)">{{ $t('مراجعة', 'Review') }}</x-actions.button><form method="POST" action="{{ route('inventory.counts.reconcile', $item) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Reconcile and approve')">{{ $t('مطابقة واعتماد', 'Reconcile and approve') }}</x-actions.button></form>@endcan @elseif($item->status === 'reconciled')<a class="{{ $button }} border border-zinc-300" href="{{ route('inventory.counts.reconcile-page', $item) }}">{{ $t('عرض الجرد المكتمل', 'View settled count') }}</a>@endif @if(in_array($item->status, ['draft','in_progress','submitted','recount_required'], true)) @can('stock_counts.cancel')<form method="POST" action="{{ route('inventory.counts.cancel', $item) }}" onsubmit="return confirm(@js($t('إلغاء جلسة الجرد؟', 'Cancel this stocktake?')))">@csrf<input name="reason" required maxlength="1000" class="{{ $field }} max-w-44" placeholder="{{ $t('سبب الإلغاء', 'Cancellation reason') }}"><button class="{{ $button }} text-rose-700">{{ $t('إلغاء', 'Cancel') }}</button></form>@endcan @endif</div></div>@empty@if($focus !== 'count-create')<div class="py-4 text-sm text-zinc-500">{{ $t('لا توجد خطط جرد.', 'No stock counts.') }}</div>@endif @endforelse
                @if($focus === 'counts')<div class="mt-4">{{ $counts->links() }}</div>@endif
                @if($count && $focus === 'count-entry')
                    <form method="GET" action="{{ url()->current() }}" class="grid gap-2 rounded-xl border border-zinc-200 bg-white p-3 sm:grid-cols-[1fr_auto_auto]">
                        <input name="count_search" value="{{ request('count_search') }}" class="{{ $field }}" placeholder="{{ $t('بحث بالمنتج أو الرمز أو الباركود', 'Search product, SKU or barcode') }}">
                        <select name="count_filter" class="{{ $field }}">
                            @foreach(['all' => $t('الكل', 'All'), 'differences' => $t('كل الفروقات', 'All differences'), 'matched' => $t('متطابق', 'Matched'), 'shortage' => $t('عجز', 'Shortage'), 'excess' => $t('زيادة', 'Excess'), 'not_counted' => $t('غير معدود', 'Not counted'), 'movements' => $t('له حركة', 'With movements')] as $filterValue => $filterLabel)
                                <option value="{{ $filterValue }}" @selected(request('count_filter', 'all') === $filterValue)>{{ $filterLabel }}</option>
                            @endforeach
                        </select>
                        <button class="{{ $button }} bg-sky-700 text-white">{{ $t('تصفية', 'Filter') }}</button>
                    </form>
                @endif
                @if($count && $focus === 'count-entry')<div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4"><h3 class="font-bold">{{ $count->count_number }} · {{ $t('عد بالباركود', 'Barcode-first counting') }}</h3><p class="text-sm">{{ \App\Support\HumanName::name($count->store) }} <small dir="ltr">{{ $count->store?->code }}</small></p>@if($count->status==='in_progress')<div class="mt-3 rounded-xl bg-white p-3 text-sm"><strong>{{ $countProgress['counted'] }} / {{ $countProgress['total'] }}</strong> {{ $t('منتج معدود', 'products counted') }} · {{ max(0, $countProgress['total'] - $countProgress['counted']) }} {{ $t('متبقٍ', 'remaining') }} · {{ $countProgress['total'] ? round(100 * $countProgress['counted'] / $countProgress['total']) : 0 }}% · {{ $countProgress['matched'] }} {{ $t('مطابق', 'matched') }} · {{ $countProgress['different'] }} {{ $t('بفارق', 'with variance') }}</div>@can('stock_counts.edit')<form method="POST" action="{{ route('inventory.counts.add-all',$count) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf<label class="text-xs font-semibold">{{ $t('إضافة كل منتجات المخزن', 'Add all warehouse products') }}<select name="store_id" class="{{ $field }}">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }}</option>@endforeach</select></label><button class="{{ $button }} border border-emerald-700 text-emerald-800">{{ $t('إضافة الكل', 'Add all') }}</button></form>@endcan @endif@if($count->status==='draft')@can('stock_counts.open')<form class="mt-3" method="POST" action="{{ route('inventory.counts.open',$count) }}">@csrf<button class="{{ $button }} bg-emerald-700 text-white">{{ $t('فتح الجلسة وتثبيت اللقطات', 'Open session and freeze snapshots') }}</button></form>@endcan @elseif($count->status==='in_progress')
                    <div data-stocktake-scan data-count-id="{{ $count->id }}" data-user-id="{{ auth()->id() }}" data-endpoint="{{ route('inventory.counts.contribute', $count) }}" data-quantity-endpoint="{{ route('inventory.counts.quantity', $count) }}" class="mt-4 space-y-3 rounded-2xl border border-emerald-300 bg-white p-4" aria-label="{{ $t('وضع المسح السريع', 'Quick scan mode') }}">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-bold">{{ $t('وضع المسح السريع', 'Quick scan mode') }}</h4><span data-scan-save-state class="text-xs text-zinc-600" role="status">{{ $t('جاهز للمسح', 'Ready to scan') }}</span></div>
                        <select data-scan-store class="{{ $field }}" aria-label="{{ $t('موقع العد', 'Count location') }}">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }} — {{ $location->store?->code }}</option>@endforeach</select>
                        <div class="flex min-w-0 flex-wrap gap-2"><input data-scan-input type="text" inputmode="none" autocomplete="off" class="{{ $field }} min-w-0 flex-1" placeholder="{{ $t('امسح الباركود أو اكتب الرمز ثم Enter', 'Scan barcode or type code then Enter') }}"><button type="button" data-scan-submit class="{{ $button }} min-h-11 bg-emerald-700 text-white">{{ $t('إضافة مسح', 'Add scan') }}</button><button type="button" data-scan-camera class="{{ $button }} min-h-11 border border-emerald-700 text-emerald-800">{{ $t('فتح الكاميرا', 'Open camera') }}</button></div>
                        <div data-scan-camera-panel hidden class="space-y-2"><video data-scan-video autoplay muted playsinline class="aspect-video max-h-72 w-full rounded-xl bg-zinc-950 object-contain"></video><button type="button" data-scan-stop class="{{ $button }} border border-zinc-400">{{ $t('إيقاف الكاميرا', 'Stop camera') }}</button></div>
                        <p data-scan-feedback role="status" aria-live="polite" class="text-sm text-zinc-700"></p>
                        <div data-scan-last hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm"><strong data-scan-last-name></strong><span data-scan-last-sku dir="ltr" class="mx-2 font-mono text-xs"></span><span data-scan-last-quantity dir="ltr" class="font-bold"></span></div><div class="grid gap-2 sm:grid-cols-[8rem_1fr_auto]"><input data-scan-override type="number" min="0" step="any" inputmode="decimal" class="{{ $field }}" placeholder="{{ $t('الكمية الفعلية', 'Actual quantity') }}" aria-label="{{ $t('تعديل كمية آخر منتج', 'Override last product quantity') }}"><input data-scan-note class="{{ $field }}" placeholder="{{ $t('ملاحظة اختيارية', 'Optional note') }}"><button type="button" data-scan-override-save class="{{ $button }} min-h-11 border border-emerald-700">{{ $t('حفظ الكمية', 'Save quantity') }}</button></div>
                    </div>
                    <form class="mt-3 grid gap-3 sm:grid-cols-2" method="POST" action="{{ route('inventory.counts.contribute',$count) }}" data-product-line-editor data-autofocus="true">@csrf<input type="hidden" name="request_id" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="device_id" value="browser-{{ auth()->id() }}"><div><label class="text-xs font-semibold">{{ $t('الموقع الدقيق', 'Exact location') }}</label><select name="store_id" class="{{ $field }}">@foreach($count->locations as $location)<option value="{{ $location->store_id }}">{{ \App\Support\HumanName::name($location->store) }} — {{ $location->store?->code }}</option>@endforeach</select></div><div class="grid gap-3 sm:col-span-2 sm:grid-cols-4" data-product-line><x-product-line-lookup name="lookup" value-field="item_code" :label="$t('المنتج', 'Product')" :required="true" /><div><label class="text-xs font-semibold">{{ $t('الكمية المضافة', 'Quantity') }}</label><input data-line-quantity name="quantity" value="{{ old('quantity', '1') }}" min="0" step="any" inputmode="decimal" class="{{ $field }}" required></div><div><span class="block text-xs font-semibold">{{ $t('تكلفة الوحدة', 'Unit cost') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-white px-3">—</span></div><div><span class="block text-xs font-semibold">{{ $t('إجمالي البند', 'Line total') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-white px-3">—</span></div></div><div><label class="text-xs font-semibold">{{ $t('طريقة الإدخال', 'Input method') }}</label><select name="input_method" class="{{ $field }}"><option value="scan">{{ $t('مسح وحدة واحدة', 'Single scan') }}</option><option value="batch">{{ $t('إضافة كمية دفعة', 'Add batch quantity') }}</option><option value="manual">{{ $t('إدخال يدوي', 'Manual entry') }}</option></select></div><button type="submit" data-add-line class="{{ $button }} bg-emerald-700 text-white">{{ $t('إضافة بند', 'Add item') }}</button></form><div class="mt-4 grid gap-2">@foreach($countLines as $line)@php $entryValues = $lineCalculations[$line->id] ?? null; @endphp<div class="rounded-xl bg-white p-3"><strong>{{ \App\Support\HumanName::name($line->product) }}</strong><small class="block" dir="ltr">{{ $line->product?->item_code }}</small><span class="ms-2 text-xs text-zinc-600">{{ $t('المتوقع وقت العد', 'Expected at count') }}: {{ $entryValues ? $qty(bcsub($entryValues['expected_quantity'], $entryValues['movement_after_completion'], 6)) : $qty($line->reference_on_hand) }} · {{ $t('العد', 'Count') }}: {{ $line->is_counted ? $qty($line->counted_quantity) : '—' }} · {{ $t('الفرق', 'Difference') }}: {{ $entryValues ? $qty($entryValues['variance_quantity']) : '—' }} · {{ ! $entryValues ? $t('غير معدود', 'Not counted') : (bccomp($entryValues['variance_quantity'], '0', 6) === 0 ? $t('مطابق', 'Matched') : (bccomp($entryValues['variance_quantity'], '0', 6) < 0 ? $t('عجز', 'Shortage') : $t('زيادة', 'Excess'))) }}</span>
                    @can('stock_counts.participate')<div data-stocktake-quantity data-endpoint="{{ route('inventory.counts.quantity', $count) }}" data-count-id="{{ $count->id }}" data-user-id="{{ auth()->id() }}" data-store-id="{{ $line->store_id }}" data-lookup="{{ $line->product?->item_code }}" class="mt-2 grid min-w-0 gap-2 sm:grid-cols-[8rem_1fr_auto]"><input data-count-quantity type="number" min="0" step="any" inputmode="decimal" value="{{ $line->is_counted ? $qty($line->counted_quantity) : '' }}" class="{{ $field }}" aria-label="{{ $t('الكمية الفعلية', 'Actual quantity') }}"><input data-count-note value="{{ $line->notes }}" class="{{ $field }}" placeholder="{{ $t('ملاحظة', 'Note') }}"><span data-count-save-state class="self-center text-xs text-zinc-600" role="status">{{ $t('محفوظ', 'Saved') }}</span></div>@endcan
                    @if(!$line->completed_at)<form method="POST" action="{{ route('inventory.counts.complete-line',$line) }}">@csrf<button class="text-sm text-primary">{{ $t('تأكيد اكتمال العد', 'Mark count complete') }}</button></form>@endif</div>@endforeach</div><div class="mt-4">{{ $countLines->links() }}</div>@endif</div>@endif
                @include('inventory.stocktake-review')
            </div></section>
            @endunless
        </div>
        @endif

        @if($focus !== 'transfer-create' && ! $isDedicatedInventoryDocument)
        <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="movements-heading" data-guide="inventory-movements"><div class="border-b border-zinc-200 px-5 py-4"><h2 id="movements-heading" class="text-xl font-bold">{{ $t('دفتر حركات المخزون', 'Inventory movement ledger') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('كل حركة مرتبطة بمصدرها وغير قابلة للتعديل بعد الترحيل.', 'Every movement is source-linked and immutable after posting.') }}</p></div><x-tables.table-shell :label="$t('حركات المخزون', 'Inventory movements')"><div class="overflow-x-auto"><table class="data-table responsive-resource-table w-full text-start text-sm"><thead><tr><th>{{ $t('التاريخ', 'Posted') }}</th><th>{{ $t('المنتج', 'Product') }}</th><th>{{ $t('المتجر', 'Store') }}</th><th>{{ $t('الحركة', 'Movement') }}</th><th>{{ $t('الكمية', 'Quantity') }}</th><th>{{ $t('المصدر', 'Source') }}</th></tr></thead><tbody>@forelse($movements as $movement)<tr data-movement-row><td data-label="{{ $t('التاريخ', 'Posted') }}" class="text-xs text-text-muted">{{ $movement->posted_at?->format('Y-m-d H:i') }}</td><td data-primary dir="ltr">{{ $movement->product?->item_code }}</td><td data-label="{{ $t('المتجر', 'Store') }}" dir="ltr">{{ $movement->store?->code }}</td><td data-label="{{ $t('الحركة', 'Movement') }}"><x-status.badge :status="$movement->movement_type" :label="$movementLabel($movement->movement_type)" /></td><td data-label="{{ $t('الكمية', 'Quantity') }}" dir="ltr" class="font-mono {{ (float) $movement->quantity < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $qty($movement->quantity) }}</td><td data-label="{{ $t('المصدر', 'Source') }}" class="text-xs text-text-muted">@if($movementReferences->url($movement))<a class="font-semibold text-primary underline" href="{{ $movementReferences->url($movement) }}">{{ $movementReferences->label($movement) }}</a>@else{{ $movementReferences->label($movement) }}@endif</td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-zinc-500">{{ $t('لا توجد حركات ضمن النطاق.', 'No movements in this scope.') }}</td></tr>@endforelse</tbody></table></div><div class="border-t border-zinc-200 px-5 py-3"><x-tables.pagination :paginator="$movements" :page-sizes="[50]" /></div></x-tables.table-shell></section>
        @endif
        @endunless
        @endif
        @if($focus === 'transfer-create' || $transfer?->status === 'draft')
        <style>
            @media (max-width: 767px) {
                .app-topbar { flex-wrap: wrap; }
                .app-topbar__actions { flex: 1 1 100%; width: 100%; flex-wrap: wrap; justify-content: flex-start; }
            }
            [data-transfer-allocation-editor] [data-product-line] > * { min-width: 0; }
            [data-transfer-allocations], [data-line-allocation-error] { grid-column: 1 / -1; min-width: 0; }
            [data-transfer-allocations] { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.6rem; }
            [data-transfer-allocations] label { display: inline-flex; flex: 0 1 auto; align-items: center; min-width: 0; max-width: 100%; gap: 0.3rem; }
            [data-transfer-allocations] label > span { min-width: 0; overflow-wrap: anywhere; }
            [data-transfer-allocation-editor] [dir="ltr"] { unicode-bidi: isolate; }
            [data-transfer-allocation-editor] [data-line-available],
            [data-transfer-allocation-editor] [data-line-cost],
            [data-transfer-allocation-editor] [data-line-selling-price] { justify-content: center; text-align: center; }
            [data-transfer-allocations] input { text-align: center; }
            [data-transfer-allocations] input { box-sizing: border-box; width: 4.25rem; min-width: 4.25rem; max-width: 4.25rem; margin-top: 0; padding-inline: 0.35rem; }
            [data-transfer-allocation-editor] [hidden] { display: none !important; }
            @media (min-width: 1024px) {
                [data-transfer-allocation-editor] [data-remove-line] { grid-column: 6; grid-row: 1; align-self: end; }
            }
        </style>
        <script>
            (() => {
                const editor = document.querySelector('[data-transfer-allocation-editor]');
                if (!editor) return;

                const numeric = (value) => {
                    if (value === null || value === undefined || String(value).trim() === '') return null;
                    const number = Number(value);
                    return Number.isFinite(number) ? number : null;
                };
                const source = editor.querySelector('[data-transfer-source]');
                const destinationChoices = [...editor.querySelectorAll('input[name="destination_store_ids[]"]')];
                const selectedDestinations = () => new Set(destinationChoices
                    .filter((choice) => choice.checked && choice.value !== source.value)
                    .map((choice) => choice.value));
                const syncDestinations = () => {
                    const selected = selectedDestinations();
                    destinationChoices.forEach((choice) => {
                        const isSource = choice.value === source.value;
                        if (isSource) choice.checked = false;
                        choice.closest('[data-destination-choice]').hidden = isSource;
                    });
                    editor.querySelector('input[name="destination_store_id"]').value = [...selected][0] ?? '';
                    editor.querySelectorAll('[data-transfer-allocations] [data-destination-id]').forEach((label) => {
                        const active = selected.has(label.dataset.destinationId);
                        const input = label.querySelector('[data-allocation-input]');
                        if (!active) input.value = '';
                        label.hidden = !active;
                        input.disabled = !active;
                    });
                    editor.querySelectorAll('[data-product-line]').forEach(update);
                };
                const update = (line) => {
                    const productId = numeric(line.querySelector('[data-product-id]')?.value);
                    const availableOutput = line.querySelector('[data-line-available]');
                    const sourceQuantity = numeric(availableOutput?.textContent);
                    if (availableOutput && sourceQuantity === null) availableOutput.textContent = '—';
                    const ready = Number.isInteger(productId) && productId > 0;
                    const inputs = [...line.querySelectorAll('[data-allocation-input]')].filter((input) => !input.closest('[data-destination-id]').hidden);
                    const quantities = inputs.map((input) => numeric(input.value));
                    const total = ready ? quantities.reduce((sum, quantity) => sum + (quantity ?? 0), 0) : 0;
                    const allocated = Number.isFinite(total) ? total : 0;
                    const totalOutput = line.querySelector('[data-line-transfer-total]');
                    if (totalOutput) totalOutput.textContent = String(allocated);
                    const overAllocated = ready && sourceQuantity !== null && allocated > sourceQuantity;

                    line.querySelector('[data-line-allocation-error]').hidden = !overAllocated;
                    inputs.forEach((input) => {
                        input.disabled = !ready;
                    });
                };
                const refresh = () => editor.querySelectorAll('[data-product-line]').forEach(update);
                editor.addEventListener('input', (event) => {
                    const line = event.target.closest('[data-product-line]');
                    if (line) update(line);
                });
                // ProductLookup writes stock outputs after dispatching product-selected.
                editor.addEventListener('product-selected', () => queueMicrotask(refresh));
                editor.addEventListener('transfer-lines-changed', () => queueMicrotask(syncDestinations));
                editor.addEventListener('change', (event) => {
                    if (event.target === source || event.target.matches('input[name=\"destination_store_ids[]\"]')) queueMicrotask(syncDestinations);
                    else queueMicrotask(refresh);
                });
                editor.addEventListener('click', (event) => {
                    if (event.target.closest('[data-add-line]')) requestAnimationFrame(syncDestinations);
                });
                syncDestinations();
            })();
        </script>
        @endif
    </div>
</x-layouts.app>
