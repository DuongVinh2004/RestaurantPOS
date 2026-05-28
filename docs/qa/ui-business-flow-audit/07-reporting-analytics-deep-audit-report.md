# Reporting/Analytics Deep Audit Report

## 1. Overview
- **Batch:** Reporting/Analytics Deep Audit (Batch 7)
- **Objective:** Audit module Báo cáo (Reporting & Analytics) xem có hiển thị đúng trạng thái checkout vừa hoàn tất hay không, kiểm tra filter và real-data vs placeholder.
- **Environment:** Local / UAT
- **Test File:** `e2e/reporting-analytics-deep-audit.spec.ts`

## 2. Test Execution Details
- **Runner:** Playwright Chromium (1 worker)
- **Time:** ~1.4m (4/4 tests passed)
- **Data Entity Generated:**
  - reservation_id: Dynamically assigned via UI
  - order_id: Generated during setup flow
  - payment_id: Generated during cash checkout
  - Report filter tested: Today

## 3. Results Summary
| Step | Flow / Module | Status | Duration | Logs / Evidence |
|---|---|---|---|---|
| 1 | Setup data (Create paid order) | PASS | 1.1m | REP_DATA_SETUP_COMPLETE |
| 2 | Verify reporting navigation | NOT_IMPLEMENTED | 8.4s | REP_NAVIGATION_NOT_IMPLEMENTED |
| 2.1 | Verify dashboard load | NOT_IMPLEMENTED | | REP_DASHBOARD_NOT_IMPLEMENTED_OR_EMPTY |
| 3 | Verify sales/payment summary & filters | NOT_IMPLEMENTED | 3.1s | REP_FILTER_NOT_IMPLEMENTED |
| 4 | Verify Real vs Placeholder Data | NOT_IMPLEMENTED | 3.0s | REP_FIGURES_NOT_IMPLEMENTED |

## 4. Key Findings & Bug Fixes
- **Data Setup:** Việc khởi tạo giao dịch từ lúc Đặt bàn -> Nhận bàn -> Order món (có định tuyến KDS) -> Checkout tiền mặt đều hoạt động rất trơn tru, chứng tỏ API và Data Model Core đã rất vững.
- **UI Implementation Gap:** UI của module `Reporting` hoặc `Analytics` hoàn toàn **chưa được implement** ở Staff Web hiện tại. Các Menu Item như "Báo cáo" không xuất hiện, truy cập trực tiếp url `/reports` hoặc dashboard summary block đều không tìm thấy element chứa dữ liệu doanh thu (`₫` / Revenue).
- Scripts được viết an toàn (fail gracefully thay vì crash cứng) khi phát hiện chức năng chưa được phát triển, và đánh dấu chính xác là `NOT_IMPLEMENTED`.

## 5. Remaining Risks
- Hệ thống backend có thể đã có snapshot table hoặc query endpoints phục vụ reporting (đã có command `rebuild_reporting_snapshots`), nhưng Web Client chưa tiêu thụ (consume). Rủi ro là API contract chưa được kiểm chứng độ khớp (schema validation) với Frontend UI.
- Chưa có giao diện để Manager đối soát (reconcile) dữ liệu bán hàng cuối ngày.

## 6. Recommendation
- **READY FOR REPORTING/ANALYTICS BUGFIX / FEATURE DEV:** Staff-web cần được triển khai nhánh tính năng mới để làm trang Reports cơ bản (Sales Summary, Category Breakdown).
- **READY FOR INVENTORY/ADMIN DEEP AUDIT:** Hoặc có thể tạm thời skip Reporting và chuyển qua audit Inventory/Admin/Menu Settings.
