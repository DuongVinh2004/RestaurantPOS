## Notification Platform v2

### Scope

Notification platform v2 keeps `notification_outbox` as the primary queue and adds:

- channel drivers with clean provider boundaries
- delivery attempt evidence in `notification_delivery_attempts`
- basic user/channel preferences in `notification_preferences`
- cooldown-based duplicate suppression on enqueue
- dead-letter and per-channel health visibility

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

### Tables

- `notification_outbox`
  - added `recipient_user_id`
  - added `dedupe_key`
  - added `last_attempted_at`
  - channel enum now includes `Zalo`
- `notification_delivery_attempts`
  - one row per delivery attempt with provider status and error evidence
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

### Limited-production delivery rehearsal

Before calling notification delivery production-ready for a rollout target, capture one real external delivery rehearsal for the configured Email channel:

1. enqueue or trigger one notification against the target mailer/provider
2. confirm `notification_outbox` reached `Sent` and `notification_delivery_attempts` captured the provider response
3. confirm the intended recipient actually received the message
4. record the result under `notification_provider_external_e2e` in the launch-readiness manual evidence JSON

`Email` is the only real delivery lane in this repo today. `SMS` and `Zalo` remain stub/provider-ready only and must not be presented as external proof until a real provider driver replaces the stub.

### Current limits

- No realtime push or provider webhooks for notification delivery receipts yet.
- SMS/Zalo are not externally connected yet.
- Preferences are channel-level only; there is no per-template preference matrix yet.
- Quiet-hour evaluation currently uses `notifications.preferences.timezone` and is not user-timezone aware.
