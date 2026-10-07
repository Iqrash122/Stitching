import { test, expect } from './fixtures';
import { readFileSync } from 'node:fs';
test('owner completes customer, measured order, three payments, delivery and private export', async ({
    page,
}, testInfo) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto('/studio/customers');
    await page
        .getByRole('button', { name: 'New customer', exact: true })
        .click();
    let dialog = page.getByRole('dialog');
    const name = `Browser Customer ${Date.now()}`;
    await dialog.getByLabel('Customer name').fill(name);
    await dialog.getByLabel(/^Phone\s*\*?$/).fill('03001234567');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
    await page.goto('/studio/orders');
    await page.getByRole('button', { name: 'New order', exact: true }).click();
    dialog = page.getByRole('dialog');
    await dialog
        .getByRole('combobox')
        .nth(0)
        .selectOption({ label: `${name} · 03001234567` });
    await dialog
        .getByRole('combobox')
        .nth(1)
        .selectOption({ label: 'Kurta stitching · PKR 2,200.00' });
    await dialog
        .getByRole('button', { name: 'Create order & enter measurements' })
        .click();
    await expect(page).toHaveURL(/studio\/orders\/\d+/);
    await page
        .getByRole('button', { name: /Measurements · Incomplete/ })
        .click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel(/\(Length\)/).fill('42');
    await dialog.getByLabel(/\(Sleeve Length\)/).fill('24');
    await dialog.getByLabel(/\(Teera\)/).fill('18');
    await dialog.getByLabel(/\(Chest\)/).fill('40');
    await dialog.getByLabel(/\(Ghera\)/).fill('24');
    await dialog.getByLabel(/\(Neck \/ Collar\)/).fill('16');
    await dialog.getByLabel(/\(Front Pocket\)/).check();
    await dialog.getByRole('button', { name: 'Save measurements' }).click();
    await expect(
        page.getByRole('button', { name: /Measurements · Complete/ }),
    ).toBeVisible();
    async function action(label: string) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: label, exact: true })
            .click();
        await expect(page.getByRole('dialog')).not.toBeVisible();
    }
    await action('Confirm order');
    await action('Create draft invoice');
    await action('Post invoice');
    for (const amount of ['1000', '500', '700']) {
        await page
            .getByRole('button', { name: 'Receive & allocate payment' })
            .click();
        dialog = page.getByRole('dialog');
        await dialog.getByLabel(/^Amount/).fill(amount);
        await dialog
            .getByRole('button', { name: 'Receive & allocate payment' })
            .click();
        await expect(page.getByRole('dialog')).not.toBeVisible();
    }
    await expect(
        page.locator('.stat').filter({ hasText: 'Outstanding receivable' }),
    ).toContainText('PKR 0.00');
    for (const label of [
        'Move to Cutting',
        'Move to Stitching',
        'Move to Ready',
        'Deliver order',
    ])
        await action(label);
    await expect(page.locator('.flow .current')).toHaveText('Delivered');
    await expect(
        page.getByRole('button', { name: 'Cancel production' }),
    ).toHaveCount(0);
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Invoice PDF' }).click();
    const pdf = await downloadPromise;
    const invoicePath = testInfo.outputPath('invoice.pdf');
    await pdf.saveAs(invoicePath);
    expect(readFileSync(invoicePath).subarray(0, 4).toString()).toBe('%PDF');
    await page.screenshot({
        path: testInfo.outputPath('desktop.png'),
        fullPage: true,
    });
    await page.goto('/studio/reports');
    await page.getByRole('button', { name: 'Export CSV' }).click();
    await expect(page).toHaveURL(/studio\/exports/);
    await expect(
        page.getByRole('link', { name: 'Download file' }).first(),
    ).toBeVisible();
    const denied = await page.request.post('/studio/customers', {
        headers: { Origin: 'https://untrusted.invalid' },
        data: { name: 'Rejected', phone: '03001234567' },
    });
    expect([403, 419]).toContain(denied.status());
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/studio');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBeTruthy();
    await page.screenshot({
        path: testInfo.outputPath('mobile.png'),
        fullPage: true,
    });
    expect(errors).toEqual([]);
});
