import { expect, test } from '@playwright/test';

async function login(page) {
    const username = process.env.E2E_USERNAME;
    const password = process.env.E2E_PASSWORD;

    if (!username || !password) throw new Error('E2E_USERNAME and E2E_PASSWORD are required for this local browser test.');

    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 }),
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);
}

test.use({ locale: 'en-US', viewport: { width: 1280, height: 900 } });

test('US-046 keeps dashboard assistant drawers available after Livewire navigation', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);

    await page.evaluate(() => {
        window.livewireNavigationCount = 0;
        document.addEventListener('livewire:navigated', () => window.livewireNavigationCount++);
    });
    await page.getByRole('link', { name: 'Open setup dashboard', exact: true }).click();
    await page.waitForURL('**/initial-setup');
    await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await expect.poll(() => page.evaluate(() => window.livewireNavigationCount)).toBeGreaterThan(0);

    await page.getByRole('button', { name: 'Page Guide', exact: true }).click();
    await expect(page.locator('#page-guide-drawer')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Appearance Customizer', exact: true }).click();
    await expect(page.locator('#appearance-customizer')).toBeVisible();
});
