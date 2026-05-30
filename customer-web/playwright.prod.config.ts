import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e/smoke',
  /* Maximum time one test can run for. */
  timeout: 30 * 1000,
  expect: {
    timeout: 5000,
  },
  fullyParallel: true,
  retries: 2,
  workers: 1, // Run sequentially on production to avoid rate limits
  reporter: 'html',
  use: {
    actionTimeout: 0,
    baseURL: process.env.BASE_URL || 'https://yourdomain.com',
    trace: 'on-first-retry',
    // Do not run destructive tests
    extraHTTPHeaders: {
      'x-smoke-test': 'true',
    },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
