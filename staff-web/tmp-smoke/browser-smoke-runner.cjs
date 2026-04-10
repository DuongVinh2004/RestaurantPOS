const fs = require('fs');
const path = require('path');
const { chromium } = require('C:/Users/Duong Vinh/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright/index.js');

(async () => {
  const root = process.cwd();
  const manifest = JSON.parse(fs.readFileSync(path.resolve(root, '..', 'storage', 'app', 'uat', 'scenario-pack.json'), 'utf8'));
  const staff = manifest.auth.staff;
  const dineIn = manifest.scenarios.dine_in_checkout;
  const tmpDir = path.resolve(root, 'tmp-smoke');
  fs.mkdirSync(tmpDir, { recursive: true });
  const ts = new Date().toISOString().replace(/[:.]/g, '-');
  const screenshotPath = path.join(tmpDir, `staff-web-browser-smoke-${ts}.png`);
  const reportPath = path.join(tmpDir, `staff-web-browser-smoke-${ts}.json`);

  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const consoleErrors = [];
  const pageErrors = [];
  const apiErrors = [];
  const results = [];

  page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', (error) => pageErrors.push(String(error)));
  page.on('response', (response) => {
    const url = response.url();
    if (url.startsWith('http://localhost:8000/api/v1') && response.status() >= 500) {
      apiErrors.push({ url, status: response.status() });
    }
  });

  async function expectText(text) {
    const locator = page.getByText(text, { exact: true }).first();
    await locator.waitFor({ state: 'visible', timeout: 20000 });
  }

  async function step(name, fn) {
    try {
      await fn();
      results.push({ name, ok: true });
      console.log(`PASS ${name}`);
    } catch (error) {
      results.push({ name, ok: false, error: String(error) });
      console.log(`FAIL ${name}: ${String(error)}`);
    }
  }

  await step('open login', async () => {
    await page.goto('http://localhost:4173/login', { waitUntil: 'domcontentloaded' });
    await expectText('Staff login');
  });

  await step('login staff', async () => {
    await page.locator('input[placeholder="host01 or staff@example.com"]').fill(staff.username);
    await page.locator('input[placeholder="Enter password"]').fill(staff.password);
    await page.locator('input[placeholder="staff-web-vite"]').fill('staff-web-browser-smoke');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForFunction(() => window.location.pathname !== '/login', null, { timeout: 20000 });
    await expectText('Table Board');
  });

  const routeChecks = [
    { name: 'tables route', url: '/tables', text: 'Table Board' },
    { name: 'reservations route', url: '/reservations', text: 'Reservation queue' },
    { name: 'orders route', url: `/orders?source=reservation&reservationId=${dineIn.reservation_id}&reservationRowVersion=1&tableId=${dineIn.table_id}`, text: 'Active Order Workspace' },
    { name: 'kitchen route', url: `/kitchen?source=reservation&reservationId=${dineIn.reservation_id}&reservationRowVersion=1&tableId=${dineIn.table_id}`, text: 'Kitchen Board' },
    { name: 'checkout route', url: `/checkout?source=reservation&reservationId=${dineIn.reservation_id}&reservationRowVersion=1&tableId=${dineIn.table_id}`, text: 'Checkout Workspace' },
    { name: 'cashier shift route', url: '/cashier-shift', text: 'Cashier Shift Center' },
    { name: 'finance review route', url: '/finance-review', text: 'Reconciliation and invoice review' },
    { name: 'conversations route', url: '/conversations', text: 'Operational conversation queue' },
    { name: 'audit trail route', url: '/audit-trail', text: 'Operational audit review' },
    { name: 'reporting route', url: '/reporting', text: 'Daily operational snapshots' },
  ];

  for (const route of routeChecks) {
    await step(route.name, async () => {
      await page.goto(`http://localhost:4173${route.url}`, { waitUntil: 'domcontentloaded' });
      await expectText(route.text);
    });
  }

  await page.screenshot({ path: screenshotPath, fullPage: true });
  await browser.close();

  const report = {
    generated_at_utc: new Date().toISOString(),
    ok: results.every((item) => item.ok) && consoleErrors.length === 0 && pageErrors.length === 0 && apiErrors.length === 0,
    results,
    consoleErrors,
    pageErrors,
    apiErrors,
    screenshotPath,
  };
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(`REPORT ${reportPath}`);
  console.log(`SCREENSHOT ${screenshotPath}`);
  if (!report.ok) process.exitCode = 1;
})();

