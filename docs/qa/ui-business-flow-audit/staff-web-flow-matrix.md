# Staff Web Flow Matrix

| Flow | Steps | Expected Result | Actual Result | Status | Evidence Link | Severity |
|---|---|---|---|---|---|---|
| Staff auth | Login staff key | Access granted on success | Pass, logs in successfully with UAT credentials | Pass | Test log | None |
| Cashier Shift | Mở ca thu ngân | Ca thu ngân mở thành công | Pass, clicks "Mở ca thu ngân" and returns | Pass | Test log | None |
| Dashboard/command center | Load overview, branch context, metrics | Dashboard loads | Pass, layout and context resolve correctly | Pass | Test Log | None |
| Table board/floor operations | Xem table board, trạng thái bàn, release table | Bảng bàn phản ánh đúng availability từ backend | Pass, table state reflects accurately | Pass | Golden Path Evidence | None |
| Reservation management | List, timeline, detail, assign, move table, check-in | Chuyển trạng thái mượt, list load đúng | Pass, end-to-end check-in workflow succeeded | Pass | Golden Path Evidence | None |
| Preorder staff | View reservation preorder, confirm/reject/convert | Convert thành công sang order item | Blocked | Blocked | N/A | N/A |
| Dine-in ordering | Tạo order, add item, update qty, close/bill snapshot | Hoạt động từ đầu đến cuối không lỗi logic | Partially Passed (Create Order PASS_WITH_API_FALLBACK due to UI branch context mismatch — BUG-ORD-009; end-to-end item mutation, void, dispatch, and concurrency PASS) | Partial | Test log | Medium |
| Kitchen/KDS | Dispatch order, view stations, fire/bump/recall | Ticket states sync chuẩn | Pass, KDS syncing and transitions verified | Pass | Golden Path Evidence | None |
| Checkout/payment | Open shift, settlement preview, bill snapshot, cash pay, idempotency guard | Tính tiền chính xác, duplicate payment guarded | PASS — settlement preview, bill snapshot, cash payment, idempotency all pass. Refund execution PASS (API-level). Over-refund guard PASS. | Pass | Batch 11 report | None |
| Cashier shift | Current, open, close, read history, shift totals | Ghi nhận ca chính xác, close verified | PASS — open, show, close, double-close rejection all verified via API (Batch 11) | Pass | Batch 11 report | None |
| Waiting list staff | List, advance, notify, seat, cancel | Workflow xử lý waiting list trơn tru | Blocked | Blocked | N/A | N/A |
| Conversations/notifications | Inbox, assign, internal notes, reply | Load nhanh, link đúng reservation/waiting list | Blocked | Blocked | N/A | N/A |
| Inventory/admin | Ingredients, stock movement, suppliers, purchase | CRUD hoàn tất, conflict handling tốt | PARTIAL (Ingredients/Suppliers/PO passed backend integration, Receipts/Stock Movement UI found but NOT_IMPLEMENTED, Export NOT_IMPLEMENTED, Conflict guards NOT_IMPLEMENTED) | Partial | Test log | None |
| Menu/admin catalog | Categories, items, prices CRUD | Phản hồi API chuẩn | Blocked | Blocked | N/A | N/A |
| Settings | Branch, table, template, kitchen station | (Nếu có) hoạt động tốt | Blocked | Blocked | N/A | N/A |
| Benefits/loyalty/voucher | Voucher apply/remove/release, loyalty redeem/release | Discount applied, total updated | NEEDS_DATA — API endpoints confirmed reachable (voucher.manage, loyalty.redeem caps). Walk-in session has no seed voucher/loyalty points. | Needs Data | Batch 11 report | Low |
| Reporting | Sales, operations, inventory | Report queries chạy ổn định | Blocked | Blocked | N/A | N/A |
| Permission/RBAC | No-auth access to settlement/pay/refund/shift-close routes | 401/403 returned, fail closed | PASS — all 4 tested finance routes return 401/403 without auth (Batch 11) | Pass | Batch 11 report | None |
