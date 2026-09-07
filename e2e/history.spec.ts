import { expect, test, type Page } from '@playwright/test';
import { createAndSaveCircuitRoutine, loginAsUser } from './support';

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

    test('shows circuit workout with collapsed summary and allows expanding', async ({ page }) => {
        const routineName = `Hist CCT ${Date.now()}`;
        await createAndSaveCircuitRoutine(page, routineName);

        // Add historical entry for this circuit routine
        await page.goto('/history');
        await page.getByRole('link', { name: 'Add historical' }).click();
        await expect(page).toHaveURL(/\/history\/create/);

        await page.getByRole('link', { name: routineName }).click();
        await expect(page.getByRole('heading', { name: routineName })).toBeVisible();

        await page.getByLabel(/Finished at/i).fill('2026-09-07T08:00');
        await page.getByRole('button', { name: 'Continue to sets' }).click();
        await expect(page.getByRole('button', { name: 'Save workout' })).toBeVisible();
        await page.getByRole('button', { name: 'Save workout' }).click();

        // If redirected to progression screen, confirm and finish
        await page.waitForTimeout(1000);
        if (page.url().includes('/progression')) {
            await page
                .getByRole('button', { name: /confirm|finish|continue|done/i })
                .first()
                .click();
        }

        // Navigate to history show
        await page.goto('/history');
        await expect(page).toHaveURL(/\/history$/);
        const link = page
            .getByRole('link')
            .filter({ has: page.getByText(routineName) })
            .first();
        await expect(link).toBeVisible();
        await link.click();
        await expect(page).toHaveURL(/\/history\/[0-9A-HJKMNP-TV-Z]{26}/i);

        // History detail view for circuit
        await expect(page.getByText('Circuit', { exact: true })).toBeVisible();
        await expect(page.getByText(/×\d+ rounds/).first()).toBeVisible();
        const detailsBtn = page.getByRole('button', { name: 'Details' }).first();
        await expect(detailsBtn).toBeVisible();
        await detailsBtn.click();
        await expect(page.getByRole('button', { name: 'Collapse' }).first()).toBeVisible();
    });
});
