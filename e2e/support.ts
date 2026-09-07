import { expect, type Page } from '@playwright/test';

export async function loginAsUser(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address/i).fill('user1@test.com');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

export async function createAndSaveCircuitRoutine(page: Page, name: string): Promise<void> {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/routines/create');
    await expect(page).toHaveURL(/\/routines\/create/);
    await page.getByLabel('Name').fill(name);
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/\/routines\/[a-z0-9-]+\/edit/);

    const addCircuitBtn = page.locator('[data-add-circuit-btn]');
    await expect(addCircuitBtn).toBeVisible();
    await expect(page.locator('[data-catalog-ready]')).toBeVisible({ timeout: 15_000 });
    await addCircuitBtn.click();
    await expect(page.getByText('CCT', { exact: true })).toBeVisible();

    await page.locator('header').getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('Routine saved.')).toBeVisible({ timeout: 15_000 });
}
