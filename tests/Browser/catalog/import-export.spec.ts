import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('catalog and customer import templates download through browser UI routes', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/catalog/products/import/template.xlsx', '/catalog/suppliers/import/template', '/customers/import/template']) {
        const downloadPromise = page.waitForEvent('download');
        await page.goto(path).catch(() => undefined);
        const download = await downloadPromise;
        expect(await download.suggestedFilename(), path).toMatch(/\.(xlsx|csv)$/i);
    }
    await logout(page);
});
