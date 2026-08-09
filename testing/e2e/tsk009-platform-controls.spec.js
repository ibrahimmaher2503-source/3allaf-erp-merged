import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

test.describe('TSK-009 platform controls', () => {
    test('uses the real central inbox for scoped evidence and an atomic source decision', async ({ page }) => {
        test.setTimeout(90000);
        const browserErrors = [];
        page.on('console', (message) => {
            if (message.type() === 'error') browserErrors.push(message.text());
        });
        page.on('pageerror', (error) => browserErrors.push(error.message));

        await login(page, 'tsk009-approver', 'PlaywrightTest!2026');
        let csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        expect((await page.request.post('/locale', { form: { _token: csrf, locale: 'ar' } })).status()).toBe(200);

        let response = await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        expect(await page.locator('html').getAttribute('dir')).toBe('rtl');
        await expect(page.getByRole('heading', { name: 'صندوق الموافقات' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        const accessibility = await new AxeBuilder({ page }).include('main').analyze();
        expect(accessibility.violations, JSON.stringify(accessibility.violations, null, 2)).toEqual([]);

        csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        expect((await page.request.post('/locale', { form: { _token: csrf, locale: 'en' } })).status()).toBe(200);
        await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
        expect(await page.locator('html').getAttribute('dir')).toBe('ltr');
        await expect(page.getByRole('heading', { name: 'Approval inbox' })).toBeVisible();
        await expect(page.locator('main')).not.toContainText('TSK-009');

        await page.getByRole('button', { name: 'Review' }).first().click();
        await expect(page.getByRole('heading', { name: 'Approval request' })).toBeVisible();
        await page.getByLabel('Evidence file').setInputFiles({
            name: 'approval-evidence.png',
            mimeType: 'image/png',
            buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64'),
        });
        await page.getByRole('button', { name: 'Upload securely' }).click();
        await expect(page.getByText('Approval evidence uploaded securely.')).toBeVisible();
        await expect(page.getByText('approval-evidence.png')).toBeVisible();

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Download' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toBe('approval-evidence.png');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(page.getByText('The source was approved and its audit trail was recorded.')).toBeVisible();

        response = await page.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await expect(page.locator('main')).toContainText('approval_approved');

        response = await page.request.get('/admin/audit/export');
        expect(response.status()).toBe(200);
        expect(response.headers()['content-disposition']).toContain('attachment');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(browserErrors).toEqual([]);
    });

    test('denies the real central inbox and audit export to an actor without permission', async ({ page }) => {
        await login(page, 'tsk009-no-access', 'PlaywrightTest!2026');
        expect((await page.goto('/approvals', { waitUntil: 'domcontentloaded' }))?.status()).toBe(403);
        expect((await page.request.get('/admin/audit/export')).status()).toBe(403);
    });
});
