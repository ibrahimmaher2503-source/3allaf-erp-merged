@php
    $isAr = str_starts_with(app()->getLocale(), 'ar');
    $features = [
        ['icon' => 'shopping-cart', 'ar' => ['نقطة بيع سريعة', 'بيع نقدي وآجل وجزئي، بالكيلو والعبوة، مع قارئ الباركود ومتابعة الورديات.'], 'en' => ['Fast point of sale', 'Cash, credit and partial payments, by kg or by bag, with barcode scanning and shift control.']],
        ['icon' => 'archive-box', 'ar' => ['مخزون دقيق', 'أرصدة لحظية وتحويلات بين المخازن واستلام جزئي وجرد بالكميات العشرية.'], 'en' => ['Accurate stock', 'Live balances, transfers between warehouses, partial receipts and decimal stocktakes.']],
        ['icon' => 'truck', 'ar' => ['مشتريات وموردون', 'فواتير شراء بالكيلو مع تحميل مصاريف النقل على التكلفة ومديونية المورد.'], 'en' => ['Purchasing & suppliers', 'Purchase invoices by kg with freight loaded onto cost and the supplier payable.']],
        ['icon' => 'users', 'ar' => ['حسابات العملاء', 'مديونية العميل وحد الائتمان والتحصيل وكشف الحساب في مكان واحد.'], 'en' => ['Customer accounts', 'Balances, credit limits, collections and statements in one place.']],
        ['icon' => 'chart-bar', 'ar' => ['تقارير واضحة', 'ملخص المبيعات وحركة الصنف وتقييم المخزون جاهزة للتصدير.'], 'en' => ['Clear reports', 'Sales summary, stock movement and inventory valuation, ready to export.']],
        ['icon' => 'shield-check', 'ar' => ['صلاحيات وتتبع', 'أدوار محددة لكل موظف وسجل نشاط لكل عملية مهمة.'], 'en' => ['Roles & audit trail', 'Defined roles per employee and an activity log for every important action.']],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-zinc-50 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
    <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 sm:px-8">
        <header class="flex min-h-20 items-center justify-between border-b border-zinc-200 dark:border-zinc-800">
            <a href="{{ route('home') }}" aria-label="{{ $isAr ? 'علاف - الرئيسية' : '3allaf home' }}"><x-brand-lockup size="sm" /></a>
            <nav class="flex items-center gap-2" aria-label="{{ $isAr ? 'التنقل' : 'Account navigation' }}">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" wire:navigate>{{ $isAr ? 'فتح لوحة القيادة' : 'Open dashboard' }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="primary" wire:navigate>{{ $isAr ? 'تسجيل الدخول' : 'Sign in' }}</flux:button>
                @endauth
            </nav>
        </header>

        <main class="flex-1">
            <section class="py-16 lg:py-24">
                <p class="text-sm font-semibold tracking-wide text-teal-700 dark:text-teal-300">{{ $isAr ? 'نظام إدارة محل الأعلاف' : 'Feed store management' }}</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-black leading-tight text-balance sm:text-5xl">{{ $isAr ? 'كل عمليات علاف اليومية في مكان واحد' : 'All daily 3allaf operations in one place' }}</h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">{{ $isAr ? 'بيع ومشتريات ومخازن وعملاء وتقارير، بواجهة عربية واضحة وتتبع كامل لكل عملية.' : 'Sales, purchasing, warehouses, customers and reports, with a clear interface and a full trail for every action.' }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" icon="squares-2x2">{{ $isAr ? 'فتح لوحة القيادة' : 'Open dashboard' }}</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="primary" icon="arrow-right-end-on-rectangle">{{ $isAr ? 'تسجيل الدخول' : 'Sign in' }}</flux:button>
                    @endauth
                </div>
            </section>

            <section class="grid gap-4 pb-16 sm:grid-cols-2 lg:grid-cols-3" aria-label="{{ $isAr ? 'المميزات' : 'Features' }}">
                @foreach ($features as $feature)
                    @php [$title, $text] = $isAr ? $feature['ar'] : $feature['en']; @endphp
                    <article class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><flux:icon :name="$feature['icon']" class="size-5" /></span>
                        <h2 class="mt-4 text-lg font-bold">{{ $title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                    </article>
                @endforeach
            </section>
        </main>

        <footer class="flex items-center justify-between border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800">
            <x-brand-lockup size="sm" />
            <span>&copy; {{ date('Y') }} {{ $isAr ? 'علاف' : '3allaf' }}</span>
        </footer>
    </div>
</body>
</html>
