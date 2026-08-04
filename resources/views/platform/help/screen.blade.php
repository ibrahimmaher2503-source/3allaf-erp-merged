@php
    $locale = app()->getLocale();
    $text = fn ($value): string => is_array($value) ? ($value[$locale] ?? $value['en'] ?? $value['ar'] ?? '') : (string) $value;
    $title = $text($context['title']);
    $flowId = $context['flows'][0] ?? null;
@endphp

<x-layouts::app :title="$title">
    <main class="guide-page" aria-labelledby="guide-page-title">
        <div class="guide-page__topbar">
            <a class="guide-page__back" href="{{ url()->previous() }}">
                <span aria-hidden="true">{{ $locale === 'ar' ? '→' : '←' }}</span>
                {{ $locale === 'ar' ? 'العودة إلى الشاشة' : 'Back to screen' }}
            </a>
            <span class="guide-page__kicker">{{ $locale === 'ar' ? 'مركز المساعدة' : 'Help center' }}</span>
        </div>

        <section class="guide-page__hero">
            <div class="guide-page__hero-glow" aria-hidden="true"></div>
            <div class="guide-page__hero-copy">
                <div class="guide-page__eyebrow-row">
                    <span class="guide-page__screen-id">{{ $context['screen_id'] }}</span>
                    <span class="guide-page__published"><span aria-hidden="true">●</span> {{ $locale === 'ar' ? 'دليل منشور' : 'Published guide' }}</span>
                </div>
                <h1 id="guide-page-title">{{ $title }}</h1>
                <p>{{ $text($context['purpose']) }}</p>
            </div>
            <div class="guide-page__hero-meta">
                <span>{{ $locale === 'ar' ? 'آخر تحديث' : 'Updated' }}</span>
                <strong>{{ $context['updated_at'] }}</strong>
                <small>{{ $locale === 'ar' ? 'الإصدار' : 'Version' }} {{ $context['version'] }}</small>
            </div>
        </section>

        <div class="guide-page__layout">
            <div class="guide-page__content">
                <section class="guide-card guide-card--intro">
                    <div class="guide-card__heading"><span class="guide-card__icon">◎</span><div><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'السياق' : 'CONTEXT' }}</p><h2>{{ $locale === 'ar' ? 'متى تستخدم هذه الصفحة؟' : 'When should you use this page?' }}</h2></div></div>
                    <p>{{ $text($context['when_to_use']) }}</p>
                </section>

                <section id="guide-steps" class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--blue">01</span><div><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'التنفيذ' : 'EXECUTION' }}</p><h2>{{ $locale === 'ar' ? 'خطوات الاستخدام' : 'Step-by-step usage' }}</h2></div></div>
                    <ol class="guide-steps">
                        @foreach($context['sections']['steps'] as $step)
                            <li><span class="guide-steps__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $text($step['title']) }}</h3><p>{{ $text($step['body']) }}</p></div></li>
                        @endforeach
                    </ol>
                </section>

                <section class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--purple">02</span><div><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'التفاصيل' : 'DETAILS' }}</p><h2>{{ $locale === 'ar' ? 'شرح الحقول' : 'Field explanations' }}</h2></div></div>
                    <div class="guide-fields">
                        @foreach($context['sections']['fields'] as $field)
                            <div class="guide-field"><strong>{{ $text($field['title']) }}</strong><p>{{ $text($field['body']) }}</p></div>
                        @endforeach
                    </div>
                </section>

                <div class="guide-page__split">
                    <section class="guide-card guide-card--note"><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'ملاحظة مهمة' : 'IMPORTANT NOTE' }}</p><h2>{{ $locale === 'ar' ? 'اعمل ضمن نطاقك' : 'Work within your scope' }}</h2><p>{{ $text($context['sections']['notes']) }}</p></section>
                    <section class="guide-card guide-card--warning"><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'تنبيه' : 'WATCH FOR' }}</p><h2>{{ $locale === 'ar' ? 'قبل المتابعة' : 'Before you continue' }}</h2><p>{{ $text($context['sections']['warnings']) }}</p></section>
                </div>

                <section class="guide-card guide-card--next">
                    <div><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'الخطوة التالية' : 'NEXT STEP' }}</p><h2>{{ $text($context['sections']['next_step']) }}</h2><p>{{ $text($context['sections']['faq']) }}</p></div>
                    @if($flowId)<a class="guide-page__flow-link" href="{{ route('platform.help.flow', $flowId) }}">{{ $locale === 'ar' ? 'فتح تدفق المستخدم' : 'Open user flow' }} <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span></a>@endif
                </section>
            </div>

            <aside class="guide-page__aside" aria-label="{{ $locale === 'ar' ? 'ملخص الدليل' : 'Guide summary' }}">
                <section class="guide-summary-card">
                    <p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'في هذه الصفحة' : 'ON THIS PAGE' }}</p>
                    <nav><a href="#guide-page-title">{{ $locale === 'ar' ? 'نظرة عامة' : 'Overview' }}</a><a href="#guide-steps">{{ $locale === 'ar' ? 'الخطوات' : 'Steps' }}</a><a href="#guide-actions">{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</a></nav>
                </section>
                <section id="guide-actions" class="guide-summary-card guide-summary-card--actions">
                    <p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'ما يمكنك فعله' : 'WHAT YOU CAN DO' }}</p>
                    <ul>@forelse($context['approved_actions'] as $action)<li><span aria-hidden="true">✓</span>{{ $text($action['label']) }}</li>@empty<li>{{ $locale === 'ar' ? 'لا توجد إجراءات متاحة.' : 'No actions are available.' }}</li>@endforelse</ul>
                </section>
                <section class="guide-summary-card guide-summary-card--tour"><span class="guide-summary-card__spark" aria-hidden="true">✦</span><div><p class="guide-card__eyebrow">{{ $locale === 'ar' ? 'تعلم تفاعلي' : 'INTERACTIVE LEARNING' }}</p><h2>{{ $locale === 'ar' ? 'تحتاج إلى جولة؟' : 'Need a walkthrough?' }}</h2><p>{{ $locale === 'ar' ? 'ابدأ الجولة من دليل الصفحة عندما تكون أهداف الجولة متاحة على الشاشة.' : 'Start the tour from the Page Guide when its targets are available on the screen.' }}</p></div></section>
            </aside>
        </div>
    </main>
</x-layouts::app>
