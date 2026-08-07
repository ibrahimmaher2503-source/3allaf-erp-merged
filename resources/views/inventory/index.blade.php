@php
    $isArabic = app()->getLocale() === 'ar';
    $t = static fn (string $ar, string $en): string => $isArabic ? $ar : $en;
    $status = static fn (?string $value): string => match ($value) {
        'draft' => $t('مسودة', 'Draft'),
        'submitted' => $t('مرسل', 'Submitted'),
        'approved' => $t('معتمد', 'Approved'),
        'in_transit' => $t('قيد النقل', 'In transit'),
        'difference_review' => $t('مراجعة فرق', 'Difference review'),
        'received' => $t('مستلم', 'Received'),
        'in_progress' => $t('قيد التنفيذ', 'In progress'),
        'reconciled' => $t('تمت المطابقة', 'Reconciled'),
        default => (string) $value,
    };
@endphp

<x-layouts.app :title="$t('مركز تحكم المخزون', 'Inventory Control Center')">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-inventory-demo>
        <div class="flex flex-col gap-4 rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-sky-50 p-6 shadow-sm dark:border-amber-900/50 dark:from-amber-950/30 dark:via-zinc-900 dark:to-sky-950/30 sm:flex-row sm:items-end sm:justify-between" data-guide="inventory-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-amber-700 dark:text-amber-300">{{ $t('بيانات تجريبية محلية فقط', 'Local Demo data only') }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-zinc-950 dark:text-white">{{ $t('مركز تحكم المخزون', 'Inventory Control Center') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $t('عرض موحّد للرصيد والدفتر والتحويلات والتسويات والجرد. كل الحركات append-only، والرصيد المتاح مشتق من on-hand ناقص المحجوز.', 'A single local slice for balances, ledger, transfers, adjustments, and counts. Movements are append-only; availability is derived from on-hand minus reserved.') }}</p>
            </div>
            <div class="rounded-2xl border border-amber-300 bg-white/80 px-4 py-3 text-sm text-amber-900 shadow-sm dark:border-amber-800 dark:bg-zinc-900/70 dark:text-amber-100">
                <strong>{{ $t('غير إنتاجي', 'Not Production') }}</strong>
                <div class="mt-1 text-xs">{{ $t('لا توجد موافقة UAT أو hardware أو بيانات افتتاحية فعلية.', 'No UAT, hardware, or real opening-stock approval is implied.') }}</div>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200" role="alert">{{ session('error') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" data-guide="inventory-summary">
            @foreach ([
                [$t('أرصدة', 'Balances'), $balances->count(), 'text-sky-700'],
                [$t('حركات دفترية', 'Ledger movements'), $movements->count(), 'text-violet-700'],
                [$t('تحويلات', 'Transfers'), $transfers->count(), 'text-amber-700'],
                [$t('تسويات', 'Adjustments'), $adjustments->count(), 'text-rose-700'],
                [$t('خطط جرد', 'Stock counts'), $counts->count(), 'text-emerald-700'],
            ] as [$caption, $value, $color])
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $caption }}</p>
                    <p class="mt-2 text-3xl font-black {{ $color }} dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="balances-heading" data-guide="inventory-balances">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 id="balances-heading" class="text-xl font-bold text-zinc-950 dark:text-white" data-guide="inventory-balances-heading">{{ $t('الرصيد والتوافر وإعادة الطلب', 'Balances, availability, and reorder') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $t('المتاح = on-hand − reserved؛ in-transit منفصل ولا يرفع on-hand قبل الاستلام.', 'Available = on-hand − reserved; in-transit stays separate until receipt.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-start text-sm" data-stock-balance-table>
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-950/50"><tr><th class="px-5 py-3">{{ $t('المنتج', 'Product') }}</th><th class="px-5 py-3">{{ $t('المتجر', 'Store') }}</th><th class="px-5 py-3">On-hand</th><th class="px-5 py-3">Reserved</th><th class="px-5 py-3">Available</th><th class="px-5 py-3">In transit</th>@if($canViewCost)<th class="px-5 py-3">WAC</th>@endif<th class="px-5 py-3">{{ $t('إعادة الطلب', 'Reorder') }}</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($balances as $balance)
                            @php $available = (float) $balance->on_hand - (float) $balance->reserved; $reorder = (float) ($balance->product?->reorder_threshold ?? 0); @endphp
                            <tr data-stock-balance-row><td class="px-5 py-4 font-semibold text-zinc-900 dark:text-white">{{ $balance->product?->item_code }}<div class="text-xs font-normal text-zinc-500">{{ $isArabic ? $balance->product?->name_ar : $balance->product?->name_en }}</div></td><td class="px-5 py-4">{{ $balance->store?->code }}</td><td class="px-5 py-4 font-mono">{{ number_format((float) $balance->on_hand, 3) }}</td><td class="px-5 py-4 font-mono">{{ number_format((float) $balance->reserved, 3) }}</td><td class="px-5 py-4 font-mono font-bold {{ $available <= $reorder ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format($available, 3) }}</td><td class="px-5 py-4 font-mono">{{ number_format((float) $balance->in_transit, 3) }}</td>@if($canViewCost)<td class="px-5 py-4 font-mono">{{ number_format((float) $balance->average_cost, 4) }}</td>@endif<td class="px-5 py-4 font-mono">{{ number_format($reorder, 3) }}</td></tr>
                        @empty
                            <tr><td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-5 py-8 text-center text-zinc-500">{{ $t('لا توجد أرصدة.', 'No balances.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="transfers-heading" data-guide="inventory-transfers">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 id="transfers-heading" class="text-xl font-bold text-zinc-950 dark:text-white">{{ $t('التحويلات وفروق الاستلام', 'Transfers and difference review') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('التدفق المحلي: مرسل ← معتمد ← قيد النقل ← مستلم أو مراجعة فرق.', 'Local flow: submitted → approved → in transit → received or difference review.') }}</p></div>
            <div class="space-y-4 p-5">
                @forelse ($transfers as $transfer)
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700" data-transfer-row>
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="font-bold text-zinc-900 dark:text-white">{{ $transfer->transfer_number }}</span><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $status($transfer->status) }}</span>@if($transfer->difference_status)<span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">{{ $status($transfer->difference_status) }}</span>@endif</div><p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $transfer->sourceStore?->code }} → {{ $transfer->destinationStore?->code }} · {{ $transfer->lines->first()?->product?->item_code }} · {{ $transfer->lines->first()?->quantity_requested }}</p></div><div class="flex flex-wrap gap-2" data-guide="inventory-transfer-actions">
                            @if($transfer->status === 'submitted')<form method="POST" action="{{ route('inventory.transfers.approve', $transfer) }}">@csrf<button class="rounded-xl bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800" type="submit">{{ $t('اعتماد', 'Approve') }}</button></form>@endif
                            @if($transfer->status === 'approved')<form method="POST" action="{{ route('inventory.transfers.dispatch', $transfer) }}">@csrf<button class="rounded-xl bg-violet-700 px-3 py-2 text-xs font-bold text-white hover:bg-violet-800" type="submit">{{ $t('إرسال', 'Dispatch') }}</button></form>@endif
                        </div></div>
                        @if($transfer->status === 'in_transit')
                            <form class="mt-4 grid gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/60 sm:grid-cols-4" method="POST" action="{{ route('inventory.transfers.receive', $transfer) }}" data-guide="inventory-receipt-form">
                                @csrf
                                @foreach($transfer->lines as $transferLine)
                                    <div><label class="text-xs font-semibold text-zinc-600 dark:text-zinc-300" for="received-{{ $transfer->id }}-{{ $transferLine->id }}">{{ $t('الكمية المستلمة', 'Received quantity') }} · {{ $transferLine->product?->item_code }}</label><input id="received-{{ $transfer->id }}-{{ $transferLine->id }}" name="received_quantities[{{ $transferLine->id }}]" value="{{ $transferLine->quantity_dispatched }}" inputmode="decimal" class="mt-1 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required></div>
                                @endforeach
                                <div><label class="text-xs font-semibold text-zinc-600 dark:text-zinc-300" for="difference-{{ $transfer->id }}">{{ $t('نوع الفرق', 'Difference type') }}</label><select id="difference-{{ $transfer->id }}" name="difference_type" class="mt-1 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">{{ $t('بدون فرق', 'No difference') }}</option><option value="shortage">{{ $t('عجز', 'Shortage') }}</option><option value="damage">{{ $t('تالف', 'Damage') }}</option><option value="refusal">{{ $t('رفض', 'Refusal') }}</option></select></div><div><label class="text-xs font-semibold text-zinc-600 dark:text-zinc-300" for="reason-{{ $transfer->id }}">{{ $t('سبب الفرق', 'Difference reason') }}</label><input id="reason-{{ $transfer->id }}" name="difference_reason" class="mt-1 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900"></div><div class="flex items-end"><button class="w-full rounded-xl bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800" type="submit">{{ $t('تسجيل الاستلام', 'Record receipt') }}</button></div>
                            </form>
                        @endif
                        @if($transfer->status === 'difference_review')<form class="mt-4 grid gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-900 dark:bg-rose-950/30 sm:grid-cols-3" method="POST" action="{{ route('inventory.transfers.differences.resolve', $transfer) }}" data-guide="inventory-difference-form">@csrf<div><label class="text-xs font-semibold text-rose-800 dark:text-rose-200" for="resolve-difference-{{ $transfer->id }}">{{ $t('نوع الفرق', 'Difference type') }}</label><select id="resolve-difference-{{ $transfer->id }}" name="difference_type" class="mt-1 w-full rounded-lg border-rose-300 text-sm dark:border-rose-700 dark:bg-zinc-900"><option value="shortage">{{ $t('عجز', 'Shortage') }}</option><option value="damage">{{ $t('تالف', 'Damage') }}</option><option value="refusal">{{ $t('رفض', 'Refusal') }}</option></select></div><div><label class="text-xs font-semibold text-rose-800 dark:text-rose-200" for="resolve-reason-{{ $transfer->id }}">{{ $t('سبب الإغلاق', 'Resolution reason') }}</label><input id="resolve-reason-{{ $transfer->id }}" name="difference_reason" value="{{ $transfer->lines->first()?->difference_reason }}" class="mt-1 w-full rounded-lg border-rose-300 text-sm dark:border-rose-700 dark:bg-zinc-900" required></div><div class="flex items-end"><button class="w-full rounded-xl bg-rose-700 px-3 py-2 text-xs font-bold text-white hover:bg-rose-800" type="submit">{{ $t('إغلاق مراجعة الفرق', 'Resolve difference') }}</button></div></form>@endif
                    </div>
                @empty <p class="text-sm text-zinc-500">{{ $t('لا توجد تحويلات Demo.', 'No Demo transfers.') }}</p> @endforelse
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="adjustments-heading" data-guide="inventory-adjustments"><div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 id="adjustments-heading" class="text-xl font-bold text-zinc-950 dark:text-white">{{ $t('الإدخالات والخروج والتسويات', 'Entries, exits, and adjustments') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('المخزون السالب محظور افتراضيًا؛ أي override يحتاج صلاحية وسببًا وتدقيقًا.', 'Negative stock is blocked by default; overrides require permission, reason, and audit.') }}</p></div><div class="space-y-3 p-5">@forelse($adjustments as $adjustment)<div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700"><div class="flex items-center justify-between gap-3"><div><strong class="text-zinc-900 dark:text-white">{{ $adjustment->adjustment_number }}</strong><div class="mt-1 text-xs text-zinc-500">{{ $adjustment->store?->code }} · {{ $adjustment->adjustment_type }} · {{ $adjustment->lines->first()?->product?->item_code }} {{ $adjustment->lines->first()?->quantity_delta }}</div></div><span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">{{ $status($adjustment->status) }}</span></div><div class="mt-3 flex gap-2">@if($adjustment->status === 'draft')<form method="POST" action="{{ route('inventory.adjustments.submit', $adjustment) }}">@csrf<button class="rounded-xl bg-amber-700 px-3 py-2 text-xs font-bold text-white" type="submit">{{ $t('إرسال للمراجعة', 'Submit for review') }}</button></form>@elseif($adjustment->status === 'submitted')<form method="POST" action="{{ route('inventory.adjustments.approve', $adjustment) }}">@csrf<button class="rounded-xl bg-emerald-700 px-3 py-2 text-xs font-bold text-white" type="submit">{{ $t('اعتماد وترحيل', 'Approve and post') }}</button></form>@endif</div></div>@empty<p class="text-sm text-zinc-500">{{ $t('لا توجد تسويات.', 'No adjustments.') }}</p>@endforelse</div></section>
            <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="counts-heading" data-guide="inventory-counts"><div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 id="counts-heading" class="text-xl font-bold text-zinc-950 dark:text-white">{{ $t('الجرد والمطابقة', 'Stock counts and reconciliation') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('تُحسب الحركة بعد reference time، ولا يتم تصفير غير المعدود تلقائيًا.', 'Movements after reference time are included; uncounted items are never auto-zeroed.') }}</p></div><div class="space-y-3 p-5">@forelse($counts as $count)<div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700"><div class="flex items-center justify-between gap-3"><div><strong class="text-zinc-900 dark:text-white">{{ $count->count_number }}</strong><div class="mt-1 text-xs text-zinc-500">{{ $count->store?->code }} · {{ $count->count_type }} · {{ $count->lines->where('is_counted', false)->count() }} {{ $t('غير معدود', 'uncounted') }}</div></div><span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">{{ $status($count->status) }}</span></div><div class="mt-3 flex gap-2">@if(in_array($count->status, ['draft', 'in_progress'], true))<form method="POST" action="{{ route('inventory.counts.submit', $count) }}">@csrf<button class="rounded-xl bg-amber-700 px-3 py-2 text-xs font-bold text-white" type="submit">{{ $t('إرسال الجرد', 'Submit count') }}</button></form>@elseif($count->status === 'submitted')<form method="POST" action="{{ route('inventory.counts.reconcile', $count) }}">@csrf<button class="rounded-xl bg-emerald-700 px-3 py-2 text-xs font-bold text-white" type="submit">{{ $t('مطابقة واعتماد', 'Reconcile and approve') }}</button></form>@endif</div></div>@empty<p class="text-sm text-zinc-500">{{ $t('لا توجد خطط جرد.', 'No stock counts.') }}</p>@endforelse</div></section>
        </div>

        <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="movements-heading" data-guide="inventory-movements"><div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 id="movements-heading" class="text-xl font-bold text-zinc-950 dark:text-white">{{ $t('دفتر الحركات append-only', 'Append-only movement ledger') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $t('كل صف مرتبط بمصدر ومفتاح idempotency؛ لا يوجد تعديل مباشر للرصيد من الواجهة.', 'Every row has a source and idempotency key; balances are never directly edited from the UI.') }}</p></div><div class="overflow-x-auto"><table class="min-w-full text-start text-sm"><thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-950/50"><tr><th class="px-5 py-3">{{ $t('التاريخ', 'Posted') }}</th><th class="px-5 py-3">{{ $t('المنتج', 'Product') }}</th><th class="px-5 py-3">{{ $t('المتجر', 'Store') }}</th><th class="px-5 py-3">{{ $t('الحركة', 'Movement') }}</th><th class="px-5 py-3">{{ $t('الكمية', 'Quantity') }}</th><th class="px-5 py-3">{{ $t('المصدر', 'Source') }}</th></tr></thead><tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">@forelse($movements as $movement)<tr data-movement-row><td class="px-5 py-3 text-xs text-zinc-500">{{ $movement->posted_at?->format('Y-m-d H:i') }}</td><td class="px-5 py-3 font-semibold">{{ $movement->product?->item_code }}</td><td class="px-5 py-3">{{ $movement->store?->code }}</td><td class="px-5 py-3">{{ $movement->movement_type }}</td><td class="px-5 py-3 font-mono {{ (float) $movement->quantity < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $movement->quantity }}</td><td class="px-5 py-3 text-xs text-zinc-500">{{ $movement->source_type ? class_basename($movement->source_type).'#'.$movement->source_id : 'Demo opening' }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-zinc-500">{{ $t('لا توجد حركات.', 'No movements.') }}</td></tr>@endforelse</tbody></table></div></section>
    </div>
</x-layouts.app>
