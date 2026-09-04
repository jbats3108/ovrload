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
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await addHistoricalDumbbellAccessories(page);

        await expect(page.getByRole('heading', { name: 'Dumbbell Accessories' })).toBeVisible();
        const save = page.getByRole('button', { name: 'Save', exact: true });
        await expect(save).toBeDisabled();

        const weight = page.getByLabel('Weight (kg)').first();
        const current = await weight.inputValue();
        const next = String(Number(current) + 2.5);
        await weight.click();
        await weight.fill(next);
        await expect(save).toBeEnabled();

        const requestPromise = page.waitForRequest(
            (request) => /\/history\/[0-9A-HJKMNP-TV-Z]{26}/i.test(request.url()) && ['PUT', 'POST'].includes(request.method()),
            { timeout: 15_000 },
        );
        await save.click();

        try {
            const request = await requestPromise;
            expect(pageErrors, `page errors: ${pageErrors.join('; ')}`).toEqual([]);
            expect(['PUT', 'POST']).toContain(request.method());
        } catch (error) {
            throw new Error(`Save did not send a history request. pageErrors=${pageErrors.join('; ') || 'none'}. ${(error as Error).message}`);
        }

        await expect(page.getByText('Workout saved.')).toBeVisible({ timeout: 15_000 });
        await expect(page.getByLabel('Weight (kg)').first()).toHaveValue(next);
        await expect(save).toBeDisabled();
    });
});
