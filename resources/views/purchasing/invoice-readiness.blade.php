@php
    $locale = app()->getLocale();
    $isArabic = $locale === 'ar';
    $label = static fn (array $value): string => $value[$locale] ?? $value['en'] ?? $value['ar'] ?? '';
@endphp

<x-app.page
    :title="$isArabic ? 'جاهزية TSK-015' : 'TSK-015 Readiness'"
    :description="$isArabic ? 'حد قراءة فقط يوضح ما يلزم قبل تفعيل فواتير الشراء والاستلام والتكلفة.' : 'A read-only boundary showing what must be resolved before purchase invoices, receiving, and cost behavior can be enabled.'"
    max-width="7xl"
    class="purchasing-screen"
>
    <x-slot:actions>
        @can('company_settings.view')
            <flux:button href="{{ route('purchasing.invoices.settings') }}" variant="subtle" icon="adjustments-horizontal">
                {{ $isArabic ? 'إعدادات الفواتير' : 'Invoice settings' }}
            </flux:button>
        @endcan
        <flux:button href="{{ route('purchasing.orders') }}" variant="subtle" icon="arrow-left">
            {{ $isArabic ? 'العودة إلى أوامر الشراء' : 'Back to purchase orders' }}
        </flux:button>
    </x-slot:actions>

    <div class="space-y-6" data-guide="tsk-015-readiness">
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>
                {{ $isArabic ? 'مطلوب اعتماد المالك — تحضير الجاهزية فقط' : 'Owner Approval Required — Readiness Preparation Only' }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ $isArabic ? 'هذه الشاشة لا تنفذ إنشاء فاتورة أو استيرادًا أو استلامًا أو حركة مخزون أو حساب WAC. القيم الهندسية المحلية ليست اعتمادًا إنتاجيًا.' : 'This screen does not create invoices, import files, receive stock, write inventory movements, or calculate WAC. Local engineering defaults are not production approval.' }}
            </flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الحالة' : 'Status' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'محجوب بانتظار القرارات' : 'Blocked pending decisions' }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'مطلوب اعتماد المالك' : 'Owner approval required' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'مجموعات القرارات' : 'Decision groups' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ count($decisionGroups) }}</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'كلها ما زالت مفتوحة أو غير معتمدة' : 'All remain open or unapproved' }}</div>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'التنفيذ المتاح' : 'Available execution' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'قراءة فقط' : 'Read only' }}</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'لا توجد mutations مفعلة' : 'No mutations are enabled' }}</div>
            </flux:card>
        </div>

        <section class="space-y-3" aria-labelledby="tsk-015-decisions-heading">
            <div>
                <h2 id="tsk-015-decisions-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'مجموعات مدخلات المالك' : 'Owner decision groups' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'يجب الإجابة عن كل مجموعة أو تحديد Not Applicable قبل تنفيذ أي سلوك مالي.' : 'Every group must be answered or marked Not Applicable before financial behavior is implemented.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($decisionGroups as $group)
                    <flux:card class="space-y-4 p-5" data-readiness-group="{{ $group['key'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $label($group['title']) }}</h3>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $group['reference'] }}</p>
                            </div>
                            <flux:badge color="amber">{{ $isArabic ? 'مفتوح' : 'Open' }}</flux:badge>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'مدخلات مطلوبة' : 'Required inputs' }}</span>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $group['items'] }}</span>
                        </div>
                        <flux:button variant="subtle" disabled class="w-full">
                            {{ $isArabic ? 'بانتظار إجابة المالك' : 'Awaiting owner answer' }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="tsk-015-blockers-heading">
            <div>
                <h2 id="tsk-015-blockers-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'الموانع الحالية' : 'Current blockers' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'وجود هذه الموانع يمنع اعتبار المهمة جاهزة للإنتاج أو الترحيل المالي.' : 'These blockers prevent production or financial-posting readiness.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($blockers as $blocker)
                    <flux:card class="space-y-3 border-amber-200/70 p-5 dark:border-amber-900/60" data-readiness-blocker="{{ $blocker['key'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $label($blocker['title']) }}</h3>
                            <flux:badge color="amber">{{ $blocker['key'] }}</flux:badge>
                        </div>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $label($blocker['detail']) }}</p>
                    </flux:card>
                @endforeach
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="tsk-015-lifecycle-heading">
            <div>
                <h2 id="tsk-015-lifecycle-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'النطاق المقصود لاحقًا' : 'Future lifecycle reference' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'هذه بطاقات مرجعية فقط وليست workflow قابلة للتنفيذ.' : 'These are reference cards only, not an executable workflow.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                @foreach ([
                    ['ar' => 'Draft', 'en' => 'Draft'],
                    ['ar' => 'Submitted', 'en' => 'Submitted'],
                    ['ar' => 'Approved', 'en' => 'Approved'],
                    ['ar' => 'Receipt / Ledger', 'en' => 'Receipt / Ledger'],
                ] as $state)
                    <flux:card class="p-4 opacity-70">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $label($state) }}</div>
                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'غير متاح قبل اكتمال الاعتماد' : 'Unavailable until approvals are complete' }}</div>
                    </flux:card>
                @endforeach
            </div>
        </section>

        <flux:card class="p-6 text-center" data-readiness-empty-state>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon name="document-magnifying-glass" class="size-6" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'لا توجد فواتير قابلة للمراجعة بعد' : 'No invoices are available for review yet' }}</h2>
            <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'سيظهر محتوى الفواتير بعد اعتماد السياسة وتنفيذ شريحة منفصلة مع تحقق وصلاحيات واضحة. لا يتم إنشاء بيانات تجريبية هنا.' : 'Invoice content will appear only after policy approval and a separate verified implementation slice. No demo financial data is created here.' }}</p>
            <div class="mt-5 flex justify-center gap-3">
                <flux:button variant="subtle" disabled>{{ $isArabic ? 'إنشاء فاتورة — محجوب' : 'Create invoice — blocked' }}</flux:button>
                <flux:button variant="subtle" disabled>{{ $isArabic ? 'استيراد — محجوب' : 'Import — blocked' }}</flux:button>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            <span>{{ $isArabic ? 'المصدر: .ai/TSK-015_OWNER_INPUTS.md و DEC-043' : 'Source: .ai/TSK-015_OWNER_INPUTS.md and DEC-043' }}</span>
            <span>{{ $isArabic ? 'الحالة: readiness preparation — ليست جاهزية إنتاجية' : 'State: readiness preparation — not production readiness' }}</span>
        </div>
    </div>
</x-app.page>
