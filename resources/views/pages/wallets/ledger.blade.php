@php
    $isArabic = app()->getLocale() === 'ar';
@endphp

<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="{{ $guidePrefix }}-header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">TSK-028 · DM 4.2 · Local/Dev</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($otherPermission && auth()->user()?->can($otherPermission))
                    <a href="{{ route($otherRoute) }}" class="inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-800 shadow-sm hover:border-cyan-300">{{ $otherLabel }}</a>
                @endif
                @can('company_settings.view')
                    <a href="{{ route('admin.settings.customer-loyalty') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300">{{ $isArabic ? 'إعدادات القيم المعلقة' : 'Pending value settings' }}</a>
                @endcan
            </div>
        </div>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm" data-guide="{{ $guidePrefix }}-boundary">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ $isArabic ? 'حدود المحفظة' : 'Wallet boundary' }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ $isArabic ? 'سجل منفصل — قراءة محلية فقط' : 'Separate ledger — Local/Dev read boundary only' }}</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-amber-900">{{ $isArabic ? 'لا يوجد تحويل بين المحافظ، ولا تسوية أو تعديل أو دفع أو رصيد تجريبي. القيم غير المحسومة تظهر PENDING من الإعدادات.' : 'There is no cross-wallet transfer, settlement, adjustment, payment, or seeded balance. Unresolved values remain PENDING from Settings.' }}</p>
                </div>
                <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-bold text-amber-800">{{ $isArabic ? 'اعتماد المالك مطلوب' : 'Owner approval required' }}</span>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3" data-guide="{{ $guidePrefix }}-summary">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $isArabic ? 'نوع السجل' : 'Ledger type' }}</p>
                <p class="mt-2 break-words font-mono text-sm font-semibold text-slate-900">{{ $ledgerTable }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $isArabic ? 'السجلات المرئية' : 'Visible entries' }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $entries->total() }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">{{ $isArabic ? 'الحالة' : 'State' }}</p>
                <p class="mt-2 text-lg font-semibold text-amber-900">{{ $entries->isEmpty() ? 'PENDING' : ($isArabic ? 'قراءة فقط' : 'Read only') }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-guide="{{ $guidePrefix }}-ledger">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">{{ $isArabic ? 'سجل الحركات' : 'Ledger entries' }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $isArabic ? 'السجل append-only؛ لا توجد أزرار إنشاء أو تعديل أو حذف في هذه الشريحة.' : 'The ledger is append-only; this slice exposes no create, edit, or delete controls.' }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $isArabic ? 'لا تحويل بين المحافظ' : 'No cross-wallet transfer' }}</span>
            </div>

            @if ($entries->isEmpty())
                <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center" data-guide="{{ $guidePrefix }}-empty">
                    <h3 class="text-lg font-semibold text-slate-900">{{ $isArabic ? 'لا توجد حركات محفظة بعد' : 'No wallet entries yet' }}</h3>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $isArabic ? 'لا يتم اختراع أرصدة أو حركات. ستظهر الحركات فقط بعد وجود مصدر موثق وسياسة وصلاحية معتمدة.' : 'No balances or entries are invented. Entries will appear only after a documented source, policy, and authorized workflow exist.' }}</p>
                </div>
            @else
                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-start">{{ $isArabic ? 'المرجع' : 'Reference' }}</th><th class="px-4 py-3 text-start">{{ $isArabic ? 'النوع' : 'Type' }}</th><th class="px-4 py-3 text-start">{{ $isArabic ? 'المبلغ' : 'Amount' }}</th><th class="px-4 py-3 text-start">{{ $isArabic ? 'التاريخ' : 'Created' }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-200 bg-white">@foreach ($entries as $entry)<tr><td class="px-4 py-3 font-mono">{{ $entry->public_id }}</td><td class="px-4 py-3">{{ $entry->entry_type }}</td><td class="px-4 py-3">{{ $entry->amount }} {{ $entry->currency_code }}</td><td class="px-4 py-3">{{ $entry->created_at?->toDateTimeString() }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $entries->links() }}</div>
            @endif
        </section>

        <section class="grid gap-4 lg:grid-cols-2" data-guide="{{ $guidePrefix }}-isolation">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">{{ $isArabic ? 'عزل الصلاحيات' : 'Permission isolation' }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $isArabic ? 'هذه الشاشة محمية بصلاحية المحفظة الخاصة بها. إخفاء الرابط ليس هو الحماية الوحيدة.' : 'This screen is protected by its own wallet permission. Hiding the link is not the only control.' }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">{{ $isArabic ? 'الفصل الثابت' : 'Fixed separation' }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $isArabic ? 'Product Wallet وParty Wallet لهما جداول وروابط وصلاحيات منفصلة، ولا يوجد endpoint للتحويل العام.' : 'Product Wallet and Party Wallet use separate tables, routes, and permissions; no generic transfer endpoint exists.' }}</p></div>
        </section>
    </div>
</x-layouts::app>
