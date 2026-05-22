# Wave 2 Lean Execution Plan

Tài liệu này là roadmap thực thi cho Wave 2 của dự án RestaurantPOS, bám sát phương pháp tiếp cận Lean, ưu tiên tỷ suất hoàn vốn (ROI) cao và đảm bảo an toàn tuyệt đối cho quá trình Limited-Production Launch. Roadmap được chia nhỏ thành các batch có thể kiểm chứng độc lập.

## 1. Batch List & Mức độ Ưu tiên

- **[x] BATCH 0:** Discovery & Task File
- **[x] BATCH 1:** QR Bill Preview / Self-pay Lite
- **[x] BATCH 2:** Online Deposit Lean
- **[x] BATCH 3:** Preorder khi đặt bàn
- **[x] BATCH 4:** Advanced Analytics UI Read-only
- **[x] BATCH 5:** Staff Launch Command Center & E2E Hardening *(thay thế External Order Adapter Mock)*

---

## 2. Kế hoạch Chi tiết từng Batch

### BATCH 1: QR Bill Preview / Self-pay Lite

- **Mục tiêu:** Cho phép khách hàng dùng Web-App/PWA quét mã QR tại bàn để xem Bill hiện tại (Read-only) và có nút "Gọi thanh toán". Không tích hợp Payment Gateway thật ở giai đoạn này.
- **Files/Modules dự kiến sửa:**
  - `app/Modules/Billing/Http/Controllers/Customer/TableBillTokenController.php` (Tạo mới)
  - `routes/api/customer_self_service.php` (Thêm public tokenized endpoints)
  - `customer-web/src/features/billing/*` (Trang Bill Preview qua token)
  - `staff-web/src/features/billing/*` (Màn hình Notification yêu cầu thanh toán)
- **Schema/API impact:**
  - Thêm cột `qr_payment_token` hoặc bảng `table_qr_tokens` (để quản lý phiên QR động).
  - API trả về Bill dựa trên token (không lộ ID tự tăng).
- **Feature flag đề xuất:** `feature_flags.qr_bill_preview`
- **Verification commands:** `npm run verify:wave-1`, `php artisan test --filter=TableBillTokenControllerTest`, `php artisan booking:route-contract:reconcile`
- **Risks:** Lộ lọt dữ liệu hóa đơn của bàn khác nếu token bị đoán được (cần random token đủ mạnh, hết hạn sau khi session đóng).
- **Rollback notes:** Có thể tắt Feature flag để ngừng cấp token mới; các token cũ tự mất hiệu lực do cơ chế validate API.

### BATCH 2: Online Deposit Lean

- **Mục tiêu:** Hoàn thiện state machine cho yêu cầu cọc.
- **Files/Modules dự kiến sửa:**
  - `app/Modules/Reservations/Domain/Models/Reservation.php` (Thêm deposit_status)
  - `app/Modules/Payments/Http/Controllers/Customer/ReservationDepositPaymentController.php` (Sửa đổi luồng intent)
  - Schema: Thêm cột `deposit_status`, `deposit_amount_required`.
  - Frontend: `customer-web` & `staff-web` Reservation detail UI.
- **Schema/API impact:** Sửa table `reservations`. Update OpenAPI.
- **Feature flag đề xuất:** `feature_flags.online_deposit_lean`
- **Risks:** Xung đột trạng thái nếu Staff "Xác nhận đặt bàn" trong lúc Khách hàng đang thực hiện thanh toán cọc.
- **Rollback notes:** Revert schema patch, tắt feature flag. Các booking cũ sẽ bypass trạng thái `deposit_required`.

### BATCH 3: Preorder khi đặt bàn

- **Mục tiêu:** Thêm Preorder entity, lưu nháp và tích hợp vào Customer-Web.
- **Files/Modules dự kiến sửa:**
  - `app/Modules/Ordering/Domain/Models/Preorder.php` và `PreorderItem.php`.
  - `app/Modules/Reservations/Http/Controllers/CustomerReservationPreorderController.php`.
  - Schema: Thêm table `preorders` và `preorder_items`.
- **Feature flag đề xuất:** `feature_flags.reservation_preorders`
- **Risks:** Preorder chuyển thành Order Item nhiều lần (nhân bản dữ liệu) nếu Idempotency check bị bỏ qua. Cần có lock khi convert.
- **Rollback notes:** Vô hiệu hoá UI hiển thị Preorder, Bếp sẽ quay lại quy trình order thủ công.

### BATCH 4: Advanced Analytics UI Read-only

- **Mục tiêu:** Giao diện xem dữ liệu kinh doanh mà không tạo giao dịch.
- **Files/Modules dự kiến sửa:**
  - `app/Modules/Reporting/Http/Controllers/Admin/AnalyticsController.php`.
  - `staff-web/src/features/analytics/*`.
- **Schema/API impact:** Tạo các Read Model (Views/Queries) thay vì sửa schema.
- **Feature flag đề xuất:** `feature_flags.advanced_analytics`
- **Risks:** Query dữ liệu lịch sử quá lớn có thể gây chậm DB. Phải dùng Index và giới hạn date range.
- **Rollback notes:** Ẩn màn hình trên Frontend.

### BATCH 5: Staff Launch Command Center & E2E Hardening

> **Scope change reason:** External Order Adapter Mock deferred. Repo already has 8+ operational domains
> (reservation, deposit, preorder, bill, checkout, waiting list, kitchen, reporting). Staff needed a unified
> view of pending actions *before* limited-production launch — higher ROI than mocking external integrations.

- **Mục tiêu:** Read-only Operations Command Center aggregating pending actions across all domains for staff.
- **Action types implemented:**
  1. `reservation_upcoming` — Confirmed reservations arriving within the horizon window
  2. `reservation_needs_check_in` — Overdue check-ins
  3. `deposit_pending` — Deposit required but not paid
  4. `deposit_expired` — Deposit intent revoked/expired
  5. `preorder_pending` — Preorders awaiting staff confirmation
  6. `bill_payment_pending` — Bills generated, not yet settled
  7. `checkout_pending` — Billed reservations awaiting checkout
  8. `waiting_list_pending` — Active waiting list entries
- **Files changed:**
  - `app/Modules/FloorOperations/Application/Queries/CommandCenter/StaffCommandCenterHandler.php` (NEW)
  - `app/Modules/FloorOperations/Http/Requests/Staff/CommandCenterRequest.php` (NEW)
  - `app/Modules/FloorOperations/Http/Controllers/Staff/CommandCenterController.php` (NEW)
  - `routes/api/staff_pos.php` (MODIFIED — added `GET /api/v1/staff/operations/command-center`)
  - `staff-web/src/domains/ops/command-center/command-center-hook.ts` (NEW)
  - `staff-web/src/workspaces/ops/pages/command-center/CommandCenterPage.tsx` (NEW)
  - `staff-web/src/workspaces/ops/routes.tsx` (MODIFIED)
  - `staff-web/src/workspaces/routes.ts` (MODIFIED)
  - `staff-web/src/app/router/workspace-paths.ts` (MODIFIED)
  - `staff-web/src/app/router/index.tsx` (MODIFIED)
  - `tests/Feature/Staff/FloorOperations/StaffCommandCenterHttpFlowTest.php` (NEW — 8 tests all green)
  - `build/api-consumer/*` (regenerated)
- **Verification commands run:**
  - `php artisan test --filter StaffCommandCenterHttpFlowTest` → **8/8 PASSED**
  - `npx tsc --noEmit` (staff-web) → **0 errors**
  - `php artisan booking:api-artifacts:generate` → **success (156 ops)**
- **Schema changes:** None — purely computed from existing `reservations`, `reservation_orders`, `waiting_list` tables.
- **Remaining risks:**
  - Command center queries `reservation_orders` with `order_type='PreOrder'` to detect preorder_pending; if preorder status tracking is refined later, the query may need updating.
  - `bill_payment_pending` detects via `billed_at NOT NULL` — if billing flow is extended, revisit condition.
  - No WebSocket/realtime. Staff must manually refresh or wait 60 s auto-refresh.
- **Follow-up after launch:**
  - **External delivery adapter** (Grab/ShopeeFood) — deferred; needs external API contracts first.
  - **WebSocket/realtime command center** — deferred to post-launch hardening.
  - **Notification automation** (push notify staff on new high-priority actions) — deferred.
  - **MRP / advanced inventory planning** — deferred.
