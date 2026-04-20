import { defineConfig, devices } from "@playwright/test";

const appHost = process.env.CUSTOMER_WEB_LIVE_APP_HOST ?? "127.0.0.1";
const appPort = process.env.CUSTOMER_WEB_LIVE_APP_PORT ?? "3000";
const baseURL = process.env.CUSTOMER_WEB_LIVE_BASE_URL ?? `http://${appHost}:${appPort}`;

export default defineConfig({
  testDir: "./e2e",
  testMatch: /customer-live\.spec\.ts/,
  timeout: 120_000,
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? "github" : "list",
  use: {
    baseURL,
    trace: "retain-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: {
        ...devices["Desktop Chrome"],
      },
    },
  ],
});
