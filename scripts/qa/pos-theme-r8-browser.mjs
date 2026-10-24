import AxeBuilder from '@axe-core/playwright';
import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const baseURL = process.env.QA_BROWSER_URL;
const screenshotDirectory = process.env.QA_POS_SCREENSHOTS;

if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') {
    throw new Error('R8 POS theme QA requires the disposable loopback router');
}

if (!screenshotDirectory?.startsWith('/tmp/')) {
    throw new Error('QA_POS_SCREENSHOTS must be under /tmp');
}

mkdirSync(screenshotDirectory, { recursive: true });

const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'))
    .find((account) => account.role === 'Admin');

if (!admin) {
    throw new Error('Disposable Admin account unavailable');
}

let assertions = 0;
const failures = [];
const browserErrors = [];
const check = (condition, label) => {
    assertions += 1;
    console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`);

    if (!condition) {
        failures.push(label);
    }
};

const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.QA_CHROMIUM,
    args: ['--no-sandbox'],
});
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

page.on('pageerror', (error) => browserErrors.push(error.message));
page.on('console', (message) => {
    if (message.type() === 'error') {
        browserErrors.push(message.text());
    }
});
page.on('response', (response) => {
    if (response.status() >= 500) {
        browserErrors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`);
    }
});

async function navigate(path) {
    const response = await page.goto(baseURL + path, { waitUntil: 'networkidle' });
    check(response?.status() === 200, `${path} HTTP 200`);
}

function waitForLivewireResponse() {
    return page.waitForResponse((response) => (
        /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname)
        && response.request().method() === 'POST'
    ));
}

async function setArabic() {
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]')?.content || '';
        await fetch('/locale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token, locale: 'ar' }),
        });
    });
}

async function setAppearance(appearance) {
    await page.evaluate((value) => {
        window.Flux.appearance = value;
    }, appearance);
    await page.waitForFunction((value) => (
        document.documentElement.classList.contains('dark') === (value === 'dark')
        && localStorage.getItem('flux.appearance') === value
    ), appearance);
}

async function assertTheme(appearance, label) {
    const state = await page.evaluate(() => ({
        dark: document.documentElement.classList.contains('dark'),
        stored: localStorage.getItem('flux.appearance'),
        direction: document.documentElement.dir,
        font: getComputedStyle(document.body).fontFamily,
        overflow: document.documentElement.scrollWidth > window.innerWidth + 2,
        untranslated: /\b(?:Product categories|Current order|Invoice delivery|Add New Customer)\b/.test(document.body.innerText),
    }));

    check(state.dark === (appearance === 'dark'), `${label} applies ${appearance} root state`);
    check(state.stored === appearance, `${label} stores canonical Flux ${appearance} preference`);
    check(state.direction === 'rtl', `${label} remains RTL`);
    check(/Cairo/i.test(state.font), `${label} retains Cairo`);
    check(!state.overflow, `${label} has no horizontal page overflow`);
    check(!state.untranslated, `${label} introduces no known English POS labels`);
}

async function assertContrast(selector, label) {
    const result = await new AxeBuilder({ page })
        .include(selector)
        .withRules(['color-contrast'])
        .analyze();
    const targets = result.violations.flatMap((violation) => violation.nodes.map((node) => node.target.join(' ')));
    check(result.violations.length === 0, `${label} readable text contrast${targets.length ? `: ${targets.join(', ')}` : ''}`);
}

async function assertControls(selector, label) {
    const result = await page.locator(selector).evaluateAll((controls) => controls
        .filter((control) => {
            const style = getComputedStyle(control);
            return style.display !== 'none' && style.visibility !== 'hidden';
        })
        .map((control) => {
            const style = getComputedStyle(control);
            const placeholder = getComputedStyle(control, '::placeholder');
            return {
                background: style.backgroundColor,
                color: style.color,
                border: style.borderColor,
                placeholder: placeholder.color,
            };
        }));

    check(result.length > 0, `${label} exposes visible controls`);
    check(result.every((control) => control.background !== 'rgba(0, 0, 0, 0)'), `${label} controls have explicit surfaces`);
    check(result.every((control) => control.color !== control.background), `${label} control values remain visible`);
    check(result.every((control) => control.border !== control.background), `${label} control borders remain distinguishable`);
    check(result.every((control) => control.placeholder !== control.background), `${label} placeholders remain distinguishable`);
}

async function capture(name, fullPage = false) {
    await page.screenshot({ path: join(screenshotDirectory, name), fullPage });
}

async function openLookup() {
    const details = page.locator('[data-pos-products-zone] details').first();
    if (!await details.evaluate((element) => element.open)) {
        await details.locator('summary').click();
    }
}

async function openCustomerModal() {
    await page.locator('[data-pos-customer-lookup] button').filter({ hasText: 'إضافة' }).first().click();
    await page.locator('#pos-new-customer').waitFor({ state: 'visible' });
}

async function closeCustomerModal() {
    await page.locator('#pos-new-customer button[aria-label]').click();
    await page.locator('#pos-new-customer').waitFor({ state: 'hidden' });
}

async function selectExistingCustomer() {
    await page.locator('[data-customer-search-results] form button[aria-label]').last().click();
    await page.waitForLoadState('networkidle');
}

async function addProductAndSetQuantity() {
    await navigate('/pos?lookup=products');
    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('[data-pos-product-grid] button').filter({ hasText: 'إضافة' }).first().click();
    await page.locator('[data-cart-line]').first().waitFor();
    let quantity = page.locator('[data-cart-line] input[aria-label]').first();
    if (await quantity.inputValue() === '3') {
        await quantity.fill('2');
        await Promise.all([
            waitForLivewireResponse(),
            quantity.press('Enter'),
        ]);
        quantity = page.locator('[data-cart-line] input[aria-label]').first();
    }
    await quantity.fill('3');
    await Promise.all([
        waitForLivewireResponse(),
        quantity.press('Enter'),
    ]);
    await page.waitForFunction(() => document.querySelector('[data-cart-line] input[aria-label]')?.value === '3');
}

async function choosePaymentMode(index, label) {
    const mode = page.getByRole('radio').nth(index);
    if (await mode.getAttribute('aria-checked') !== 'true') {
        await Promise.all([waitForLivewireResponse(), mode.click()]);
    }
    await page.waitForFunction((modeIndex) => (
        document.querySelectorAll('[role=radio]')[modeIndex]?.getAttribute('aria-checked') === 'true'
    ), index);
    check(await page.getByRole('radio').nth(index).getAttribute('aria-checked') === 'true', `${label} payment mode remains selectable`);
}

async function verifyCheckoutDialog(theme) {
    const checkout = page.getByRole('button', { name: 'إنهاء البيع' });
    await Promise.all([waitForLivewireResponse(), checkout.click()]);
    const dialog = page.locator('dialog[open]');
    await dialog.waitFor({ state: 'visible' });
    check(await dialog.isVisible(), `${theme} checkout dialog opens without navigation`);
    await assertContrast('dialog[open]', `${theme} checkout dialog`);
    await assertControls('dialog[open] input:not([type=hidden]), dialog[open] select, dialog[open] textarea', `${theme} checkout dialog`);
    await dialog.locator('header > button[aria-label]').click();
    await dialog.waitFor({ state: 'hidden' });
}

async function transactionState() {
    return page.evaluate(() => ({
        ticket: document.querySelector('[data-pos-ticket-header]')?.innerText || '',
        customer: document.querySelector('[aria-labelledby=pos-customer-heading]')?.innerText || '',
        lines: document.querySelectorAll('[data-cart-line]').length,
        quantity: document.querySelector('[data-cart-line] input[aria-label]')?.value || '',
        payment: document.querySelector('[role=radiogroup] [aria-checked=true]')?.innerText || '',
    }));
}

try {
    await navigate('/login');
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login')),
        page.locator('[data-test=login-button]').click(),
    ]);
    check(!page.url().endsWith('/login'), 'disposable Admin authenticated');

    await setArabic();
    await navigate('/pos');
    await setAppearance('light');
    await assertTheme('light', '1440 main POS');
    await assertContrast('[data-pos-workstation]', '1440 light main POS');
    await capture('r8-pos-light-1440.png');
    await page.locator('[data-pos-theme-toggle]').screenshot({ path: join(screenshotDirectory, 'r8-theme-toggle-light.png') });

    await openLookup();
    check(await page.locator('[data-pos-lookup-panel] a').count() === 3, 'light lookup retains three canonical actions');
    await assertContrast('[data-pos-lookup-panel]', '1440 light lookup');
    await capture('r8-lookup-light-1440.png');

    await navigate('/pos?lookup=customers&customer_q=عميل%20اختبار');
    check(await page.locator('[data-customer-search-results] form').count() >= 6, 'light customer search renders scoped cards');
    await assertControls('[data-pos-customer-lookup] input:not([type=hidden])', 'light customer search');
    await assertContrast('[data-pos-customer-lookup]', '1440 light customer search');
    await capture('r8-customer-search-light-1440.png');
    await openCustomerModal();
    await assertControls('#pos-new-customer input:not([type=hidden]), #pos-new-customer select', 'light customer modal');
    await assertContrast('#pos-new-customer', '1440 light customer modal');
    await capture('r8-customer-modal-light-1440.png');
    await closeCustomerModal();

    await selectExistingCustomer();
    await addProductAndSetQuantity();
    check(await page.getByRole('radio').count() >= 3, 'cash, card/electronic, and mixed payment modes remain available');
    await choosePaymentMode(1, 'card/electronic');
    await choosePaymentMode(2, 'mixed');
    await choosePaymentMode(0, 'cash');
    await assertControls('[data-pos-transaction-zone] input:not([type=hidden]), [data-pos-transaction-zone] select, [data-pos-transaction-zone] textarea', 'light cart and payment');
    await assertContrast('[data-pos-transaction-zone]', '1440 light cart and payment');
    await verifyCheckoutDialog('light');
    await capture('r8-cart-payment-light-1440.png');
    const beforeToggle = await transactionState();

    const preferenceResponse = page.waitForResponse((response) => response.url().includes('/ui/preferences') && response.request().method() === 'POST');
    await page.locator('[data-pos-theme-toggle]').click();
    check((await preferenceResponse).status() === 200, 'theme toggle persists through canonical Rajeh preference endpoint');
    await page.waitForFunction(() => document.documentElement.classList.contains('dark'));
    await assertTheme('dark', '1440 toggle');
    check(JSON.stringify(await transactionState()) === JSON.stringify(beforeToggle), 'Light to Dark toggle preserves ticket, customer, cart, quantity, and payment mode');
    await page.locator('[data-pos-theme-toggle]').screenshot({ path: join(screenshotDirectory, 'r8-theme-toggle-dark.png') });
    await assertContrast('[data-pos-transaction-zone]', '1440 dark cart and payment');
    await assertControls('[data-pos-transaction-zone] input:not([type=hidden]), [data-pos-transaction-zone] select, [data-pos-transaction-zone] textarea', 'dark cart and payment');
    await verifyCheckoutDialog('dark');
    await capture('r8-cart-payment-dark-1440.png');

    await navigate('/pos?lookup=products');
    await assertTheme('dark', '1440 dark main POS');
    await assertContrast('[data-pos-workstation]', '1440 dark main POS');
    await capture('r8-pos-dark-1440.png');
    await openLookup();
    await assertContrast('[data-pos-lookup-panel]', '1440 dark lookup');
    await capture('r8-lookup-dark-1440.png');

    await navigate('/pos?lookup=customers&customer_q=عميل%20اختبار');
    await assertContrast('[data-pos-customer-lookup]', '1440 dark customer search');
    await assertControls('[data-pos-customer-lookup] input:not([type=hidden])', 'dark customer search');
    await capture('r8-customer-search-dark-1440.png');
    await openCustomerModal();
    await assertContrast('#pos-new-customer', '1440 dark customer modal');
    await assertControls('#pos-new-customer input:not([type=hidden]), #pos-new-customer select', 'dark customer modal');
    await capture('r8-customer-modal-dark-1440.png');
    await closeCustomerModal();

    await page.setViewportSize({ width: 390, height: 844 });
    await navigate('/pos?lookup=products');
    await assertTheme('dark', '390 product view');
    check(await page.locator('[data-pos-theme-toggle]').isVisible(), '390 dark header theme toggle remains accessible');
    await capture('r8-pos-dark-390.png');
    await page.getByRole('button', { name: 'الطلب الحالي' }).click();
    check(await page.locator('[data-pos-transaction-zone]').isVisible(), '390 dark current-order view opens');
    check((await transactionState()).quantity === '3', '390 dark current-order view preserves quantity');
    await capture('r8-transaction-dark-390.png');

    const darkMobileState = await transactionState();
    const lightPreferenceResponse = page.waitForResponse((response) => response.url().includes('/ui/preferences') && response.request().method() === 'POST');
    await page.locator('[data-pos-theme-toggle]').click();
    check((await lightPreferenceResponse).status() === 200, 'Dark to Light toggle persists through canonical Rajeh preference endpoint');
    await page.waitForFunction(() => !document.documentElement.classList.contains('dark'));
    await assertTheme('light', '390 light current-order view');
    check(JSON.stringify(await transactionState()) === JSON.stringify(darkMobileState), 'Dark to Light toggle preserves mobile transaction state');
    await capture('r8-transaction-light-390.png');
    await page.getByRole('button', { name: 'المنتجات' }).click();
    check(await page.locator('[data-pos-products-zone]').isVisible(), '390 light product view opens');
    await capture('r8-pos-light-390.png');

    await page.reload({ waitUntil: 'networkidle' });
    await assertTheme('light', '390 reload');
    check((await transactionState()).quantity === '3', 'light reload preserves canonical open-ticket quantity');

    await setAppearance('dark');
    await page.reload({ waitUntil: 'networkidle' });
    await assertTheme('dark', 'dark persistence reload');
    check((await transactionState()).quantity === '3', 'dark reload preserves canonical open-ticket quantity');

    check(browserErrors.length === 0, `browser console and page errors: ${browserErrors.join(' | ')}`);
} finally {
    await browser.close();
}

console.log(`R8 POS theme browser QA: ${assertions} assertions; screenshots ${screenshotDirectory}`);

if (failures.length > 0) {
    console.error(failures.join('\n'));
    process.exitCode = 1;
}
