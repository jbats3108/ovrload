/**
 * One-shot: refresh tutorial Play guide screenshots from a live Barbell Strength set.
 * Usage (Sail up, DB seeded): npx playwright test e2e/capture-guide-play.ts --config=playwright.guide.config.ts
 */
import { expect, test } from '@playwright/test';
import path from 'node:path';

const guideDir = path.join(process.cwd(), 'public/images/guide');

test('capture play guide screenshots', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel(/email address/i).fill('user1@test.com');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);

    const abandon = page.getByRole('button', { name: 'Abandon' });
    if (await abandon.isVisible()) {
        await abandon.click();
        const dialog = page.getByRole('dialog');
        await dialog.getByRole('button', { name: 'Abandon' }).click();
        await expect(abandon).not.toBeVisible();
    }

    const card = page.locator('div.rounded-xl.border').filter({ has: page.getByRole('heading', { name: 'Barbell Strength', level: 3 }) });
    await card.getByRole('button', { name: 'Start' }).click();
    await expect(page).toHaveURL(/\/workouts\//);

    // Advance to first working set with plate guide (4 warm-ups + setup + rest).
    for (let i = 0; i < 4; i++) {
        const done = page.getByRole('button', { name: 'Done', exact: true });
        await done.scrollIntoViewIfNeeded();
        await done.click();
        await page.getByRole('button', { name: 'Log set' }).click();
        if (i < 3) {
            await page.getByRole('button', { name: 'Skip', exact: true }).click();
            await page.getByRole('button', { name: 'Skip', exact: true }).click();
        }
    }

    await page.getByRole('button', { name: 'Setup done' }).scrollIntoViewIfNeeded();
    await page.getByRole('button', { name: 'Setup done' }).click();
    await page.getByRole('button', { name: 'Skip', exact: true }).click();
    await page.getByRole('button', { name: 'Skip', exact: true }).click();

    await expect(page.getByText(/working/i)).toBeVisible();
    await expect(page.getByText('Plates', { exact: true })).toBeVisible();

    await page.setViewportSize({ width: 799, height: 937 });
    await page.screenshot({ path: path.join(guideDir, 'play-desktop.png'), fullPage: false });
    await page.screenshot({ path: path.join(guideDir, 'play-mobile.png'), fullPage: false });
});
