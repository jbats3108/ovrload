import { expect, test } from '@playwright/test';

test.describe('dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel(/email address/i).fill('user1@test.com');
        await page.getByLabel(/^password$/i).fill('password');
        await page.getByRole('button', { name: /log in/i }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('shows seeded routines', async ({ page }) => {
        await expect(page.getByText('Barbell Strength')).toBeVisible();
    });

    test('opens routine editor', async ({ page }) => {
        await page.getByLabel('Edit routine').first().click();
        await expect(page).toHaveURL(/\/routines\/[a-z0-9-]+\/edit/);
        await expect(page.locator('[data-desktop-routine-settings]')).toBeVisible();
        await expect(page.locator('[data-deload-alternate]').first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Customise' }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save' }).first()).toBeVisible();
    });

    test('requires a profile when creating a routine', async ({ page }) => {
        await page.goto('/routines/create');

        await expect(page.getByLabel('Training profile')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Continue' })).toBeDisabled();

        await page.getByLabel('Name').fill('E2E Profile Routine');
        await expect(page.getByRole('button', { name: 'Continue' })).toBeEnabled();
    });
});

test.describe('admin exercise profiles', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel(/email address/i).fill('admin1@test.com');
        await page.getByLabel(/^password$/i).fill('password');
        await page.getByRole('button', { name: /log in/i }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('creates and removes a preset draft', async ({ page }) => {
        await page.goto('/admin/exercise-profiles');
        await page.getByRole('button', { name: 'New draft' }).click();
        await page.getByLabel(/base name/i).fill('E2E Preset');
        await page.getByRole('button', { name: 'Save draft' }).click();

        await expect(page.getByRole('heading', { name: 'E2E Preset' })).toBeVisible();
        await page.getByRole('button', { name: 'Delete' }).first().click();
        await page.getByRole('button', { name: 'Delete draft' }).click();
        await expect(page.getByText('No draft profiles yet.')).toBeVisible();
    });
});
