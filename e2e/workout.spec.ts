import { expect, test, type Page } from '@playwright/test';

async function loginAsUser(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address/i).fill('user1@test.com');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

async function clearInProgressWorkout(page: Page): Promise<void> {
    const abandon = page.getByRole('button', { name: 'Abandon' });
    if (await abandon.isVisible()) {
        await abandon.click();
        const dialog = page.getByRole('dialog');
        await expect(dialog.getByRole('heading', { name: 'Abandon this workout?' })).toBeVisible();
        await dialog.getByRole('button', { name: 'Abandon' }).click();
        await expect(abandon).not.toBeVisible();
    }
}

async function startBarbellStrength(page: Page): Promise<void> {
    const card = page.locator('div.rounded-xl.border').filter({ has: page.getByRole('heading', { name: 'Barbell Strength', level: 3 }) });
    await card.getByRole('button', { name: 'Start' }).click();
    await expect(page).toHaveURL(/\/workouts\/[0-9A-HJKMNP-TV-Z]{26}/i);
}

async function completeCurrentSet(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Log set' })).toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: 'Log set' }).click();
    await expect(page.locator('header .font-mono')).toHaveText(/\d+\/\d+/, { timeout: 30_000 });
}

async function skipRest(page: Page): Promise<void> {
    await expect(page.getByRole('button', { name: 'Skip', exact: true })).toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: 'Skip', exact: true }).click();
    await expect(page.getByText('Skip rest?')).toBeVisible();
    await page.getByRole('button', { name: 'Skip', exact: true }).click();
}

test.describe('workout player', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
        await clearInProgressWorkout(page);
    });

    test('starts routine and opens player', async ({ page }) => {
        await startBarbellStrength(page);
        await expect(page.getByRole('heading', { name: 'Barbell Strength' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Finish', exact: true })).toBeVisible();
    });

    test('completes a warm-up set', async ({ page }) => {
        await startBarbellStrength(page);
        await expect(page.getByText(/warm-up/i)).toBeVisible();
        await completeCurrentSet(page);
        await expect(page.locator('header .font-mono')).toHaveText(/1\/\d+/);
    });

    test('shows rest timer and can skip', async ({ page }) => {
        await startBarbellStrength(page);
        await completeCurrentSet(page);
        await expect(page.getByText('Rest', { exact: true })).toBeVisible();
        await skipRest(page);
        await expect(page.getByText(/warm-up/i)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Done', exact: true })).toBeVisible();
    });

    test('prompts setup after warm-up block', async ({ page }) => {
        await startBarbellStrength(page);
        for (let i = 0; i < 2; i++) {
            await completeCurrentSet(page);
            await skipRest(page);
        }
        await completeCurrentSet(page);
        await expect(page.getByRole('button', { name: 'Setup done' })).toBeVisible();
        await expect(page.getByText(/before working sets/i)).toBeVisible();
        await page.getByRole('button', { name: 'Setup done' }).click();
        await skipRest(page);
        await expect(page.getByText(/working/i)).toBeVisible();
    });
});
