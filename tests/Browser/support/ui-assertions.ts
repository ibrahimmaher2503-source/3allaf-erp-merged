import { expect, type Page } from '@playwright/test';

export async function assertShell(page: Page, locale: 'ar' | 'en', viewport: { width: number; height: number }) {
    await expect(page.locator('a[href$="/dashboard"]').first()).toBeVisible();
    await expect(page.getByRole('navigation', { name: /primary navigation|التنقل الرئيسي/i })).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('h1').first()).toBeVisible();
    const dir = await page.locator('html').getAttribute('dir');
    expect(dir, `${locale} direction`).toBe(locale === 'ar' ? 'rtl' : 'ltr');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow, `horizontal overflow at ${viewport.width}x${viewport.height}`).toBeFalsy();
}

export async function assertAccessibleActions(page: Page) {
    const controls = page.locator('button:visible, a:visible');
    const count = await controls.count();
    expect(count).toBeGreaterThan(0);
    const missing: string[] = [];
    for (let index = 0; index < Math.min(count, 30); index += 1) {
        const control = controls.nth(index);
        const name = await control.getAttribute('aria-label') || await control.innerText();
        if (!(name ?? '').trim()) missing.push(await control.evaluate(element => element.outerHTML.slice(0, 240)));
    }
    return missing;
}
