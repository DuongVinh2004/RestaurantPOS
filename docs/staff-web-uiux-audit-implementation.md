# Staff Web UI/UX Audit Implementation

## A. Tổng quan

### Audit thực tế trước khi patch

- Router đang mount thật qua `staff-web/src/app/router/index.tsx`: `AccessGatePage`, `DashboardPage`, `TableBoardPage`, `ReservationsPage`, `OrderWorkspacePage`, `KitchenBoardPage`, `CheckoutPage`, `WaitingListPage`, `CashierShiftPage`, `FinanceReviewPage`, `ConversationInboxPage`, `AuditTrailPage`, `ReportingHubPage`.
- Shell/context chạy thật qua `staff-web/src/app/layout/StaffAppShell.tsx`, `staff-web/src/app/layout/useStaffShellContext.ts`, `staff-web/src/components/layout/PageHeader.tsx`, `staff-web/src/components/layout/SplitWorkspace.tsx`.
- State dùng thật để giữ operational context: `staff-web/src/app/store/auth-store.ts`, `staff-web/src/app/store/flow-store.ts`, `staff-web/src/core/utils/journey.ts`.
- Nền tảng được giữ nguyên trong toàn bộ patch: capability gating, readiness gating, branch context, journey URL context, row_version/concurrency safety, split workspace, selected context propagation, route-driven shell.

### Đã triển khai patch nào

- Đã triển khai mạnh: `UX-01`, `UX-02`, `UX-03`, `UX-04`, `UX-05`, `UX-06`, `UX-07`, `UX-08`, `UX-09`, `UX-10`.
- Đã triển khai một phần có kiểm soát: `UX-11`, `UX-12`, `UX-13`, `UX-14`, `UX-15`, `UX-16`.

### Chưa triển khai trọn vẹn patch nào

- `UX-12` mới có command palette tối giản trong shell, chưa có keyboard action layer sâu hơn theo từng workspace.
- `UX-11` mới thêm bulk action an toàn cho conversation inbox, chưa mở rộng sang các queue khác vì rủi ro nghiệp vụ.
- `UX-13` mới đổi focus dashboard theo role/focus model, chưa tách layout dashboard chuyên biệt cho từng role.
- `UX-14` mới nâng cashier shift/access gate theo hướng handoff + risk review, chưa có chế độ handoff mode riêng.
- `UX-15` mới thêm cue theo aging/urgency/predictive hint trong dashboard và queue pages, chưa có engine dự báo riêng.
- `UX-16` mới đổi audit/reporting theo hướng investigation, chưa có workflow điều tra chuyên sâu nhiều bước.

### Lý do phạm vi còn lại

- Ưu tiên giữ an toàn cho luồng tiền, branch scoping, readiness gating và journey context trước khi mở thêm command/automation layer.
- Một số hạng mục cần thêm backend signals hoặc thống nhất vận hành trước khi làm sâu hơn, đặc biệt với predictive cues và bulk action trên queue nhạy cảm.

## B. Theo từng patch

### Patch UX-01

- Mục tiêu: biến shell/header thành operating header có context dock, resume, refresh và branch awareness.
- Files changed: `staff-web/src/app/layout/StaffAppShell.tsx`, `staff-web/src/app/layout/useStaffShellContext.ts`, `staff-web/src/components/layout/PageHeader.tsx`, `staff-web/src/index.css`.
- UI/UX changes: shell header sticky; context dock hiển thị branch, shift, readiness, freshness, selected context; thêm quick navigation, refresh, resume tray và cảnh báo flow khác chi nhánh.
- Copy changes: đổi shell copy sang staff-facing, bỏ kiểu header generic.
- Logic safety notes: vẫn đi qua auth/readiness hiện có; branch switch vẫn bám flow store và không bỏ journey context.
- Acceptance criteria reached: đạt cho "nhìn 1-2 giây biết đang thao tác ở đâu", "resume nhanh", "branch switch không thao tác mù".
- Caveats: chưa có command palette và chưa resolve triệt để mọi raw ID nếu backend không trả tên.

### Patch UX-02

- Mục tiêu: chuẩn hóa loading/empty/error/stale/retry state.
- Files changed: `staff-web/src/components/states/StateBlocks.tsx`, `staff-web/src/components/feedback/StaffFacingAlert.tsx`, `staff-web/src/core/utils/format.ts`, `staff-web/src/index.css`.
- UI/UX changes: thêm reusable stale/inline warning/retry blocks; last updated/freshness copy rõ hơn; state block nhìn như trạng thái vận hành chứ không như app hỏng.
- Copy changes: thống nhất staff-facing copy cho loading, empty, stale, retry.
- Logic safety notes: shared state components chỉ bọc hiển thị, không đổi data contract.
- Acceptance criteria reached: đạt cho loading/empty/error/stale rõ nghĩa và có hướng xử lý tiếp.
- Caveats: chưa thay hết toàn bộ app, tập trung vào các màn được audit.

### Patch UX-03

- Mục tiêu: dashboard phải trả lời "việc gì nóng nhất", không chỉ đếm số lượng.
- Files changed: `staff-web/src/features/dashboard/DashboardPage.tsx`, `staff-web/src/features/dashboard/dashboard-view-model.ts`, `staff-web/src/features/dashboard/components/DashboardTopBar.tsx`, `staff-web/src/features/dashboard/components/UrgentAlertCard.tsx`, `staff-web/src/features/dashboard/components/QueueSnapshotCard.tsx`, `staff-web/src/features/dashboard/components/MiniTableBoardCard.tsx`, `staff-web/src/features/dashboard/components/ShiftHealthCard.tsx`, `staff-web/src/features/dashboard/components/CashierSnapshotCard.tsx`, `staff-web/src/index.css`.
- UI/UX changes: urgency band, aging labels, grouped priority, freshness bar, activity strip, adaptive top focus; card order bám priority paths thay vì layout tĩnh.
- Copy changes: thêm ngôn ngữ "xử lý ngay", "cần chú ý", "theo dõi", "dữ liệu đã cũ".
- Logic safety notes: urgency model dùng snapshot/view-model hiện có, không chạm business mutation.
- Acceptance criteria reached: đạt cho waiting/kitchen/checkout/conversations có aging/SLA cue và CTA xử lý nhanh.
- Caveats: urgency vẫn là heuristic frontend, chưa dùng SLA contract từ backend.

### Patch UX-04

- Mục tiêu: nâng queue triage cho tables/reservations/waiting/kitchen/conversations/finance.
- Files changed: `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/features/reservations/ReservationsPage.tsx`, `staff-web/src/components/drawers/ReservationDetailDrawer.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`, `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/conversations/ConversationInboxPage.tsx`.
- UI/UX changes: preset chips/filter nhanh, relative age, clearer selected context, stronger side-panel detail, queue-first entry points.
- Copy changes: title/description ở các page đổi sang ngôn ngữ điều phối và xử lý.
- Logic safety notes: mọi action vẫn đi qua mutation/row_version/readiness cũ; không đổi API shape.
- Acceptance criteria reached: đạt ở mức "nhìn list biết nên xử gì trước" và "không cần mở từng item mới thấy độ nóng" cho phần lớn màn chính.
- Caveats: tables và reservations được tăng clarity/preset nhưng chưa có sorting heuristic sâu như dashboard.

### Patch UX-05

- Mục tiêu: thêm anti-mistake UX cho checkout, cashier shift và finance review.
- Files changed: `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/features/cashier/CashierShiftPage.tsx`, `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/dashboard/components/ShiftHealthCard.tsx`, `staff-web/src/features/dashboard/components/CashierSnapshotCard.tsx`.
- UI/UX changes: context chips rõ selected reservation/order/shift/readiness; review blocks trước action nhạy cảm; finance filters có reset/advanced section; cashier shift nhấn mạnh handoff/risk review.
- Copy changes: đổi từ technical wording sang "review trước khi chốt tiền", "review handoff trước khi đóng ca".
- Logic safety notes: không bỏ readiness gating, không bỏ invoice/settlement checks, không nới destructive action.
- Acceptance criteria reached: đạt cho "thấy đủ context trước action nhạy cảm" và "khó bấm nhầm hơn".
- Caveats: destructive vs primary action separation vẫn bám component hiện có, chưa có dedicated confirmation shell riêng.

### Patch UX-06

- Mục tiêu: dọn copy staff-facing, giảm technical/generic phrasing.
- Files changed: `staff-web/src/app/layout/useStaffShellContext.ts`, `staff-web/src/components/states/StateBlocks.tsx`, `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/orders/OrderWorkspacePage.tsx`, `staff-web/src/features/access/AccessGatePage.tsx`, `staff-web/src/features/dashboard/*`.
- UI/UX changes: title/description toàn app thiên về ngôn ngữ vận hành.
- Copy changes: giảm raw system wording; thêm explanation staff-facing cho stale/error/review state.
- Logic safety notes: chỉ đổi presentational copy.
- Acceptance criteria reached: đạt ở các màn chính đã audit.
- Caveats: vài label vẫn cần backend trả semantic name tốt hơn để bỏ hoàn toàn raw ID.

### Patch UX-07

- Mục tiêu: pinned work + resume tray cho continuity/resume flow.
- Files changed: `staff-web/src/app/store/flow-store.ts`, `staff-web/src/core/utils/journey.ts`, `staff-web/src/app/layout/useStaffShellContext.ts`, `staff-web/src/app/layout/StaffAppShell.tsx`, `staff-web/src/features/access/AccessGatePage.tsx`, `staff-web/src/app/store/flow-store.test.ts`.
- UI/UX changes: shell có recent work/pinned work/resume tray; access gate có thẻ "Công việc có thể tiếp tục ngay".
- Copy changes: giải thích resume theo branch-safe context.
- Logic safety notes: work items giữ theo path + branchId + subtitle, không phá journey URL context cũ.
- Acceptance criteria reached: đạt cho quay lại nhiều flow gần đây, có pin/unpin/remove.
- Caveats: hiện đang lưu lightweight work metadata, chưa có deep draft restoration cho form state nặng.

### Patch UX-08

- Mục tiêu: quick actions + preset filters ở các màn queue-heavy.
- Files changed: `staff-web/src/features/reservations/ReservationsPage.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/features/orders/OrderWorkspacePage.tsx`, `staff-web/src/features/checkout/CheckoutPage.tsx`, `staff-web/src/features/conversations/ConversationInboxPage.tsx`, `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/features/finance/FinanceReviewPage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`.
- UI/UX changes: preset chips cho reservation bucket/waiting/kitchen/conversations; quick nav giữa table-reservation-waiting; finance có reset filter và advanced toggle.
- Copy changes: button labels staff-facing, ngắn và rõ hơn.
- Logic safety notes: preset chỉ ghi vào query/filter state hiện có.
- Acceptance criteria reached: đạt cho giảm click ở các thao tác phổ biến.
- Caveats: order workspace chưa có command-style item search/preset sâu.

### Patch UX-09

- Mục tiêu: polish split workspace và quick preview drawer/detail panel.
- Files changed: `staff-web/src/components/layout/SplitWorkspace.tsx`, `staff-web/src/components/drawers/ReservationDetailDrawer.tsx`, `staff-web/src/features/conversations/ConversationInboxPage.tsx`.
- UI/UX changes: sticky side panel, sticky action footer trong reservation drawer, selected detail rõ hơn.
- Copy changes: detail drawer ưu tiên next action thay vì mô tả dài.
- Logic safety notes: không đổi detail fetching contract.
- Acceptance criteria reached: đạt ở reservation detail và conversation split layout.
- Caveats: chưa có width memory/collapse persistence riêng cho từng workspace.

### Patch UX-10

- Mục tiêu: live event strip + freshness feedback.
- Files changed: `staff-web/src/features/dashboard/DashboardPage.tsx`, `staff-web/src/features/dashboard/components/DashboardTopBar.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`, `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/components/states/StateBlocks.tsx`, `staff-web/src/app/layout/StaffAppShell.tsx`.
- UI/UX changes: shell freshness chip, dashboard stale notice + activity strip, kitchen live feed toggle, realtime version cues ở table board.
- Copy changes: last updated/stale language đổi sang staff-facing.
- Logic safety notes: chỉ dùng dataUpdatedAt/realtime version hiện có.
- Acceptance criteria reached: đạt cho "không thao tác mù trên dữ liệu cũ" ở shell/dashboard/kitchen/tables.
- Caveats: chưa có websocket/live event stream riêng, vẫn dựa polling metadata.

### Patch UX-11

- Mục tiêu: bulk actions cho queue screens khi an toàn.
- Files changed: `staff-web/src/features/conversations/ConversationInboxPage.tsx`.
- UI/UX changes: multi-select inbox, bulk take-over, bulk unassign, selection alert.
- Copy changes: bulk triage copy ngắn, trực tiếp.
- Logic safety notes: chỉ mở bulk action ownership an toàn; không thêm bulk destructive actions.
- Acceptance criteria reached: đạt một phần cho conversation inbox.
- Caveats: waiting list chưa thêm bulk notify/seat vì rủi ro thao tác hàng loạt cao hơn giá trị hiện tại.

### Patch UX-12

- Mục tiêu: command palette + keyboard-first navigation.
- Files changed: `staff-web/src/app/layout/StaffAppShell.tsx`, `staff-web/src/index.css`.
- UI/UX changes: thêm command palette mở bằng nút `Command` hoặc `Ctrl/Cmd + K`, gom quick nav và resume work vào một nơi.
- Copy changes: command/search copy dùng ngôn ngữ staff-facing, ưu tiên "đi nhanh" và "tiếp tục việc đang dở".
- Logic safety notes: palette chỉ điều hướng tới route/work item sẵn có, không thêm mutation mới.
- Acceptance criteria reached: đạt một phần.
- Caveats: chưa có workspace-level shortcuts hay action verbs riêng cho từng domain.

### Patch UX-13

- Mục tiêu: adaptive dashboard theo role/focus.
- Files changed: `staff-web/src/features/dashboard/dashboard-view-model.ts`, `staff-web/src/features/dashboard/DashboardPage.tsx`, `staff-web/src/features/dashboard/components/DashboardTopBar.tsx`.
- UI/UX changes: focus title/description/priority paths đổi theo capability set gần với cashier/service floor/kitchen/supervisor.
- Copy changes: top bar nhấn đúng nhiệm vụ vận hành hiện tại.
- Logic safety notes: chỉ suy ra từ capability/session hiện có, không thêm role mapping backend mới.
- Acceptance criteria reached: đạt một phần.
- Caveats: layout vẫn chung, chưa có dashboard composition riêng theo từng role persona.

### Patch UX-14

- Mục tiêu: nâng cashier shift thành trải nghiệm handoff mode rõ hơn.
- Files changed: `staff-web/src/features/cashier/CashierShiftPage.tsx`, `staff-web/src/features/access/AccessGatePage.tsx`.
- UI/UX changes: access gate trở thành startup control center; cashier shift có review handoff trước khi close.
- Copy changes: nhấn readiness, blocker, bước nên làm ngay.
- Logic safety notes: vẫn giữ luồng cashier shift hiện tại.
- Acceptance criteria reached: đạt một phần.
- Caveats: chưa có dedicated handoff checklist timeline riêng.

### Patch UX-15

- Mục tiêu: predictive/risk cues.
- Files changed: `staff-web/src/features/dashboard/dashboard-view-model.ts`, `staff-web/src/features/tables/TableBoardPage.tsx`, `staff-web/src/features/kitchen/KitchenBoardPage.tsx`, `staff-web/src/features/reservations/ReservationsPage.tsx`, `staff-web/src/features/waiting/WaitingListPage.tsx`, `staff-web/src/features/conversations/ConversationInboxPage.tsx`.
- UI/UX changes: priority hints, relative age, unassigned/ready-to-seat/queued heuristics.
- Copy changes: dùng gợi ý "cần chú ý", "theo dõi", "xử lý ngay".
- Logic safety notes: heuristic chỉ đọc snapshot hiện có.
- Acceptance criteria reached: đạt một phần.
- Caveats: chưa có predicted breach model hay recommendation engine.

### Patch UX-16

- Mục tiêu: audit/reporting investigation polish.
- Files changed: `staff-web/src/features/audit/AuditTrailPage.tsx`, `staff-web/src/features/reporting/ReportingHubPage.tsx`.
- UI/UX changes: audit page hiển thị relative age để điều tra; reporting hub đổi từ generic reporting sang snapshot investigation hub với context/freshness.
- Copy changes: title, description, panel title theo ngôn ngữ giám sát/điều tra.
- Logic safety notes: không đổi reporting query contract.
- Acceptance criteria reached: đạt một phần.
- Caveats: chưa có cross-link investigation workflow giữa audit/reporting/finance.

## C. Rủi ro còn lại

- Dashboard urgency vẫn là frontend heuristic. Nếu muốn nâng tiếp cần backend support cho SLA targets, breached thresholds và queue severity metadata.
- Một số màn vẫn còn raw numeric context khi backend không trả semantic label đầy đủ.
- Live freshness hiện dựa polling/query timestamps, chưa phải realtime push thật.
- Bulk action mới mở ở conversations. Muốn mở rộng sang waiting/finance cần review nghiệp vụ và guardrail mạnh hơn.
- Dashboard bundle vẫn lớn theo cảnh báo Vite chunk size. Đây không làm vỡ build nhưng nên là vòng tối ưu kế tiếp.
- Một số warning runtime từ Ant Design trong test log (`Alert message`, `Space direction`) đến từ code cũ/chung và chưa được xử lý hết trong batch này.

## D. Verification

### Kết quả lệnh

- `npm run lint` trong `staff-web`: passed.
- `npm run build -- --mode development` trong `staff-web`: passed.
- `npm test -- --run src/features/dashboard/DashboardPage.test.tsx src/features/access/AccessGatePage.test.tsx src/app/store/flow-store.test.ts src/features/finance/finance-review.test.ts src/app/router/index.test.tsx src/features/checkout/CheckoutPage.test.tsx src/features/orders/OrderWorkspacePage.test.tsx` trong `staff-web`: passed.
- `typecheck`: verified gián tiếp qua `tsc` trong `npm run build`.

### Checklist tự kiểm tra cuối

- [x] Không vỡ build.
- [x] Không vỡ typecheck.
- [x] Không vỡ router đang mount thật.
- [x] Không vỡ shell context và journey/resume flow.
- [x] Không nới lỏng financial guardrails hiện có.
- [x] Copy staff-facing đồng nhất hơn ở shell, dashboard, queue pages và finance flows.
- [x] Lint sạch ở phạm vi `staff-web`.

### Manual reasoning notes

- Các patch đều giữ nguyên capability gating và readiness gating từ router/session hiện có.
- Các mutation quan trọng vẫn đi qua row_version/backend checks cũ; UI chỉ tăng context và guardrail trước action.
- Những phần chưa làm trọn đã được dừng có chủ đích để tránh rewrite lớn hoặc thêm abstraction không tương xứng giá trị.
