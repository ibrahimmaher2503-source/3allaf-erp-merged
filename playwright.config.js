import { defineConfig, devices } from '@playwright/test';

/**
 * Critical-path browser E2E configuration. Chromium-only for the routine
 * suite per the QA audit's "broad Chromium coverage plus a smaller critical
 * cross-browser suite" guidance — Firefox/WebKit projects can be added once
 * the routine suite is stable.
 *
 * baseURL points at a dedicated local server + MySQL/MariaDB database seeded
 * specifically for this suite (see testing/e2e/README.md); it must never
 * point at a developer's real local database or any staging/production URL.
 */
export default defineConfig({
    timeout: 120_000,
    testDir: './tests/Browser',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: [['html', { open: 'never', outputFolder: 'work/ui-acceptance/report' }], ['list']],
    outputDir: 'work/ui-acceptance/results',
    webServer: process.env.PLAYWRIGHT_START_SERVER === '1' ? {
        command: `php artisan serve --host=127.0.0.1 --port=${process.env.PLAYWRIGHT_SERVER_PORT || '8820'}`,
        url: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8820',
        reuseExistingServer: false,
        timeout: 120_000,
    } : undefined,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        // WebKit on the local PHP built-in server can take longer on the
        // first authenticated navigation after a fresh disposable schema.
        actionTimeout: 60_000,
        navigationTimeout: 60_000,
    },
    projects: [
        {
            name: 'ar-desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' },
        },
        {
            name: 'en-desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 }, locale: 'en-US' },
        },
        {
            name: 'ar-mobile',
            use: { ...devices['iPhone 13'], browserName: 'chromium', locale: 'ar-EG' },
        },
        {
            name: 'pos-keyboard',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' },
        },
    ],
});
