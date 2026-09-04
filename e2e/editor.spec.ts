import { expect, test, type Page } from '@playwright/test';

async function loginAsUser(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address/i).fill('user1@test.com');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

async function openBarbellEditor(page: Page): Promise<void> {
    await page.goto('/routines/barbell-strength/edit');
    await expect(page).toHaveURL(/\/routines\/barbell-strength\/edit/);
    await expect(page.locator('[data-desktop-routine-settings]')).toBeVisible();
}

test.describe('routine editor', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    test('shows Deload alternate while a profile is assigned', async ({ page }) => {
        await openBarbellEditor(page);

        await expect(page.locator('[data-deload-alternate]').first()).toBeVisible();
        await expect(page.locator('[data-exercise-target]')).toHaveCount(0);
        await expect(page.getByText('Target 6 · Floor 4').first()).toBeVisible();
    });

    test('Customise reveals Target; Cancel restores the profile snapshot', async ({ page }) => {
        await openBarbellEditor(page);

        await page.locator('[data-customise-exercise]').first().click();
        const target = page.locator('[data-exercise-target]').first();
        await expect(target).toBeVisible();
        await expect(target).toHaveValue('6');

        await target.fill('9');
        await page.locator('[data-cancel-customise-exercise]').click();

        await expect(page.locator('[data-exercise-target]')).toHaveCount(0);
        await expect(page.getByText('Target 6 · Floor 4').first()).toBeVisible();
        await expect(page.locator('[data-deload-alternate]').first()).toBeVisible();
    });

    test('Customise shared rest; Cancel restores shared profile', async ({ page }) => {
        await openBarbellEditor(page);

        // First block has a shared profile; later Barbell Strength blocks are seeded Custom (no shared profile).
        await page.locator('[data-customise-shared]').first().click();
        const restInput = page.getByLabel(/Working rest/i).first();
        await expect(restInput).toBeVisible();
        await restInput.fill('45');

        await page.locator('[data-cancel-customise-shared]').first().click();
        await expect(page.locator('[data-cancel-customise-shared]')).toHaveCount(0);
        await expect(page.locator('[data-shared-rest-summary]').first()).toContainText('3m');
    });

    test('Swap A↔B reverses a superset pair', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/routines/superset-pump/edit');
        await expect(page).toHaveURL(/\/routines\/superset-pump\/edit/);

        const swap = page.locator('.md\\:flex [data-swap-superset]').first();
        await expect(swap).toBeVisible();

        const rowA = page
            .locator('tbody tr')
            .filter({ has: page.getByText('A', { exact: true }) })
            .first();
        const kgInput = rowA.locator('input[type="number"]').first();
        await expect(kgInput).toHaveValue('60');

        await swap.click();

        await expect(kgInput).toHaveValue('30');
    });
});
