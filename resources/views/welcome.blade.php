<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    @include('partials.head', ['title' => 'راجح ERP | إدارة أعمالك اليوم لبناء غدٍ أقوى'])
    <meta name="description" content="راجح ERP منصة عربية متكاملة لإدارة المبيعات والمشتريات والمخزون ونقاط البيع والفروع.">
</head>
<body class="marketing-page">
    <header class="marketing-header">
        <div class="marketing-shell marketing-header__inner">
            <a href="#top" class="marketing-brand" aria-label="راجح ERP - الصفحة الرئيسية">
                <img src="{{ asset('images/rajeh-logo.png') }}" alt="راجح ERP" width="150" height="65">
            </a>
            <nav class="marketing-nav" aria-label="التنقل الرئيسي">
                <a href="#top">الرئيسية</a><a href="#product">المنتج</a><a href="#features">المميزات</a><a href="#sectors">القطاعات</a><a href="#ai">الذكاء الاصطناعي</a><a href="#roadmap">خارطة الطريق</a><a href="#contact">تواصل معنا</a>
            </nav>
            <div class="marketing-header__actions"><a class="marketing-button marketing-button--small marketing-button--outline marketing-header__login" href="{{ route('login') }}">تسجيل الدخول</a><a class="marketing-button marketing-button--small marketing-button--primary marketing-header__cta" href="mailto:hello@s-eg.com?subject=طلب%20تجربة%20راجح">اطلب تجربة مجانية <span aria-hidden="true">←</span></a></div>
            <button class="marketing-menu-button" type="button" aria-label="فتح القائمة" aria-expanded="false" data-marketing-menu>☰</button>
        </div>
        <nav class="marketing-mobile-nav marketing-shell" aria-label="التنقل للجوال" data-marketing-mobile hidden>
            <a class="marketing-mobile-nav__login" href="{{ route('login') }}">تسجيل الدخول</a><a href="#top">الرئيسية</a><a href="#product">المنتج</a><a href="#features">المميزات</a><a href="#sectors">القطاعات</a><a href="#ai">الذكاء الاصطناعي</a><a href="#roadmap">خارطة الطريق</a><a href="#contact">تواصل معنا</a>
        </nav>
    </header>

    <main id="top">
        <section class="marketing-hero">
            <div class="marketing-shell marketing-hero__grid">
                <div class="marketing-hero__copy">
                    <span class="marketing-eyebrow"><i></i> منظومة أعمال عربية تنمو معك</span>
                    <h1>إدارة أعمالك اليوم<br><strong>لبناء غدٍ أقوى</strong></h1>
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

        <section class="marketing-ai" id="ai"><div class="marketing-shell marketing-ai__inner"><div class="marketing-ai__copy"><span class="marketing-eyebrow marketing-eyebrow--light"><i></i> ذكاء يفهم أعمالك</span><h2>راجح أذكى<br><strong>مع كل قرار</strong></h2><p>مراجعة أعمق وقرارات أسرع، بذكاء اصطناعي مصمم ليفهم سياق أعمالك.</p><a class="marketing-button marketing-button--white" href="mailto:hello@s-eg.com?subject=استفسار%20عن%20ذكاء%20راجح">تعرّف على الذكاء الاصطناعي <span>←</span></a></div><div class="marketing-ai__cards"><article><span class="marketing-ai__badge">متاح الآن</span><b>✦</b><h3>المراجعة والتدقيق الذكي</h3><p>اكتشف التناقضات والبيانات التي تحتاج إلى مراجعة قبل أن تتحول إلى مشكلة.</p></article><article><span class="marketing-ai__badge">متاح الآن</span><b>⌕</b><h3>المساعد الذكي</h3><p>اطرح أسئلتك بلغة طبيعية واحصل على رؤى عملية من بيانات منظومتك.</p></article><article class="is-soon"><span class="marketing-ai__badge">قريباً</span><b>♩</b><h3>الأوامر الصوتية</h3><p>تفاعل مع راجح بالصوت لتنفيذ النوايا والمهام اليومية بسرعة.</p></article></div></div></section>

        <section class="marketing-section marketing-section--soft" id="product"><div class="marketing-shell marketing-why"><div class="marketing-section-heading"><span class="marketing-eyebrow"><i></i> لماذا راجح؟</span><h2>تقنية واضحة.<br><strong>أثر ملموس.</strong></h2></div><div class="marketing-benefit-grid"><article><b>١</b><h3>مصمم بالعربية</h3><p>تجربة عربية أصلية، من الواجهة إلى التقارير.</p></article><article><b>٢</b><h3>أمان وحوكمة</h3><p>صلاحيات، موافقات وسجل تدقيق يحمي قراراتك.</p></article><article><b>٣</b><h3>ينمو معك</h3><p>من متجر واحد إلى شبكة فروع، دون تعقيد.</p></article><article><b>٤</b><h3>دعم يفهم السوق</h3><p>حلول عملية ودعم محلي قريب من احتياجك.</p></article></div></div></section>

        <section class="marketing-section" id="roadmap"><div class="marketing-shell"><div class="marketing-section-heading marketing-section-heading--center"><span class="marketing-eyebrow"><i></i> إلى أين نتجه؟</span><h2>اليوم، <strong>وغداً</strong></h2><p>نطور راجح بخطوات مدروسة، ونحافظ على وعد واضح لعملائنا.</p></div><div class="marketing-roadmap"><div><span class="marketing-roadmap__label marketing-roadmap__label--available">متاح الآن</span><h3>أساس قوي لإدارة يومك</h3><p>المبيعات والمشتريات والمخزون ونقاط البيع، مع التقارير والموافقات وإدارة الفروع.</p><ul><li>لوحة تحكم وتقارير تشغيلية</li><li>صلاحيات ومراجعة واعتماد</li><li>عملاء وموردون وفروع ومتاجر</li></ul></div><div><span class="marketing-roadmap__label marketing-roadmap__label--soon">قريباً</span><h3>ذكاء أوسع، وتواصل أسرع</h3><p>نواصل توسيع تجربة الذكاء الاصطناعي والتفاعل الصوتي وفق احتياج أعمالك.</p><ul><li>الأوامر الصوتية باللغة العربية</li><li>رؤى تنبؤية أعمق</li><li>تكاملات أعمال إضافية</li></ul></div></div></div></section>

        <section class="marketing-cta" id="contact"><div class="marketing-shell marketing-cta__inner"><span class="marketing-eyebrow marketing-eyebrow--light"><i></i> ابدأ الخطوة التالية</span><h2>جاهز تدير أعمالك<br><strong>بطريقة أذكى؟</strong></h2><p>تواصل معنا لنعرّفك على راجح ونناقش ما تحتاجه أعمالك.</p><div class="marketing-actions"><a class="marketing-button marketing-button--white" href="mailto:hello@s-eg.com?subject=طلب%20تجربة%20راجح">اطلب تجربة مجانية <span>←</span></a><a class="marketing-button marketing-button--outline" href="tel:01116888073">تواصل مع المبيعات</a></div></div></section>
    </main>
    <footer class="marketing-footer"><div class="marketing-shell marketing-footer__inner"><div><img src="{{ asset('images/rajeh-logo.png') }}" alt="راجح ERP" width="135" height="59"><p>إدارة أعمالك اليوم لبناء غدٍ أقوى.</p></div><div class="marketing-footer__links"><a href="#features">المميزات</a><a href="#ai">الذكاء الاصطناعي</a><a href="#roadmap">خارطة الطريق</a><a href="mailto:hello@s-eg.com">hello@s-eg.com</a></div><div class="marketing-footer__contact"><strong>راجح ERP</strong><span>تطوير I.S. Intelligent Solutions</span><a href="https://s-eg.com" target="_blank" rel="noreferrer">s-eg.com</a><a href="tel:01116888073">01116888073</a></div></div><div class="marketing-shell marketing-footer__bottom"><span>© {{ date('Y') }} راجح ERP. جميع الحقوق محفوظة.</span><span>صنع بعناية في مصر</span></div></footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => { const button = document.querySelector('[data-marketing-menu]'); const menu = document.querySelector('[data-marketing-mobile]'); if (!button || !menu) return; button.addEventListener('click', () => { const open = button.getAttribute('aria-expanded') === 'true'; button.setAttribute('aria-expanded', String(!open)); menu.hidden = open; }); menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => { button.setAttribute('aria-expanded', 'false'); menu.hidden = true; })); });
    </script>
</body>
</html>
