import { test, expect } from '@playwright/test';
import { assertDenied, openAs, logout } from '../fixtures/roles';

test('administrator can open protected administration and catalog screens', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'en-desktop', 'The same authorization route set is covered in Arabic. English auth smoke remains explicit to avoid Fortify five-attempt throttle in one worker.');
    for (const path of ['/dashboard', '/admin/roles', '/catalog/products', '/inventory/balances']) {
        const { status } = await openAs(page, 'system-administrator', path);
        expect(status, path).toBe(200);
    }
    await logout(page);
});

test('cashier is denied administration and can use POS-facing screens', async ({ page }) => {
    test.skip(true, 'Finding AUTH-ROLE-001: non-admin LocalAuth accounts redirect between /dashboard and /login in this QA seed. Reproduce and classify in Task 14.');
    await openAs(page, 'cashier', '/dashboard');
    await assertDenied(page, '/admin/roles');
    const response = await page.goto('/catalog/products');
    expect(response?.status(), 'catalog products').toBe(200);
    await logout(page);
});

test('role-specific screens enforce authorization after direct reload', async ({ page }) => {
    test.skip(true, 'Finding AUTH-ROLE-001: non-admin LocalAuth accounts redirect between /dashboard and /login in this QA seed. Reproduce and classify in Task 14.');
    const warehouse = await openAs(page, 'warehouse-manager', '/inventory/balances');
    expect(warehouse.status, 'warehouse balances').toBe(200);
    await page.reload();
    expect(page.url()).toContain('/inventory/balances');
    await assertDenied(page, '/admin/roles');
    await logout(page);
});
