<section class="min-w-0 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="stocktake-create-heading" data-stocktake-create-layout>
    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800 sm:px-5">
        <h2 id="stocktake-create-heading" class="text-base font-bold">{{ $t('بيانات الجرد الجديد', 'New stocktake') }}</h2>
    </div>
    <form id="stocktake-create-form" method="POST" action="{{ route('inventory.counts.store') }}" class="space-y-5 p-4 sm:p-5" data-stocktake-create-form>
        @csrf
        <section class="min-w-0 space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" aria-labelledby="stocktake-basic-heading" data-stocktake-primary-fields>
            <h3 id="stocktake-basic-heading" class="text-sm font-bold">{{ $t('١. المعلومات الأساسية للجرد', '1. Basic stocktake information') }}</h3>
            <div class="min-w-0 space-y-3" data-stocktake-reference-fields>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-600">{{ $t('رقم الجرد', 'Reference') }}</span>
                    <strong class="text-sm">{{ $t('يُخصص عند الإنشاء', 'Assigned when created') }}</strong>
                </div>
                <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800"><span class="block text-xs font-semibold text-zinc-600">{{ $t('التاريخ', 'Date') }}</span><strong class="mt-1 block font-mono text-sm" dir="ltr">{{ now()->format('Y-m-d') }}</strong></div>
                    <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800"><span class="block text-xs font-semibold text-zinc-600">{{ $t('الوقت', 'Time') }}</span><strong class="mt-1 block font-mono text-sm" dir="ltr">{{ now()->format('H:i') }}</strong></div>
                </div>
            </div>
        </section>

        <section class="min-w-0 space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" aria-labelledby="stocktake-location-heading">
            <h3 id="stocktake-location-heading" class="text-sm font-bold">{{ $t('٢. موقع الجرد', '2. Location') }}</h3>
            <p class="text-xs text-zinc-600">{{ $t('اختر مخزناً مستقلاً أو حدد فرعاً ثم اختر مخزنه أو منفذ البيع التابع له.', 'Choose an independent warehouse, or select a branch and its warehouse or selling outlet.') }}</p>
            <label class="block text-xs font-semibold text-zinc-600">{{ $t('الفرع', 'Branch') }}<select name="branch_id" class="{{ $field }}" data-stocktake-branch><option value="">{{ $t('مخزن مستقل بدون فرع', 'Independent warehouse without branch') }}</option>@foreach($visibleStores->pluck('branch')->filter()->unique('id') as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ \App\Support\HumanName::name($branch) }} — {{ $branch->code }}</option>@endforeach</select></label>
            <div class="min-w-0" data-stocktake-warehouse-field>
                <span class="mb-1 block text-sm font-bold">{{ $t('المخزن / المنفذ', 'Warehouse / store') }} <span class="text-rose-600">*</span></span>
                <details class="rounded-xl border border-zinc-200 dark:border-zinc-800" data-stocktake-location-picker>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-sm"><span data-stocktake-location-summary>{{ $t('اختر المخزن أو المنفذ', 'Choose warehouse or store') }}</span><span aria-hidden="true">⌄</span></summary>
                    <div class="grid max-h-44 gap-2 overflow-y-auto border-t border-zinc-100 p-2 dark:border-zinc-800 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    @foreach($visibleStores->whereIn('type', ['warehouse', 'selling']) as $store)
                        <label class="flex min-w-0 items-start gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800" data-stocktake-store-branch="{{ $store->branch_id ?? '' }}">
                            <input type="checkbox" name="location_ids[]" value="{{ $store->id }}" @checked(in_array($store->id, array_map('intval', (array) old('location_ids', [])), true)) class="mt-1">
                            <span class="min-w-0"><strong class="block truncate">{{ \App\Support\HumanName::name($store) }}</strong><small class="block text-zinc-500" dir="ltr">{{ $store->code }}</small></span>
                        </label>
                    @endforeach
                    </div>
                    <p class="px-3 pb-2 text-xs text-zinc-500">{{ $t('يمكن تحديد أكثر من موقع للجرد.', 'You can select more than one count location.') }}</p>
                </details>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 dark:border-zinc-800" aria-labelledby="stocktake-settings-heading" data-stocktake-settings>
            <h3 id="stocktake-settings-heading" class="border-b border-zinc-100 px-4 py-3 text-sm font-bold text-zinc-700 dark:border-zinc-800 dark:text-zinc-200">{{ $t('٣. نوع الجرد ونطاقه', '3. Stocktake type and scope') }}</h3>
            <div class="grid min-w-0 gap-4 p-4 sm:grid-cols-2">
                <label class="text-xs font-semibold text-zinc-600">{{ $t('نوع الجرد', 'Count type') }}<select name="count_type" class="{{ $field }}" required><option value="partial" @selected(old('count_type', 'partial') === 'partial')>{{ $t('جرد جزئي', 'Partial count') }}</option><option value="full" @selected(old('count_type') === 'full')>{{ $t('جرد كلي', 'Full count') }}</option><option value="random" @selected(old('count_type') === 'random')>{{ $t('جرد عشوائي', 'Random count') }}</option></select></label>
                <input type="hidden" name="scope_type" value="{{ old('scope_type', 'category') }}" data-stocktake-scope-fallback>
                <div data-stocktake-partial-scope class="space-y-3 sm:col-span-2">
                    <label class="block text-xs font-semibold text-zinc-600">{{ $t('تحديد نطاق الجرد', 'Select count scope') }}<select name="scope_type" class="{{ $field }}"><option value="category" @selected(old('scope_type', 'category') === 'category')>{{ $t('حسب التصنيف', 'By category') }}</option><option value="supplier" @selected(old('scope_type') === 'supplier')>{{ $t('حسب المورد', 'By supplier') }}</option></select></label>
                    <label data-stocktake-category class="block text-xs font-semibold text-zinc-600">{{ $t('التصنيف', 'Category') }}<select name="category_id" class="{{ $field }}"><option value="">{{ $t('اختر التصنيف', 'Select category') }}</option>@foreach($countCategories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ \App\Support\HumanName::name($category) }}</option>@endforeach</select></label>
                    <label data-stocktake-supplier class="block text-xs font-semibold text-zinc-600">{{ $t('المورد', 'Supplier') }}<select name="supplier_id" class="{{ $field }}"><option value="">{{ $t('اختر المورد', 'Select supplier') }}</option>@foreach($countSuppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ \App\Support\HumanName::name($supplier) }}</option>@endforeach</select></label>
                </div>
            </div>
        </section>

        <fieldset class="min-w-0 space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" data-stocktake-execution><legend class="px-1 text-sm font-bold">{{ $t('٤. طريقة العد', '4. Counting method') }}</legend><div class="flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="execution_method" value="manual" @checked(old('execution_method', 'manual') === 'manual')>{{ $t('يدوي', 'Manual') }}</label><label class="flex items-center gap-2"><input type="radio" name="execution_method" value="scan" @checked(old('execution_method') === 'scan')>{{ $t('مسح باركود', 'Scan / barcode') }}</label><label data-stocktake-excel class="flex items-center gap-2"><input type="radio" name="execution_method" value="excel" @checked(old('execution_method') === 'excel')>{{ $t('ملف Excel', 'Excel file') }}</label><label class="flex items-center gap-2"><input type="radio" name="execution_method" value="mobile" @checked(old('execution_method') === 'mobile')>{{ $t('موبايل', 'Mobile') }}</label></div></fieldset>
        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><h3 class="text-sm font-bold">{{ $t('٥. مدير الجلسة', '5. Session manager') }}</h3><label class="mt-3 block text-xs font-semibold text-zinc-600">{{ $t('مدير الجلسة والمراجع', 'Session manager / reviewer') }}<select name="manager_id" class="{{ $field }}" required>@foreach($countAssignees->filter(fn ($candidate) => $candidate->is_super_admin || $candidate->hasPermission('stock_counts.close')) as $assignee)<option value="{{ $assignee->id }}" @selected((string) old('manager_id') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label></section>
        <fieldset class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><legend class="px-1 text-sm font-bold">{{ $t('٦. فريق الجرد', '6. Counting team') }}</legend><div class="grid max-h-32 gap-1.5 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">@foreach($countAssignees as $assignee)<label class="flex min-w-0 items-center gap-2 text-xs" data-count-team-candidate data-eligible-stores="{{ implode(',', $countTeamEligibility[$assignee->id] ?? []) }}"><input type="checkbox" name="member_ids[]" value="{{ $assignee->id }}" @checked(in_array($assignee->id, array_map('intval', (array) old('member_ids', [])), true))><span class="truncate">{{ $assignee->name }}</span></label>@endforeach</div><p class="mt-2 text-xs text-zinc-500">{{ $t('تظهر فقط الأسماء المصرح لها بالعد في جميع المواقع المحددة.', 'Only members authorized to count in every selected location remain available.') }}</p></fieldset>

        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-200" data-stocktake-notes-field>{{ $t('٧. ملاحظات وتعليمات الجرد', '7. Stocktake notes and instructions') }}
            <textarea name="notes" rows="3" class="{{ $field }}" placeholder="{{ $t('تعليمات النطاق أو المناطق', 'Scope or zone instructions') }}">{{ old('notes') }}</textarea>
        </label>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"><p class="text-xs text-zinc-500">{{ $t('إنشاء الجلسة لا يغير رصيد المخزون.', 'Creating a session does not change stock balances.') }}</p><button type="submit" class="{{ $button }} min-h-10 bg-emerald-700 px-6 text-white">{{ $t('٨. إنشاء الجرد', '8. Create stocktake') }}</button></div>
    </form>
</section>
<script>
    document.querySelectorAll('[data-stocktake-create-form]').forEach((form) => {
        const branch = form.querySelector('[data-stocktake-branch]');
        const picker = form.querySelector('[data-stocktake-location-picker]');
        const update = () => {
            picker.querySelectorAll('[data-stocktake-store-branch]').forEach((choice) => {
                const visible = choice.dataset.stocktakeStoreBranch === branch.value;
                choice.hidden = !visible;
                choice.classList.toggle('hidden', !visible);
                if (!visible) choice.querySelector('input').checked = false;
            });
            picker.dispatchEvent(new Event('change'));
        };
        branch.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-stocktake-location-picker]').forEach((picker) => {
        const summary = picker.querySelector('[data-stocktake-location-summary]');
        const candidates = document.querySelectorAll('[data-count-team-candidate]');
        const update = () => {
            const selected = [...picker.querySelectorAll('input[name="location_ids[]"]:checked')];
            const names = selected.map((input) => input.closest('label').querySelector('strong').textContent.trim());
            summary.textContent = names.length ? names.join('، ') : @js($t('اختر المخزن أو المنفذ', 'Choose warehouse or store'));
            candidates.forEach((candidate) => {
                const eligible = new Set(candidate.dataset.eligibleStores.split(',').filter(Boolean));
                const available = selected.length > 0 && selected.every((input) => eligible.has(input.value));
                candidate.hidden = !available;
                const checkbox = candidate.querySelector('input');
                checkbox.disabled = !available;
                if (!available) checkbox.checked = false;
            });
        };
        picker.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-stocktake-settings]').forEach((settings) => {
        const type = settings.querySelector('[name="count_type"]');
        const scope = settings.querySelector('select[name="scope_type"]');
        const fallback = settings.querySelector('[data-stocktake-scope-fallback]');
        const category = settings.querySelector('[data-stocktake-category]');
        const supplier = settings.querySelector('[data-stocktake-supplier]');
        const partial = settings.querySelector('[data-stocktake-partial-scope]');
        const excel = settings.closest('form').querySelector('[data-stocktake-excel]');
        const update = () => {
            const isPartial = type.value === 'partial';
            partial.hidden = !isPartial;
            scope.disabled = !isPartial;
            fallback.value = type.value === 'full' ? 'full' : (type.value === 'random' ? 'random' : scope.value);
            category.hidden = !isPartial || scope.value !== 'category';
            supplier.hidden = !isPartial || scope.value !== 'supplier';
            category.querySelector('select').disabled = category.hidden;
            supplier.querySelector('select').disabled = supplier.hidden;
            excel.hidden = type.value === 'random';
            excel.querySelector('input').disabled = excel.hidden;
            if (excel.hidden && excel.querySelector('input').checked) settings.closest('form').querySelector('[value="manual"]').checked = true;
        };
        type.addEventListener('change', update);
        scope.addEventListener('change', update);
        update();
    });
</script>
