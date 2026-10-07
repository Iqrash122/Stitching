import { test as base, expect } from '@playwright/test';
import type { BrowserContext } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

type OwnerStorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

export const test = base.extend<{}, { ownerStorageState: OwnerStorageState }>({
    ownerStorageState: [
        async ({ browser }, use) => {
            const credentialPath =
                process.env.STUDIO_TEST_CREDENTIALS ??
                join(tmpdir(), 'stitching-browser-credentials.json');
            const credentials = JSON.parse(readFileSync(credentialPath, 'utf8')) as {
                email: string;
                password: string;
            };
            const context = await browser.newContext();
            let state: OwnerStorageState;
            try {
                const page = await context.newPage();
                const baseURL = process.env.STUDIO_TEST_URL ?? 'http://127.0.0.1:8000';
                await page.goto(`${baseURL}/login`);
                await page.getByLabel('Email address').fill(credentials.email);
                await page.getByLabel('Password', { exact: true }).fill(credentials.password);
                await page.getByRole('button', { name: 'Log in', exact: true }).click();
                await expect(page).toHaveURL(/\/studio/);
                state = await context.storageState();
            } finally {
                await context.close();
            }
            await use(state);
        },
        { scope: 'worker' },
    ],
    storageState: async ({ ownerStorageState }, use) => {
        await use(ownerStorageState);
    },
});

export { expect };
