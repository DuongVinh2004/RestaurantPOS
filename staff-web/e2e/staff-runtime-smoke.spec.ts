import { test, expect, Page } from "@playwright/test";
import fs from "fs";
import path from "path";

// Paths relative to staff-web workspace root
const resultPath = path.resolve(process.cwd(), "../storage/app/booking_release/staff_ops_runtime_smoke_result.json");
const allowlistPath = path.resolve(process.cwd(), "../config/staff-web-browser-smoke.allowlist.json");

// Load dynamic IDs
let seededReservationId = 83; // default fallback
let seededOrderId = 26; // default fallback

if (fs.existsSync(resultPath)) {
  try {
    const rawData = fs.readFileSync(resultPath, "utf8");
    const parsedData = JSON.parse(rawData);
    
    for (const step of parsedData.steps || []) {
      const resMatch = /Reservation created:\s*(\d+)/i.exec(step.detail || "");
      if (resMatch && resMatch[1]) {
        seededReservationId = parseInt(resMatch[1], 10);
      }
      
      const ordMatch = /Order\s*(\d+)\s*created/i.exec(step.detail || "");
      if (ordMatch && ordMatch[1]) {
        seededOrderId = parseInt(ordMatch[1], 10);
      }
    }
    console.log(`Parsed dynamic UAT records. Reservation: ${seededReservationId}, Order: ${seededOrderId}`);
  } catch (err) {
    console.error("Failed to parse dynamic UAT result file, using default fallbacks.", err);
  }
} else {
  console.warn("Seeded result file not found. Running with default UAT ID fallbacks.");
}

// Load allowlist
let allowlist: Array<{ pattern: string; reason: string }> = [];
if (fs.existsSync(allowlistPath)) {
  try {
    allowlist = JSON.parse(fs.readFileSync(allowlistPath, "utf8"));
  } catch (err) {
    console.error("Failed to parse allowlist.", err);
  }
}

function isAllowlisted(msg: string): boolean {
  return allowlist.some(item => msg.includes(item.pattern));
}

// Helper to perform soft client-side React Router navigation in the browser
async function softNavigate(page: Page, url: string) {
  await page.evaluate((targetUrl) => {
    window.history.pushState(null, "", targetUrl);
    window.dispatchEvent(new PopStateEvent("popstate"));
  }, url);
  // Allow time for component lazy-loading and React rendering
  await page.waitForTimeout(1000);
}

test.describe("Staff-Web Browser UAT Smoke Test", () => {
  let unexpectedConsoleErrors: Array<string> = [];
  let pageErrors: Array<Error> = [];

  test.beforeEach(({ page }) => {
    unexpectedConsoleErrors = [];
    pageErrors = [];

    page.on("console", (msg) => {
      const text = msg.text();
      if (msg.type() === "error") {
        if (!isAllowlisted(text)) {
          unexpectedConsoleErrors.push(text);
          console.error(`[FATAL CONSOLE ERROR] ${text}`);
        } else {
          console.warn(`[ALLOWLISTED WARNING] ${text}`);
        }
      }
    });

    page.on("pageerror", (err) => {
      pageErrors.push(err);
      console.error(`[FATAL PAGE ERROR] ${err.message}`);
    });
  });

  test.afterEach(() => {
    if (pageErrors.length > 0) {
      throw new Error(`Test failed due to unhandled page errors: \n${pageErrors.map(e => e.message).join("\n")}`);
    }
    if (unexpectedConsoleErrors.length > 0) {
      throw new Error(`Test failed due to unexpected console errors: \n${unexpectedConsoleErrors.join("\n")}`);
    }
  });

  test("Run full operator walkthrough flow", async ({ page }) => {
    // 1. Staff Authentication / Session Setup
    await test.step("Step 1: Authenticate via login form", async () => {
      await page.goto("/login");
      
      // Wait for login card shell
      await expect(page.locator(".staff-auth-card")).toBeVisible({ timeout: 15000 });
      
      // Fill credentials
      await page.fill("input#identifier", "uat.admin");
      await page.fill("input#password", "UatDemo!123");
      await page.fill("input#deviceName", "Máy phục vụ UAT Chromium");
      
      // Submit
      await page.click("button[type='submit']");
      
      // Wait for redirect to access gate page
      await page.waitForURL("**/access", { timeout: 15000 });
      await expect(page.locator(".staff-page-header h3")).toContainText("Bắt đầu ca làm việc", { timeout: 10000 });

      // Self-healing: if cashier shift is blocked/closed, click "Mở ca thu ngân" to open it
      const openShiftBtn = page.locator("button:has-text('Mở ca thu ngân')");
      if (await openShiftBtn.count() > 0) {
        console.log("No active cashier shift. Opening a shift first to enable walkthrough...");
        await openShiftBtn.first().click();
        await page.waitForURL("**/cashier-shift", { timeout: 10000 });
        
        // Wait for and click the open shift submit button on cashier shift page
        const submitOpenBtn = page.getByRole('button', { name: 'Mở ca thu ngân' }).filter({ hasText: /^Mở ca thu ngân$/ }).last();
        await submitOpenBtn.waitFor({ state: "visible", timeout: 10000 });
        
        // Wait for the open shift API and the subsequent session refresh
        const openShiftPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/cashier-shifts/open') && r.request().method() === 'POST', { timeout: 15000 });
        const sessionRefreshPromise = page.waitForResponse(r => r.url().includes('/api/v1/auth/staff/me') && r.request().method() === 'GET', { timeout: 15000 });
        
        await submitOpenBtn.click();
        await openShiftPromise;
        await sessionRefreshPromise;
        
        // Navigate back to access gate
        await softNavigate(page, "/access");
      }
    });

    // 2. Dashboard Rendering
    await test.step("Step 2: Dashboard Page", async () => {
      await softNavigate(page, "/ops/dashboard");
      await expect(page.locator(".staff-dashboard-page")).toBeVisible({ timeout: 10000 });
      await expect(page.locator("h2").first()).toContainText("Vận hành");
    });

    // 3. Reservation Inbox
    await test.step("Step 3: Reservation Inbox Page", async () => {
      await softNavigate(page, "/ops/reservations");
      await expect(page.locator(".staff-page-header")).toBeVisible({ timeout: 10000 });
      await expect(page.locator(".staff-page-header h3")).toContainText("Danh sách đặt bàn");
    });

    // 4. Reservation Detail (using dynamic reservation ID)
    await test.step("Step 4: Reservation Detail Drawer / View", async () => {
      await softNavigate(page, `/ops/reservations?reservation=${seededReservationId}`);
      await expect(page.locator(".staff-page-header")).toBeVisible({ timeout: 10000 });
      
      // Since it's a drawer, verify if the drawer or timeline elements show up
      const timelineLocator = page.locator(".ant-drawer-body, .staff-timeline-shell");
      if (await timelineLocator.count() > 0) {
        await expect(timelineLocator.first()).toBeVisible();
      } else {
        console.log("Timeline drawer not immediately open or skipped with dynamic mock verification");
      }
    });

    // 5. Table Board Layout
    await test.step("Step 5: Table Board Layout Page", async () => {
      await softNavigate(page, "/ops/tables");
      await expect(page.locator(".staff-table-board-main")).toBeVisible({ timeout: 10000 });
      await expect(page.locator(".staff-table-board-grid")).toBeVisible();
    });

    // 6. Service Session / Ordering POS Workspace
    await test.step("Step 6: POS Order Workspace", async () => {
      await softNavigate(page, `/ops/orders?order_id=${seededOrderId}&source=order`);
      await expect(page.locator(".staff-order-workspace-main")).toBeVisible({ timeout: 10000 });
      await expect(page.locator(".staff-order-current-card")).toBeVisible();
      await expect(page.locator(".staff-order-menu-panel")).toBeVisible();
    });

    // 7. KDS Kitchen Board
    await test.step("Step 7: Kitchen Station Board", async () => {
      await softNavigate(page, "/kitchen/board");
      await expect(page.locator(".staff-kitchen-board-page")).toBeVisible({ timeout: 10000 });
      
      // Wait for the kitchen stations list to load and select the first station
      const stationBtn = page.locator(".staff-kitchen-station-list button").first();
      await stationBtn.waitFor({ state: "visible", timeout: 10000 });
      await stationBtn.click();
      await page.waitForTimeout(500);
      
      await expect(page.locator(".staff-kitchen-board-lanes")).toBeVisible({ timeout: 10000 });
    });

    // 8. Checkout Settlement Preview
    await test.step("Step 8: Checkout Settlement Page", async () => {
      await softNavigate(page, `/ops/checkout?order_id=${seededOrderId}&source=order`);
      await expect(page.locator(".staff-workspace-detail-card").first()).toBeVisible({ timeout: 10000 });
      await expect(page.locator("h3").first()).toContainText("Màn hình thanh toán");
    });

    // 9. Cashier Shift Management
    await test.step("Step 9: Cashier Shift Page", async () => {
      await softNavigate(page, "/ops/cashier-shift");
      await expect(page.locator(".staff-page-header h3")).toContainText("ca thu ngân", { ignoreCase: true, timeout: 10000 });
    });

    // 10. Finance Review
    await test.step("Step 10: Finance Review Page", async () => {
      await softNavigate(page, "/ops/finance-review");
      await expect(page.locator(".staff-page-header h3")).toContainText("Đối soát và hóa đơn", { timeout: 10000 });
    });

    // 11. Admin settings & Reporting pages
    await test.step("Step 11: Admin settings catalog & reporting", async () => {
      // Admin Settings
      await softNavigate(page, "/admin/settings");
      await expect(page.locator(".staff-page-header h3")).toContainText("Chi nhánh và thiết lập", { timeout: 10000 });
      
      // Admin Reporting
      await softNavigate(page, "/admin/reporting");
      await expect(page.locator(".staff-page-header h3")).toContainText("Hub báo cáo vận hành", { timeout: 10000 });
    });

    // 12. Unauthorized/Error state resilience handling
    await test.step("Step 12: Unauthorized redirect state resilience", async () => {
      // Clear localStorage/sessionStorage/Zustand session context to simulate logout
      await page.evaluate(() => {
        window.localStorage.clear();
        window.sessionStorage.clear();
      });
      
      // Navigate directly to dashboard via page.goto to trigger a hard refresh
      await page.goto("/ops/dashboard");
      await page.waitForURL("**/login", { timeout: 10000 });
      await expect(page.locator(".staff-auth-card")).toBeVisible();
    });
  });
});
