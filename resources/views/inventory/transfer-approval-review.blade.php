@php
    $ar = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $arabic, string $english): string => $ar ? $arabic : $english;
@endphp
<x-layouts.app :title="$t('مراجعة اعتماد التحويل', 'Transfer approval review')">
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6" data-transfer-approval-review>
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-bold">{{ $t('مراجعة اعتماد التحويل', 'Transfer approval review') }}</h1><p class="text-sm text-zinc-600">{{ $transfer->transfer_number }} · {{ \App\Support\HumanName::name($transfer->sourceStore) }} → {{ \App\Support\HumanName::name($transfer->destinationStore) }}</p></div>
            <a href="{{ route('inventory.transfers') }}" class="text-sm font-semibold text-sky-700">{{ $t('العودة إلى التحويلات', 'Back to transfers') }}</a>
        </header>
        @if($errors->any())<div class="rounded-xl bg-rose-50 p-3 text-rose-800">{{ $errors->first() }}</div>@endif
        <section class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full min-w-[620px] text-sm"><thead class="bg-zinc-100"><tr><th class="p-3 text-start">{{ $t('المنتج والرمز', 'Product and SKU') }}</th><th class="p-3 text-end">{{ $t('كمية التحويل', 'Transfer quantity') }}</th><th class="p-3 text-start">{{ $t('الوجهات والكميات المخصصة', 'Destination allocations') }}</th></tr></thead><tbody>
                @foreach($transfer->lines as $line)<tr class="border-t"><td class="p-3"><strong>{{ \App\Support\HumanName::name($line->product) }}</strong><small class="block font-mono" dir="ltr">{{ $line->product?->item_code }}</small></td><td class="p-3 text-end" dir="ltr"><x-product-quantity :value="$line->quantity_requested" /></td><td class="p-3">@forelse($line->allocations as $allocation)<div>{{ \App\Support\HumanName::name($allocation->destination) }} · <span dir="ltr"><x-product-quantity :value="$allocation->quantity" /></span></div>@empty{{ \App\Support\HumanName::name($transfer->destinationStore) }}@endforelse</td></tr>@endforeach
            </tbody></table>
        </section>
        <form method="POST" action="{{ route('inventory.transfers.approve', $transfer) }}" class="flex flex-wrap justify-end gap-3">@csrf<input type="hidden" name="review_version" value="{{ $transfer->lock_version }}"><a href="{{ route('inventory.transfers') }}" class="rounded-xl border px-5 py-2 font-semibold">{{ $t('إلغاء', 'Cancel') }}</a><button class="rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white">{{ $t('تأكيد الاعتماد', 'Confirm approval') }}</button></form>
    </main>
</x-layouts.app>
