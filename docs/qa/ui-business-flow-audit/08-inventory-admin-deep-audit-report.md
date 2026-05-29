# Inventory/Admin Deep Audit Report

## 1. Overview
- **Batch:** Inventory/Admin Deep Audit (Batch 8)
- **Objective:** Audit sâu các luồng Admin/Inventory trên staff-web/admin UI (Ingredients, Suppliers, PO, Receipt, Stock movements, Menu item recipe, Guards).
- **Environment:** Local / UAT
- **Test File:** `e2e/inventory-admin-deep-audit.spec.ts`

## 2. Test Execution Details
- **Runner:** Playwright Chromium (1 worker)
- **Time:** ~29.2s (12/12 tests passed gracefully)
- **Data Entity Generated:** Không thành công do thiếu các Routes API phía Backend.

## 3. Results Summary
| Step | Flow / Module | Status | Logs / Evidence |
|---|---|---|---|
| 1 | Login Admin | PASS | `INV_LOGIN_OK` |
| 2 | Inventory Navigation Baseline | PASS | `INV_NAVIGATION_FOUND` |
| 3 | Ingredients CRUD | PARTIAL | `INV_INGREDIENT_CRUD_PARTIAL` (UI exists, POST 404) |
| 4 | Suppliers CRUD | PARTIAL | `INV_SUPPLIER_ERROR` (UI exists, POST 404) |
| 5 | Purchase Orders | PARTIAL | `INV_PO_ERROR` (UI exists, POST 404) |
| 6 | Purchase Receipts | NOT_IMPLEMENTED | `INV_RECEIPT_UI_FOUND` (Matched placeholder text only) |
| 7 | Stock Movement & Recon | NOT_IMPLEMENTED | `INV_STOCK_MOVEMENT_UI_FOUND` (Matched placeholder text only) |
| 8 | Row Version Conflict | NOT_IMPLEMENTED | `INV_ROW_VERSION_NOT_IMPLEMENTED` |
| 9 | Negative Stock Guard | NOT_IMPLEMENTED | `INV_NEGATIVE_STOCK_NOT_IMPLEMENTED` |
| 10 | Recipe Management | NOT_IMPLEMENTED | `INV_RECIPE_UI_FOUND` (Matched placeholder text only) |
| 11 | Import/Export | NOT_IMPLEMENTED | `INV_EXPORT_NOT_IMPLEMENTED` |
| 12 | Permission Guard | NEEDS_DATA | `INV_PERMISSION_GUARD_NEEDS_DATA` |

## 4. Key Findings
- **Inventory Navigation:** Navigation Menu Workspace Quản trị/Kho đã hoạt động và có render các Components Layout Card cho Nguyên liệu, Nhà cung cấp, và Nhập kho/Lịch sử xuất nhập kho.
- **Partial CRUD Forms:** Modals Create/Update cho `Ingredients`, `Suppliers`, và `Purchase Orders` đã được code UI và gắn API call bằng TanStack Query.
- **Missing Backend Routes:** Quá trình submit forms trên bị thất bại (HTTP 404 Not Found) do Backend chưa được implement hoặc chưa có các endpoints tương ứng (`POST /api/v1/admin/inventory/ingredients`, v.v.).
- **Missing UI Modules:** Chức năng thực sự cho Purchase Receipts (Phiếu nhập kho), Stock Movement (Kiểm kê/Điều chỉnh), và Recipe (Định lượng menu item) chưa có UI để nhập liệu, chỉ có các text hiển thị thống kê.
- **Guards & Edge cases:** Row Version Guard và Negative Stock Guard chưa thể test do chức năng tạo phiếu nhập/xuất kho bị thiếu (Blocked by missing API and UI).

## 5. Remaining Risks
- Rủi ro rất cao đối với luồng Nhập kho (Purchase Order/Receipt) vì UI hiện tại sử dụng mock API endpoint chưa tồn tại ở backend, gây ra lỗi 404 làm chặn toàn bộ quy trình.
- Cần có backend endpoints chính thức với cơ chế kiểm soát số lượng xuất/nhập an toàn và hỗ trợ idempotency cho các operation Inventory.

## 6. Recommendation
- **NEEDS BACKEND ROUTES DEVELOPMENT:** Yêu cầu Backend Team phát triển các Routes API CRUD cho Inventory (Ingredients, Suppliers, Purchase Orders) để match với các API client ở `inventory-crud-api.ts`.
- **NEEDS FURTHER UI DEVELOPMENT:** Tiếp tục hoàn thiện UI cho Receipt và Stock Movements.
- **STOP AUDIT HERE:** Ngưng các phần Audit về sau của Inventory cho đến khi foundation API backend được hoàn thành.
