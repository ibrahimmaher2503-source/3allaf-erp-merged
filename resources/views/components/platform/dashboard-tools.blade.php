@php
    use App\Modules\Platform\Data\PageGuideContext;

    $pageGuideContext = $pageGuide ?? PageGuideContext::fromRequest(auth()->user());
    $guide = $pageGuideContext?->toArray();
    $locale = app()->getLocale();
    $isArabic = $locale === 'ar';
    $fallback = $isArabic
        ? 'لم يتم نشر دليل تفصيلي لهذه الصفحة بعد. يمكنك استخدام الصفحة وفق صلاحياتك الحالية.'
        : 'A detailed guide has not been published for this page yet. You can still use the page according to your current permissions.';
@endphp

<div
    x-data="dashboardAssistant({ context: @js($guide), locale: @js($locale), fallback: @js($fallback), preferences: window.__toyJoyUiPreferences || {} })"
    x-on:keydown.escape.window="closeAll()"
    class="platform-assistant"
>
    <div class="platform-assistant__desktop-launchers" aria-label="{{ $isArabic ? 'أدوات الصفحة' : 'Page tools' }}">
        <button type="button" class="platform-assistant__launcher" x-on:click="openGuide($event)" aria-controls="page-guide-drawer" aria-label="{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}" data-guide="page-guide-launcher">
            <span aria-hidden="true">?</span><span class="sr-only">{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</span>
        </button>
        <button type="button" class="platform-assistant__launcher platform-assistant__launcher--appearance" x-on:click="openCustomizer($event)" aria-controls="appearance-customizer" aria-label="{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}">
            <span aria-hidden="true">✦</span><span class="sr-only">{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}</span>
        </button>
    </div>

    <button type="button" class="platform-assistant__mobile-launcher" x-on:click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-dashboard-tools" aria-label="{{ $isArabic ? 'أدوات لوحة التحكم' : 'Dashboard tools' }}">
        <span aria-hidden="true">✦</span>
    </button>
    <div id="mobile-dashboard-tools" x-cloak x-show="mobileMenuOpen" x-transition class="platform-assistant__mobile-menu">
        <button type="button" x-on:click="mobileMenuOpen = false; openGuide($event)" class="platform-assistant__menu-action">? {{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</button>
        <button type="button" x-on:click="mobileMenuOpen = false; openCustomizer($event)" class="platform-assistant__menu-action">✦ {{ $isArabic ? 'تخصيص المظهر' : 'Customize appearance' }}</button>
    </div>

    <div x-cloak x-show="guideOpen" x-transition.opacity class="platform-assistant__backdrop" x-on:click="closeGuide()"></div>
    <aside id="page-guide-drawer" x-cloak x-show="guideOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100" class="platform-assistant__drawer" role="dialog" aria-modal="true" aria-labelledby="page-guide-title" x-trap.noscroll="guideOpen" x-ref="guideDrawer">
        <header class="platform-assistant__header">
            <div>
                <p class="platform-assistant__eyebrow">{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</p>
                <h2 id="page-guide-title" class="platform-assistant__title" x-text="text(context?.title) || (locale === 'ar' ? 'دليل الصفحة' : 'Page Guide')"></h2>
                <p class="platform-assistant__screen" x-show="context?.screen_id" x-text="context?.screen_id"></p>
            </div>
            <button type="button" class="platform-assistant__close" x-on:click="closeGuide()" aria-label="{{ $isArabic ? 'إغلاق' : 'Close' }}">×</button>
        </header>

        <div class="platform-assistant__body">
            <template x-if="context">
                <div class="space-y-5">
                    <section><h3>{{ $isArabic ? 'ماذا تفعل هذه الصفحة؟' : 'What this page does' }}</h3><p x-text="text(context.purpose)"></p></section>
                    <section><h3>{{ $isArabic ? 'متى تستخدمها؟' : 'When to use it' }}</h3><p x-text="text(context.when_to_use)"></p></section>
                    <section><h3>{{ $isArabic ? 'ما يمكنك فعله' : 'What you can do here' }}</h3><ul class="platform-assistant__list"><template x-for="action in context.approved_actions" :key="action.key"><li x-text="text(action.label)"></li></template></ul><p x-show="!context.approved_actions?.length" class="platform-assistant__muted">{{ $isArabic ? 'لا توجد إجراءات متاحة لهذا المستخدم هنا.' : 'No actions are available for this user here.' }}</p></section>
                    <section><h3>{{ $isArabic ? 'خطوات الاستخدام' : 'Step-by-step usage' }}</h3><ol class="platform-assistant__steps"><template x-for="step in context.sections.steps" :key="step.key"><li><strong x-text="text(step.title)"></strong><span x-text="text(step.body)"></span></li></template></ol></section>
                    <details open><summary>{{ $isArabic ? 'شرح الحقول' : 'Field explanations' }}</summary><template x-for="field in context.sections.fields" :key="field.key"><p class="mt-3" x-text="text(field.body)"></p></template></details>
                    <details><summary>{{ $isArabic ? 'ملاحظات مهمة' : 'Important notes' }}</summary><p class="mt-3" x-text="text(context.sections.notes)"></p></details>
                    <details><summary>{{ $isArabic ? 'تحذيرات وأخطاء شائعة' : 'Warnings and common errors' }}</summary><p class="mt-3" x-text="text(context.sections.warnings)"></p><p class="mt-2" x-text="text(context.sections.errors)"></p></details>
                    <section><h3>{{ $isArabic ? 'الخطوة التالية' : 'Next step' }}</h3><p x-text="text(context.sections.next_step)"></p></section>
                    <details><summary>{{ $isArabic ? 'الأسئلة الشائعة' : 'Frequently asked questions' }}</summary><p class="mt-3" x-text="text(context.sections.faq)"></p></details>
                    <section class="platform-assistant__meta"><span>{{ $isArabic ? 'الإصدار' : 'Version' }} <b x-text="context.version"></b></span><span>{{ $isArabic ? 'آخر تحديث' : 'Updated' }} <b x-text="context.updated_at"></b></span></section>
                </div>
            </template>
            <template x-if="!context"><p class="platform-assistant__fallback" x-text="fallback"></p></template>
        </div>

        <footer class="platform-assistant__footer">
            <button type="button" class="platform-assistant__primary" x-show="context?.tour_steps?.length" x-on:click="startTour()">{{ $isArabic ? 'ابدأ الجولة التفاعلية' : 'Start Interactive Tour' }}</button>
            <a x-show="context?.screen_id" :href="context?.screen_id ? '{{ url('/help/screens') }}/' + context.screen_id : '#'" class="platform-assistant__secondary">{{ $isArabic ? 'فتح الدليل الكامل' : 'Open Full Guide' }}</a>
            <a x-show="context?.flows?.length" :href="context?.flows?.length ? '{{ url('/help/flows') }}/' + context.flows[0] : '#'" class="platform-assistant__secondary">{{ $isArabic ? 'فتح تدفق المستخدم' : 'Open User Flow' }}</a>
        </footer>
    </aside>

    <div x-cloak x-show="customizerOpen" x-transition.opacity class="platform-assistant__backdrop" x-on:click="closeCustomizer()"></div>
    <aside id="appearance-customizer" x-cloak x-show="customizerOpen" x-transition class="platform-assistant__drawer platform-assistant__drawer--customizer" role="dialog" aria-modal="true" aria-labelledby="appearance-title" x-trap.noscroll="customizerOpen">
        <header class="platform-assistant__header"><div><p class="platform-assistant__eyebrow">{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}</p><h2 id="appearance-title" class="platform-assistant__title">{{ $isArabic ? 'إعدادات العرض' : 'Display settings' }}</h2></div><button type="button" class="platform-assistant__close" x-on:click="closeCustomizer()" aria-label="{{ $isArabic ? 'إغلاق' : 'Close' }}">×</button></header>
        <div class="platform-assistant__body platform-assistant__controls">
            <label>{{ $isArabic ? 'المظهر' : 'Appearance' }}<select x-model="preferences.appearance" x-on:change="applyPreferences(); savePreferences()"><option value="system">{{ $isArabic ? 'النظام' : 'System' }}</option><option value="light">{{ $isArabic ? 'فاتح' : 'Light' }}</option><option value="dark">{{ $isArabic ? 'داكن' : 'Dark' }}</option></select></label>
            <label>{{ $isArabic ? 'اللون المميز' : 'Accent color' }}<select x-model="preferences.accent_color" x-on:change="applyPreferences(); savePreferences()"><option value="teal">Teal</option><option value="indigo">Indigo</option><option value="amber">Amber</option><option value="rose">Rose</option></select></label>
            <label>{{ $isArabic ? 'الشريط الجانبي' : 'Sidebar' }}<select x-model="preferences.sidebar_mode" x-on:change="applyPreferences(); savePreferences()"><option value="expanded">{{ $isArabic ? 'موسع' : 'Expanded' }}</option><option value="collapsed">{{ $isArabic ? 'مطوي' : 'Collapsed' }}</option></select></label>
            <label>{{ $isArabic ? 'عرض المحتوى' : 'Content width' }}<select x-model="preferences.content_width" x-on:change="applyPreferences(); savePreferences()"><option value="wide">{{ $isArabic ? 'واسع' : 'Wide' }}</option><option value="compact">{{ $isArabic ? 'مضغوط' : 'Compact' }}</option></select></label>
            <label>{{ $isArabic ? 'كثافة الجداول' : 'Table density' }}<select x-model="preferences.table_density" x-on:change="applyPreferences(); savePreferences()"><option value="comfortable">{{ $isArabic ? 'مريح' : 'Comfortable' }}</option><option value="compact">{{ $isArabic ? 'مضغوط' : 'Compact' }}</option></select></label>
            <label>{{ $isArabic ? 'حجم الخط' : 'Font scale' }}<select x-model="preferences.font_scale" x-on:change="applyPreferences(); savePreferences()"><option value="small">{{ $isArabic ? 'صغير' : 'Small' }}</option><option value="normal">{{ $isArabic ? 'عادي' : 'Normal' }}</option><option value="large">{{ $isArabic ? 'كبير' : 'Large' }}</option></select></label>
            <label class="platform-assistant__check"><input type="checkbox" x-model="preferences.reduced_motion" x-on:change="applyPreferences(); savePreferences()"> {{ $isArabic ? 'تقليل الحركة' : 'Reduced motion' }}</label>
            <label class="platform-assistant__check items-start">
                <input type="checkbox" x-model="darkSidebar" x-on:change="updateDarkSidebar()" class="mt-0.5 shrink-0">
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-semibold text-text-primary">{{ $isArabic ? 'شريط جانبي وخلفية داكنان' : 'Dark sidebar/background' }}</span>
                    <span class="text-xs font-normal text-text-muted">{{ $isArabic ? 'يغير الشريط الجانبي وخلفية التطبيق بشكل مستقل عن المظهر الفاتح/الداكن.' : 'Changes the sidebar and app background independently of light/dark appearance.' }}</span>
                </span>
            </label>
            <button type="button" class="platform-assistant__secondary" x-on:click="resetPreferences()">{{ $isArabic ? 'إعادة الإعدادات الافتراضية' : 'Reset to defaults' }}</button>
            <p class="platform-assistant__save-status" x-show="saveStatus !== 'idle'" x-cloak role="status" aria-live="polite" :class="{ 'platform-assistant__save-status--error': saveStatus === 'error' }" x-text="saveStatusText()"></p>
        </div>
    </aside>

    <div x-cloak x-show="tour.active" x-transition.opacity class="platform-assistant__tour-backdrop" x-on:click="finishTour()"></div>
    <div
        x-cloak
        x-show="tour.active"
        x-transition
        x-ref="tourCard"
        class="platform-assistant__tour"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tour-title"
        x-trap.noscroll="tour.active"
        :style="tourCardStyle"
    >
        <p class="platform-assistant__eyebrow">{{ $isArabic ? 'جولة إرشادية' : 'Guided tour' }}</p>
        <h2 id="tour-title" x-text="text(tour.step?.title)"></h2>
        <p x-text="text(tour.step?.body)"></p>
        <p class="platform-assistant__muted" x-text="tour.visibleIndex + ' / ' + tour.steps.length"></p>
        <div class="platform-assistant__tour-actions">
            <button type="button" x-on:click="previousTour()" :disabled="tour.visibleIndex <= 1">{{ $isArabic ? 'السابق' : 'Previous' }}</button>
            <button type="button" x-on:click="skipTour()">{{ $isArabic ? 'تخطي' : 'Skip' }}</button>
            <button type="button" class="platform-assistant__primary" x-on:click="nextTour()" x-text="tour.visibleIndex === tour.steps.length ? (locale === 'ar' ? 'إنهاء' : 'Finish') : (locale === 'ar' ? 'التالي' : 'Next')"></button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dashboardAssistant', ({ context, locale, fallback, preferences }) => ({
            context, locale, fallback,
            defaults: { appearance: 'system', accent_color: 'teal', sidebar_mode: 'expanded', navbar_mode: 'sticky', content_width: 'wide', table_density: 'comfortable', font_scale: 'normal', reduced_motion: false },
            preferences: {},
            darkSidebar: false,
            guideOpen: false, customizerOpen: false, mobileMenuOpen: false,
            saveStatus: 'idle',
            launcherFocus: null,
            tour: { active: false, steps: [], step: null, visibleIndex: 0, lastFocus: null, currentElement: null, savedStyle: null, cardPosition: null, scrollAdjusting: false },
            _tourScrollHandler: null,
            init() {
                let initialDark = false;
                try {
                    initialDark = localStorage.getItem('toyjoy_ui_dark_sidebar') === 'true';
                } catch (e) {
                    initialDark = document.documentElement.dataset.darkSidebar === 'true';
                }
                this.darkSidebar = initialDark;
                this.preferences = this.normalizePreferences(preferences);
                this.applyPreferences();
            },
            normalizePreferences(value = {}) {
                const merged = { ...this.defaults, ...(value || {}) };
                merged.reduced_motion = merged.reduced_motion === true || merged.reduced_motion === 1 || merged.reduced_motion === '1' || merged.reduced_motion === 'true';
                return merged;
            },
            saveStatusText() {
                return { saving: locale === 'ar' ? 'جارٍ حفظ إعدادات العرض…' : 'Saving display settings…', saved: locale === 'ar' ? 'تم حفظ إعدادات العرض' : 'Display settings saved', error: locale === 'ar' ? 'تعذر حفظ الإعدادات، لكن تم تطبيقها محلياً' : 'Could not save settings; they remain applied locally' }[this.saveStatus] || '';
            },
            text(value) {
                if (!value) return '';
                if (typeof value === 'string') return value;
                if (Array.isArray(value)) return value.map(v => this.text(v)).filter(Boolean).join(' ');
                if (typeof value === 'object') {
                    const val = value[this.locale] || value.en || value.ar;
                    if (typeof val === 'string') return val;
                    if (Array.isArray(val)) return val.map(v => this.text(v)).filter(Boolean).join(' ');
                    if (val) return String(val);
                }
                return String(value);
            },
            openGuide(event) {
                if (event && event.currentTarget) {
                    this.launcherFocus = event.currentTarget;
                } else if (!this.launcherFocus) {
                    this.launcherFocus = document.activeElement;
                }
                this.customizerOpen = false;
                this.guideOpen = true;
            },
            closeGuide() {
                this.guideOpen = false;
                this.restoreLauncherFocus();
            },
            openCustomizer(event) {
                if (event && event.currentTarget) {
                    this.launcherFocus = event.currentTarget;
                } else if (!this.launcherFocus) {
                    this.launcherFocus = document.activeElement;
                }
                this.guideOpen = false;
                this.customizerOpen = true;
            },
            closeCustomizer() {
                this.customizerOpen = false;
                this.restoreLauncherFocus();
            },
            closeAll() {
                const hadOpen = this.guideOpen || this.customizerOpen || this.tour.active || this.mobileMenuOpen;
                this.guideOpen = false;
                this.customizerOpen = false;
                this.mobileMenuOpen = false;
                if (this.tour.active) {
                    this.finishTour();
                } else if (hadOpen) {
                    this.restoreLauncherFocus();
                }
            },
            restoreLauncherFocus() {
                if (this.launcherFocus && typeof this.launcherFocus.focus === 'function' && this.launcherFocus.isConnected) {
                    this.launcherFocus.focus();
                }
                this.launcherFocus = null;
            },
            applyPreferences() {
                const root = document.documentElement;
                const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
                const appearance = this.preferences.appearance === 'system' ? (systemDark ? 'dark' : 'light') : this.preferences.appearance;
                root.classList.toggle('dark', appearance === 'dark');
                root.style.colorScheme = appearance;
                root.dataset.appearance = this.preferences.appearance;
                root.dataset.accent = this.preferences.accent_color;
                root.dataset.sidebarMode = this.preferences.sidebar_mode;
                root.dataset.contentWidth = this.preferences.content_width;
                root.dataset.tableDensity = this.preferences.table_density;
                root.dataset.fontScale = this.preferences.font_scale;
                root.dataset.reducedMotion = this.preferences.reduced_motion ? 'true' : 'false';
                root.dataset.darkSidebar = this.darkSidebar ? 'true' : 'false';
                const syncSidebar = () => {
                    const body = document.body;
                    if (body) body.style.gridTemplateColumns = '';
                    const sidebar = document.querySelector('.app-sidebar');
                    if (!sidebar) return;
                    if (this.preferences.sidebar_mode === 'collapsed') sidebar.setAttribute('data-flux-sidebar-collapsed-desktop', '');
                    else sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
                };
                syncSidebar();
                window.requestAnimationFrame?.(syncSidebar);
            },
            async savePreferences() {
                this.preferences = this.normalizePreferences(this.preferences);
                this.applyPreferences();
                this.saveStatus = 'saving';
                try {
                    const response = await fetch('{{ route('platform.ui-preferences') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify(this.preferences)
                    });
                    if (!response.ok) throw new Error('Preference save failed: ' + response.status);
                    const data = await response.json();
                    if (data && typeof data === 'object') {
                        this.preferences = this.normalizePreferences(data);
                        this.applyPreferences();
                    }
                    this.saveStatus = 'saved';
                } catch (error) {
                    console.warn('UI preference save failed', error);
                    this.saveStatus = 'error';
                }
            },
            updateDarkSidebar() {
                const isDark = Boolean(this.darkSidebar);
                document.documentElement.dataset.darkSidebar = isDark ? 'true' : 'false';
                try {
                    if (isDark) {
                        localStorage.setItem('toyjoy_ui_dark_sidebar', 'true');
                    } else {
                        localStorage.removeItem('toyjoy_ui_dark_sidebar');
                    }
                } catch (e) {}
            },
            resetPreferences() {
                this.darkSidebar = false;
                this.updateDarkSidebar();
                this.preferences = { ...this.defaults };
                this.applyPreferences();
                this.savePreferences();
            },
            get tourCardStyle() {
                if (!this.tour.cardPosition) return '';
                return `top: ${this.tour.cardPosition.top}px; left: ${this.tour.cardPosition.left}px; transform: none;`;
            },
            isVisibleTarget(el) {
                if (!el || !el.isConnected) return false;
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
                if (el.classList?.contains('sr-only') || (el.matches && el.matches('.sr-only, [class*="sr-only"]'))) return false;
                const rect = el.getBoundingClientRect();
                if (rect.width <= 2 || rect.height <= 2) return false;
                if (typeof el.getClientRects === 'function' && el.getClientRects().length === 0) return false;
                return true;
            },
            isUnsafeParent(el) {
                if (!el || !(el instanceof HTMLElement || el instanceof SVGElement)) return true;
                const tag = el.tagName ? el.tagName.toUpperCase() : '';
                if (tag === 'BODY' || tag === 'HTML' || tag === 'MAIN') return true;
                if (el.matches && el.matches('body, html, main, [role="main"]')) return true;
                if (el.closest && el.closest('.platform-assistant')) return true;
                return false;
            },
            resolveTarget(selectorOrElement) {
                if (!selectorOrElement) return null;
                if (typeof selectorOrElement === 'string' && selectorOrElement.includes(',')) {
                    const selectors = selectorOrElement.split(',').map(s => s.trim()).filter(Boolean);
                    for (const s of selectors) {
                        const target = this.resolveTarget(s);
                        if (target) return target;
                    }
                    return null;
                }
                const el = typeof selectorOrElement === 'string' ? document.querySelector(selectorOrElement) : selectorOrElement;
                if (!el) return null;
                if (this.isUnsafeParent(el)) return null;

                const fluxContainer = el.closest && el.closest('[data-flux-input-file]');
                if (fluxContainer && this.isVisibleTarget(fluxContainer) && !this.isUnsafeParent(fluxContainer)) {
                    if (this.isVisibleTarget(el) || (el.tagName === 'INPUT' && el.type === 'file')) {
                        return fluxContainer;
                    }
                }

                if (this.isVisibleTarget(el)) {
                    return el;
                }

                return null;
            },
            getValidSteps() {
                return (this.context?.tour_steps || []).filter(step => {
                    if (!step || !step.selector) return false;
                    return this.resolveTarget(step.selector) !== null;
                });
            },
            clearHighlight() {
                if (this.tour.currentElement) {
                    const el = this.tour.currentElement;
                    el.classList.remove('platform-assistant__tour-target');
                    if (this.tour.savedStyle) {
                        el.style.position = this.tour.savedStyle.position;
                        el.style.zIndex = this.tour.savedStyle.zIndex;
                    }
                    this.tour.currentElement = null;
                    this.tour.savedStyle = null;
                }
            },
            highlightTarget(element) {
                this.clearHighlight();
                if (!element) return;
                this.tour.currentElement = element;
                this.tour.savedStyle = {
                    position: element.style.position || '',
                    zIndex: element.style.zIndex || '',
                };
                const computedPos = window.getComputedStyle(element).position;
                if (computedPos === 'static') {
                    element.style.position = 'relative';
                }
                element.style.zIndex = '55';
                element.classList.add('platform-assistant__tour-target');
            },
            repositionTour() {
                if (!this.tour.active || !this.tour.step) return;
                const element = this.resolveTarget(this.tour.step.selector);
                if (!element) return;
                
                const card = this.$refs.tourCard;
                if (!card) return;
                
                const targetRect = element.getBoundingClientRect();
                const cardWidth = card.offsetWidth || 380;
                const cardHeight = card.offsetHeight || 200;
                
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const gap = 14;
                const padding = 16;
                
                const spaceBelow = viewportHeight - targetRect.bottom - gap - padding;
                const spaceAbove = targetRect.top - gap - padding;

                // When the target and card can fit together but the current scroll
                // position cannot, move the target upward so the card sits below it
                // instead of clamping the card over the highlighted content.
                const combinedHeight = targetRect.height + cardHeight + gap + (padding * 2);
                if (spaceBelow < cardHeight && spaceAbove < cardHeight && combinedHeight <= viewportHeight && !this.tour.scrollAdjusting) {
                    const desiredTop = Math.max(padding, Math.min(targetRect.top, viewportHeight - targetRect.height - cardHeight - gap - padding));
                    const delta = targetRect.top - desiredTop;
                    if (Math.abs(delta) > 4) {
                        this.tour.scrollAdjusting = true;
                        window.scrollBy({ top: delta, left: 0, behavior: 'auto' });
                        window.requestAnimationFrame(() => {
                            this.tour.scrollAdjusting = false;
                            this.repositionTour();
                        });
                        return;
                    }
                }

                let top;
                if (spaceBelow >= cardHeight) {
                    top = targetRect.bottom + gap;
                } else if (spaceAbove >= cardHeight) {
                    top = targetRect.top - gap - cardHeight;
                } else {
                    if (spaceBelow >= spaceAbove) {
                        top = Math.min(targetRect.bottom + gap, viewportHeight - cardHeight - padding);
                    } else {
                        top = Math.max(padding, targetRect.top - gap - cardHeight);
                    }
                }
                
                const targetCenterX = targetRect.left + (targetRect.width / 2);
                let left = targetCenterX - (cardWidth / 2);
                
                left = Math.max(padding, Math.min(left, viewportWidth - cardWidth - padding));
                top = Math.max(padding, Math.min(top, viewportHeight - cardHeight - padding));
                
                this.tour.cardPosition = { top: Math.round(top), left: Math.round(left) };
            },
            startTour() {
                if (this.tour.active) {
                    this.finishTour();
                }
                this.tour.lastFocus = this.launcherFocus || document.activeElement;
                this.tour.steps = this.getValidSteps();
                if (!this.tour.steps.length) {
                    return;
                }
                this.tour.visibleIndex = 0;
                this.tour.active = true;
                this.guideOpen = false;
                
                if (!this._tourScrollHandler) {
                    this._tourScrollHandler = () => {
                        if (this.tour.active) {
                            this.repositionTour();
                        }
                    };
                    window.addEventListener('scroll', this._tourScrollHandler, { passive: true, capture: true });
                    window.addEventListener('resize', this._tourScrollHandler, { passive: true });
                }
                
                this.showTourStep(0);
            },
            showTourStep(index) {
                this.clearHighlight();
                this.tour.steps = this.getValidSteps();
                
                if (!this.tour.steps.length || index < 0 || index >= this.tour.steps.length) {
                    return this.finishTour();
                }
                
                this.tour.visibleIndex = index + 1;
                this.tour.step = this.tour.steps[index];
                
                const element = this.resolveTarget(this.tour.step.selector);
                if (!element) {
                    return this.nextTour();
                }
                
                element.scrollIntoView({
                    behavior: this.preferences?.reduced_motion ? 'auto' : 'smooth',
                    block: 'center'
                });
                
                this.highlightTarget(element);
                
                this.$nextTick(() => {
                    this.repositionTour();
                    setTimeout(() => this.repositionTour(), 150);
                    setTimeout(() => this.repositionTour(), 350);
                });
            },
            nextTour() {
                if (!this.tour.active) return;
                const nextIndex = this.tour.visibleIndex;
                if (nextIndex >= this.tour.steps.length) {
                    return this.finishTour();
                }
                this.showTourStep(nextIndex);
            },
            previousTour() {
                if (!this.tour.active) return;
                const prevIndex = this.tour.visibleIndex - 2;
                if (prevIndex < 0) return;
                this.showTourStep(prevIndex);
            },
            skipTour() {
                this.finishTour();
            },
            finishTour() {
                this.clearHighlight();
                this.tour.active = false;
                this.tour.step = null;
                this.tour.steps = [];
                this.tour.visibleIndex = 0;
                this.tour.cardPosition = null;
                this.tour.scrollAdjusting = false;
                
                if (this._tourScrollHandler) {
                    window.removeEventListener('scroll', this._tourScrollHandler, { capture: true });
                    window.removeEventListener('resize', this._tourScrollHandler);
                    this._tourScrollHandler = null;
                }
                
                if (this.tour.lastFocus && typeof this.tour.lastFocus.focus === 'function' && this.tour.lastFocus.isConnected) {
                    this.tour.lastFocus.focus();
                } else {
                    this.restoreLauncherFocus();
                }
                this.tour.lastFocus = null;
                this.launcherFocus = null;
            },
        }));
    });
</script>

