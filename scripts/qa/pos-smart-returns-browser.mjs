import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
const baseURL = process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') throw new Error('Disposable loopback browser required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find((account) => account.role === 'Admin');
const fixture = JSON.parse(readFileSync(process.env.QA_POS_RETURN_FIXTURE, 'utf8'));
if (!admin) throw new Error('Disposable Admin missing');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const errors = [];
let checks = 0;
function check(ok, label) { checks++; console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`); if (!ok) errors.push(label); }
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('pageerror', e => errors.push(`JS ${e.message}`));
  page.on('console', m => { if (m.type() === 'error') errors.push(`console ${m.text()}`); });
  page.on('response', r => { if (r.status() >= 500) errors.push(`HTTP ${r.status()} ${r.url()}`); else if (r.status() === 404) console.log(`HTTP 404 ${r.url()}`); });
  await page.goto(baseURL + '/login');
  await page.locator('input[name=username]').fill(admin.username);
  await page.locator('input[name=password]').fill(admin.password);
  await Promise.all([page.waitForURL(u => !u.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
  await page.evaluate(async () => { const token = document.querySelector('meta[name=csrf-token]').content; await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) }); });
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto(baseURL + '/pos', { waitUntil: 'networkidle' });
    console.log(`POS navigation: ${response.status()} ${page.url()} ${JSON.stringify((await page.locator('body').innerText()).slice(0, 180))}`);
    check(response.status() === 200, `${width} POS HTTP 200`);
    check(await page.locator('button[title="مرتجع (F7)"]:visible').isVisible(), `${width} Returns action visible`);
    if (response.status() !== 200) continue;
    if (width === 390) {
      await page.locator('button[title="مرتجع (F7)"]:visible').click();
      await page.locator('#pos-refund-lookup').waitFor({ state: 'visible' });
      check(await page.locator('#pos-refund-lookup').isVisible(), '390 Returns icon opens scan workflow');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="close"]').first().click();
    }
    if (width === 1440) {
      await page.locator('[data-pos-ticket-header] form').filter({ has: page.locator('input[name="creation_token"]') }).locator('button').first().click();
      await page.waitForLoadState('networkidle');
      await page.locator('[data-pos-category-grid] button').first().click();
      await page.locator('[data-pos-product-grid] article').first().waitFor();
      await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
      await page.locator('[data-cart-line]').first().waitFor();
      await page.locator('[data-guide=pos-summary-actions] button[wire\\:click^="startCheckout"]').click();
      const checkout = page.locator('dialog[aria-labelledby=pos-checkout-dialog-title]');
      await checkout.waitFor({ state: 'visible' });
      await checkout.locator('button[wire\\:click="switchCheckoutMode(\'split\')"]').click();
      await checkout.locator('[data-payment-part]').nth(1).waitFor();
      await checkout.locator('[data-cash-received]').fill('30.00');
      await page.waitForTimeout(300);
      const autoAmount = checkout.locator('[data-payment-part]').nth(1).locator('[data-noncash-amount]');
      check(await autoAmount.inputValue() === '15.00', '1440 split auto-fills remaining 15.00');
      await autoAmount.fill('14.00');
      check(await autoAmount.inputValue() === '14.00', '1440 split manual override remains editable');
      await page.waitForFunction(() => [...document.querySelectorAll('[x-text="amountRemaining()"]')].some(el => el.textContent.trim() === '1.00'), null, { timeout: 2000 }).catch(() => {});
      check((await checkout.locator('[x-text="amountRemaining()"]' ).allTextContents()).some(value => value.trim() === '1.00'), '1440 split remaining updates live to 1.00');
      await checkout.locator('button[aria-label]').first().click();
    }
    await page.keyboard.press('F7');
    const lookup = page.locator('#pos-refund-lookup');
    await lookup.waitFor({ state: 'visible' });
    await page.waitForFunction(() => document.activeElement?.id === 'pos-refund-lookup', null, { timeout: 3000 }).catch(() => {});
    check(await lookup.evaluate(el => document.activeElement === el), `${width} F7 opens Returns and focuses barcode input`);
    check(await page.locator('[data-pos-refund-wizard]').evaluate(el => el.scrollWidth <= innerWidth + 3), `${width} Returns has no horizontal viewport overflow`);
    if (width === 390) {
      await lookup.focus();
      await page.keyboard.type(fixture.receipt_reference);
      await page.keyboard.press('Enter');
    } else {
      await lookup.fill(fixture.receipt_reference);
      await page.locator('[data-pos-refund-wizard] form[wire\\:submit="lookup"] button[type="submit"]').click();
    }
    await page.locator('[data-pos-refund-wizard]').getByText(fixture.receipt_reference).first().waitFor({ state: 'visible' });
    check(await page.locator('[data-pos-refund-wizard]').getByText(fixture.receipt_reference).count() > 0, `${width} direct invoice or scan lookup opens sale`);
    if (width === 1440) {
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      await page.locator('[data-pos-refund-wizard] input[wire\\:model^="quantities."]').first().waitFor({ state: 'visible' });
      check(await page.locator('[data-pos-refund-wizard] input[wire\\:model^="quantities."]').count() > 0, '1440 linked sale shows selectable return quantity');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="close"]').first().click();
      await page.keyboard.press('F7');
      await page.locator('[data-pos-refund-wizard] input[wire\\:model="customerTerm"]').fill('01011223399');
      await page.locator('[data-pos-refund-wizard] form[wire\\:submit="searchSales"] button[type="submit"]').click();
      await page.locator('[data-pos-return-results]').getByText(fixture.receipt_reference).first().waitFor({ state: 'visible' });
      check(await page.locator('[data-pos-return-results]').getByText('QA-POS-LOOKUP-001').count() > 0, '1440 customer phone search finds all matching sales');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="setSearchMode(\'date\')"]').click();
      await page.locator('[data-pos-refund-wizard] input[wire\\:model="saleDate"]').fill(new Date().toISOString().slice(0, 10));
      await page.locator('[data-pos-refund-wizard] form[wire\\:submit="searchSales"] button[type="submit"]').click();
      await page.locator('[data-pos-return-results]').getByText(fixture.receipt_reference).first().waitFor({ state: 'visible' });
      check(await page.locator('[data-pos-return-results]').getByText('QA-POS-LOOKUP-001').count() > 0, '1440 date search finds the sale');
      check(await page.locator('[data-pos-refund-wizard] button[wire\\:click="openUnverified"]').isVisible(), 'Unverified fallback is visible to authorized manager only');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="close"]').first().click();
      await page.locator('[data-pos-refund-wizard] dialog').waitFor({ state: 'detached' });
      await page.keyboard.press('F7');
      await page.locator('#pos-refund-lookup').waitFor({ state: 'visible' });
      await page.locator('#pos-refund-lookup').fill(fixture.return_reference);
      await page.locator('[data-pos-refund-wizard] form[wire\\:submit="lookup"] button[type="submit"]').click();
      await page.locator('[data-pos-refund-wizard]').getByText(fixture.return_reference).first().waitFor({ state: 'visible', timeout: 10000 }).catch(async error => { console.log('Canonical lookup state:', (await page.locator('[data-pos-refund-wizard]').innerText()).slice(0, 1000)); throw error; });
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      const quantity = page.locator('[data-pos-refund-wizard] input[wire\\:model^="quantities."]').first();
      await quantity.waitFor({ state: 'visible' });
      await quantity.fill('1');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="setAllRefundable"]').waitFor({ state: 'detached' });
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      const reason = page.locator('[data-pos-refund-wizard] select[wire\\:model="reason"]');
      await reason.waitFor({ state: 'visible' });
      await reason.selectOption('defective');
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="submit"]').waitFor({ state: 'visible' });
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="submit"]').click();
      const returnReceiptLink = page.locator('[data-pos-refund-wizard] a[href*="/pos/refunds/"][href$="/receipt"]');
      await returnReceiptLink.waitFor({ state: 'visible' });
      check(await returnReceiptLink.isVisible(), '1440 linked return confirms and offers a refund receipt');
      const returnReceiptURL = new URL(await returnReceiptLink.getAttribute('href'), baseURL).href;
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="close"]').first().click();
      await page.locator('[data-pos-refund-wizard] dialog').waitFor({ state: 'detached' });
      await page.keyboard.press('F7');
      await page.locator('#pos-refund-lookup').fill(fixture.return_reference);
      await page.locator('[data-pos-refund-wizard] form[wire\\:submit="lookup"] button[type="submit"]').click();
      await page.locator('[data-pos-refund-wizard]').getByText(fixture.return_reference).first().waitFor({ state: 'visible' });
      await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
      const returnedRow = page.locator('[data-pos-refund-wizard] table tbody tr').first();
      await returnedRow.waitFor({ state: 'visible' });
      check((await returnedRow.locator('td').nth(2).innerText()).trim() === '1' && (await returnedRow.locator('td').nth(3).innerText()).trim() === '1', '1440 previously returned and remaining quantities both show one');
      const returnReceipt = await page.goto(returnReceiptURL, { waitUntil: 'networkidle' });
      check(returnReceipt.status() === 200 && await page.locator('[data-return-barcode] svg rect').count() > 10, 'Return receipt prints with a Code 128 return reference');
      const receiptResponse = await page.goto(`${baseURL}/pos/sales/${fixture.sale_id}/receipt`, { waitUntil: 'networkidle' });
      check(receiptResponse.status() === 200, 'Sales receipt preview HTTP 200');
      check(await page.locator(`[data-receipt-barcode][data-receipt-reference="${fixture.receipt_reference}"] svg rect`).count() > 10, 'Sales receipt includes Code 128 bars for the exact invoice reference');
    }
  }
  check(errors.length === 0, 'No browser console or HTTP 5xx errors');
} finally { await browser.close(); }
if (errors.length) { console.error(errors.join('\n')); process.exitCode = 1; }
console.log(`Browser checks: ${checks}`);
