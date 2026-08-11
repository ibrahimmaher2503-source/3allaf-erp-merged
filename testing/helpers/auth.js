/**
 * Shared Playwright auth helpers. Reused across specs instead of duplicating
 * the login form interaction in every test file.
 */

/**
 * Local-only browser fixture credentials. These are never valid production
 * credentials and are intentionally shared by the local DemoSeeder actors.
 */
export const LOCAL_DEMO_PASSWORD = process.env.PLAYWRIGHT_LOCAL_PASSWORD ?? 'LocalDemoOnly!2026';

export const LOCAL_BROWSER_ACTORS = Object.freeze({
    administrator: { username: 'demo-admin', password: LOCAL_DEMO_PASSWORD },
    support: { username: 'demo-support', password: LOCAL_DEMO_PASSWORD },
    reviewer: { username: 'demo-reviewer', password: LOCAL_DEMO_PASSWORD },
    branchScoped: { username: 'demo-branch-manager', password: LOCAL_DEMO_PASSWORD },
    storeScoped: { username: 'demo-cashier', password: LOCAL_DEMO_PASSWORD },
    restricted: { username: 'demo-no-access', password: LOCAL_DEMO_PASSWORD },
});

/**
 * Log in via the real login form (username + password), matching how a
 * production user authenticates. Waits for the post-login redirect.
 * @param {import('@playwright/test').Page} page
 * @param {string} username
 * @param {string} password
 */
export async function login(page, username, password) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}
