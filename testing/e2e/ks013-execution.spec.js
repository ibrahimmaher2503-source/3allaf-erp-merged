import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { login, LOCAL_BROWSER_ACTORS } from '../helpers/auth.js';

const sequenceType = process.env.KS013_SEQUENCE_TYPE ?? 'ks013_ui_final5_20260810';
const dbEnv = {
    ...process.env,
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '3306',
    DB_DATABASE: 'toyjoy_ks_011_020_20260810', DB_USERNAME: 'root', DB_PASSWORD: '',
};

function artisan(code) {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: process.cwd(), env: dbEnv, encoding: 'utf8', timeout: 30_000,
    }).trim();
}

async function openSequence(page) {
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await page.getByRole('tab', { name: /sequence/i }).click();
    const row = page.getByRole('row', { name: new RegExp(sequenceType, 'i') });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: /^edit$/i }).click();
    await expect(page.getByLabel('Audited counter override')).toBeVisible({ timeout: 30_000 });
}

test.use({ locale: 'en-US', viewport: { width: 1440, height: 1000 }, launchOptions: { slowMo: 175 }, trace: 'on', screenshot: 'on', video: 'off' });
test.setTimeout(180_000);

test('KS-013 stale-safe audited sequence override through visible UI', async ({ page }) => {
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    expect(await page.locator('html').getAttribute('lang')).toBe('en');
    expect(await page.locator('html').getAttribute('dir')).toBe('ltr');

    // A opens the current authoritative counter/version in the real settings UI.
    await openSequence(page);
    await expect(page.getByLabel(/new next value/i)).toHaveValue('501');

    // B is an authenticated local actor using the real allocator action,
    // deliberately after A captured the optimistic-lock version.
    const firstAllocation = artisan(`use App\\Models\\User; use App\\Modules\\Platform\\Actions\\AllocateDocumentNumber; use Illuminate\\Support\\Facades\\Auth; Auth::setUser(User::query()->where('username','demo-support')->firstOrFail()); echo app(AllocateDocumentNumber::class)->execute('${sequenceType}');`);
    expect(firstAllocation).toContain('KS013-UI-0501');

    await page.getByLabel(/new next value/i).fill('550');
    await page.getByLabel(/override reason/i).fill('KS-013 stale override must be rejected.');
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: /override counter/i }).click();
    await expect(page.getByLabel('Audited counter override').getByRole('alert')).toHaveText(/sequence changed in another session.*reload before overriding/i);
    await page.screenshot({ path: 'artifacts/ks013-execution-20260810/stale-rejected.png', fullPage: true });

    // Reloaded A sees B's committed counter then makes a reasoned override.
    await openSequence(page);
    await expect(page.getByLabel(/new next value/i)).toHaveValue('502');
    await page.getByLabel(/new next value/i).fill('550');
    await page.getByLabel(/override reason/i).fill('KS-013 approved controlled counter recovery.');
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: /override counter/i }).click();
    await expect(page.getByText(/sequence counter overridden with audit evidence/i)).toBeVisible();
    await expect(page.getByLabel(/new next value/i)).toHaveValue('550');
    await page.screenshot({ path: 'artifacts/ks013-execution-20260810/override-succeeded.png', fullPage: true });

    // The next real allocation proves the override is authoritative and does
    // not roll back or create a duplicate future number.  DB assertions only
    // inspect completed business invariants and append-only audit evidence.
    const result = artisan(`use App\\Models\\User; use App\\Modules\\Platform\\Actions\\AllocateDocumentNumber; use App\\Modules\\Platform\\Models\\AuditLog; use App\\Modules\\Platform\\Models\\DocumentSequence; use Illuminate\\Support\\Facades\\Auth; Auth::setUser(User::query()->where('username','demo-reviewer')->firstOrFail()); $number=app(AllocateDocumentNumber::class)->execute('${sequenceType}'); $s=DocumentSequence::query()->where('document_type','${sequenceType}')->firstOrFail(); $audit=AuditLog::query()->where('source_id',(string)$s->id)->where('event','document_sequence_counter_overridden')->latest('id')->firstOrFail(); echo json_encode(['number'=>$number,'next_value'=>$s->next_value,'lock_version'=>$s->lock_version,'reason'=>$audit->reason_text,'actor'=>$audit->actor_name,'before'=>$audit->before_values,'after'=>$audit->after_values]);`);
    const json = JSON.parse(result.match(/\{.*\}/s)[0]);
    expect(json.number).toBe('KS013-UI-0550');
    expect(json.next_value).toBe(551);
    expect(json.lock_version).toBe(4);
    expect(json.reason).toBe('KS-013 approved controlled counter recovery.');
    expect(json.actor).toContain('Local Demo Administrator');
    expect(json.before.next_value).toBe(502);
    expect(json.after.next_value).toBe(550);
});
