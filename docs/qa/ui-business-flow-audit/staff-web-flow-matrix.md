# Staff Web Flow Matrix

| Flow | Steps | Expected Result | Actual Result | Status | Evidence Link | Severity |
|---|---|---|---|---|---|---|
| Staff auth | Login staff key | Access granted on success | Pass, logs in successfully with UAT credentials | Pass | Test log | None |
| Cashier Shift | Mở ca thu ngân | Ca thu ngân mở thành công | Pass, clicks "Mở ca thu ngân" and returns | Pass | Test log | None |
| Dashboard/command center | Load overview, branch context, metrics | Dashboard loads | Pass, layout and context resolve correctly | Pass | Test Log | None |
| Table board/floor operations | Xem table board, trạng thái bàn, release table | Bảng bàn phản ánh đúng availability từ backend | Pass, table state reflects accurately | Pass | Golden Path Evidence | None |
| Reservation management | List, timeline, detail, assign, move table, check-in | Chuyển trạng thái mượt, list load đúng | Pass, end-to-end check-in workflow succeeded | Pass | Golden Path Evidence | None |
| Preorder staff | View reservation preorder, confirm/reject/convert | Convert thành công sang order item | Blocked | Blocked | N/A | N/A |
| Dine-in ordering | Tạo order, add item, update qty, close/bill snapshot | Hoạt động từ đầu đến cuối không lỗi logic | Partially Passed (Create Order PASS_WITH_API_FALLBACK due to UI branch context mismatch, end-to-end item mutation, void, dispatch, and concurrency PASS) | Partial | Test log | Medium |
| Kitchen/KDS | Dispatch order, view stations, fire/bump/recall | Ticket states sync chuẩn | Pass, KDS syncing and transitions verified | Pass | Golden Path Evidence | None |
| Checkout/payment | Open shift, checkout, refund preview, refund | Tính tiền chính xác, refund luồng chuẩn | Pass, safe path for cash checkout succeeded | Pass | Golden Path Evidence | None |
| Cashier shift | Current, open, close, read history | Ghi nhận ca chính xác | Pass, active shift maintained throughout operations | Pass | Golden Path Evidence | None |
| Waiting list staff | List, advance, notify, seat, cancel | Workflow xử lý waiting list trơn tru | Blocked | Blocked | N/A | N/A |
| Conversations/notifications | Inbox, assign, internal notes, reply | Load nhanh, link đúng reservation/waiting list | Blocked | Blocked | N/A | N/A |
| Inventory/admin | Ingredients, stock movement, suppliers, purchase | CRUD hoàn tất, conflict handling tốt | PARTIAL (Ingredients/Suppliers/PO passed backend integration, Receipts/Stock Movement UI found but NOT_IMPLEMENTED, Export NOT_IMPLEMENTED, Conflict guards NOT_IMPLEMENTED) | Partial | Test log | None |
| Menu/admin catalog | Categories, items, prices CRUD | Phản hồi API chuẩn | Blocked | Blocked | N/A | N/A |
| Settings | Branch, table, template, kitchen station | (Nếu có) hoạt động tốt | Blocked | Blocked | N/A | N/A |
| Benefits/loyalty/voucher | Tiers, vouchers | (Nếu có) hoạt động tốt | Blocked | Blocked | N/A | N/A |
| Reporting | Sales, operations, inventory | Report queries chạy ổn định | Blocked | Blocked | N/A | N/A |
| Permission/RBAC | Verify capabilities guard trên các route | Fail closed khi truy cập trái phép | Blocked | Blocked | N/A | N/A |
