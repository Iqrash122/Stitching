import { test, expect } from './fixtures';

test('failed navigation shows a connection error and allows retry', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/studio/customers');
    await page.route('**/studio/orders', (route) => route.abort('failed'));
    const ordersLink = page.getByRole('link', {
        name: 'Stitching orders',
        exact: true,
    });
    await ordersLink.click();
    await expect(page.getByRole('alert')).toContainText(
        'Check your connection',
    );
    await expect(page).toHaveURL(/\/studio\/customers$/);
    await page.unroute('**/studio/orders');
    await ordersLink.click();
    await expect(page).toHaveURL(/\/studio\/orders$/);
    await expect(page.getByRole('alert')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('dirty customer form protects Escape and close without losing values', async ({
    page,
}) => {
    await page.goto('/studio/customers');
    await page.getByRole('button', { name: 'New customer' }).click();
    const dialog = page.getByRole('dialog');
    const name = dialog.getByLabel('Customer name');
    await name.fill('Unsaved browser customer');

    page.once('dialog', async (confirmation) => {
        expect(confirmation.type()).toBe('confirm');
        expect(confirmation.message()).toContain('unsaved changes');
        await confirmation.dismiss();
    });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeVisible();
    await expect(name).toHaveValue('Unsaved browser customer');

    page.once('dialog', async (confirmation) => {
        expect(confirmation.type()).toBe('confirm');
        await confirmation.dismiss();
    });
    await dialog.getByRole('button', { name: 'Close dialog' }).click();
    await expect(dialog).toBeVisible();
    await expect(name).toHaveValue('Unsaved browser customer');

    page.once('dialog', async (confirmation) => {
        await confirmation.accept();
    });
    await dialog.getByRole('button', { name: 'Close dialog' }).click();
    await expect(dialog).not.toBeVisible();
});

test('server validation focuses the first error and preserves entered values', async ({
    page,
}) => {
    await page.goto('/studio/customers');
    await page.getByRole('button', { name: 'New customer' }).click();
    const dialog = page.getByRole('dialog');
    const name = dialog.getByLabel('Customer name');
    const phone = dialog.getByLabel(/^Phone\s*\*?$/);
    await phone.fill('03001234567');

    // Bypass native required validation so the server-side validation path is exercised.
    await name.evaluate((element) => element.removeAttribute('required'));
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();

    await expect(name).toHaveAttribute('aria-invalid', 'true');
    await expect(name).toBeFocused();
    await expect(phone).toHaveValue('03001234567');
    await expect(dialog.getByRole('alert')).toContainText(/name.*required/i);
});

test('successful save does not warn and synchronous double submit sends one request', async ({
    page,
}) => {
    await page.goto('/studio/customers');
    await page.getByRole('button', { name: 'New customer' }).click();
    const dialog = page.getByRole('dialog');
    const name = `Submit lock ${Date.now()}`;
    await dialog.getByLabel('Customer name').fill(name);
    await dialog.getByLabel(/^Phone\s*\*?$/).fill('03001234567');

    let posts = 0;
    let unexpectedConfirm = false;
    page.on('request', (request) => {
        if (
            request.method() === 'POST' &&
            new URL(request.url()).pathname === '/studio/customers'
        )
            posts += 1;
    });
    page.on('dialog', async (confirmation) => {
        unexpectedConfirm = true;
        await confirmation.accept();
    });

    await dialog.locator('form').evaluate((form) => {
        form.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        form.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
    });

    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
    expect(posts).toBe(1);
    expect(unexpectedConfirm).toBeFalsy();
});

test('dirty settings block Inertia navigation and preserve the edit when cancelled', async ({
    page,
}) => {
    await page.goto('/studio/configuration');
    const businessName = page.getByLabel('Business name');
    const original = await businessName.inputValue();
    await businessName.fill(`${original} unsaved`);

    page.once('dialog', async (confirmation) => {
        expect(confirmation.type()).toBe('confirm');
        expect(confirmation.message()).toContain('unsaved changes');
        await confirmation.dismiss();
    });
    await page
        .getByRole('link', { name: 'Stitching orders', exact: true })
        .click();

    await expect(page).toHaveURL(/\/studio\/configuration$/);
    await expect(businessName).toHaveValue(`${original} unsaved`);

    // Restore the original client-side value so the test never saves fixture changes.
    await businessName.fill(original);
});

test('logo upload keeps the optimistic version in sync for repeat uploads', async ({
    page,
}) => {
    await page.goto('/studio/configuration');

    const logoInput = page.getByLabel('Upload business logo');
    const uploadButton = page.getByRole('button', {
        name: 'Upload PNG / JPEG',
    });
    const pngA = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAF',
        'base64',
    );
    const pngB = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAcAAAAF',
        'base64',
    );

    await logoInput.setInputFiles({
        name: 'logo-a.png',
        mimeType: 'image/png',
        buffer: pngA,
    });
    await uploadButton.click();
    await expect(page.getByRole('alert')).toContainText('Logo saved');

    await logoInput.setInputFiles({
        name: 'logo-b.png',
        mimeType: 'image/png',
        buffer: pngB,
    });
    await uploadButton.click();

    await expect(page.getByRole('alert')).toContainText('Logo saved');
    await expect(
        page.getByText('This record changed in another window'),
    ).toHaveCount(0);
});

test('report filters preserve named business dimensions in the URL', async ({
    page,
}) => {
    await page.goto('/studio/reports');
    await page.getByLabel('Workflow state').selectOption('New');
    await page.getByLabel('Billing status').selectOption('posted');
    await page.getByText('Advanced date filters').click();
    await page.getByLabel('Order date from').fill('2026-09-01T00:00');
    await page.getByRole('button', { name: 'Run report' }).click();

    await expect(page).toHaveURL(/stage=New/);
    await expect(page).toHaveURL(/billing_status=posted/);
    await expect(page).toHaveURL(/order_from=2026-09-01T00%3A00/);
    await expect(page.getByLabel('Workflow state')).toHaveValue('New');
    await expect(page.getByLabel('Billing status')).toHaveValue('posted');
});

test('native history navigation is covered by the dirty-form guard', async ({
    page,
}) => {
    await page.goto('/studio');
    await page.goto('/studio/configuration');
    const businessName = page.getByLabel('Business name');
    const original = await businessName.inputValue();
    await businessName.fill(`${original} browser-history-edit`);

    let prompted = false;
    page.once('dialog', async (confirmation) => {
        prompted = true;
        await confirmation.dismiss();
    });
    // A dismissed beforeunload cancels navigation, so no DOMContentLoaded follows.
    await page
        .goBack({ waitUntil: 'domcontentloaded', timeout: 2000 })
        .catch(() => null);

    expect(prompted).toBeTruthy();
    await expect(page).toHaveURL(/\/studio\/configuration$/);
    await expect(businessName).toHaveValue(`${original} browser-history-edit`);
    await businessName.fill(original);
});
