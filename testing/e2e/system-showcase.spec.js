import { test, expect } from '@playwright/test';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

const showcase = path.resolve('artifacts/system-showcase/index.html');

test.describe('Arabic system showcase', () => {
  test.setTimeout(90_000);
  test('desktop loads every real screenshot without console errors', async ({ page }) => {
    const errors = [];
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    await page.goto(pathToFileURL(showcase).href);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('img')).toHaveCount(19);
    await expect.poll(async () => page.locator('img').evaluateAll((images) => images.filter((image) => image.complete && image.naturalWidth > 0).length)).toBe(19);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    expect(errors).toEqual([]);
    await expect(page).toHaveScreenshot('system-showcase-desktop.png', { fullPage: true, timeout: 15_000 });
  });

  test('mobile layout remains readable without overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(pathToFileURL(showcase).href);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await expect(page).toHaveScreenshot('system-showcase-mobile.png', { fullPage: true, timeout: 15_000 });
  });
});
