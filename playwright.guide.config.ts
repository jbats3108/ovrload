import { defineConfig, devices } from '@playwright/test';

/** Isolated config so guide capture is not part of npm run test:e2e. */
export default defineConfig({
    testDir: 'e2e',
    testMatch: /capture-guide-.*\.ts/,
    outputDir: '/tmp/ovrload-guide-test-results',
    fullyParallel: false,
    retries: 0,
    workers: 1,
    timeout: 120_000,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'off',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
