import { chromium, devices } from 'playwright';
import path from 'path';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext(devices['iPhone 13']);
  const page = await context.newPage();

  const outDir = 'C:/Users/Duong Vinh/.gemini/antigravity/brain/564c59bc-db69-4e18-b27b-faa1fe9c18ba/scratch';

  console.log("Taking Home Page screenshot...");
  await page.goto('https://mocsen-bistro.ddns.net');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: path.join(outDir, 'home.png'), fullPage: true });

  console.log("Taking Menu Page screenshot...");
  await page.goto('https://mocsen-bistro.ddns.net/menu');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: path.join(outDir, 'menu.png'), fullPage: true });

  console.log("Taking Booking Page screenshot...");
  await page.goto('https://mocsen-bistro.ddns.net/booking');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: path.join(outDir, 'booking.png'), fullPage: true });

  console.log("Taking Login Page screenshot...");
  await page.goto('https://mocsen-bistro.ddns.net/auth/login');
  await page.waitForTimeout(3000);
  await page.screenshot({ path: path.join(outDir, 'login.png'), fullPage: true });

  await browser.close();
  console.log("Done!");
})();
