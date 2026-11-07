import path from 'node:path';

export const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
export const dbName = process.env.DB_DATABASE ?? 'qa_rajh_m1_ui_acceptance_20261007';
export const artifactsDir = process.env.UI_ACCEPTANCE_ARTIFACTS ?? path.resolve('work/ui-acceptance');
export const locale = process.env.UI_ACCEPTANCE_LOCALE ?? 'ar';
export const viewport = { width: 1440, height: 1000 };

export function roleCredentials(role: string) {
    const prefix = `UI_AUTH_${role.toUpperCase().replaceAll('-', '_')}`;
    return {
        username: process.env[`${prefix}_USERNAME`] ?? `local.${role}`,
        password: process.env[`${prefix}_PASSWORD`],
    };
}
