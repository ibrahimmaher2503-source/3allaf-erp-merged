<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-5 sm:px-8 lg:px-12">
            <header class="flex min-h-20 items-center justify-between border-b border-zinc-200 dark:border-zinc-800">
                <a href="{{ route('home') }}" aria-label="{{ __('3allaf home') }}" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-teal-700 text-white">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-bold tracking-wide">{{ config('app.name', '3allaf | علاف') }}</span>
                </a>

                <nav aria-label="{{ __('Account navigation') }}" class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                            {{ __('Open dashboard') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" wire:navigate>
                            {{ __('Sign in') }}
                        </flux:button>

                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" wire:navigate>
                                {{ __('Create account') }}
                            </flux:button>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="grid flex-1 items-center gap-14 py-16 lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)] lg:py-24">
                <section class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-teal-700 dark:text-teal-300">
                        {{ __('3allaf operations') }}
                    </p>
                    <h1 class="mt-5 text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        {{ __('One dependable workspace for daily 3allaf operations.') }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">
                        {{ __('A browser-based operational platform for retail, inventory, customers, and point of sale, built with clear controls and traceable workflows.') }}
                    </p>

    <main id="top">
        <section class="marketing-hero">
            <div class="marketing-shell marketing-hero__grid">
                <div class="marketing-hero__copy">
                    <span class="marketing-eyebrow"><i></i> منظومة أعمال عربية تنمو معك</span>
                    <h1><span>إدارة أعمالك اليوم</span><strong>لبناء غدٍ أقوى</strong></h1>
                    <p>راجح ERP يمنحك رؤية موحّدة وتحكماً أوضح في المبيعات، المخزون، المشتريات والفروع، لتتخذ القرار المناسب في الوقت المناسب.</p>
                    <div class="marketing-actions"><a class="marketing-button marketing-button--primary" href="mailto:hello@s-eg.com?subject=طلب%20عرض%20راجح">اطلب عرضاً توضيحياً <span aria-hidden="true">←</span></a><a class="marketing-button marketing-button--light" href="#product"><span class="marketing-play" aria-hidden="true">▶</span> اكتشف راجح</a></div>
                    <div class="marketing-trust"><span><b>✓</b> عربي من البداية</span><span><b>✓</b> فروع ومتاجر متعددة</span><span><b>✓</b> صلاحيات وموافقات واضحة</span></div>
                </div>
                <div class="marketing-dashboard" aria-label="معاينة لوحة تحكم راجح">
                    <div class="marketing-dashboard__bar"><span class="marketing-dashboard__dots">● ● ●</span><span>لوحة تحكم راجح</span><span class="marketing-dashboard__bell">♧</span></div>
                    <div class="marketing-dashboard__body"><aside><span class="marketing-dashboard__mini-logo">ر</span><span class="is-active">▦</span><span>▤</span><span>◫</span><span>⚙</span></aside><div class="marketing-dashboard__content"><div class="marketing-dashboard__welcome"><span><small>مرحباً بك في راجح</small><strong>صباح الخير، أحمد 👋</strong></span><span class="marketing-avatar">أ</span></div><div class="marketing-stat-grid"><div><small>إجمالي المبيعات</small><strong>١٢٤,٨٥٠ <em>ر.س</em></strong><b class="up">↑ ١٨.٤٪</b></div><div><small>الطلبات الجديدة</small><strong>١,٢٨٤</strong><b class="up">↑ ١٢.٦٪</b></div><div><small>الأصناف منخفضة المخزون</small><strong>٢٣</strong><b class="down">تحتاج متابعة</b></div></div><div class="marketing-chart-row"><div class="marketing-chart"><div class="marketing-chart__head"><strong>نظرة عامة على المبيعات</strong><small>آخر ٧ أيام⌄</small></div><div class="marketing-chart__line"></div><div class="marketing-chart__labels"><span>السبت</span><span>الأحد</span><span>الإثنين</span><span>الثلاثاء</span><span>الأربعاء</span><span>الخميس</span><span>الجمعة</span></div></div><div class="marketing-donut"><div class="marketing-donut__ring"></div><strong>٤٢٪</strong><small>نمو هذا الشهر</small></div></div><div class="marketing-dashboard__table"><strong>آخر العمليات</strong><span>عرض الكل ←</span><div><b>فاتورة مبيعات #١٢٨٤</b><i>مكتملة</i><b>١,٢٥٠ ر.س</b></div><div><b>طلب شراء #٠٥٦٢</b><i class="pending">قيد المراجعة</i><b>٤,٨٠٠ ر.س</b></div></div></div></div>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="features"><div class="marketing-shell"><div class="marketing-section-heading"><span class="marketing-eyebrow"><i></i> كل ما تحتاجه في مكان واحد</span><h2>منظومة متكاملة،<br><strong>ببساطة راجح</strong></h2><p>أدوات مترابطة تساعد فريقك على العمل بكفاءة، وتحافظ على تفاصيل أعمالك في صورة واحدة واضحة.</p></div><div class="marketing-feature-grid">
            @foreach ([['▣','المبيعات','من عروض الأسعار إلى الفاتورة، دورة مبيعات واضحة وسريعة.'],['◫','المشتريات','تابع الموردين وأوامر الشراء والاستلام في مسار واحد.'],['▤','المخزون','رؤية لحظية للأصناف، الحركات، التحويلات والتنبيهات.'],['▥','نقاط البيع','تجربة بيع مرنة مع الورديات، المدفوعات والمرتجعات.'],['◌','التقارير والتحليلات','حوّل الأرقام إلى قرارات بتقارير قابلة للتنفيذ.'],['✓','الموافقات','حوكمة واضحة للمراجعة والاعتماد في كل خطوة.'],['⌂','الفروع والمتاجر','إدارة متعددة الفروع والمتاجر بصلاحيات دقيقة.'],['♙','العملاء والموردون','ملف موحد للعلاقات والبيانات التجارية.']] as $feature)<article class="marketing-feature-card"><span class="marketing-feature-card__icon">{{ $feature[0] }}</span><h3>{{ $feature[1] }}</h3><p>{{ $feature[2] }}</p><span class="marketing-feature-card__arrow" aria-hidden="true">←</span></article>@endforeach
        </div></div></section>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ([
                            __('Daily retail sales and payments'),
                            __('Inventory and purchasing visibility'),
                            __('Customer and inventory workflows'),
                            __('Arabic RTL and English LTR direction support'),
                        ] as $item)
                            <li class="flex items-start gap-3 px-6 py-4 text-sm text-zinc-700 dark:text-zinc-200">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-teal-600" aria-hidden="true"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>

        <section class="marketing-section marketing-section--soft" id="product"><div class="marketing-shell marketing-why"><div class="marketing-section-heading"><span class="marketing-eyebrow"><i></i> لماذا راجح؟</span><h2>تقنية واضحة.<br><strong>أثر ملموس.</strong></h2></div><div class="marketing-benefit-grid"><article><b>١</b><h3>مصمم بالعربية</h3><p>تجربة عربية أصلية، من الواجهة إلى التقارير.</p></article><article><b>٢</b><h3>أمان وحوكمة</h3><p>صلاحيات، موافقات وسجل تدقيق يحمي قراراتك.</p></article><article><b>٣</b><h3>ينمو معك</h3><p>من متجر واحد إلى شبكة فروع، دون تعقيد.</p></article><article><b>٤</b><h3>دعم يفهم السوق</h3><p>حلول عملية ودعم محلي قريب من احتياجك.</p></article></div></div></section>

            <footer class="border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                {{ __('3allaf operational workspace') }}
            </footer>
        </div>

        <section class="marketing-cta" id="contact"><div class="marketing-shell marketing-cta__inner"><span class="marketing-eyebrow marketing-eyebrow--light"><i></i> ابدأ الخطوة التالية</span><h2>جاهز تدير أعمالك<br><strong>بطريقة أذكى؟</strong></h2><p>تواصل معنا لنعرّفك على راجح ونناقش ما تحتاجه أعمالك.</p><div class="marketing-actions"><a class="marketing-button marketing-button--white" href="mailto:hello@s-eg.com?subject=طلب%20تجربة%20راجح">اطلب تجربة مجانية <span>←</span></a><a class="marketing-button marketing-button--outline" href="tel:01116888073">تواصل مع المبيعات</a></div></div></section>
    </main>
    <footer class="marketing-footer"><div class="marketing-shell marketing-footer__inner"><div><img src="{{ asset('images/rajeh-logo.png') }}" alt="راجح ERP" width="135" height="59"><p>إدارة أعمالك اليوم لبناء غدٍ أقوى.</p></div><div class="marketing-footer__links"><a href="#features">المميزات</a><a href="#ai">الذكاء الاصطناعي</a><a href="#roadmap">خارطة الطريق</a><a href="mailto:hello@s-eg.com">hello@s-eg.com</a></div><div class="marketing-footer__contact"><strong>راجح ERP</strong><span>تطوير I.S. Intelligent Solutions</span><a href="https://s-eg.com" target="_blank" rel="noreferrer">s-eg.com</a><a href="tel:01116888073">01116888073</a></div></div><div class="marketing-shell marketing-footer__bottom"><span>© {{ date('Y') }} راجح ERP. جميع الحقوق محفوظة.</span><span>صنع بعناية في مصر</span></div></footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.querySelector('[data-marketing-menu]');
            const menu = document.querySelector('[data-marketing-mobile]');
            if (!button || !menu) return;
            const closeMenu = () => { button.setAttribute('aria-expanded', 'false'); menu.hidden = true; };
            button.addEventListener('click', () => {
                const open = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!open));
                menu.hidden = open;
            });
            menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeMenu(); button.focus(); } });
            document.addEventListener('click', (event) => { if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) closeMenu(); });
        });
    </script>
</body>
</html>
