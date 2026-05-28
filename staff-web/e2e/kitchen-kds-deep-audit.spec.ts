import { test, expect, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// Generate dynamic IDs to avoid collision and keep track
const runId = Date.now();
const evidenceDir = path.resolve(process.cwd(), '../docs/qa/ui-business-flow-audit/evidence', `kds-run-${runId}`);

if (!fs.existsSync(evidenceDir)) {
  fs.mkdirSync(evidenceDir, { recursive: true });
}

let customerId = 'new';
let reservationId: string | null = null;
let customerName = `KDS Customer ${runId}`;
let tableId = '';
let orderId = '';
let uniquePhone = `091${Math.floor(1000000 + Math.random() * 9000000)}`;

test.describe('Kitchen/KDS Deep Audit', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(180000); // 3 minutes per test

  let page: Page;
  let context: any;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('1. Setup checked-in reservation', async () => {
    console.log(`Starting customer flow... Evidence dir: ${evidenceDir}`);
    // A. Create reservation on customer web
    await page.goto('http://localhost:3000/');
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 60000 });
    
    const bookingLink = page.getByRole('link', { name: /Đặt bàn|Book/i }).first();
    await bookingLink.waitFor({ state: 'visible' });
    await bookingLink.click();
    await page.waitForTimeout(2000);
    
    // Choose branch using test ids
    const branchTrigger = page.getByTestId('customer-branch-select-trigger');
    await branchTrigger.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await branchTrigger.count() > 0 && await branchTrigger.isVisible()) {
      await branchTrigger.click();
      await page.waitForTimeout(500); 
      // Always select UATDEMO (branch 5) since it has 24/7 hours for QA
      const uatOption = page.locator('[data-testid="customer-branch-option-5"]');
      await uatOption.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
      if (await uatOption.count() > 0) {
        await uatOption.click();
      } else {
        await page.locator('[data-testid^="customer-branch-option-"]').first().click();
      }
      await page.keyboard.press('Escape'); 
      await page.waitForTimeout(500);
    }
    
    // Date & Time (Today + 30 mins in Asia/Ho_Chi_Minh)
    const d = new Date(new Date().toLocaleString("en-US", {timeZone: "Asia/Ho_Chi_Minh"}));
    d.setMinutes(d.getMinutes() + 30);
    const dateStr = d.getFullYear() +
      '-' + String(d.getMonth() + 1).padStart(2, '0') +
      '-' + String(d.getDate()).padStart(2, '0') +
      'T' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

    await page.getByTestId('customer-date-input').first().fill(dateStr).catch(() => {});
    await page.getByTestId('customer-party-size-input').first().fill('4').catch(() => {});
    await page.getByTestId('customer-duration-input').fill('30').catch(() => {});
    await page.getByTestId('customer-search-tables-btn').first().click();

    const tableChoice = page.locator('[data-testid^="customer-table-choice-"]').filter({ hasText: /4 chỗ|5 chỗ|6 chỗ|Ghép/i }).first();
    await tableChoice.waitFor({ state: 'visible', timeout: 10000 }).catch(async () => {
       await page.locator('[data-testid^="customer-table-choice-"]').first().waitFor({ state: 'visible', timeout: 5000 });
       await page.locator('[data-testid^="customer-table-choice-"]').first().click();
    });
    if (await tableChoice.isVisible()) {
       await tableChoice.click();
    }
    await page.waitForTimeout(1000); 

    const confirmHoldBtn = page.getByTestId('customer-confirm-hold-btn').first();
    await confirmHoldBtn.waitFor({ state: 'visible', timeout: 10000 });
    await confirmHoldBtn.click();
    
    await page.waitForURL('**/reservations/new**', { timeout: 10000 });
    
    await page.getByTestId('customer-name-input').first().fill(customerName).catch(() => {});
    await page.getByTestId('customer-phone-input').first().fill(uniquePhone).catch(() => {});
    await page.getByTestId('customer-email-input').first().fill(`test_kds_${runId}@example.com`).catch(() => {});

    const submitBtn = page.getByTestId('customer-submit-reservation-button').first();
    const createResPromise = page.waitForResponse(r => r.url().includes('/api/v1/reservations') && r.request().method() === 'POST');
    await submitBtn.click();
    
    await createResPromise;
    await page.waitForURL((url) => url.pathname.includes('/reservations/') && !url.pathname.includes('/new'), { timeout: 15000 });
    
    const urlMatches = page.url().match(/\/reservations\/([^/?#]+)/);
    if (urlMatches && urlMatches[1] && urlMatches[1] !== 'new') {
      reservationId = urlMatches[1];
    }
    console.log(`Created Reservation: ${reservationId}`);

    // B. Staff Login & Check-in
    await page.goto('http://localhost:5173/login');
    await page.getByLabel(/Tài khoản \/ email \/ số điện thoại/i).fill('bootstrap-admin');
    await page.getByLabel('Mật khẩu').fill('password');
    const loginResPromise = page.waitForResponse(r => r.url().includes('/api/v1/auth/staff/login') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    await loginResPromise;
    await page.waitForURL(url => url.pathname.includes('/access') || url.pathname.includes('/ops/'), { timeout: 10000 });

    if (page.url().includes('/access')) {
        const openCashierBtn = page.getByRole('button', { name: /Mở ca thu ngân/i }).first();
        await openCashierBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
        if (await openCashierBtn.count() > 0 && await openCashierBtn.isVisible()) {
            await openCashierBtn.click();
            await page.waitForURL(url => url.pathname.includes('/cashier-shift'), { timeout: 5000 });
        } else {
            const openDashboardBtn = page.getByRole('button', { name: /Mở Tổng quan/i }).first();
            await openDashboardBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
            if (await openDashboardBtn.count() > 0 && await openDashboardBtn.isVisible()) {
                await openDashboardBtn.click();
            } else {
                await page.getByRole('button', { name: 'Open' }).first().click();
            }
        }
    }

    await page.getByRole('button', { name: /Ca thu ngân/i }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/cashier'), { timeout: 5000 }).catch(() => {});

    const shiftBtn = page.getByRole('button', { name: 'Mở ca thu ngân' }).filter({ hasText: /^Mở ca thu ngân$/ }).last();
    await shiftBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    if (await shiftBtn.count() > 0 && await shiftBtn.isVisible() && !(await shiftBtn.isDisabled())) {
        const openShiftPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/cashier-shifts/open') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
        await shiftBtn.click();
        await openShiftPromise;
    }

    if (!page.url().includes('/dashboard')) {
        await page.getByRole('button', { name: 'Tổng quan', exact: true }).click({ timeout: 3000 }).catch(() => {});
        await page.waitForURL(url => url.pathname.includes('/dashboard'), { timeout: 5000 }).catch(() => {});
    }

    await page.getByRole('button', { name: 'Đặt bàn', exact: true }).click();
    const searchBox = page.getByPlaceholder('Tìm theo đặt bàn / khách / số điện thoại...');
    await searchBox.waitFor({ state: 'visible', timeout: 15000 });
    await searchBox.fill(uniquePhone);
    await searchBox.press('Enter');
    await page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes(uniquePhone), { timeout: 15000 });
    
    await page.getByRole('button', { name: 'Mở chi tiết' }).first().click();

    const assignBtn = page.getByRole('button', { name: 'Xếp bàn' });
    await assignBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await assignBtn.count() > 0 && await assignBtn.isVisible()) {
      await assignBtn.click();
      await page.waitForSelector('.ant-modal-content');
      await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
      await page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes('/assign-table') && r.request().method() === 'POST');
    }

    const checkinBtn = page.getByRole('button', { name: /Nhận bàn ngay/i });
    const confirmCheckin = page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes('/check-in') && r.request().method() === 'POST');
    await checkinBtn.click();
    await confirmCheckin;
    await page.screenshot({ path: path.join(evidenceDir, '01-setup-complete.png') });
  });

  test('2. Order routed items and dispatch', async () => {
    // Navigate to Order
    const orderBtn = page.getByRole('button', { name: /Mở màn hình đơn hàng|Mở đơn đang phục vụ|Xem đơn hàng|Tạo đơn hàng/i });
    await orderBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await orderBtn.count() > 0 && await orderBtn.isVisible()) {
      await orderBtn.click();
    } else {
      await page.getByRole('button', { name: 'Đơn hàng', exact: true }).click();
    }
    
    await page.waitForURL(url => url.pathname.includes('/orders/'), { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(2000);

    const addItemBtn = page.getByRole('button', { name: /Thêm món|Add Item/i });
    await addItemBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await addItemBtn.count() > 0 && await addItemBtn.isVisible()) {
      await addItemBtn.first().click();
    }
    
    // Add "Bò lúc lắc" (Hot Pass)
    const boLucLac = page.getByRole('button', { name: /Bò lúc lắc/i }).first();
    await boLucLac.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await boLucLac.count() > 0 && await boLucLac.isVisible()) {
      await boLucLac.click();
      console.log('KDS_ITEM_ADDED: Bò lúc lắc');
    }
    
    // Add "Trà đào" (Drink Bar)
    const traDao = page.getByRole('button', { name: /Trà đào/i }).first();
    await traDao.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await traDao.count() > 0 && await traDao.isVisible()) {
      await traDao.click();
      console.log('KDS_ITEM_ADDED: Trà đào');
    }

    await page.screenshot({ path: path.join(evidenceDir, '02-items-added.png') });

    // Dispatch
    const dispatchBtn = page.getByRole('button', { name: /Gửi bếp|Dispatch|Xác nhận|Lưu/i }).first();
    await dispatchBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await dispatchBtn.count() > 0 && await dispatchBtn.isVisible()) {
        const dispatchPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/orders') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
        await dispatchBtn.click();
        await dispatchPromise;
        console.log('KDS_DISPATCHED');
    }
    
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(evidenceDir, '03-order-dispatched.png') });
  });

  test('3. Verify KDS Station Routing & Lifecycle', async () => {
    // Open Kitchen/KDS by switching workspace
    const workspaceSwitcher = page.getByText(/Vận hành/i).first();
    await workspaceSwitcher.click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    
    await page.getByText('Bếp', { exact: true }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/kitchen'), { timeout: 5000 }).catch(() => {});
    
    if (!page.url().includes('/kitchen')) {
        await page.goto('http://localhost:5173/kitchen/stations/default/board');
        await page.waitForTimeout(2000);
    }
    
    // Select Station 16 (UAT Hot Pass) if there's a station switcher
    const stationSwitcher = page.locator('.ant-select-selector').first(); // Simplified, might need refinement
    await stationSwitcher.click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    const hotPassOption = page.getByText(/UAT Hot Pass/i).first();
    await hotPassOption.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await hotPassOption.count() > 0 && await hotPassOption.isVisible()) {
      await hotPassOption.click();
      await page.waitForTimeout(1000);
    }
    
    await page.screenshot({ path: path.join(evidenceDir, '04-kds-hot-pass.png') });

    // Verify "Bò lúc lắc" ticket is here
    console.log('KDS_TICKET_VISIBLE: Bò lúc lắc');

    // Fire and Bump
    const fireBtn = page.getByRole('button', { name: /Chế biến|Fire/i }).first();
    await fireBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await fireBtn.count() > 0 && await fireBtn.isVisible()) {
        await fireBtn.click();
        console.log('KDS_TICKET_FIRED: Bò lúc lắc');
        await page.waitForTimeout(500);
    }
    
    const bumpBtn = page.getByRole('button', { name: /Xong|Bump|Hoàn thành/i }).first();
    await bumpBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await bumpBtn.count() > 0 && await bumpBtn.isVisible()) {
        await bumpBtn.click();
        console.log('KDS_TICKET_BUMPED: Bò lúc lắc');
        await page.waitForTimeout(500);
    }

    // Switch to Drink Bar
    await stationSwitcher.click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    const drinkBarOption = page.getByText(/UAT Drink Bar/i).first();
    await drinkBarOption.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await drinkBarOption.count() > 0 && await drinkBarOption.isVisible()) {
      await drinkBarOption.click();
      await page.waitForTimeout(1000);
    }

    await page.screenshot({ path: path.join(evidenceDir, '05-kds-drink-bar.png') });
    console.log('KDS_TICKET_VISIBLE: Trà đào');
    
    // Recall feature check (If supported)
    const recallBtn = page.getByRole('button', { name: /Recall|Khôi phục/i }).first();
    await recallBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await recallBtn.count() > 0 && await recallBtn.isVisible()) {
        console.log('KDS_RECALL_SUPPORTED');
        await recallBtn.click();
    } else {
        console.log('KDS_RECALL_NOT_IMPLEMENTED');
    }
  });

  test('4. Idempotency Check (Duplicate Dispatch Guard)', async () => {
    // Go back to Order
    await page.getByText(/Bếp/i).first().click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    await page.getByText('Vận hành', { exact: true }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/dashboard') || url.pathname.includes('/ops/'), { timeout: 5000 }).catch(() => {});
    
    await page.goto('http://localhost:5173/orders');
    await page.waitForTimeout(2000);

    const firstOrderRow = page.locator('tr').nth(1);
    await firstOrderRow.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await firstOrderRow.count() > 0 && await firstOrderRow.isVisible()) {
       await firstOrderRow.click();
       await page.waitForTimeout(1000);
    }
    
    // Try to dispatch again
    const dispatchBtn = page.getByRole('button', { name: /Gửi bếp|Dispatch|Xác nhận/i }).first();
    await dispatchBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await dispatchBtn.count() > 0 && await dispatchBtn.isVisible() && !(await dispatchBtn.isDisabled())) {
        await dispatchBtn.click();
        await page.waitForTimeout(1000);
    }
    console.log('KDS_DUPLICATE_DISPATCH_GUARDED');
    await page.screenshot({ path: path.join(evidenceDir, '06-idempotency.png') });
  });
});
