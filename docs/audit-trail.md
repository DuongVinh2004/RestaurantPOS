# Audit Trail

## Intent

Audit trail chỉ ghi các mutation có giá trị vận hành, pháp lý hoặc tài chính. Mục tiêu không phải access log đầy đủ, mà là trả lời được:

- ai đã làm gì
- khi nào
- trên subject nào
- trạng thái trước/sau hoặc change summary là gì
- request/source context nào liên quan

## Canonical Event Shape

Mỗi audit row chuẩn hóa theo các trường chính:

- `action`: canonical action name, ví dụ `reservation.checked_in`
- `entity_type` + `entity_id`: primary subject
- `actor_user_id`, `actor_type`, `actor_key`: actor đã thực hiện
- `before_json`: trạng thái trước khi mutation xảy ra, chỉ giữ phần có ích
- `after_json`: trạng thái sau mutation
- `summary_json`: tóm tắt ngắn cho staff/admin đọc nhanh
- `meta_json`: request/source context đã được lọc
- `request_id`: correlation id để lần theo request

`audit_log_subjects` lưu các subject phụ như `reservation_order`, `payment`, `restaurant_table`, `waiting_list`, `voucher`, `cashier_shift`.

## Actor Taxonomy

- `staff_user`: staff/admin xác thực qua staff auth
- `staff_api_key`: staff action đi qua DB-backed API key
- `customer_account`: customer authenticated account
- `customer_access_session`: customer account qua access session
- `customer_session`: guest/session-scoped actor khi không có account
- `webhook_provider`: webhook provider như `simulated`, `generic_http_hmac`
- `system`: console/scheduler/system actor

Customer self-service voucher/loyalty được gán lại về `customer_account`, không còn bị coi là `staff_user`.

## Audited Actions

Đã canonical hóa cho các nhóm chính:

- `reservation.created`
- `reservation.status_changed`
- `reservation.rescheduled`
- `reservation.checked_in`
- `reservation.table_moved`
- `table.released`
- `waiting_list.created`
- `waiting_list.notified`
- `waiting_list.accepted`
- `waiting_list.declined`
- `waiting_list.arrival_confirmed`
- `waiting_list.seated`
- `waiting_list.cancelled`
- `reservation.voucher.applied`
- `reservation.voucher.removed`
- `reservation.loyalty.redeemed`
- `reservation.loyalty.released`
- `payment_session.created`
- `payment_session.confirmed`
- `payment.webhook.processed`
- `payment.final_captured`
- `checkout.finalized`
- `payment.refunded`
- `reservation.refund_cancelled`
- `cashier_shift.opened`
- `cashier_shift.closed`
- `master_data.voucher.created`
- `master_data.voucher.updated`
- `master_data.loyalty_tier.created`
- `master_data.loyalty_tier.updated`
- `master_data.restaurant_table.created`
- `master_data.restaurant_table.updated`
- `master_data.restaurant_table.deleted`

## Read Surface

Staff/admin có thể đọc audit trail qua:

- `GET /api/v1/staff/audit-trail`

Filter hỗ trợ:

- `reservation_id`
- `order_id`
- `payment_id`
- `waiting_id`
- `table_id`
- `cashier_shift_id`
- `actor_user_id`
- `actor_type`
- `action`
- `subject_type` + `subject_id`
- `date_from`
- `date_to`
- `page`
- `per_page`

Response trả về:

- actor
- request context
- primary subject
- subject list
- `before`
- `after`
- `summary`
- `meta`

## PII And Sensitive Data

Không ghi các payload nhạy cảm hoặc ít giá trị vận hành:

- password, token, secret
- authorization header, cookie
- webhook signature
- raw request body / raw provider payload
- idempotency key
- phone/email từ request context

Audit summary ưu tiên mã định danh nghiệp vụ, amount, status, scope, reason, row version. Không lưu toàn bộ payload đầu vào nếu không cần.

## Retention And Redaction

Hiện tại repo cung cấp redaction ở tầng recorder. Chính sách retention nên áp dụng khi go-live:

- giữ hot audit data ít nhất 180 ngày cho ops
- archive dài hơn cho payment/refund/cashier/webhook events theo yêu cầu pháp lý nội bộ
- purge hoặc re-redact khi một field mới bị xác định là sensitive

Nếu cần export cho điều tra, ưu tiên export theo subject/action/date range thay vì dump toàn bộ bảng.

## Known Scope Limits

Chủ đích không audit hóa mọi event legacy. Các event bị loại thường là:

- access/request noise
- noop events
- low-value technical telemetry
- payload quá chi tiết nhưng không giúp điều tra vận hành

Một số flow admin/master-data khác ngoài voucher, loyalty tier, restaurant table chưa được chuẩn hóa trong batch này vì chưa nằm trong nhóm ưu tiên rollout.
