import { chromium, devices } from 'playwright';
import path from 'path';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext(devices['iPhone 13']);
  const page = await context.newPage();

  const outDir = 'C:/Users/Duong Vinh/.gemini/antigravity/brain/564c59bc-db69-4e18-b27b-faa1fe9c18ba/scratch';

  const screens = [
    { name: 'home', url: 'https://mocsen-bistro.ddns.net' },
    { name: 'menu', url: 'https://mocsen-bistro.ddns.net/menu' },
    { name: 'booking', url: 'https://mocsen-bistro.ddns.net/booking' },
    { name: 'reservations', url: 'https://mocsen-bistro.ddns.net/reservations' },
    { name: 'waitlist', url: 'https://mocsen-bistro.ddns.net/waiting-list' },
    { name: 'account', url: 'https://mocsen-bistro.ddns.net/account' },
    { name: 'login', url: 'https://mocsen-bistro.ddns.net/auth/login' }
  ];

  for (const screen of screens) {
    console.log(`Taking ${screen.name} screenshot...`);
    await page.goto(screen.url);
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(outDir, `${screen.name}_v2.png`), fullPage: true });
  }

  await browser.close();
  console.log("Done!");
})();
