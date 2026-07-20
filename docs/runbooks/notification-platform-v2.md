## Notification Platform v2

### Scope

Notification platform v2 keeps `notification_outbox` as the primary queue and adds:

- channel drivers with clean provider boundaries
- delivery attempt evidence in `notification_delivery_attempts`
- basic user/channel preferences in `notification_preferences`
- cooldown-based duplicate suppression on enqueue
- dead-letter and per-channel health visibility

### Channel readiness

- `Email`
  - readiness: `production_lean`
  - delivery mode: `real`
  - usable now through the configured Laravel mailer with delivery-attempt evidence
- `SMS`
  - readiness: `provider_ready`
  - delivery mode: `stub`
  - can exercise queue and attempt evidence, but does not send externally
- `Zalo`
  - readiness: `provider_ready`
  - delivery mode: `stub`
  - can exercise queue and attempt evidence, but does not send externally

`Readiness` tells operators whether a channel is acceptable as rollout proof. `Delivery mode` tells the runtime whether the current driver is real or stubbed.

### What is really usable

- `Email`: usable now. Delivery goes through the configured Laravel mailer and records attempt evidence.

### Provider-ready only

- `SMS`: stub/provider-ready only. When `notifications.channels.sms.enabled=true` with driver `stub`, the system records a successful stub delivery but does not send externally.
- `Zalo`: stub/provider-ready only. When `notifications.channels.zalo.enabled=true` with driver `stub`, the system records a successful stub delivery but does not send externally.

Do not treat SMS/Zalo as production-ready until a real provider driver replaces the stub.

### Runtime model

- Domain flows still enqueue through `NotificationOutboxService`.
- Domain events and outbound delivery are separated by `_notification` metadata in `payload_json`.
- `idempotency_key` remains the hard uniqueness guard.
- `dedupe_key` plus configured cooldowns suppress near-duplicate enqueue requests.
- Disabled channel preferences suppress enqueue.
- Quiet-hour preferences delay delivery by setting `next_retry_at` instead of dropping the row.
- Delivery re-checks recipient preference and quiet hours immediately before send, so a message queued earlier does not bypass a later customer preference change.
- Non-retryable boundary failures such as disabled channels or unsupported drivers cancel immediately instead of creating repeated retry noise.

### Reminder durability and reschedule semantics

- Reservation reminder identity is derived from the normalized current schedule (`row_version`, start/end UTC, lead minutes, and due time). A reschedule therefore gets a new hard `idempotency_key`; it cannot be deduplicated against a reminder that was already sent for the previous schedule.
- Reschedule enqueueing supersedes pending and failed reminders for the reservation in the same transaction. The supersession is recorded as `Suppressed` evidence with `reminder_schedule_superseded`, and the old row is moved to `Cancelled`.
- The worker locks and rechecks the reservation schedule immediately before the provider side effect. A stale claimed reminder is cancelled with durable suppression evidence and is never sent.
- Reminder enqueueing has a bounded outage catch-up window controlled by `notifications.reminder_catch_up_minutes` (default: 120 minutes). The health snapshot exposes catch-up due/expired counts, oldest reminder lag, and the configured window so an operator can distinguish healthy catch-up from an overdue scheduler.

### Tables

- `notification_outbox`
  - added `recipient_user_id`
  - added `dedupe_key`
  - added `last_attempted_at`
  - channel enum now includes `Zalo`
- `notification_delivery_attempts`
  - every provider handoff records `Started` before the side effect and `Succeeded` or `Failed` after acknowledgement
  - delivery-gate decisions can also record `Deferred` or `Suppressed` evidence with explicit `error_code`
  - a crash after provider acceptance but before database acknowledgement leaves `Started`; lease recovery quarantines it as `Unknown` rather than guessing or silently retrying
- `notification_preferences`
  - per-user per-channel enable flag
  - optional quiet hours via minute-of-day window

### Commands

- `php artisan notifications:process-outbox`
- `php artisan notifications:enqueue-reminders`
- `php artisan notifications:outbox-health --json`
- `php artisan notifications:outbox-dead-letter --json`

### Suggested operator flow

1. Check current health:
   `php artisan notifications:outbox-health --json`
2. Inspect dead-letter rows:
   `php artisan notifications:outbox-dead-letter --limit=20 --json`
3. Process due messages manually if needed:
   `php artisan notifications:process-outbox --limit=50`

Operator interpretation:

- `production_lean` + `real` means the channel can count toward rollout proof once the manual rehearsal is captured.
- `provider_ready` + `stub` means queue logic is testable, but business flows must still treat delivery as best-effort and non-external.
- Dead-letter rows now surface readiness, delivery mode, and latest error code so operators can distinguish provider failures from configuration or preference gates quickly.
- When `notifications:outbox-health --json` returns `ok=false` with a non-empty `error` while MySQL is unavailable, treat that as a database prerequisite blocker. Zero counts in that state do not prove the outbox is healthy or empty.
- `unknown_delivery_outcome_count > 0` is an immediate operational stop: inspect the provider boundary and receipt/handoff evidence before replaying anything.
- `reminder_catch_up_expired_count > 0` or an oldest reminder lag above `health.reminder_lag_warn_seconds` means the scheduler window has been missed and requires operator action.

### Limited-production delivery rehearsal

Before calling notification delivery production-ready for a rollout target, capture one real external delivery rehearsal for the configured Email channel:

1. enqueue or trigger one notification against the target mailer/provider
2. confirm `notification_outbox` reached `Sent` and `notification_delivery_attempts` captured the provider response
3. confirm the intended recipient actually received the message
4. record the result under `notification_provider_external_e2e` in the launch-readiness manual evidence JSON

`Email` is the only `production_lean` real-delivery lane in this repo today. `SMS` and `Zalo` remain `provider_ready` with stub delivery only and must not be presented as external proof until a real provider driver replaces the stub.

### Current limits

- No realtime push or provider webhooks for notification delivery receipts yet.
- The configured Laravel Email mailer currently exposes neither provider idempotency nor a durable provider receipt. The `Started`/`Unknown` handoff state is the safest local guarantee (at-most-once on an ambiguous crash), but it is not an exactly-once proof; an external idempotency/receipt-capable provider is required before claiming that guarantee.
- SMS/Zalo are not externally connected yet.
- Preferences are channel-level only; there is no per-template preference matrix yet.
- Quiet-hour evaluation now uses the message preferred timezone when available, typically the reservation or conversation branch timezone, and falls back to `notifications.preferences.timezone` only when no operational timezone is available.
