@php
    $ar = str_starts_with(app()->getLocale(), 'ar');
    $t = static fn (string $arabic, string $english): string => $ar ? $arabic : $english;
    $quantity = static fn ($value): string => \App\Support\ProductQuantity::format($value);
@endphp
<x-layouts.app :title="$t('حركات المنتج أثناء الجرد', 'Movements during stocktake')">
    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6">
        <a href="{{ route('inventory.counts.reconcile-page', $count) }}" class="text-sm font-semibold text-sky-700">← {{ $t('العودة إلى مراجعة الجرد', 'Back to stocktake review') }}</a>
        <header class="rounded-2xl border border-zinc-200 bg-white p-5">
            <p class="text-xs text-zinc-500">{{ $count->count_number }} · {{ \App\Support\HumanName::name($line->store) }}</p>
            <h1 class="mt-1 text-xl font-bold">{{ \App\Support\HumanName::name($line->product) }}</h1>
            <p class="font-mono text-sm" dir="ltr">{{ $line->product?->item_code }}</p>
            <p class="mt-2 text-sm">{{ $t('العد الفعلي', 'Physical count') }}: <strong dir="ltr">{{ $line->is_counted ? $quantity($line->counted_quantity) : '—' }}</strong> · {{ $t('وقت العد', 'Counted at') }}: {{ $line->counted_at?->format('Y-m-d H:i:s') ?? '—' }}</p>
        </header>
        <section class="space-y-3" aria-label="{{ $t('الحركات أثناء الجرد', 'Movements during count') }}">
            @forelse($movements as $movement)
                <article class="grid gap-2 rounded-xl border border-zinc-200 bg-white p-4 text-sm sm:grid-cols-[1fr_auto]">
                    <div><strong>{{ \App\Modules\Inventory\Support\InventoryMovementLabel::label($movement->movement_type) }}</strong><p class="mt-1 break-all text-xs text-zinc-600" dir="ltr">{{ $movement->source_type }} #{{ $movement->source_id }} · {{ $movement->posted_at?->format('Y-m-d H:i:s') }}</p><p class="text-xs text-zinc-500">{{ $movement->creator?->name ?? '—' }}</p></div>
                    <strong dir="ltr" class="font-mono text-base {{ bccomp((string) $movement->quantity, '0', 6) < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $quantity($movement->quantity) }}</strong>
                </article>
            @empty
                <p class="rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-500">{{ $t('لا توجد حركات بعد مرجع هذا المنتج.', 'No movements after this product reference.') }}</p>
            @endforelse
            {{ $movements->links() }}
        </section>
    </div>
</x-layouts.app>
