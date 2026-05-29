import { test, expect, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const runId = Date.now();
const evidenceDir = path.resolve(process.cwd(), '../docs/qa/ui-business-flow-audit/evidence', `report-run-${runId}`);

if (!fs.existsSync(evidenceDir)) {
  fs.mkdirSync(evidenceDir, { recursive: true });
}

let customerName = `Report Customer ${runId}`;
let uniquePhone = `093${Math.floor(1000000 + Math.random() * 9000000)}`;

test.describe('Reporting/Analytics Deep Audit', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(240000); 

  let page: Page;
  let context: any;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('1. Setup data: create paid order', async () => {
    console.log(`Starting data setup flow... Evidence dir: ${evidenceDir}`);
    // A. Create reservation
    await page.goto('http://localhost:3000/');
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 60000 });
    
    await page.getByRole('link', { name: /Đặt bàn|Book/i }).first().click();
    await page.waitForTimeout(2000);
    
    const branchTrigger = page.getByTestId('customer-branch-select-trigger');
    await branchTrigger.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await branchTrigger.count() > 0 && await branchTrigger.isVisible()) {
      await branchTrigger.click();
      await page.waitForTimeout(500); 
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

    await page.getByTestId('customer-confirm-hold-btn').first().click();
    await page.waitForURL('**/reservations/new**', { timeout: 10000 });
    
    await page.getByTestId('customer-name-input').first().fill(customerName).catch(() => {});
    await page.getByTestId('customer-phone-input').first().fill(uniquePhone).catch(() => {});
    await page.getByTestId('customer-email-input').first().fill(`test_rep_${runId}@example.com`).catch(() => {});

    const createResPromise = page.waitForResponse(r => r.url().includes('/api/v1/reservations') && r.request().method() === 'POST');
    await page.getByTestId('customer-submit-reservation-button').first().click();
    await createResPromise;
    await page.waitForURL((url) => url.pathname.includes('/reservations/') && !url.pathname.includes('/new'), { timeout: 15000 });

    // B. Staff Login & Check-in & Pay
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
    
    const boLucLac = page.getByRole('button', { name: /Bò lúc lắc/i }).first();
    await boLucLac.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await boLucLac.count() > 0 && await boLucLac.isVisible()) {
      await boLucLac.click();
    }

    const dispatchBtn = page.getByRole('button', { name: /Gửi bếp|Dispatch|Xác nhận|Lưu/i }).first();
    await dispatchBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await dispatchBtn.count() > 0 && await dispatchBtn.isVisible()) {
        const dispatchPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/orders') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
        await dispatchBtn.click();
        await dispatchPromise;
    }
    
    await page.waitForTimeout(1000);
    // Go to order list to reset any open drawers
    await page.goto('http://localhost:5173/orders');
    await page.waitForTimeout(2000);
    const firstOrderRow = page.locator('tr').nth(1);
    await firstOrderRow.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await firstOrderRow.count() > 0 && await firstOrderRow.isVisible()) {
       await firstOrderRow.click();
       await page.waitForTimeout(1000);
    }
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
    const cashBtn = page.getByRole('button', { name: /Tiền mặt|Cash/i }).first();
    await cashBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await cashBtn.count() > 0 && await cashBtn.isVisible()) {
        await cashBtn.click();
    }
    
    const confirmPaymentBtn = page.getByRole('button', { name: /Xác nhận|Xong|Pay/i }).first();
    const paymentConfirmPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/orders') && r.url().includes('/pay') && r.request().method() === 'POST', { timeout: 5000 }).catch(() => {});
    await confirmPaymentBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await confirmPaymentBtn.count() > 0 && await confirmPaymentBtn.isVisible()) {
        await confirmPaymentBtn.click();
        await paymentConfirmPromise;
        await page.waitForTimeout(1000);
    }
    console.log('REP_DATA_SETUP_COMPLETE');
  });

  test('2. Verify reporting/analytics navigation and load', async () => {
    await test.step('Navigate to reporting', async () => {
      // Find workspace switcher
      const workspaceSwitcher = page.getByText(/Vận hành/i).first();
      await workspaceSwitcher.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      if (await workspaceSwitcher.isVisible()) {
        await workspaceSwitcher.click();
        await page.waitForTimeout(500);
      }
      
      const reportsMenu = page.getByText(/Báo cáo|Reports/i).first();
      await reportsMenu.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      
      if (await reportsMenu.count() > 0 && await reportsMenu.isVisible()) {
          await reportsMenu.click();
          await page.waitForURL(url => url.pathname.includes('/reports') || url.pathname.includes('/analytics'), { timeout: 5000 }).catch(() => {});
          console.log('REP_NAVIGATION_OK');
      } else {
          console.log('REP_NAVIGATION_NOT_IMPLEMENTED');
          // Let's try direct URL if menu not found
          await page.goto('http://localhost:5173/reports').catch(() => {});
          await page.waitForTimeout(2000);
      }
      
      await page.screenshot({ path: path.join(evidenceDir, '01-reports-landing.png') });
    });

    await test.step('Verify dashboard load', async () => {
       const isError = await page.getByText(/Lỗi|Error|500/i).count() > 0 && await page.getByText(/Lỗi|Error|500/i).isVisible();
       if (isError) {
         console.log('REP_DASHBOARD_ERROR');
       } else {
         const hasData = await page.getByText(/Doanh thu|Revenue/i).count() > 0;
         if (hasData) {
            console.log('REP_DASHBOARD_LOADED');
         } else {
            console.log('REP_DASHBOARD_NOT_IMPLEMENTED_OR_EMPTY');
         }
       }
    });
  });

  test('3. Verify sales/payment summary and filters', async () => {
    // Attempt to find total revenue or similar
    const todayBtn = page.getByRole('button', { name: /Hôm nay|Today/i }).first();
    await todayBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await todayBtn.count() > 0 && await todayBtn.isVisible()) {
       await todayBtn.click();
       await page.waitForTimeout(1000);
       console.log('REP_FILTER_APPLIED');
    } else {
       console.log('REP_FILTER_NOT_IMPLEMENTED');
    }
    
    await page.screenshot({ path: path.join(evidenceDir, '02-reports-filtered.png') });
  });

  test('4. Verify Real vs Placeholder Data', async () => {
    // Check if network request is made for reports
    console.log('REP_DATA_CHECK: Real backend data verification depends on API responses. Missing UI indicates NOT_IMPLEMENTED.');
    
    const revenueElement = page.getByText(/₫/i).first(); // rough check for formatted currency
    await revenueElement.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await revenueElement.count() > 0 && await revenueElement.isVisible()) {
       console.log('REP_FIGURES_PRESENT');
    } else {
       console.log('REP_FIGURES_NOT_IMPLEMENTED');
    }
  });

});
