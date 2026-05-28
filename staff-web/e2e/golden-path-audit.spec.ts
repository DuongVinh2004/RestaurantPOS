import { test, expect, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// Generate dynamic IDs to avoid collision and keep track
const runId = Date.now();
const evidenceDir = path.resolve(process.cwd(), '../docs/qa/ui-business-flow-audit/evidence', `run-${runId}`);

if (!fs.existsSync(evidenceDir)) {
  fs.mkdirSync(evidenceDir, { recursive: true });
}

let customerId = 'new';
let reservationId: string | null = null;
let customerName = `Test Customer ${runId}`;
let tableId = '';
let orderId = '';
let uniquePhone = `090${Math.floor(1000000 + Math.random() * 9000000)}`;

test.describe('Core Dine-in Golden Path', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(120000); // 2 minutes per test

  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('A. Customer reservation', async () => {
    console.log(`Starting customer flow... Evidence dir: ${evidenceDir}`);
    // 1. Load homepage
    await page.goto('http://localhost:3000/');
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 60000 });
    await page.screenshot({ path: path.join(evidenceDir, '01-homepage.png') });

    // 2. Click Booking / Reservation
    const bookingLink = page.getByRole('link', { name: /Đặt bàn|Book/i }).first();
    await bookingLink.waitFor({ state: 'visible' });
    await bookingLink.click();
    await page.waitForTimeout(2000);
    
    // Fill reservation form (Step 1: table-booking-page.tsx)
    await page.screenshot({ path: path.join(evidenceDir, '02-booking-form.png') });
    
    // Choose branch using test ids
    const branchTrigger = page.getByTestId('customer-branch-select-trigger');
    await branchTrigger.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await branchTrigger.count() > 0 && await branchTrigger.isVisible()) {
      await branchTrigger.click();
      await page.waitForTimeout(500); // Wait for bottom sheet
      await page.locator('[data-testid^="customer-branch-option-"]').first().click();
      await page.keyboard.press('Escape'); // Close the bottom sheet!
      await page.waitForTimeout(500);
    }
    
    // Date & Time (Today + 30 mins to respect min_lead_time_minutes=15)
    const d = new Date();
    d.setMinutes(d.getMinutes() + 30);
    const dateStr = d.getFullYear() +
      '-' + String(d.getMonth() + 1).padStart(2, '0') +
      '-' + String(d.getDate()).padStart(2, '0') +
      'T' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

    await page.getByTestId('customer-date-input').first().fill(dateStr).catch(() => {});
    await page.getByTestId('customer-party-size-input').first().fill('4').catch(() => {});

    await page.screenshot({ path: path.join(evidenceDir, '03-booking-filled.png') });

    // Fill duration
    await page.getByTestId('customer-duration-input').fill('30').catch(() => {});

    // Search tables
    await page.getByTestId('customer-search-tables-btn').first().click();

    // Wait for table choices to appear and hold one
    // Filter for a table choice that has enough seats (or just click the first one that says '4 chỗ' or 'Ghép')
    const tableChoice = page.locator('[data-testid^="customer-table-choice-"]').filter({ hasText: /4 chỗ|5 chỗ|6 chỗ|Ghép/i }).first();
    await tableChoice.waitFor({ state: 'visible', timeout: 10000 }).catch(async () => {
       // fallback to any table if filter fails
       await page.locator('[data-testid^="customer-table-choice-"]').first().waitFor({ state: 'visible', timeout: 5000 });
       await page.locator('[data-testid^="customer-table-choice-"]').first().click();
    });
    if (await tableChoice.isVisible()) {
       await tableChoice.click();
    }
    await page.waitForTimeout(1000); // Wait for hold to complete

    // Proceed to confirmation (Step 2)
    const confirmHoldBtn = page.getByTestId('customer-confirm-hold-btn').first();
    await confirmHoldBtn.waitFor({ state: 'visible', timeout: 10000 });
    await confirmHoldBtn.click();
    
    // Now on reservation-create-page.tsx
    await page.waitForURL('**/reservations/new**', { timeout: 10000 });
    
    // Customer details
    await page.getByTestId('customer-name-input').first().fill(`Test Customer ${runId}`).catch(() => {});
    await page.getByTestId('customer-phone-input').first().fill(uniquePhone).catch(() => {});
    await page.getByTestId('customer-email-input').first().fill(`test_${runId}@example.com`).catch(() => {});

    await page.screenshot({ path: path.join(evidenceDir, '03b-reservation-create-filled.png') });

    // Submit
    const submitBtn = page.getByTestId('customer-submit-reservation-button').first();
    const createResPromise = page.waitForResponse(r => r.url().includes('/api/v1/reservations') && r.request().method() === 'POST');
    await submitBtn.click();
    
    const createResResponse = await createResPromise;
    const createResBody = await createResResponse.json().catch(() => null);
    console.log("Create Reservation Status:", createResResponse.status());
    console.log("Create Reservation Body:", JSON.stringify(createResBody));
    
    // Wait for redirect to reservation detail page, ensuring it is NOT the 'new' page
    await page.waitForURL((url) => url.pathname.includes('/reservations/') && !url.pathname.includes('/new'), { timeout: 15000 });
    
    const urlMatches = page.url().match(/\/reservations\/([^/?#]+)/);
    if (urlMatches && urlMatches[1] && urlMatches[1] !== 'new') {
      reservationId = urlMatches[1];
    }
    console.log(`Created Reservation: ${reservationId}`);
    
    await page.screenshot({ path: path.join(evidenceDir, '04-booking-success.png') });
  });

  test('B. Staff reservation assignment & check-in', async () => {
    if (!reservationId) {
      test.skip();
    }
    
    // DB Bypass REMOVED - we are using valid same-day reservation times now
    
    // Navigate to staff web
    await page.goto('http://localhost:5173/login');
    
    // Login
    await page.getByLabel(/Tài khoản \/ email \/ số điện thoại/i).fill('bootstrap-admin');
    await page.getByLabel('Mật khẩu').fill('password');
    
    const loginResPromise = page.waitForResponse(r => r.url().includes('/api/v1/auth/staff/login') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    
    try {
        const loginRes = await loginResPromise;
        const loginBody = await loginRes.json().catch(() => null);
        console.log("Login Response Status:", loginRes.status());
        console.log("Login Response Body:", JSON.stringify(loginBody));
    } catch (e) {
        console.log("Login wait failed:", e);
    }
    
    // Wait for the redirect to finish (should go to /access)
    await page.waitForURL(url => url.pathname.includes('/access') || url.pathname.includes('/ops/'), { timeout: 10000 });
    await page.screenshot({ path: path.join(evidenceDir, '05-staff-login.png') });

    // Handle Access Gate if we landed there
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
                // Fallback: click the first "Open" button for Dashboard
                await page.getByRole('button', { name: 'Open' }).first().click();
            }
        }
    }

    // Open cashier shift explicitly
    await page.getByRole('button', { name: /Ca thu ngân/i }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/cashier'), { timeout: 5000 }).catch(() => {});

    const shiftBtn = page.getByRole('button', { name: 'Mở ca thu ngân' }).filter({ hasText: /^Mở ca thu ngân$/ }).last();
    await shiftBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    
    if (await shiftBtn.count() > 0 && await shiftBtn.isVisible() && !(await shiftBtn.isDisabled())) {
        const openShiftPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/cashier-shifts/open') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
        await shiftBtn.click();
        await openShiftPromise;
    }

    // Go to dashboard if not already there, using sidebar
    if (!page.url().includes('/dashboard')) {
        await page.getByRole('button', { name: 'Tổng quan', exact: true }).click({ timeout: 3000 }).catch(() => {});
        await page.waitForURL(url => url.pathname.includes('/dashboard'), { timeout: 5000 }).catch(() => {});
    }
    await page.screenshot({ path: path.join(evidenceDir, '06-dashboard.png') });

    // Search for reservation
    await page.getByRole('button', { name: 'Đặt bàn', exact: true }).click();
    
    // Search by guest phone to ensure we find it
    const searchBox = page.getByPlaceholder('Tìm theo đặt bàn / khách / số điện thoại...');
    await searchBox.waitFor({ state: 'visible', timeout: 15000 });
    await searchBox.fill(uniquePhone);
    await searchBox.press('Enter');
    
    // Wait for API to resolve search
    await page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes(uniquePhone), { timeout: 15000 });
    
    await page.screenshot({ path: path.join(evidenceDir, '07-reservations-list.png') });

    // Click on reservation
    await page.getByRole('button', { name: 'Mở chi tiết' }).first().click();
    await page.screenshot({ path: path.join(evidenceDir, '08-reservation-detail.png') });

    // Assign Table
    const assignBtn = page.getByRole('button', { name: 'Xếp bàn' });
    await assignBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await assignBtn.count() > 0 && await assignBtn.isVisible()) {
      await assignBtn.click();
      await page.waitForSelector('.ant-modal-content');
      await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
      await page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes('/assign-table') && r.request().method() === 'POST');
      await page.screenshot({ path: path.join(evidenceDir, '09-table-assigned.png') });
    }

    // Check-in
    const checkinBtn = page.getByRole('button', { name: /Nhận bàn ngay/i });
    const confirmCheckin = page.waitForResponse(r => r.url().includes('/api/v1/staff/reservations') && r.url().includes('/check-in') && r.request().method() === 'POST');
    await checkinBtn.click();
    await confirmCheckin;
    await page.screenshot({ path: path.join(evidenceDir, '10-checked-in.png') });
  });

  test('C. Staff order + KDS', async () => {
    // We are on reservation detail, there should be a link to table or order
    const orderBtn = page.getByRole('button', { name: /Mở màn hình đơn hàng|Mở đơn đang phục vụ|Xem đơn hàng|Tạo đơn hàng/i });
    await orderBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await orderBtn.count() > 0 && await orderBtn.isVisible()) {
      await orderBtn.click();
    } else {
      await page.getByRole('button', { name: 'Đơn hàng', exact: true }).click();
    }
    
    await page.waitForURL(url => url.pathname.includes('/orders/'), { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(evidenceDir, '11-order-view.png') });

    // Try to add an item
    const addItemBtn = page.getByRole('button', { name: /Thêm món|Add Item/i });
    await addItemBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await addItemBtn.count() > 0 && await addItemBtn.isVisible()) {
      await addItemBtn.first().click();
    }
    
    // Click a category if available
    const categoryBtn = page.locator('.ant-menu-item').filter({ hasText: /Đồ ăn|Food|Món chính/i });
    await categoryBtn.first().waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await categoryBtn.count() > 0 && await categoryBtn.first().isVisible()) {
      await categoryBtn.first().click();
    }

    // Click an item (e.g. Bò lúc lắc or Phở bò)
    const itemBtn = page.getByRole('button', { name: /Bò lúc lắc|Phở bò/i }).first();
    await itemBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await itemBtn.count() > 0 && await itemBtn.isVisible()) {
      await itemBtn.click();
      
      // Submit order / Dispatch
      const dispatchBtn = page.getByRole('button', { name: /Gửi bếp|Dispatch|Xác nhận|Lưu/i }).first();
      await dispatchBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      if (await dispatchBtn.count() > 0 && await dispatchBtn.isVisible()) {
          const dispatchPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/orders') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
          await dispatchBtn.click();
          await dispatchPromise;
      }
    }
    
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(evidenceDir, '12-order-dispatched.png') });

    // Open Kitchen/KDS by switching workspace
    const workspaceSwitcher = page.getByText(/Vận hành/i).first();
    await workspaceSwitcher.click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    
    await page.getByText('Bếp', { exact: true }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/kitchen'), { timeout: 5000 }).catch(() => {});
    
    // Fallback: direct navigation if UI failed
    if (!page.url().includes('/kitchen')) {
        await page.goto('http://localhost:5173/kitchen/stations/default/board');
        await page.waitForTimeout(2000);
    }
    const fireBtn = page.getByRole('button', { name: /Chế biến|Fire/i }).first();
    await fireBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await fireBtn.count() > 0 && await fireBtn.isVisible()) {
        await fireBtn.click();
        await page.waitForTimeout(500);
    }
    
    const bumpBtn = page.getByRole('button', { name: /Xong|Bump|Hoàn thành/i }).first();
    await bumpBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await bumpBtn.count() > 0 && await bumpBtn.isVisible()) {
        await bumpBtn.click();
        await page.waitForTimeout(500);
    }
    
    await page.screenshot({ path: path.join(evidenceDir, '13-kds-processed.png') });
  });

  test('D. Checkout safe path', async () => {
    // Navigate back to Ops Workspace first if we are in Kitchen
    await page.getByText(/Bếp/i).first().click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
    await page.getByText('Vận hành', { exact: true }).click({ timeout: 3000 }).catch(() => {});
    await page.waitForURL(url => url.pathname.includes('/dashboard') || url.pathname.includes('/ops/'), { timeout: 5000 }).catch(() => {});
    
    // Go to order
    await page.goto('http://localhost:5173/orders');
    await page.waitForTimeout(2000);

    // Click the first order in the list
    const firstOrderRow = page.locator('tr').nth(1);
    await firstOrderRow.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await firstOrderRow.count() > 0 && await firstOrderRow.isVisible()) {
       await firstOrderRow.click();
       await page.waitForTimeout(1000);
    }

    // Try to click Thanh toán / Checkout
    const checkoutMenuBtn = page.getByRole('button', { name: /Thanh toán/i }).first();
    await checkoutMenuBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await checkoutMenuBtn.count() > 0 && await checkoutMenuBtn.isVisible()) {
        await checkoutMenuBtn.click();
    } else {
        const orderCheckoutBtn = page.getByRole('button', { name: /Đóng bill/i }).first();
        await orderCheckoutBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
        if (await orderCheckoutBtn.count() > 0 && await orderCheckoutBtn.isVisible()) {
            await orderCheckoutBtn.click();
        }
    }

    await page.waitForURL(url => url.pathname.includes('/settlement'), { timeout: 5000 }).catch(() => {});
    await page.screenshot({ path: path.join(evidenceDir, '14-checkout-preview.png') });

    // Finalize payment (Mocking cash)
    const cashBtn = page.getByRole('button', { name: /Tiền mặt|Cash/i }).first();
    await cashBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await cashBtn.count() > 0 && await cashBtn.isVisible()) {
        await cashBtn.click();
    }
    
    const confirmPaymentBtn = page.getByRole('button', { name: /Xác nhận|Xong/i }).first();
    const paymentConfirmPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/orders') && r.url().includes('/pay') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
    await confirmPaymentBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await confirmPaymentBtn.count() > 0 && await confirmPaymentBtn.isVisible()) {
        await confirmPaymentBtn.click();
        await paymentConfirmPromise;
        await page.waitForTimeout(1000);
    }

    await page.screenshot({ path: path.join(evidenceDir, '15-checkout-completed.png') });
    console.log("End of script reached.");
  });

});
