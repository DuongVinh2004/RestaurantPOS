# Staff Web Post-UIUX Closure Report

## Tổng quan

Đợt này tập trung khóa chất lượng sau vòng UI/UX cockpit, không rewrite app và không đổi kiến trúc route/query hiện có. Các route đang mount thật vẫn đi qua `src/app/router/index.tsx` với shell `StaffAppShell`, gồm: access, dashboard, tables, reservations, orders, kitchen, checkout, waiting-list, cashier-shift, finance-review, conversations, audit-trail, reporting và login.

Đã hoàn tất các nhóm A/B/C/D/E/F ở mức closure thực dụng: sửa runtime polish, copy staff-facing, flow handoff, query invalidation sau mutation, journey context audit, và tăng test cho journey/error/audit. Chưa hoàn tất tuyệt đối các luồng cần backend contract mới; các gap được ghi ở phần cuối.

## A. Stabilization Pass

**Mục tiêu:** giảm lỗi sau patch UI/UX, loại warning runtime, giữ router/shell/context/type safety.

**Files changed:** `staff-web/src/components/feedback/StaffFacingAlert.tsx`, `staff-web/src/components/drawers/ReservationDetailDrawer.tsx`, `staff-web/src/features/auth/LoginPage.tsx`, `staff-web/src/features/cashier/CashierShiftPage.tsx`, `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/features/conversations/ConversationInboxPage.tsx`, `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/audit/AuditTrailPage.tsx`, `staff-web/src/features/reporting/ReportingHubPage.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`.

**Thay đổi chính:** đổi AntD `Alert message` sang `title`, đổi `Space direction="vertical"` sang `orientation="vertical"` trong các màn đang mount thật, giảm copy kỹ thuật như `backend`, `API`, `row_version` ở primary UX.

**Lý do:** giảm warning runtime, giữ UI scan rõ hơn, tránh staff-facing copy giống tool kỹ thuật.

**Acceptance criteria đạt:** lint sạch, typecheck sạch, build sạch; copy vận hành đồng nhất hơn; không đổi router.

**Rủi ro còn lại:** một số page cũ không mount trong router vẫn còn copy/test legacy, không xử lý trong scope này để tránh dàn trải.

## B. Backend Integration Closure

**Mục tiêu:** đóng các chỗ UI đã đẹp nhưng dữ liệu sau mutation có thể stale hoặc flow không quay lại đúng backend state.

**Files changed:** `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/features/orders/OrderWorkspacePage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`, `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/core/api/errors.ts`, `staff-web/src/lib/api-errors.ts`.

**Thay đổi chính:** sau check-in bàn, dispatch bếp, ticket action, finalize checkout, hệ thống invalidate thêm các query liên quan như reservations, active order, kitchen tickets, order detail, checkout order detail, finance reconciliation, cashier shift và reporting sales.

**Lý do:** khi quay lại từ bếp/checkout/finance, nhân viên không nhìn dữ liệu cũ hoặc phải tự refresh thủ công.

**Acceptance criteria đạt:** query/mutation behavior giữ nguyên contract, chỉ tăng invalidation an toàn; row_version vẫn được gửi khi backend yêu cầu; error formatter vẫn giữ mã truy vết nhưng copy staff-facing hơn.

**Rủi ro còn lại:** finance reconciliation chưa nhận `branch_id`; audit trail chưa lọc trực tiếp theo branch context từ shell.

## C. Flow Closure Theo 6 Nhóm

### 1. Access / readiness / branch / role

**Files changed:** `staff-web/src/features/access/AccessGatePage.tsx`, `staff-web/src/features/auth/LoginPage.tsx`, `staff-web/src/core/utils/journey.ts`, `staff-web/src/core/utils/journey.test.ts`.

**Thay đổi chính:** copy access/login bớt technical; thêm `audit` vào journey source để trace được nguồn quay lại từ audit mà vẫn resume đúng route thực.

**Acceptance criteria đạt:** readiness/branch context không bị phá; resume target vẫn route-driven.

### 2. Tables + reservations + waiting

**Files changed:** `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/components/drawers/ReservationDetailDrawer.tsx`.

**Thay đổi chính:** check-in từ table board giờ set reservation/table context, invalidate reservations/active-order/table-board và handoff sang `/orders` bằng journey params; candidate flags chuyển từ `due_soon/overdue` sang copy staff-facing; waiting copy bỏ thuật ngữ backend/user_id ở alert chính.

**Acceptance criteria đạt:** flow dashboard/tables -> nhận bàn -> orders liền hơn, không cần reconstruct ID thủ công; selected context rõ hơn.

**Rủi ro còn lại:** waiting list chưa có deep-link URL cho selected waiting item, nên conversation -> waiting vẫn chỉ mở danh sách chờ.

### 3. Orders + kitchen

**Files changed:** `staff-web/src/features/orders/OrderWorkspacePage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`.

**Thay đổi chính:** dispatch từ order invalidate kitchen stations/tickets và checkout order detail; fire/bump/recall ở kitchen invalidate kitchen, order detail và checkout detail liên quan.

**Acceptance criteria đạt:** quay lại order/checkout sau thao tác bếp ít gặp stale state hơn; row_version safety vẫn giữ.

**Rủi ro còn lại:** ticket actions hiện không gửi row_version vì backend wrapper hiện tại không nhận row_version cho fire/bump/recall.

### 4. Checkout + cashier shift

**Files changed:** `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/features/cashier/CashierShiftPage.tsx`.

**Thay đổi chính:** finalize settlement invalidate finance, cashier shift và reporting sales; copy review trước chốt tiền/đóng ca dùng “phiên bản” thay vì `row_version`; giữ guard cashier shift readiness.

**Acceptance criteria đạt:** luồng tiền an toàn hơn sau chốt; staff thấy context order/shift rõ hơn trước action nhạy cảm.

**Rủi ro còn lại:** sau khi mở cashier shift, readiness vẫn phụ thuộc refresh session như contract hiện tại.

### 5. Finance review

**Files changed:** `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/finance/finance-review.test.ts`.

**Thay đổi chính:** copy branch-scope gap staff-facing hơn; giữ warning rõ rằng reconciliation chưa branch-scoped trực tiếp; giữ issue invoice flow và link quay lại reservation/checkout/cashier.

**Acceptance criteria đạt:** không giả lập branch filter không có trong backend; finance review vẫn dùng dữ liệu thật từ reconciliation/invoice endpoints.

**Rủi ro còn lại:** `FinancialReservationSummary` chưa có row_version, nên link finance -> reservations không thể truyền reservation_row_version.

### 6. Conversations + audit trail

**Files changed:** `staff-web/src/features/conversations/ConversationInboxPage.tsx`, `staff-web/src/features/conversations/ConversationsPage.test.tsx`, `staff-web/src/features/audit/AuditTrailPage.tsx`, `staff-web/src/features/audit/audit-trail.ts`, `staff-web/src/features/audit/audit-trail.test.ts`.

**Thay đổi chính:** conversation copy/error copy staff-facing hơn; audit trail mở luồng liên quan bằng journey-aware path thay vì `/reservations` hoặc `/orders` trống; thêm helper `auditLinkedEntityTarget` để test path và permission fallback.

**Acceptance criteria đạt:** audit -> reservation/order truy vết và resume context rõ hơn; permission-aware fallback được test.

**Rủi ro còn lại:** audit API chưa branch-scoped trực tiếp; conversation -> waiting selected item cần URL state ở `WaitingListPage` nếu muốn deep-link chính xác.

## D. Test Hardening

**Files changed:** `staff-web/src/core/api/errors.test.ts`, `staff-web/src/core/utils/journey.test.ts`, `staff-web/src/lib/api-errors.test.ts`, `staff-web/src/features/audit/audit-trail.test.ts`, `staff-web/src/features/conversations/ConversationsPage.test.tsx`.

**Thay đổi chính:** thêm test core API error formatter; cập nhật lib error formatter expectation; thêm audit journey link tests; thêm journey audit-source resume test.

**Acceptance criteria đạt:** full staff-web test suite pass: 50 files, 202 tests.

**Rủi ro còn lại:** chưa thêm browser/e2e test thật cho 6 flow; hiện verify bằng unit/component tests và static reasoning.

## E. Design System / Reusable Pattern Consolidation

**Files changed:** `staff-web/src/components/feedback/StaffFacingAlert.tsx`, `staff-web/src/core/api/errors.ts`, `staff-web/src/lib/api-errors.ts`, `staff-web/src/features/audit/audit-trail.ts`.

**Thay đổi chính:** shared alert dùng prop AntD mới; error formatter dùng “Thiếu quyền” và “Mã truy vết”; audit link target gom thành helper nhỏ cùng feature.

**Acceptance criteria đạt:** giảm duplicate warning/copy logic, không thêm abstraction lớn.

**Rủi ro còn lại:** vẫn còn một số pattern legacy ở page không mount thật, chưa chạm để tránh phạm vi lan rộng.

## F. Production Readiness Pass

**Thay đổi chính:** chạy encoding guard, lint, TypeScript, test suite, build; giảm AntD deprecated props ở mounted pages; giữ responsiveness/layout hiện có.

**Acceptance criteria đạt:** không có regression rõ ở router/shell/context/finance flows; selected context và resume flow ổn định hơn; error/loading/stale copy đồng nhất hơn.

**Rủi ro còn lại:** Vite build vẫn cảnh báo chunk lớn hơn 500 kB; đây là warning bundling hiện hữu, chưa code-split trong đợt này vì không phục vụ flow closure trực tiếp.

## Flow Chưa Khép Kín Hoàn Toàn

- Finance reconciliation chưa branch-scoped trực tiếp bằng `branch_id`.
- Audit trail chưa branch-scoped trực tiếp bằng `branch_id`.
- Finance -> reservations chưa truyền được `reservation_row_version` vì response summary chưa có row_version.
- Conversation -> waiting list chưa deep-link vào đúng waiting item vì waiting page chưa có URL state cho selected waiting id.
- Waiting bulk action chưa mở rộng vì các action an toàn cần per-entry row_version/table/user payload; bulk dễ tạo thao tác sai nếu backend không có batch contract.

## Backend Gaps

- Thêm `branch_id` vào finance reconciliation query contract nếu muốn dashboard/finance khớp shell branch tuyệt đối.
- Thêm `branch_id` vào audit trail query contract hoặc server-side branch scope metadata.
- Trả `row_version` trong `FinancialReservationSummary` nếu finance review cần handoff sang reservation mutation an toàn hơn.
- Cân nhắc batch-safe endpoint cho waiting list nếu muốn bulk notify/advance có guardrail.
- Cân nhắc row_version/concurrency payload cho kitchen ticket fire/bump/recall nếu backend muốn harden thao tác KDS.

## Verification

- `npm run lint`: passed.
- `npx tsc --noEmit`: passed. Repo không có script `typecheck`, nên dùng TypeScript CLI trực tiếp; `npm run build` cũng chạy `tsc`.
- `npm test -- --run src/core/utils/journey.test.ts src/core/api/errors.test.ts src/lib/api-errors.test.ts src/features/audit/audit-trail.test.ts src/features/orders/OrderWorkspacePage.test.tsx src/features/checkout/CheckoutPage.test.tsx src/features/dashboard/DashboardPage.test.tsx src/features/finance/finance-review.test.ts`: passed. Do package script luôn chạy toàn bộ `src`, kết quả thực tế là 50 files / 202 tests passed.
- `npm run build -- --mode development`: passed. Có Vite chunk-size warning cho bundle JS lớn hơn 500 kB.
- `npm run encoding:check`: passed.

## Manual Reasoning Notes

- Không thay đổi route mount hoặc capability gate trong `src/app/router/index.tsx`.
- Không thay đổi payload mutation ngoài việc giữ nguyên row_version đang dùng.
- Không thêm mock/fallback giả để che backend gap; các gap finance/audit/waiting được ghi rõ.
