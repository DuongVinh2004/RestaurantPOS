# Kitchen/KDS Deep Audit Report

## 1. Overview
- **Batch:** Kitchen/KDS Deep Audit (Batch 5)
- **Objective:** Audit toàn bộ nghiệp vụ Kitchen/KDS (Kitchen Display System), bao gồm lifecycle của ticket, hệ thống định tuyến (routing), tính toán idempotency (chống trùng lặp lệnh dispatch), và đồng bộ trạng thái thực tế.
- **Scope:** 
  1. Setup checked-in reservation (Tạo & nhận bàn).
  2. Order routed items and dispatch (Gọi món có định tuyến KDS).
  3. Verify KDS Station Routing & Lifecycle (Kiểm tra KDS nhận ticket, fire, bump, recall).
  4. Idempotency Check (Kiểm tra chặn duplicate dispatch).

## 2. Test Execution Details
- **Test File:** `e2e/kitchen-kds-deep-audit.spec.ts`
- **Runner:** Playwright Chromium (1 worker)
- **Time:** ~1.9m

## 3. Results Summary
| Step | Flow / Module | Status | Duration | Evidence |
|---|---|---|---|---|
| 1 | Setup checked-in reservation | PASS | 33.8s | Test Output Log |
| 2 | Order routed items and dispatch | PASS | 25.4s | Test Output Log |
| 3 | Verify KDS Station Routing & Lifecycle | PASS | 36.1s | Test Output Log |
| 4 | Idempotency Check (Duplicate Dispatch Guard) | PASS | 16.9s | Test Output Log |

**Overall Status:** PASS (4/4 tests)

## 4. Key Findings & Bug Fixes
- **BUG-UI-008:** Lệnh helper `booking:qa-branch-247` gây lỗi crash toàn hệ thống Customer Web.
  - **Root Cause:** Command đã set cứng `default_service_minutes` trong booking policy là `15` phút. Trong khi `BranchSchedulingPolicyService` của backend có rule chặt chẽ: `30` đến `480` phút. Sự vi phạm này khiến Laravel ném ra `ValidationException`, làm API `/api/v1/restaurant/profile` trả về lỗi 422 và bị map thành 400 "The requested action violates a business rule."
  - **Resolution:** Sửa lại giá trị `default_service_minutes => 30` trong lệnh `booking:qa-branch-247`. Hệ thống phục hồi và KDS Test chạy thành công.

## 5. Next Steps
- Cập nhật tài liệu QA và chuẩn bị chuyển sang các batch test tiếp theo (như Check-out / Billing Deep Audit hoặc Inventory / Settings).
- Luồng Kitchen/KDS đã ổn định và không cần phải sửa đổi thêm ở vòng audit này.
