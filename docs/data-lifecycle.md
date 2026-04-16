# Data Lifecycle

## Intent

Foundation này tách rõ ba lớp:

- business deletion: soft-delete/hủy trong flow nghiệp vụ, không đồng nghĩa xóa dữ liệu cá nhân
- privacy anonymization: cắt định danh cá nhân nhưng giữ khóa tham chiếu, financial integrity và history
- audit/legal retention: dữ liệu phải giữ để đối soát, hoàn tiền, khiếu nại, điều tra vận hành

Phase hiện tại ưu tiên customer-facing data đang có thật trong repo, không dựng một compliance framework lớn hơn nhu cầu vận hành hiện tại.

## API And Command Surface

- `GET /api/v1/me/data-export`
  - customer authenticated export, format `json`
- `GET /api/v1/me/privacy-requests`
  - customer xem request lịch sử
- `POST /api/v1/me/privacy-requests`
  - customer tạo request `anonymize`
- `GET /api/v1/admin/privacy/requests`
  - admin list request
- `GET /api/v1/admin/privacy/customers/{user_id}/data-export`
  - admin export thay mặt customer
- `POST /api/v1/admin/privacy/requests/{request_id}/review`
  - `decision=approve|reject`
  - `mode=dry_run|commit`
- `php artisan data-lifecycle:enforce-retention`
  - prune auth/notification/conversation derived artifacts theo retention policy
- `php artisan data-lifecycle:enforce-retention --dry-run`
  - preview retention action

## Privacy Request Flow

1. Customer authenticated tạo `customer_privacy_requests` với `request_type=Anonymize`.
2. Admin review request.
3. `dry_run` trả về preview counts + blockers, không mutate dữ liệu.
4. `commit` chỉ cho phép khi không còn blocker vận hành:
   - active reservations
   - active waiting-list entries
   - open conversations
   - non-terminal payment sessions
5. Khi approve commit:
   - user account bị de-activate bằng `is_deleted=1`
   - `users.privacy_anonymized_at` được set
   - access session/auth token/bank account/notification preference bị purge
   - customer-facing identity và communication payload bị redact
   - reservation/payment/loyalty/audit history được giữ

## Customer Export Shape

Export hiện tại là JSON table-oriented để trung thực với dữ liệu hệ thống và dễ diff:

- `customer`
  - `users`, `roles`, `loyalty_tiers`, `user_points`
- `tables.customer_access_sessions`
- `tables.bank_accounts`
- `tables.user_auth_tokens`
- `tables.notification_preferences`
- `tables.reservations`
- `tables.reservation_tables`
- `tables.reservation_orders`
- `tables.reservation_order_items`
- `tables.payments`
- `tables.billing_invoices`
- `tables.reservation_deposit_payment_sessions`
- `tables.reservation_bill_payment_sessions`
- `tables.user_vouchers`
- `tables.loyalty_point_transactions`
- `tables.user_tier_history`
- `tables.waiting_list`
- `tables.conversations`
- `tables.conversation_messages`
- `tables.conversation_files`
- `tables.message_entities`
- `tables.conversation_events`
- `tables.conversation_analyses`
- `tables.notification_outbox`
- `tables.notification_delivery_attempts`
- `tables.customer_privacy_requests`

JSON-only là intentional cho phase đầu vì payload nhiều quan hệ lồng nhau, CSV sẽ làm mất ngữ nghĩa.

## Lifecycle Matrix

| Table / Domain | Policy | Why |
| --- | --- | --- |
| `users` | anonymize + deactivate | chặn login nhưng giữ `user_id` cho history/reporting |
| `bank_accounts` | purge | rất nhạy cảm, không cần cho POS reconciliation hiện tại |
| `customer_access_sessions` | purge | auth artifact, không cần giữ sau privacy deletion |
| `user_auth_tokens` | purge | auth artifact ngắn hạn |
| `notification_preferences` | purge | preference không còn ý nghĩa sau anonymization |
| `reservations` | keep row, redact `notes`/`cancel_reason` | booking history và revenue context phải giữ |
| `payments` | keep unchanged | financial integrity / refund lineage |
| `reservation_*_payment_sessions` | keep unchanged | payment session lineage và reconciliation |
| `billing_invoices` | keep unchanged | accounting / tax history |
| `user_vouchers` | keep unchanged | benefit usage lineage |
| `user_points` | keep unchanged | loyalty balance history |
| `loyalty_point_transactions` | keep unchanged | financial-like loyalty audit trail |
| `user_tier_history` | keep unchanged | loyalty progression history |
| `waiting_list` | keep row, redact guest identity + notes | operational history có ích nhưng không cần PII cũ |
| `conversations` | keep row, redact session identifiers | giữ linkage/history nhưng bỏ customer session identifiers |
| `conversation_messages` | redact message content | text có thể chứa PII trực tiếp |
| `conversation_files` | redact file URL | attachment thường chứa dữ liệu nhạy cảm |
| `message_entities` | redact entity text/normalized | extracted PII |
| `conversation_events` | redact `event_data` | event payload có thể chứa phone/email |
| `conversation_analyses` | redact `extracted_info` | derived PII/intent extraction |
| `notification_outbox` | keep row, redact recipient/payload/error | giữ delivery history tối thiểu |
| `notification_delivery_attempts` | keep row, redact recipient/request/response payload | giữ delivery lineage tối thiểu |
| `audit_logs` | keep unchanged | legal/ops audit trail |
| `payment_provider_webhook_receipts` | keep row, scrub verbose request/provider payload fields after retention | dispute / reconciliation support without long-lived raw payload retention |
| reporting snapshots | keep unchanged | aggregated, không cần customer PII để vận hành |

## Redaction Scope

Field đã tránh log/export nhầm hoặc sẽ bị redact khi anonymize:

- `users.username`
- `users.full_name`
- `users.email`
- `users.phone`
- `users.password_hash`
- `bank_accounts.bank_account_number`
- `bank_accounts.account_holder_name`
- `customer_access_sessions.token_hash`
- `customer_access_sessions.created_ip`
- `customer_access_sessions.user_agent`
- `user_auth_tokens.recipient`
- `user_auth_tokens.token_hash`
- `user_auth_tokens.otp_hash`
- `reservations.notes`
- `reservations.cancel_reason`
- `waiting_list.guest_name`
- `waiting_list.phone`
- `waiting_list.notes`
- `waiting_list.cancel_reason`
- `conversations.session_id`
- `conversations.customer_session_id`
- `conversation_messages.message_text`
- `conversation_messages.attachment_url`
- `conversation_files.file_url`
- `message_entities.entity_text`
- `message_entities.entity_normalized`
- `conversation_events.event_data`
- `conversation_analyses.extracted_info`
- `notification_outbox.recipient`
- `notification_outbox.payload_json`
- `notification_outbox.last_error`
- `notification_delivery_attempts.recipient`
- `notification_delivery_attempts.error_message`
- `notification_delivery_attempts.request_payload_json`
- `notification_delivery_attempts.response_payload_json`

## Retention Hooks

Current retention command prunes only categories có thể cắt an toàn:

- `customer_access_sessions`
  - revoked/expired beyond retention
- `user_auth_tokens`
  - expired/used beyond retention
- `notification_outbox`
  - terminal rows beyond retention
- `notification_delivery_attempts`
  - rows tied to pruned outbox or older than attempt retention
- `conversation_analyses`
  - closed conversation derived artifacts beyond retention
- `message_entities`
  - closed conversation derived artifacts beyond retention
- `payment_provider_webhook_receipts`
  - keep the receipt row but scrub verbose request signature/header/body/provider payload fields after retention

Current foundation does **not** prune:

- `audit_logs`
- `payments`
- `billing_invoices`

Lý do là production operations vẫn cần các bảng này cho audit, refund, dispute và accounting.

## Admin And Staff Visibility After Anonymization

- Existing staff/admin endpoints vẫn xem được row lịch sử qua `user_id`.
- Nhưng vì `users.full_name/email/phone` đã bị thay bằng placeholder hoặc null, UI/read model sẽ không còn trả PII cũ.
- Waiting-list và conversation artifacts cũng bị redact ở source row, nên staff đọc lại history sẽ chỉ thấy placeholder/redacted payload.

## Limits

- Chỉ cover data gắn được với `user_id` hoặc `customer_access_sessions.session_id`.
- Guest-only legacy data không có `user_id` và không liên kết session sẽ không được backfill tự động trong phase này.
- Không xóa binary file thực sự khỏi object storage/file storage; phase này mới redact DB URL/reference.
- Export hiện là synchronous JSON response, chưa có async package/archive/download link flow.
