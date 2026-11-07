import fs from 'node:fs/promises';
import path from 'node:path';
import type { Page, TestInfo } from '@playwright/test';
import { artifactsDir } from './env';

export async function step(page: Page, id: string, action: string) {
    await page.evaluate(({ id, action }) => {
        document.body.dataset.uiAcceptanceStep = `${id}: ${action}`;
    }, { id, action });
}

export async function capture(page: Page, id: string) {
    const dir = path.join(artifactsDir, 'evidence');
    await fs.mkdir(dir, { recursive: true });
    await page.screenshot({ path: path.join(dir, `${id}.png`), fullPage: true });
}

export async function recordFailure(testInfo: TestInfo) {
    const dir = path.join(artifactsDir, 'failures');
    await fs.mkdir(dir, { recursive: true });
    await fs.writeFile(path.join(dir, `${testInfo.title.replaceAll(/[^a-z0-9_-]+/gi, '_')}.json`), JSON.stringify({
        title: testInfo.title,
        status: testInfo.status,
        errors: testInfo.errors,
    }, null, 2));
}
