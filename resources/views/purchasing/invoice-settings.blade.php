@php
    $locale = app()->getLocale();
    $isArabic = $locale === 'ar';
    $label = static fn (array $value): string => $value[$locale] ?? $value['en'] ?? $value['ar'] ?? '';
@endphp

<x-app.page
    :title="$isArabic ? 'إعدادات فواتير الشراء' : 'Purchase Invoice Settings'"
    :description="$isArabic ? 'أساس الإصدار الزمني لإعدادات TSK-015 — قراءة فقط حتى اعتماد السياسات.' : 'Versioned TSK-015 financial settings foundation — read only until policy approval.'"
    max-width="7xl"
    class="purchasing-screen"
>
    <x-slot:actions>
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="arrow-left">
            {{ $isArabic ? 'العودة إلى الجاهزية' : 'Back to readiness' }}
        </flux:button>
    </x-slot:actions>

    <div class="space-y-6" data-guide="tsk-015-settings">
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>
                {{ $isArabic ? 'قراءة فقط — اعتماد المالك مطلوب' : 'Read only — owner approval required' }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ $isArabic ? 'هذه الشاشة تعرض عقد الإصدار الزمني فقط. لا يتم زرع defaults تجارية، ولا يمكن حفظ أو تعديل إعدادات أو تفعيل posting.' : 'This screen exposes the versioning contract only. No commercial defaults are seeded, and settings cannot be saved or changed.' }}
            </flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الإصدارات الحالية' : 'Current versions' }}</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $versions->count() }}</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'لا توجد قيم مالية مزروعة محليًا' : 'No financial values are seeded locally' }}</div>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'الحفظ' : 'Write mode' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'محجوب' : 'Blocked' }}</div>
                <flux:badge color="amber">{{ $isArabic ? 'Owner approval' : 'Owner approval' }}</flux:badge>
            </flux:card>
            <flux:card class="space-y-2 p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $isArabic ? 'النشر المالي' : 'Financial posting' }}</div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'غير متاح' : 'Unavailable' }}</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'لا يوجد أي أثر على المخزون' : 'No inventory side effect' }}</div>
            </flux:card>
        </div>

        <section class="space-y-3" aria-labelledby="settings-contract-heading">
            <div>
                <h2 id="settings-contract-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'عقد الإصدار الزمني' : 'Versioning contract' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'كل تغيير مستقبلي سيضيف إصدارًا، ولن يعيد كتابة التاريخ المالي.' : 'Every future change will add a version and never rewrite financial history.' }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['key' => 'key', 'title' => ['ar' => 'مفتاح الإعداد', 'en' => 'Setting key'], 'detail' => ['ar' => 'اسم ثابت مثل rounding_mode أو tax_enabled.', 'en' => 'A stable key such as rounding_mode or tax_enabled.']],
                    ['key' => 'value', 'title' => ['ar' => 'القيمة والنوع', 'en' => 'Value and type'], 'detail' => ['ar' => 'القيمة تُحفظ كنص مع value_type لتفسيرها صراحةً.', 'en' => 'The value is stored with an explicit value_type.']],
                    ['key' => 'effective_from', 'title' => ['ar' => 'تاريخ السريان', 'en' => 'Effective time'], 'detail' => ['ar' => 'المستند يُقيّم على الإصدار الساري وقت الاعتماد.', 'en' => 'A document resolves the version effective at approval time.']],
                    ['key' => 'locked_at', 'title' => ['ar' => 'قفل الإعداد', 'en' => 'Lock time'], 'detail' => ['ar' => 'الإعدادات الحساسة ستُقفل بعد أول posting وفق docs/46.', 'en' => 'Sensitive keys will lock after first posting per docs/46.']],
                ] as $contract)
                    <flux:card class="space-y-2 p-5" data-settings-contract="{{ $contract['key'] }}">
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $label($contract['title']) }}</h3>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $label($contract['detail']) }}</p>
                    </flux:card>
                @endforeach
            </div>
        </section>

        @if ($versions->isEmpty())
            <flux:card class="p-6 text-center" data-settings-empty-state>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <flux:icon name="adjustments-horizontal" class="size-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'لا توجد إصدارات مالية بعد' : 'No financial setting versions yet' }}</h2>
                <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $isArabic ? 'سيتم إنشاء أول إصدار بعد اعتماد قيم المالك وتنفيذ شريحة الحفظ المصرح بها. لا يتم تخمين القيم هنا.' : 'The first version will be created only after owner values are approved and an authorized save slice is implemented. No values are guessed here.' }}</p>
                <div class="mt-5 flex justify-center gap-3">
                    <flux:button variant="subtle" disabled>{{ $isArabic ? 'إضافة إصدار — محجوب' : 'Add version — blocked' }}</flux:button>
                    <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle">{{ $isArabic ? 'مراجعة الموانع' : 'Review blockers' }}</flux:button>
                </div>
            </flux:card>
        @else
            <section class="space-y-3" aria-labelledby="settings-versions-heading">
                <h2 id="settings-versions-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $isArabic ? 'الإصدارات المسجلة' : 'Recorded versions' }}</h2>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 dark:bg-zinc-900">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">Key</th>
                                <th class="px-4 py-3 text-start font-semibold">Type</th>
                                <th class="px-4 py-3 text-start font-semibold">Version</th>
                                <th class="px-4 py-3 text-start font-semibold">Effective from</th>
                                <th class="px-4 py-3 text-start font-semibold">Locked at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($versions as $version)
                                <tr>
                                    <td class="px-4 py-3 font-mono">{{ $version->key }}</td>
                                    <td class="px-4 py-3">{{ $version->value_type }}</td>
                                    <td class="px-4 py-3">{{ $version->version }}</td>
                                    <td class="px-4 py-3">{{ $version->effective_from?->toIso8601String() }}</td>
                                    <td class="px-4 py-3">{{ $version->locked_at?->toIso8601String() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            <span>{{ $isArabic ? 'المصدر: docs/46 و DEC-043' : 'Source: docs/46 and DEC-043' }}</span>
            <span>{{ $isArabic ? 'الحالة: foundation/readiness — ليست جاهزية إنتاجية' : 'State: foundation/readiness — not production readiness' }}</span>
        </div>
    </div>
</x-app.page>
