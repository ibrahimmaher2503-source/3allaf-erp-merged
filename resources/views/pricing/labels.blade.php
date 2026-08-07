@php
    $locale = app()->getLocale();
    $isArabic = $locale === 'ar';
    $text = static fn (array $value): string => $value[$locale] ?? $value['en'] ?? $value['ar'] ?? '';
@endphp

<x-app.page
    :title="$isArabic ? 'جاهزية طوابير الملصقات' : 'Label Queue Readiness'"
    :description="$isArabic ? 'حد محلي للقراءة فقط يوضح ما يلزم قبل إنشاء ملصقات الأسعار.' : 'A Local/Dev read-only boundary showing what is required before price-label generation can be enabled.'"
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
                {{ $isArabic ? 'Local/Dev — لا توجد طباعة مفعلة' : 'Local/Dev — Printing is not enabled' }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ $isArabic ? 'هذه الشاشة توضح الجاهزية فقط. لا تنشئ طوابير أو أحداث طباعة، ولا تعتمد طابعة أو مقاس ملصق أو كمية مخزون Production.' : 'This screen is readiness-only. It does not create queues or print events, and it does not approve a Production printer, label size, or stock quantity.' }}
            </flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-4">
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'السعر المعتمد' : 'Approved price' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $approvedPriceCount }}</div>
                <flux:badge color="green">{{ $isArabic ? 'متاح من TSK-017' : 'Available from TSK-017' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'رصيد المخزون' : 'Stock balance' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'غير متاح' : 'Unavailable' }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'بانتظار TSK-019' : 'Awaiting TSK-019' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الطابعة والقالب' : 'Printer & template' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'غير مهيأ' : 'Not configured' }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'Owner/Operations pending' : 'Owner/Operations pending' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'التنفيذ' : 'Execution' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'معطل' : 'Disabled' }}</div>
                <flux:badge color="zinc">{{ $isArabic ? 'قراءة فقط' : 'Read only' }}</flux:badge>
            </flux:card>
        </div>

        <section class="space-y-3" aria-labelledby="label-readiness-requirements">
            <div>
                <h2 id="label-readiness-requirements" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'متطلبات إنشاء الطابور' : 'Queue generation requirements' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'يجب أن تتحقق هذه المتطلبات قبل إنشاء أي ملصق.' : 'These requirements must be satisfied before any label is generated.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['key' => 'stock', 'title' => ['ar' => 'كمية متبقية حسب المتجر', 'en' => 'Remaining quantity by store'], 'detail' => ['ar' => 'تأتي الكمية من stock_balances المشتق من stock_movements، وليس من إدخال يدوي.', 'en' => 'Quantity must come from stock_balances derived from stock_movements, never from manual input.']],
                    ['key' => 'price', 'title' => ['ar' => 'سعر معتمد وساري', 'en' => 'Approved effective price'], 'detail' => ['ar' => 'لا يستخدم إلا السعر المعتمد والساري من TSK-017؛ المنتج بلا سعر ممنوع من الملصق.', 'en' => 'Only the approved effective TSK-017 price is eligible; unpriced products remain blocked.']],
                    ['key' => 'printer', 'title' => ['ar' => 'طابعة وقالب معتمدان', 'en' => 'Approved printer and template'], 'detail' => ['ar' => 'الطابعة والمقاس والقالب والـdevice scope ما زالت Owner/Operations pending.', 'en' => 'Printer, size, template, and device scope remain Owner/Operations pending.']],
                    ['key' => 'audit', 'title' => ['ar' => 'تدقيق الطباعة وإعادة الطباعة', 'en' => 'Print and reprint audit'], 'detail' => ['ar' => 'كل حدث يجب أن يسجل المستخدم والطابعة والكمية والسبب والوقت دون تعديل queue بصمت.', 'en' => 'Every event must record user, printer, quantity, reason, and time without silently rewriting a queue.']],
                ] as $requirement)
                    <flux:card class="space-y-3 border-amber-200/70 p-5 dark:border-amber-900/60" data-readiness-requirement="{{ $requirement['key'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $text($requirement['title']) }}</h3>
                            <flux:badge color="amber">{{ $isArabic ? 'معلق' : 'Pending' }}</flux:badge>
                        </div>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $text($requirement['detail']) }}</p>
                        <flux:button variant="subtle" disabled class="w-full">{{ $isArabic ? 'بانتظار العقد/الاعتماد' : 'Awaiting contract/approval' }}</flux:button>
                    </flux:card>
                @endforeach
            </div>
        </section>

        <flux:card class="p-6 text-center" data-label-queue-empty-state>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon name="printer" class="size-6" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ $isArabic ? 'لا توجد طوابير ملصقات' : 'No label queues' }}</flux:heading>
            <flux:text class="mx-auto mt-2 max-w-2xl">{{ $isArabic ? 'لن يتم إنشاء صفوف تجريبية. سيظهر هذا الجدول بعد تنفيذ عقد المخزون والطابعة واعتماد متطلبات الكمية والقالب.' : 'No demo rows are created. The queue will appear only after the stock and printer contracts exist and quantity/template requirements are approved.' }}</flux:text>
            <div class="mt-4 flex justify-center gap-2">
                <flux:button href="{{ route('pricing.index') }}" variant="primary">{{ $isArabic ? 'مراجعة الأسعار المعتمدة' : 'Review approved prices' }}</flux:button>
                <flux:button variant="subtle" disabled>{{ $isArabic ? 'إنشاء طابور (معطل)' : 'Generate queue (disabled)' }}</flux:button>
            </div>
        </flux:card>
    </div>
</x-app.page>
