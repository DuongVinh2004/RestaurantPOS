# Audit Trail

## Intent

The audit trail is intentionally narrower than an access log. It records mutations that matter for operational, financial, or legal investigation so staff can answer:

- who performed the action
- when it happened
- which primary subject was changed
- which related subjects were part of the same action
- what changed before and after
- which request or source context should be used for correlation

## Canonical Event Shape

Each audit row is normalized around these fields:

- `action`: canonical action name such as `reservation.checked_in`
- `entity_type` and `entity_id`: the primary subject
- `actor_user_id`, `actor_type`, `actor_key`: the actor that performed the mutation
- `before_json`: the relevant before-state
- `after_json`: the relevant after-state
- `summary_json`: a compact operational summary for quick reading
- `meta_json`: filtered request and source context
- `request_id`: correlation key for request tracing

`audit_log_subjects` stores additional subjects that belong to the same mutation, such as `reservation_order`, `payment`, `restaurant_table`, `waiting_list`, or `cashier_shift`.

## Durability Classes

Audit events are classified centrally as either critical or best effort.

- Critical: payment capture, refund, cashier-shift, inventory ingredient, and staff API-key mutations. The business mutation and its `audit_logs`/`audit_log_subjects` evidence share the same database transaction. A missing table, rejected insert, invalid audit shape, or call outside an active transaction aborts the mutation.
- Best effort: lower-risk operational telemetry. Sink failure emits a warning but does not abort its caller.

Critical failures emit `critical_audit_persistence_failed` to `AUDIT_FAILURE_ALERT_CHANNEL` (the `audit_alert` stack by default). The alert contains only event/action names, exception type, request correlation, and transaction level; it deliberately excludes SQL exception messages and business payloads. Operators must collect every channel named by `AUDIT_ALERT_LOG_STACK`.

## Actor Taxonomy

- `staff_user`
- `staff_api_key`
- `customer_account`
- `customer_access_session`
- `customer_session`
- `webhook_provider`
- `system`

Customer-facing loyalty and voucher actions should resolve back to a customer actor, not a staff actor, unless staff actually performed the mutation.

## Staff Read Surface

Staff and admin users read the audit trail through:

- `GET /api/v1/staff/audit-trail`

Supported filters:

- `branch_id`
- `reservation_id`
- `order_id`
- `payment_id`
- `waiting_id`
- `table_id`
- `cashier_shift_id`
- `actor_user_id`
- `actor_type`
- `action`
- `request_id`
- `q`
- `subject_type`
- `subject_id`
- `date_from`
- `date_to`
- `page`
- `per_page`

Staff audit reads now fail closed to the authenticated actor's operational branches. Without an explicit `branch_id`, the query defaults to that actor's accessible branch scope. When a caller sends `branch_id` outside that scope, the API returns `404 not_found` instead of silently broadening or returning cross-branch data.

Within that allowed scope, `branch_id` remains useful for day-to-day triage. The query first checks branch context embedded in audit metadata, then falls back to branch-owned entity and subject lookups for reservations, orders, payments, waiting list entries, tables, and cashier shifts. This keeps branch-scoped investigation useful even when older events did not persist `meta_json.branch_id`.

`q` is intentionally narrow and operational. It searches canonical action, request id, primary entity, actor key, actor name, and related subject identifiers.

## Response Shape

Each row returns:

- actor summary
- request context
- primary subject
- related subjects
- `before`
- `after`
- `summary`
- `meta`

Request context now includes `request.branch_id` when the audit event carried branch metadata. This gives the staff web a stable way to keep branch investigation context visible inside the detail panel.

## Investigation Recipes

Typical high-signal reads:

1. Branch-scoped refund investigation
   - `GET /api/v1/staff/audit-trail?branch_id=3&action=payment.refunded&date_from=2026-04-10&date_to=2026-04-10`
2. Request-correlation follow-up from API or runtime logs
   - `GET /api/v1/staff/audit-trail?request_id=req-abc123`
3. Free-text triage when the exact subject is not known
   - `GET /api/v1/staff/audit-trail?q=refund`
4. Primary-subject drill-in
   - `GET /api/v1/staff/audit-trail?payment_id=7001`

Operational guidance:

- start with `branch_id` whenever the incident is branch-local
- use `request_id` when the report already comes from an API error, smoke log, or release evidence
- move to a different branch only after the actor has operational access for that branch

## PII And Sensitive Data

Do not persist high-risk request material such as:

- passwords
- tokens
- secrets
- authorization headers
- cookies
- webhook signatures
- raw provider payloads
- idempotency keys
- request-context phone or email values

`AuditEvent` builds one envelope and passes it through the shared sanitizer before either the structured database recorder or the audit file sink sees it. HTTP request audit logging uses the same sanitizer. Guest/contact fields, IP addresses, credentials, provider payloads, and raw request bodies are redacted. Customer session identifiers are replaced with `hmac-sha256:<digest>` using `AUDIT_HASH_KEY`, with `APP_KEY` as fallback, so incidents can be correlated without retaining the source identifier.

Set `AUDIT_HASH_KEY` to a dedicated high-entropy secret in production-like environments. Rotating it intentionally breaks correlation with hashes produced by the previous key, so record the rotation time in the incident log without recording either key.

Audit summaries should prefer business identifiers, amounts, statuses, scope markers, reasons, and row versions over raw payload copies.

## Retention And Redaction

Current code sanitizes before all audit sinks. For go-live operations, keep these principles:

- keep hot audit data long enough for operational triage
- retain payment, refund, cashier, and webhook events per internal finance policy
- re-redact or purge newly identified sensitive fields when the schema evolves

Prefer targeted exports by subject, action, and date range over dumping the full table for investigation.

## Known Limits

- The audit trail is not intended to capture every low-value legacy event.
- Some system-level events remain legitimately global and may not carry branch metadata.
- Branch fallback works for the current high-value operational subjects, not for every possible legacy entity type.
- Best-effort telemetry is not guaranteed to have a structured database row; only events in the central critical classification are fail-closed.
