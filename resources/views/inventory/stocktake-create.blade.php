<section class="min-w-0 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-create-heading" data-stocktake-create-layout>
    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800 sm:px-5">
        <h2 id="stocktake-create-heading" class="text-base font-bold">{{ $t('بيانات الجرد الجديد', 'New stocktake') }}</h2>
    </div>
    <form id="stocktake-create-form" method="POST" action="{{ route('inventory.counts.store') }}" class="space-y-5 p-4 sm:p-5" data-stocktake-create-form>
        @csrf
        <div class="grid min-w-0 gap-4 lg:grid-cols-2 lg:items-start" data-stocktake-primary-fields>
            <div class="min-w-0 lg:order-1" data-stocktake-warehouse-field>
                <span class="mb-1 block text-sm font-bold">{{ $t('المخزن / المنفذ', 'Warehouse / store') }} <span class="text-rose-600">*</span></span>
                <details class="rounded-xl border border-zinc-200 dark:border-zinc-800" data-stocktake-location-picker>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-sm"><span data-stocktake-location-summary>{{ $t('اختر المخزن أو المنفذ', 'Choose warehouse or store') }}</span><span aria-hidden="true">⌄</span></summary>
                    <div class="grid max-h-44 gap-2 overflow-y-auto border-t border-zinc-100 p-2 dark:border-zinc-800 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    @foreach($visibleStores->whereIn('type', ['warehouse', 'selling']) as $store)
                        <label class="flex min-w-0 items-start gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800">
                            <input type="checkbox" name="location_ids[]" value="{{ $store->id }}" @checked(in_array($store->id, array_map('intval', (array) old('location_ids', [])), true)) class="mt-1">
                            <span class="min-w-0"><strong class="block truncate">{{ \App\Support\HumanName::name($store) }}</strong><small class="block text-zinc-500" dir="ltr">{{ $store->code }}</small></span>
                        </label>
                    @endforeach
                    </div>
                    <p class="px-3 pb-2 text-xs text-zinc-500">{{ $t('يمكن تحديد أكثر من موقع للجرد.', 'You can select more than one count location.') }}</p>
                </details>
            </div>
            <div class="min-w-0 space-y-3 lg:order-2" data-stocktake-reference-fields>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-600">{{ $t('رقم الجرد', 'Reference') }}</span>
                    <strong class="text-sm">{{ $t('يُخصص عند الإنشاء', 'Assigned when created') }}</strong>
                </div>
                <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800"><span class="block text-xs font-semibold text-zinc-600">{{ $t('التاريخ', 'Date') }}</span><strong class="mt-1 block font-mono text-sm" dir="ltr">{{ now()->format('Y-m-d') }}</strong></div>
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800"><span class="block text-xs font-semibold text-zinc-600">{{ $t('الوقت', 'Time') }}</span><strong class="mt-1 block font-mono text-sm" dir="ltr">{{ now()->format('H:i') }}</strong></div>
                </div>
            </div>
        </div>

        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-200" data-stocktake-notes-field>{{ $t('ملاحظات وتعليمات الجرد', 'Stocktake notes and instructions') }}
            <textarea name="notes" rows="3" class="{{ $field }}" placeholder="{{ $t('تعليمات النطاق أو المناطق', 'Scope or zone instructions') }}">{{ old('notes') }}</textarea>
        </label>

        <details class="rounded-xl border border-zinc-200 dark:border-zinc-800" data-stocktake-advanced @if($errors->any()) open @endif>
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ $t('خيارات متقدمة', 'Advanced options') }}</summary>
            <div class="grid min-w-0 gap-4 border-t border-zinc-100 p-4 dark:border-zinc-800 sm:grid-cols-2">
                <label class="text-xs font-semibold text-zinc-600">{{ $t('الفرع', 'Branch') }}<select name="branch_id" class="{{ $field }}"><option value="">{{ $t('مخزن مستقل بدون فرع', 'Independent warehouse without branch') }}</option>@foreach($visibleStores->pluck('branch')->filter()->unique('id') as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ \App\Support\HumanName::name($branch) }} — {{ $branch->code }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-zinc-600">{{ $t('نوع الجرد', 'Count type') }}<select name="count_type" class="{{ $field }}"><option value="partial" @selected(old('count_type', 'partial') === 'partial')>{{ $t('جرد جزئي', 'Partial') }}</option><option value="full" @selected(old('count_type') === 'full')>{{ $t('جرد كامل', 'Full') }}</option></select></label>
                <label class="text-xs font-semibold text-zinc-600">{{ $t('نطاق الجرد', 'Scope') }}<select name="scope_type" class="{{ $field }}"><option value="partial" @selected(old('scope_type', 'partial') === 'partial')>{{ $t('نطاق صريح عند المسح', 'Explicit scan scope') }}</option><option value="full" @selected(old('scope_type') === 'full')>{{ $t('كل المنتجات', 'All products') }}</option><option value="category" @selected(old('scope_type') === 'category')>{{ $t('فئة', 'Category') }}</option><option value="supplier" @selected(old('scope_type') === 'supplier')>{{ $t('مورد', 'Supplier') }}</option></select></label>
                <label class="text-xs font-semibold text-zinc-600">{{ $t('فئة اختيارية', 'Optional category') }}<select name="category_id" class="{{ $field }}"><option value="">{{ $t('كل الفئات', 'All categories') }}</option>@foreach($countCategories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ \App\Support\HumanName::name($category) }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-zinc-600">{{ $t('مورد اختياري', 'Optional supplier') }}<select name="supplier_id" class="{{ $field }}"><option value="">{{ $t('كل الموردين', 'All suppliers') }}</option>@foreach($countSuppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ \App\Support\HumanName::name($supplier) }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-zinc-600">{{ $t('مدير الجلسة والمراجع', 'Session manager / reviewer') }}<select name="manager_id" class="{{ $field }}" required>@foreach($countAssignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) old('manager_id') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label>
                <fieldset class="min-w-0 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 sm:col-span-2"><legend class="px-1 text-xs font-bold">{{ $t('فريق الجرد', 'Count team') }}</legend><div class="grid max-h-32 gap-1.5 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">@foreach($countAssignees as $assignee)<label class="flex min-w-0 items-center gap-2 text-xs"><input type="checkbox" name="member_ids[]" value="{{ $assignee->id }}" @checked(in_array($assignee->id, array_map('intval', (array) old('member_ids', [])), true))><span class="truncate">{{ $assignee->name }}</span></label>@endforeach</div></fieldset>
            </div>
        </details>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"><p class="text-xs text-zinc-500">{{ $t('إنشاء الجلسة لا يغير رصيد المخزون.', 'Creating a session does not change stock balances.') }}</p><button type="submit" class="{{ $button }} min-h-10 bg-emerald-700 px-6 text-white">{{ $t('إنشاء الجرد', 'Create stocktake') }}</button></div>
    </form>
</section>
<script>
    document.querySelectorAll('[data-stocktake-location-picker]').forEach((picker) => {
        const summary = picker.querySelector('[data-stocktake-location-summary]');
        const update = () => {
            const names = [...picker.querySelectorAll('input[name="location_ids[]"]:checked')].map((input) => input.closest('label').querySelector('strong').textContent.trim());
            summary.textContent = names.length ? names.join('، ') : @js($t('اختر المخزن أو المنفذ', 'Choose warehouse or store'));
        };
        picker.addEventListener('change', update);
        update();
    });
</script>
