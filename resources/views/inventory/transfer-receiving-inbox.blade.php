@php
    $ar = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $arabic, string $english): string => $ar ? $arabic : $english;
    $qty = static fn ($value): string => \App\Support\ProductQuantity::format($value);
@endphp
<x-layouts.app :title="$t('الأذون الواردة للاستلام', 'Incoming transfer receipts')">
    <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6" data-transfer-receiving-inbox>
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ $t('الأذون الواردة للاستلام', 'Incoming transfer receipts') }}</h1>
            <a href="{{ route('inventory.transfers') }}" class="text-sm font-semibold text-sky-700">{{ $t('سجل التحويلات', 'Transfer register') }}</a>
        </header>
        <div class="space-y-3">
            @forelse($transfers as $transfer)
                @php
                    $destinationGroups = $transfer->lines->flatMap->allocations->whereIn('destination_store_id', $visibleIds)->groupBy('destination_store_id');
                    if ($destinationGroups->isEmpty() && $visibleIds->contains($transfer->destination_store_id)) {
                        $destinationGroups = collect([$transfer->destination_store_id => collect()]);
                    }
                @endphp
                @foreach($destinationGroups as $destinationId => $allocations)
                    @php
                        $draft = $drafts->get($transfer->id.':'.$destinationId);
                        $received = $draft?->finalized_at !== null;
                        $partial = ! $received && $draft !== null;
                        $destination = $allocations->first()?->destination ?? $transfer->destinationStore;
                        $productCount = $allocations->isEmpty() ? $transfer->lines->count() : $allocations->pluck('stock_transfer_line_id')->unique()->count();
                        $allocatedQuantity = $allocations->isEmpty() ? $transfer->lines->sum('quantity_dispatched') : $allocations->sum('quantity');
                    @endphp
                    <a href="{{ route('inventory.transfers.receive-page', [$transfer, 'destination_store_id' => $destinationId]) }}" class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm hover:border-sky-400 dark:border-zinc-700 dark:bg-zinc-900" data-incoming-transfer="{{ $transfer->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><strong class="font-mono text-lg" dir="ltr">{{ $transfer->transfer_number }}</strong><p class="text-sm">{{ \App\Support\HumanName::name($transfer->sourceStore) }} <span class="text-zinc-500" dir="ltr">{{ $transfer->sourceStore?->code }}</span> → {{ \App\Support\HumanName::name($destination) }}</p></div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $received ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $received ? $t('تم الاستلام', 'Received') : ($partial ? $t('مستلم جزئياً', 'Partially received') : $t('بانتظار الاستلام', 'Awaiting receipt')) }}</span></div>
                        <dl class="mt-3 grid gap-2 text-xs text-zinc-600 sm:grid-cols-4"><div><dt>{{ $t('تاريخ الإنشاء', 'Created date') }}</dt><dd dir="ltr">{{ $transfer->created_at?->format('Y-m-d') }}</dd></div><div><dt>{{ $t('وقت الإنشاء', 'Created time') }}</dt><dd dir="ltr">{{ $transfer->created_at?->format('H:i') }}</dd></div><div><dt>{{ $t('المنتجات', 'Products') }}</dt><dd>{{ $productCount }}</dd></div><div><dt>{{ $t('الكمية المخصصة', 'Allocated quantity') }}</dt><dd dir="ltr">{{ $qty($allocatedQuantity) }}</dd></div></dl>
                    </a>
                @endforeach
            @empty
                <p class="rounded-xl border border-dashed p-6 text-center text-zinc-500">{{ $t('لا توجد أذون واردة ضمن نطاقك.', 'No incoming transfers in your scope.') }}</p>
            @endforelse
        </div>
        {{ $transfers->links() }}
    </main>
</x-layouts.app>
