import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('catalog product and reference screens render with search and actions', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/catalog/products', '/catalog/products/create', '/catalog/brands', '/catalog/categories', '/catalog/lookups', '/catalog/product-options']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('body')).toContainText(/product|brand|category|lookup|option|منتج|علامة|تصنيف/i);
    }
    await page.goto('/catalog/products');
    expect(await page.locator('input, select, button').count()).toBeGreaterThan(0);
    await logout(page);
});

test('product entry exposes decimal quantity and unit controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/catalog/products/create');
    const numericInputs = page.locator('input[type="number"]');
    expect(await numericInputs.count()).toBeGreaterThan(0);
    const steps = await numericInputs.evaluateAll(elements => elements.map(element => element.getAttribute('step')).filter(Boolean));
    expect(steps.some(step => step === '0.0001' || step === '0.000001')).toBeTruthy();
    await logout(page);
});
