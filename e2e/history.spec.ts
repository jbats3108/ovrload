import { expect, test, type Page } from '@playwright/test';

async function loginAsUser(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address/i).fill('user1@test.com');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

async function addHistoricalDumbbellAccessories(page: Page): Promise<void> {
    await page.goto('/history');
    await page.getByRole('link', { name: 'Add historical' }).click();
    await expect(page).toHaveURL(/\/history\/create/);
    await page.getByRole('link', { name: 'Dumbbell Accessories' }).click();
    await expect(page.getByRole('heading', { name: 'Dumbbell Accessories' })).toBeVisible();

    await page.getByLabel(/Finished at/i).fill('2026-08-01T10:00');
    await page.getByRole('checkbox', { name: /Deload/i }).check();
    await page.getByRole('button', { name: 'Continue to sets' }).click();
    await expect(page.getByRole('button', { name: 'Save workout' })).toBeVisible();
    await page.getByRole('button', { name: 'Save workout' }).click();
    await expect(page).toHaveURL(/\/history\/[0-9A-HJKMNP-TV-Z]{26}/i);
}

test.describe('history', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('saves all working set edits with one Save and shows confirmation', async ({ page }) => {
        await addHistoricalDumbbellAccessories(page);

        await expect(page.getByRole('heading', { name: 'Dumbbell Accessories' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled();

        const weight = page.getByLabel('Weight (kg)').first();
        const current = await weight.inputValue();
        const next = String(Number(current) + 2.5);
        await weight.fill(next);

        await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();
        await page.getByRole('button', { name: 'Save', exact: true }).click();

        await expect(page.getByRole('status')).toHaveText('Workout saved.');
        await expect(page.getByLabel('Weight (kg)').first()).toHaveValue(next);
        await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled();
    });
});
