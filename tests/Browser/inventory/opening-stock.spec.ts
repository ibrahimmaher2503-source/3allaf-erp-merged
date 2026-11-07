import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('opening stock list and template routes render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/inventory/opening', '/inventory/opening-template.xlsx']) {
        const downloadPromise = page.waitForEvent('download').catch(() => null);
        const response = await page.goto(path).catch(() => null);
        if (downloadPromise) {
            const download = await downloadPromise;
            if (download) expect(await download.suggestedFilename()).toMatch(/\.(xlsx|csv)$/i);
        }
        if (response) expect(response.status(), path).toBe(200);
    }
    await logout(page);
});
