/**
 * Shared Playwright auth helpers. Reused across specs instead of duplicating
 * the login form interaction in every test file.
 */

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
