import { test, expect } from '@playwright/test';
import { login, LOCAL_BROWSER_ACTORS } from '../helpers/auth.js';

test.use({ locale: 'en-US', viewport: { width: 1440, height: 1000 }, launchOptions: { slowMo: 220 }, trace: 'on', screenshot: 'only-on-failure' });

test('KS-013 administrator can open the visible document-sequence override surface', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    const response = await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    const sequenceTab = page.getByRole('tab', { name: /sequence/i });
    await expect(sequenceTab).toBeVisible();
    await sequenceTab.click();
    await expect(page.getByText(/document sequence|sequence counter/i).first()).toBeVisible();
    await page.screenshot({ path: 'artifacts/ks013-settings-sequence-en-ltr.png', fullPage: true });
});

test('KS-014 privileged audit UI versus low-role direct audit denial', async ({ browser }) => {
    // This flow deliberately creates two authenticated browser contexts. The
    // previous 30s default expired during repeated local runs before it could
    // report a genuine assertion failure (login throttling and full Livewire
    // hydration both consume the shared budget), so it was a harness defect.
    test.setTimeout(90_000);
    const adminContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const lowContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const admin = await adminContext.newPage(); const low = await lowContext.newPage();
    await login(admin, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    const audit = await admin.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
    expect(audit?.status()).toBe(200);
    await expect(admin.locator('[data-guide="audit-filters"]')).toBeVisible();
    await admin.screenshot({ path: 'artifacts/ks014-privileged-audit-en-ltr.png', fullPage: true });
    await login(low, LOCAL_BROWSER_ACTORS.restricted.username, LOCAL_BROWSER_ACTORS.restricted.password);
    const denied = await low.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
    expect(denied?.status()).toBe(403);
    const exportDenied = await low.goto('/admin/audit/export', { waitUntil: 'domcontentloaded' });
    expect(exportDenied?.status()).toBe(403);
    await adminContext.close(); await lowContext.close();
});
