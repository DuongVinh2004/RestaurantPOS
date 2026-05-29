# Inventory/Admin Deep Audit Report

## 1. Overview
- **Batch:** Inventory/Admin Deep Audit (Batch 8)
- **Objective:** Audit sâu các luồng Admin/Inventory trên staff-web/admin UI (Ingredients, Suppliers, PO, Receipt, Stock movements, Menu item recipe, Guards).
- **Environment:** Local / UAT
- **Test File:** `e2e/inventory-admin-deep-audit.spec.ts`

## 2. Test Execution Details
- **Runner:** Playwright Chromium (1 worker)
- **Time:** ~20.9s (11/11 tests passed gracefully)
- **Data Entity Generated:** Không có do UI thiếu hụt (NOT_IMPLEMENTED) phần lớn các nút chức năng Create/Update.

## 3. Results Summary
| Step | Flow / Module | Status | Logs / Evidence |
|---|---|---|---|
| 1 | Login Admin | PASS | `INV_LOGIN_OK` |
| 2 | Inventory Navigation Baseline | PASS | `INV_NAVIGATION_FOUND` |
| 3 | Ingredients CRUD | PARTIAL | `INV_INGREDIENT_CRUD_PARTIAL` |
| 4 | Suppliers CRUD | PARTIAL | `INV_SUPPLIER_CRUD_PARTIAL` |
| 5 | Purchase Orders | NOT_IMPLEMENTED | `INV_PO_NOT_IMPLEMENTED` |
| 6 | Purchase Receipts | NOT_IMPLEMENTED | `INV_RECEIPT_NOT_IMPLEMENTED` |
| 7 | Stock Movement & Recon | NOT_IMPLEMENTED | `INV_STOCK_MOVEMENT_NOT_IMPLEMENTED` |
| 8 | Recipe Management | NOT_IMPLEMENTED | `INV_RECIPE_NOT_IMPLEMENTED` |
| 9 | Row Version Conflict | BLOCKED | `INV_ROW_VERSION_CONFLICT_NOT_TESTABLE_UI` |
| 10 | Import/Export | NOT_IMPLEMENTED | `INV_IMPORT_EXPORT_NOT_IMPLEMENTED` |
| 11 | Permission Guard | NEEDS_DATA | `INV_PERMISSION_GUARD_NEEDS_DATA` |

## 4. Key Findings
- **Inventory Navigation:** Navigation Menu Workspace Quản trị/Kho đã hoạt động và có tab Nguyên liệu (Ingredients), Nhà cung cấp (Suppliers).
- **Missing UI Modules:** Chức năng Purchase Orders (Đơn mua hàng), Purchase Receipts (Phiếu nhập kho), Stock Movement (Kiểm kê/Điều chỉnh), Recipe (Định lượng) hoàn toàn chưa có Tab Navigation hoặc Route tương ứng.
- **Partial CRUD:** Tab Ingredients và Suppliers có tồn tại nhưng thiếu các Form/Button Create/Update hoàn chỉnh hoặc bị disabled/ẩn. Chức năng chưa dùng được end-to-end.
- **Row Version Guard & Imports:** Không thể test do không có UI trigger các hành động lưu hoặc export.

## 5. Remaining Risks
- Rủi ro rất cao đối với luồng Nhập kho (Purchase Order/Receipt) vì Backend có thể đã hoàn thiện bảng và logic Row Version/Optimistic Locking, nhưng Staff Web hoàn toàn mù mờ và không có cách nào input data ngoài Tinker.
- Khi làm UI cho Ingredient/Supplier, team Frontend cần cẩn trọng implement truyền `row_version` (nếu API bắt buộc) để tránh lỗi 409 Conflict hoặc Stale Object Error.

## 6. Recommendation
- **NEEDS INVENTORY BUGFIX BATCH / FEATURE DEV:** Yêu cầu UI Team bắt đầu code các màn hình Create/Update/List cho Ingredient, Supplier và đặc biệt là Purchase Order.
- **READY FOR ADMIN MASTER DATA DEEP AUDIT:** Có thể tiến hành Audit Master Data (Branch settings, Users, Roles) nếu các luồng Admin khác đã sẵn sàng.
