@php
    $locale = app()->getLocale();
    $isArabic = $locale === 'ar';
    $text = static fn (array $value): string => $value[$locale] ?? $value['en'] ?? $value['ar'] ?? '';
    $statusLabel = static fn (string $status): string => match ($status) {
        'partial' => $isArabic ? 'جزئي' : 'Partial',
        'completed' => $isArabic ? 'مكتمل' : 'Completed',
        'failed' => $isArabic ? 'فشل' : 'Failed',
        default => $isArabic ? 'معلق' : 'Pending',
    };
@endphp

<x-app.page
    :title="$isArabic ? 'طوابير ملصقات الأسعار' : 'Price Label Queues'"
    :description="$isArabic ? 'بيانات Demo محلية توضح الكمية والطابعة وسجل الطباعة؛ التنفيذ الفعلي ما زال محميًا.' : 'Local Demo data showing quantity, printer, and print history; real execution remains guarded.'"
    max-width="7xl"
    class="pricing-screen"
>
    <x-slot:actions>
        <flux:button href="{{ route('pricing.index') }}" variant="subtle" icon="arrow-left">
            {{ $isArabic ? 'العودة إلى مساحة التسعير' : 'Back to Pricing Workspace' }}
        </flux:button>
    </x-slot:actions>

    <div class="space-y-6" data-guide="tsk-018-label-readiness">
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>
                {{ $isArabic ? 'Local/Dev Demo — الطباعة الفعلية غير مفعلة' : 'Local/Dev Demo — Real printing is not enabled' }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ $isArabic ? 'الصفوف الظاهرة Dummy data محلية فقط. لا يوجد اتصال Hardware، ولا تمثل الطابعة أو المقاس أو الكمية اعتماد Production/UAT.' : 'The rows shown are Local-only Dummy data. There is no hardware connection, and the printer, size, and quantities are not Production/UAT approval.' }}
            </flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الأسعار المعتمدة' : 'Approved prices' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $approvedPriceCount }}</div>
                <flux:badge color="green">{{ $isArabic ? 'مصدر TSK-017' : 'TSK-017 source' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'أرصدة المخزون' : 'Stock balances' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $stockBalanceCount }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'Demo فقط' : 'Demo only' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الطابعات النشطة' : 'Active printers' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $printerCount }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'لا Hardware' : 'No hardware' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'التنفيذ' : 'Execution' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'معطل' : 'Disabled' }}</div>
                <flux:badge color="zinc">{{ $isArabic ? 'قراءة فقط' : 'Read only' }}</flux:badge>
            </flux:card>
        </div>

        <section class="space-y-3" aria-labelledby="label-readiness-requirements">
            <div>
                <h2 id="label-readiness-requirements" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'ضوابط إنشاء الطابور' : 'Queue generation controls' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'السعر المعتمد والرصيد التجريبي والطابعة التجريبية موجودة، لكن generate/print/reprint ما زالت معطلة.' : 'The approved price, Demo balance, and Demo printer exist, but generate/print/reprint remain disabled.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['key' => 'stock', 'title' => ['ar' => 'كمية متبقية حسب المتجر', 'en' => 'Remaining quantity by store'], 'detail' => ['ar' => 'تأتي الكمية من stock_balances التجريبي؛ المنتج بلا سعر لا ينتج Queue.', 'en' => 'Quantity comes from the Demo stock_balances; an unpriced product produces no queue.']],
                    ['key' => 'price', 'title' => ['ar' => 'سعر معتمد وساري', 'en' => 'Approved effective price'], 'detail' => ['ar' => 'الطابور التجريبي مرتبط بسعر DEMO المعتمد للمنتج DEMO-PROD-001.', 'en' => 'The Demo queue is linked to the approved Demo price for DEMO-PROD-001.']],
                    ['key' => 'printer', 'title' => ['ar' => 'طابعة وقالب Demo', 'en' => 'Demo printer and template'], 'detail' => ['ar' => 'Demo Label Printer - Local وقالب demo_price_label_v1؛ لا يوجد اتصال Hardware.', 'en' => 'Demo Label Printer - Local and demo_price_label_v1; no hardware connection exists.']],
                    ['key' => 'audit', 'title' => ['ar' => 'تدقيق الطباعة وإعادة الطباعة', 'en' => 'Print and reprint audit'], 'detail' => ['ar' => 'يوجد حدث initial واحد بكمية 2 وسبب Demo؛ الأحداث append-only.', 'en' => 'One initial event of quantity 2 has a Demo reason; events are append-only.']],
                ] as $requirement)
                    <flux:card class="space-y-3 border-amber-200/70 p-5 dark:border-amber-900/60" data-readiness-requirement="{{ $requirement['key'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $text($requirement['title']) }}</h3>
                            <flux:badge color="amber">{{ $isArabic ? 'Demo' : 'Demo' }}</flux:badge>
                        </div>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $text($requirement['detail']) }}</p>
                        <flux:button variant="subtle" disabled class="w-full">{{ $isArabic ? 'التنفيذ معطل' : 'Execution disabled' }}</flux:button>
                    </flux:card>
                @endforeach
            </div>
        </section>

        @if ($queues->isNotEmpty())
            <section class="space-y-3" aria-labelledby="label-queues-heading">
                <div>
                    <h2 id="label-queues-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'صفوف الملصقات التجريبية' : 'Demo label queues' }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'هذه الصفوف زرعها DemoSeeder ويمكن إعادة تشغيله بأمان. لا توجد طباعة فعلية.' : 'These rows are seeded by DemoSeeder and are safe to rerun. No real printing occurs.' }}</p>
                </div>
                <flux:card class="overflow-hidden p-0" data-label-queue-table>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-start text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                                <tr>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'المنتج' : 'Product' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'المتجر' : 'Store' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'المطلوب' : 'Required' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'المطبوع' : 'Printed' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'الحالة' : 'Status' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'الطابعة' : 'Printer' }}</th>
                                    <th class="px-5 py-3 text-start">{{ $isArabic ? 'الأحداث' : 'Events' }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($queues as $queue)
                                    <tr data-label-queue-row="{{ $queue->id }}">
                                        <td class="px-5 py-4 font-medium text-zinc-900 dark:text-white">{{ $queue->product?->item_code }} — {{ $isArabic ? $queue->product?->name_ar : $queue->product?->name_en }}</td>
                                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400">{{ $queue->store?->code }}</td>
                                        <td class="px-5 py-4 font-mono text-zinc-900 dark:text-white">{{ $queue->required_quantity }}</td>
                                        <td class="px-5 py-4 font-mono text-zinc-900 dark:text-white">{{ $queue->printed_quantity }}</td>
                                        <td class="px-5 py-4"><flux:badge color="amber">{{ $statusLabel($queue->status) }}</flux:badge></td>
                                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400">{{ $queue->printer?->name }}</td>
                                        <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400">{{ $queue->printEvents->count() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="subtle" disabled>{{ $isArabic ? 'طباعة (معطل)' : 'Print (disabled)' }}</flux:button>
                    <flux:button variant="subtle" disabled>{{ $isArabic ? 'إعادة طباعة (معطل)' : 'Reprint (disabled)' }}</flux:button>
                    <flux:button variant="subtle" disabled>{{ $isArabic ? 'إنشاء طابور (معطل)' : 'Generate queue (disabled)' }}</flux:button>
                </div>
            </section>
        @else
            <flux:card class="p-6 text-center" data-label-queue-empty-state>
                <flux:heading size="lg" class="mt-4">{{ $isArabic ? 'لا توجد طوابير ملصقات' : 'No label queues' }}</flux:heading>
                <flux:text class="mx-auto mt-2 max-w-2xl">{{ $isArabic ? 'شغّل DemoSeeder المحلي لرؤية صف تجريبي؛ لا يتم إنشاء صفوف Production.' : 'Run the local DemoSeeder to see a Demo row; Production queues are never created here.' }}</flux:text>
            </flux:card>
        @endif
    </div>
</x-app.page>
