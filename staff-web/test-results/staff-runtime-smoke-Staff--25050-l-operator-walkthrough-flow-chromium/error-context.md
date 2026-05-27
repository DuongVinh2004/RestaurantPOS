# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: staff-runtime-smoke.spec.ts >> Staff-Web Browser UAT Smoke Test >> Run full operator walkthrough flow
- Location: e2e\staff-runtime-smoke.spec.ts:96:3

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: page.waitForResponse: Test timeout of 30000ms exceeded.
```

```
Error: page.waitForResponse: Test timeout of 30000ms exceeded.
```

# Page snapshot

```yaml
- generic [ref=e3]:
  - complementary [ref=e4]:
    - generic [ref=e5]:
      - generic [ref=e6]: Mộc Sen Bistro
      - heading "Vận hành" [level=2] [ref=e7]
    - navigation "Staff workspace navigation" [ref=e8]:
      - generic [ref=e9]:
        - generic [ref=e10]: Điều phối sàn
        - generic [ref=e11]:
          - button "Tổng quan" [ref=e12] [cursor=pointer]:
            - img [ref=e14]
            - generic [ref=e20]: Tổng quan
          - button "Sơ đồ bàn" [ref=e21] [cursor=pointer]:
            - img [ref=e23]
            - generic [ref=e27]: Sơ đồ bàn
          - button "Đặt bàn" [ref=e28] [cursor=pointer]:
            - img [ref=e30]
            - generic [ref=e35]: Đặt bàn
          - button "Chờ bàn" [ref=e36] [cursor=pointer]:
            - img [ref=e38]
            - generic [ref=e42]: Chờ bàn
          - button "Đơn hàng" [ref=e43] [cursor=pointer]:
            - img [ref=e45]
            - generic [ref=e49]: Đơn hàng
      - generic [ref=e50]:
        - generic [ref=e51]: Tiền & kiểm soát ca
        - generic [ref=e52]:
          - button "Thanh toán & hoàn tiền" [ref=e53] [cursor=pointer]:
            - img [ref=e55]
            - generic [ref=e58]: Thanh toán & hoàn tiền
          - button "Ca thu ngân" [ref=e59] [cursor=pointer]:
            - img [ref=e61]
            - generic [ref=e66]: Ca thu ngân
          - button "Đối soát" [ref=e67] [cursor=pointer]:
            - img [ref=e69]
            - generic [ref=e73]: Đối soát
      - generic [ref=e74]:
        - generic [ref=e75]: Hỗ trợ khách
        - button "Hội thoại" [ref=e77] [cursor=pointer]:
          - img [ref=e79]
          - generic [ref=e83]: Hội thoại
  - generic [ref=e84]:
    - banner [ref=e85]:
      - generic [ref=e86]:
        - generic "Current workspace context" [ref=e88]:
          - generic [ref=e90]:
            - generic [ref=e91]: Vận hành
            - heading "Ca thu ngân" [level=1] [ref=e92]
            - generic [ref=e93]: Đã cập nhật trong <1 phút
        - generic [ref=e94]:
          - combobox "Chuyển khu vực làm việc" [ref=e97]:
            - option "Vận hành" [selected]
            - option "Bếp"
            - option "Quản trị"
          - combobox "Chọn chi nhánh hoạt động" [ref=e100]:
            - option "UATDEMO • Chi nhánh UAT" [selected]
            - option "MAIN • Chi nhanh chinh"
            - option "MS-CG • Mộc Sen Bistro - Cầu Giấy"
            - option "MS-HK • Mộc Sen Bistro - Hoàn Kiếm"
            - option "MS-TD • Mộc Sen Bistro - Thảo Điền"
          - generic [ref=e101]:
            - button "Làm mới" [ref=e102] [cursor=pointer]:
              - img [ref=e103]
            - button "Tìm nhanh" [ref=e108] [cursor=pointer]:
              - img [ref=e109]
            - button "Đăng xuất" [ref=e111] [cursor=pointer]:
              - img [ref=e112]
    - main [ref=e115]:
      - status [ref=e117]:
        - generic [ref=e118]:
          - img [ref=e120]
          - generic [ref=e125]: Đã làm mới phiên làm việc của nhân viên.
          - button "Đóng thông báo" [ref=e126] [cursor=pointer]:
            - img [ref=e127]
      - generic [ref=e130]:
        - generic [ref=e132]:
          - generic [ref=e134]:
            - generic [ref=e135]:
              - generic [ref=e136]: Ca thu ngân
              - generic [ref=e137]:
                - heading "Trung tâm ca thu ngân" [level=3] [ref=e138]
                - paragraph [ref=e139]: Mở ca, theo dõi giao dịch đang chạy và chỉ đóng sau khi đã kiểm đếm khớp tiền mặt.
              - generic [ref=e140]:
                - generic [ref=e141]: Phiên bản ca 1
                - generic [ref=e142]:
                  - generic [ref=e144]: CSH-20260527101841-88-6NICND
                  - generic [ref=e146]: "Lịch sử theo chi nhánh #9"
                  - generic [ref=e148]: Sẵn sàng
            - button "Làm mới dữ liệu ca" [ref=e151] [cursor=pointer]:
              - generic [ref=e152]: Làm mới dữ liệu ca
          - generic [ref=e154]:
            - generic [ref=e158]:
              - generic [ref=e160]: Ca hiện tại
              - generic [ref=e162]: CSH-20260527101841-88-6NICND
            - generic [ref=e166]:
              - generic [ref=e168]: Ca gần đây
              - generic [ref=e170]: "1"
            - generic [ref=e174]:
              - generic [ref=e176]: Giao dịch hiện tại
              - generic [ref=e178]: "0"
          - generic [ref=e180]:
            - generic [ref=e183]: Ca hiện tại
            - table [ref=e187]:
              - rowgroup [ref=e188]:
                - row "Ca CSH-20260527101841-88-6NICND Đang mở Chi nhánh UATDEMO" [ref=e189]:
                  - rowheader "Ca" [ref=e190]
                  - cell "CSH-20260527101841-88-6NICND Đang mở" [ref=e191]:
                    - generic [ref=e193]:
                      - strong [ref=e196]: CSH-20260527101841-88-6NICND
                      - generic [ref=e199]: Đang mở
                  - rowheader "Chi nhánh" [ref=e200]
                  - cell "UATDEMO" [ref=e201]
                - row "Mở lúc 17:18 27/05/2026 Thiết bị staff-web-main" [ref=e202]:
                  - rowheader "Mở lúc" [ref=e203]
                  - cell "17:18 27/05/2026" [ref=e204]
                  - rowheader "Thiết bị" [ref=e205]
                  - cell "staff-web-main" [ref=e206]
                - row "Tiền đầu ca 0 ₫ Tiền mặt kỳ vọng 0 ₫" [ref=e207]:
                  - rowheader "Tiền đầu ca" [ref=e208]
                  - cell "0 ₫" [ref=e209]
                  - rowheader "Tiền mặt kỳ vọng" [ref=e210]
                  - cell "0 ₫" [ref=e211]
          - generic [ref=e213]:
            - generic [ref=e216]: Mở ca thu ngân
            - generic [ref=e218]:
              - generic [ref=e219]:
                - generic [ref=e222]:
                  - generic "Tiền đầu ca" [ref=e224]
                  - generic [ref=e228]:
                    - spinbutton "Tiền đầu ca" [ref=e229]: "0"
                    - generic:
                      - button "Increase Value" [ref=e230] [cursor=pointer]:
                        - img "up" [ref=e231]:
                          - img [ref=e232]
                      - button "Decrease Value" [disabled] [ref=e234] [cursor=pointer]:
                        - img "down" [ref=e235]:
                          - img [ref=e236]
                - generic [ref=e240]:
                  - generic "Loại tiền" [ref=e242]: "* Loại tiền"
                  - textbox "* Loại tiền" [ref=e246]: VND
                - generic [ref=e249]:
                  - generic "Mã thiết bị" [ref=e251]
                  - textbox "Mã thiết bị" [ref=e255]:
                    - /placeholder: Mã thiết bị nếu cần
                    - text: staff-web-main
              - generic [ref=e257]:
                - generic "Ghi chú mở ca" [ref=e259]
                - textbox "Ghi chú mở ca Ghi chú đóng ca" [ref=e263]:
                  - /placeholder: Ghi chú mở ca nếu cần
              - alert [ref=e264]:
                - img "info-circle" [ref=e266]:
                  - img [ref=e267]
                - generic [ref=e270]: Ca mới sẽ mở theo chi nhánh thao tác 9 từ shell.
              - button "Mở ca thu ngân" [disabled] [ref=e271]:
                - generic: Mở ca thu ngân
          - generic [ref=e273]:
            - generic [ref=e275]:
              - generic [ref=e276]: Lịch sử ca gần đây
              - generic [ref=e278]:
                - generic [ref=e280] [cursor=pointer]:
                  - generic "Tất cả ca" [ref=e281]:
                    - text: Tất cả ca
                    - combobox [ref=e282]
                  - img "down" [ref=e284]:
                    - img [ref=e285]
                - generic [ref=e288]:
                  - searchbox "Tìm theo ca / thiết bị" [ref=e290]
                  - button "search" [ref=e292] [cursor=pointer]:
                    - img "search" [ref=e294]:
                      - img [ref=e295]
            - table [ref=e304]:
              - rowgroup [ref=e305]:
                - row "Ca Trạng thái Mở lúc Thiết bị" [ref=e306]:
                  - columnheader "Ca" [ref=e307]
                  - columnheader "Trạng thái" [ref=e308]
                  - columnheader "Mở lúc" [ref=e309]
                  - columnheader "Thiết bị" [ref=e310]
              - rowgroup [ref=e311]:
                - row "CSH-20260527101841-88-6NICND UATDEMO Đang mở 17:18 27/05/2026 staff-web-main" [ref=e312]:
                  - cell "CSH-20260527101841-88-6NICND UATDEMO" [ref=e313]:
                    - generic [ref=e314]:
                      - strong [ref=e317]: CSH-20260527101841-88-6NICND
                      - generic [ref=e318]: UATDEMO
                  - cell "Đang mở" [ref=e319]:
                    - generic [ref=e321]: Đang mở
                  - cell "17:18 27/05/2026" [ref=e322]
                  - cell "staff-web-main" [ref=e323]
        - generic [ref=e325]:
          - generic [ref=e327]:
            - generic [ref=e330]: Ca đang chọn
            - generic [ref=e332]:
              - table [ref=e336]:
                - rowgroup [ref=e337]:
                  - row "Ca CSH-20260527101841-88-6NICND Đang mở" [ref=e338]:
                    - rowheader "Ca" [ref=e339]
                    - cell "CSH-20260527101841-88-6NICND Đang mở" [ref=e340]:
                      - generic [ref=e342]:
                        - strong [ref=e345]: CSH-20260527101841-88-6NICND
                        - generic [ref=e348]: Đang mở
                  - row "Phiên bản thao tác 1" [ref=e349]:
                    - rowheader "Phiên bản thao tác" [ref=e350]
                    - cell "1" [ref=e351]
                  - row "Nhân viên UAT Admin" [ref=e352]:
                    - rowheader "Nhân viên" [ref=e353]
                    - cell "UAT Admin" [ref=e354]
                  - row "Mở lúc 17:18 27/05/2026" [ref=e355]:
                    - rowheader "Mở lúc" [ref=e356]
                    - cell "17:18 27/05/2026" [ref=e357]
                  - row "Đóng lúc Không có" [ref=e358]:
                    - rowheader "Đóng lúc" [ref=e359]
                    - cell "Không có" [ref=e360]
              - generic [ref=e362]:
                - generic [ref=e366]:
                  - generic [ref=e368]: Tiền mặt kỳ vọng
                  - generic [ref=e370]: 0 ₫
                - generic [ref=e374]:
                  - generic [ref=e376]: Thu ròng
                  - generic [ref=e378]: 0 ₫
              - generic [ref=e380]:
                - generic [ref=e383]: Đóng ca
                - generic [ref=e384]:
                  - alert [ref=e385]:
                    - img "check-circle" [ref=e387]:
                      - img [ref=e388]
                    - generic [ref=e390]:
                      - generic [ref=e391]: Review handoff trước khi đóng ca
                      - generic [ref=e392]: Xác nhận lại tiền mặt kiểm đếm, chênh lệch, ghi chú handoff và các phương thức nổi bật trước khi chốt đóng ca.
                  - generic [ref=e393]:
                    - generic [ref=e395]:
                      - generic "Tiền mặt kiểm đếm" [ref=e397]: "* Tiền mặt kiểm đếm"
                      - generic [ref=e401]:
                        - spinbutton "* Tiền mặt kiểm đếm" [ref=e402]: "0"
                        - generic:
                          - button "Increase Value" [ref=e403] [cursor=pointer]:
                            - img "up" [ref=e404]:
                              - img [ref=e405]
                          - button "Decrease Value" [disabled] [ref=e407] [cursor=pointer]:
                            - img "down" [ref=e408]:
                              - img [ref=e409]
                    - generic [ref=e412]:
                      - generic "Ghi chú đóng ca" [ref=e414]
                      - textbox "Ghi chú đóng ca nếu cần" [ref=e418]
                    - alert [ref=e419]:
                      - img "check-circle" [ref=e421]:
                        - img [ref=e422]
                      - generic [ref=e425]: "Chênh lệch: 0 ₫"
                    - button "Đóng ca thu ngân" [ref=e426] [cursor=pointer]:
                      - generic [ref=e427]: Đóng ca thu ngân
          - generic [ref=e429]:
            - generic [ref=e432]: Bước chuyển tiếp tiếp theo
            - generic [ref=e434]:
              - generic [ref=e435]: Giữ màn hình thanh toán ở trạng thái chờ cho tới khi phiên nhân viên được làm mới và phản ánh đúng ca thu ngân đang hoạt động.
              - button "Mở đối soát tài chính" [ref=e437] [cursor=pointer]:
                - generic [ref=e438]: Mở đối soát tài chính
              - button "Quay lại sơ đồ bàn" [ref=e440] [cursor=pointer]:
                - generic [ref=e441]: Quay lại sơ đồ bàn
              - generic [ref=e444]: Sẵn sàng
```

# Test source

```ts
  29  |     console.log(`Parsed dynamic UAT records. Reservation: ${seededReservationId}, Order: ${seededOrderId}`);
  30  |   } catch (err) {
  31  |     console.error("Failed to parse dynamic UAT result file, using default fallbacks.", err);
  32  |   }
  33  | } else {
  34  |   console.warn("Seeded result file not found. Running with default UAT ID fallbacks.");
  35  | }
  36  | 
  37  | // Load allowlist
  38  | let allowlist: Array<{ pattern: string; reason: string }> = [];
  39  | if (fs.existsSync(allowlistPath)) {
  40  |   try {
  41  |     allowlist = JSON.parse(fs.readFileSync(allowlistPath, "utf8"));
  42  |   } catch (err) {
  43  |     console.error("Failed to parse allowlist.", err);
  44  |   }
  45  | }
  46  | 
  47  | function isAllowlisted(msg: string): boolean {
  48  |   return allowlist.some(item => msg.includes(item.pattern));
  49  | }
  50  | 
  51  | // Helper to perform soft client-side React Router navigation in the browser
  52  | async function softNavigate(page: Page, url: string) {
  53  |   await page.evaluate((targetUrl) => {
  54  |     window.history.pushState(null, "", targetUrl);
  55  |     window.dispatchEvent(new PopStateEvent("popstate"));
  56  |   }, url);
  57  |   // Allow time for component lazy-loading and React rendering
  58  |   await page.waitForTimeout(1000);
  59  | }
  60  | 
  61  | test.describe("Staff-Web Browser UAT Smoke Test", () => {
  62  |   let unexpectedConsoleErrors: Array<string> = [];
  63  |   let pageErrors: Array<Error> = [];
  64  | 
  65  |   test.beforeEach(({ page }) => {
  66  |     unexpectedConsoleErrors = [];
  67  |     pageErrors = [];
  68  | 
  69  |     page.on("console", (msg) => {
  70  |       const text = msg.text();
  71  |       if (msg.type() === "error") {
  72  |         if (!isAllowlisted(text)) {
  73  |           unexpectedConsoleErrors.push(text);
  74  |           console.error(`[FATAL CONSOLE ERROR] ${text}`);
  75  |         } else {
  76  |           console.warn(`[ALLOWLISTED WARNING] ${text}`);
  77  |         }
  78  |       }
  79  |     });
  80  | 
  81  |     page.on("pageerror", (err) => {
  82  |       pageErrors.push(err);
  83  |       console.error(`[FATAL PAGE ERROR] ${err.message}`);
  84  |     });
  85  |   });
  86  | 
  87  |   test.afterEach(() => {
  88  |     if (pageErrors.length > 0) {
  89  |       throw new Error(`Test failed due to unhandled page errors: \n${pageErrors.map(e => e.message).join("\n")}`);
  90  |     }
  91  |     if (unexpectedConsoleErrors.length > 0) {
  92  |       throw new Error(`Test failed due to unexpected console errors: \n${unexpectedConsoleErrors.join("\n")}`);
  93  |     }
  94  |   });
  95  | 
  96  |   test("Run full operator walkthrough flow", async ({ page }) => {
  97  |     // 1. Staff Authentication / Session Setup
  98  |     await test.step("Step 1: Authenticate via login form", async () => {
  99  |       await page.goto("/login");
  100 |       
  101 |       // Wait for login card shell
  102 |       await expect(page.locator(".staff-auth-card")).toBeVisible({ timeout: 15000 });
  103 |       
  104 |       // Fill credentials
  105 |       await page.fill("input#identifier", "uat.admin");
  106 |       await page.fill("input#password", "UatDemo!123");
  107 |       await page.fill("input#deviceName", "Máy phục vụ UAT Chromium");
  108 |       
  109 |       // Submit
  110 |       await page.click("button[type='submit']");
  111 |       
  112 |       // Wait for redirect to access gate page
  113 |       await page.waitForURL("**/access", { timeout: 15000 });
  114 |       await expect(page.locator(".staff-page-header h3")).toContainText("Bắt đầu ca làm việc", { timeout: 10000 });
  115 | 
  116 |       // Self-healing: if cashier shift is blocked/closed, click "Mở ca thu ngân" to open it
  117 |       const openShiftBtn = page.locator("button:has-text('Mở ca thu ngân')");
  118 |       if (await openShiftBtn.count() > 0) {
  119 |         console.log("No active cashier shift. Opening a shift first to enable walkthrough...");
  120 |         await openShiftBtn.first().click();
  121 |         await page.waitForURL("**/cashier-shift", { timeout: 10000 });
  122 |         
  123 |         // Wait for and click the open shift submit button on cashier shift page
  124 |         const submitOpenBtn = page.locator("button:has-text('Mở ca thu ngân')");
  125 |         await submitOpenBtn.waitFor({ state: "visible", timeout: 10000 });
  126 |         
  127 |         // Wait for the open shift API and the subsequent session refresh
  128 |         const openShiftPromise = page.waitForResponse(r => r.url().includes('/api/v1/staff/cashier-shifts') && r.request().method() === 'POST');
> 129 |         const sessionRefreshPromise = page.waitForResponse(r => r.url().includes('/api/v1/auth/staff/me') && r.request().method() === 'GET');
      |                                            ^ Error: page.waitForResponse: Test timeout of 30000ms exceeded.
  130 |         
  131 |         await submitOpenBtn.click();
  132 |         await openShiftPromise;
  133 |         await sessionRefreshPromise;
  134 |         
  135 |         // Navigate back to access gate
  136 |         await softNavigate(page, "/access");
  137 |       }
  138 |     });
  139 | 
  140 |     // 2. Dashboard Rendering
  141 |     await test.step("Step 2: Dashboard Page", async () => {
  142 |       await softNavigate(page, "/ops/dashboard");
  143 |       await expect(page.locator(".staff-dashboard-page")).toBeVisible({ timeout: 10000 });
  144 |       await expect(page.locator("h2").first()).toContainText("Vận hành");
  145 |     });
  146 | 
  147 |     // 3. Reservation Inbox
  148 |     await test.step("Step 3: Reservation Inbox Page", async () => {
  149 |       await softNavigate(page, "/ops/reservations");
  150 |       await expect(page.locator(".staff-page-header")).toBeVisible({ timeout: 10000 });
  151 |       await expect(page.locator(".staff-page-header h3")).toContainText("Danh sách đặt bàn");
  152 |     });
  153 | 
  154 |     // 4. Reservation Detail (using dynamic reservation ID)
  155 |     await test.step("Step 4: Reservation Detail Drawer / View", async () => {
  156 |       await softNavigate(page, `/ops/reservations?reservation=${seededReservationId}`);
  157 |       await expect(page.locator(".staff-page-header")).toBeVisible({ timeout: 10000 });
  158 |       
  159 |       // Since it's a drawer, verify if the drawer or timeline elements show up
  160 |       const timelineLocator = page.locator(".ant-drawer-body, .staff-timeline-shell");
  161 |       if (await timelineLocator.count() > 0) {
  162 |         await expect(timelineLocator.first()).toBeVisible();
  163 |       } else {
  164 |         console.log("Timeline drawer not immediately open or skipped with dynamic mock verification");
  165 |       }
  166 |     });
  167 | 
  168 |     // 5. Table Board Layout
  169 |     await test.step("Step 5: Table Board Layout Page", async () => {
  170 |       await softNavigate(page, "/ops/tables");
  171 |       await expect(page.locator(".staff-table-board-main")).toBeVisible({ timeout: 10000 });
  172 |       await expect(page.locator(".staff-table-board-grid")).toBeVisible();
  173 |     });
  174 | 
  175 |     // 6. Service Session / Ordering POS Workspace
  176 |     await test.step("Step 6: POS Order Workspace", async () => {
  177 |       await softNavigate(page, `/ops/orders?order_id=${seededOrderId}&source=order`);
  178 |       await expect(page.locator(".staff-order-workspace-main")).toBeVisible({ timeout: 10000 });
  179 |       await expect(page.locator(".staff-order-current-card")).toBeVisible();
  180 |       await expect(page.locator(".staff-order-menu-panel")).toBeVisible();
  181 |     });
  182 | 
  183 |     // 7. KDS Kitchen Board
  184 |     await test.step("Step 7: Kitchen Station Board", async () => {
  185 |       await softNavigate(page, "/kitchen/board");
  186 |       await expect(page.locator(".staff-kitchen-board-page")).toBeVisible({ timeout: 10000 });
  187 |       
  188 |       // Wait for the kitchen stations list to load and select the first station
  189 |       const stationBtn = page.locator(".staff-kitchen-station-list button").first();
  190 |       await stationBtn.waitFor({ state: "visible", timeout: 10000 });
  191 |       await stationBtn.click();
  192 |       await page.waitForTimeout(500);
  193 |       
  194 |       await expect(page.locator(".staff-kitchen-board-lanes")).toBeVisible({ timeout: 10000 });
  195 |     });
  196 | 
  197 |     // 8. Checkout Settlement Preview
  198 |     await test.step("Step 8: Checkout Settlement Page", async () => {
  199 |       await softNavigate(page, `/ops/checkout?order_id=${seededOrderId}&source=order`);
  200 |       await expect(page.locator(".staff-workspace-detail-card").first()).toBeVisible({ timeout: 10000 });
  201 |       await expect(page.locator("h3").first()).toContainText("Màn hình thanh toán");
  202 |     });
  203 | 
  204 |     // 9. Cashier Shift Management
  205 |     await test.step("Step 9: Cashier Shift Page", async () => {
  206 |       await softNavigate(page, "/ops/cashier-shift");
  207 |       await expect(page.locator(".staff-page-header h3")).toContainText("ca thu ngân", { ignoreCase: true, timeout: 10000 });
  208 |     });
  209 | 
  210 |     // 10. Finance Review
  211 |     await test.step("Step 10: Finance Review Page", async () => {
  212 |       await softNavigate(page, "/ops/finance-review");
  213 |       await expect(page.locator(".staff-page-header h3")).toContainText("Đối soát và hóa đơn", { timeout: 10000 });
  214 |     });
  215 | 
  216 |     // 11. Admin settings & Reporting pages
  217 |     await test.step("Step 11: Admin settings catalog & reporting", async () => {
  218 |       // Admin Settings
  219 |       await softNavigate(page, "/admin/settings");
  220 |       await expect(page.locator(".staff-page-header h3")).toContainText("Chi nhánh và thiết lập", { timeout: 10000 });
  221 |       
  222 |       // Admin Reporting
  223 |       await softNavigate(page, "/admin/reporting");
  224 |       await expect(page.locator(".staff-page-header h3")).toContainText("Hub báo cáo vận hành", { timeout: 10000 });
  225 |     });
  226 | 
  227 |     // 12. Unauthorized/Error state resilience handling
  228 |     await test.step("Step 12: Unauthorized redirect state resilience", async () => {
  229 |       // Clear localStorage/sessionStorage/Zustand session context to simulate logout
```