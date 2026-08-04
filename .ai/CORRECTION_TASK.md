# TSK-004B Focused Repair — Real Tour Targets and Appearance Customizer

Repository: /home/ubuntu/toy-joy-phase-1-documentation. User explicitly requested AGY.

Implement only these fixes. Preserve all existing unrelated uncommitted changes. Do not commit, push, install packages, change business workflows, permissions, routes outside help cache behavior, or create tests.

1. In resources/views/components/platform/dashboard-tools.blade.php:
   - Fix resolveTarget()/fallback resolution so a hidden/unsafe first comma-separated selector returns null for that selector and the next fallback is tried. Never climb from a hidden target to an outer page wrapper. Only return the resolved element if the selected element itself (or its documented visible semantic wrapper, e.g. Flux file input wrapper) is visible and safe.
   - Preserve explicit data-guide selectors, highlighting, viewport scrolling, card positioning, cleanup, and all existing tour steps.
   - Record the launcher element when opening Page Guide or Appearance Customizer and restore focus to that launcher in closeAll(), Escape, backdrop close, finish/skip where applicable. Do not steal focus from normal page actions.
   - Ensure Appearance Customizer controls apply immediately, save with response checking/status, and Reset applies/persists defaults.

2. In resources/views/partials/head.blade.php:
   - Apply persisted sidebar_mode and content_width before first paint, alongside existing appearance/accent/font/table/reduced-motion values. Use safe JSON/escaped values already supplied by the server; no secrets.
   - Keep local-only, permission-aware behavior unchanged.

3. In app/Modules/Platform/Http/Controllers/DashboardAssistantController.php:
   - Add private no-cache/no-store/private headers to permission-filtered screen() and flow() responses without changing content or authorization.

4. If CSS needs a narrowly-scoped adjustment for the above, only edit resources/css/app.css. Do not modify business markup beyond existing tour data-guide hooks.

Verification required after edits: php artisan view:cache --no-ansi, npm run build, git diff --check. Report exact files and no commit/push.
