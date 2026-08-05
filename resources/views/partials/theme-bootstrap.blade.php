@php
    use App\Modules\Platform\Models\UserUiPreference;

    $defaults = class_exists(UserUiPreference::class)
        ? UserUiPreference::defaults()
        : [
            'appearance' => 'system',
            'accent_color' => 'teal',
            'sidebar_mode' => 'expanded',
            'navbar_mode' => 'sticky',
            'content_width' => 'wide',
            'table_density' => 'comfortable',
            'font_scale' => 'normal',
            'reduced_motion' => false,
        ];

    $user = auth()->user();
    $uiPreference = $user?->uiPreference;
    $uiPreferences = array_merge(
        $defaults,
        $uiPreference?->only(array_keys($defaults)) ?? []
    );
@endphp

<script>
    window.__toyJoyUiPreferences = @js($uiPreferences);
    (() => {
        const prefs = window.__toyJoyUiPreferences;
        const root = document.documentElement;
        const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
        const appearance = prefs.appearance === 'system' ? (systemDark ? 'dark' : 'light') : prefs.appearance;
        root.classList.toggle('dark', appearance === 'dark');
        root.dataset.appearance = prefs.appearance;
        root.dataset.accent = prefs.accent_color;
        root.dataset.sidebarMode = prefs.sidebar_mode;
        root.dataset.contentWidth = prefs.content_width;
        root.dataset.fontScale = prefs.font_scale;
        root.dataset.tableDensity = prefs.table_density;
        root.dataset.reducedMotion = prefs.reduced_motion ? 'true' : 'false';

        let darkSidebar = false;
        try {
            darkSidebar = localStorage.getItem('toyjoy_ui_dark_sidebar') === 'true';
        } catch (e) {
            darkSidebar = false;
        }
        root.dataset.darkSidebar = darkSidebar ? 'true' : 'false';
    })();
</script>
