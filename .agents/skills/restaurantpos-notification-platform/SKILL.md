---
name: restaurantpos-notification-platform
description: Harden RestaurantPOS notification outbox, channel drivers, channel preferences, dedupe and quiet-hour behavior, reminder enqueue, and dead-letter or health tooling. Use when Codex changes notification enqueue or delivery logic, preference suppression, outbox commands, or tests and docs that define the current Email-real and SMS/Zalo-stub runtime contract.
---

# RestaurantPOS Notification Platform

Read `AGENTS.md`, `.codex/AGENTS.md`, `docs/runbooks/notification-platform-v2.md`, and `references/paths.md` before patching.

## Workflow

1. Classify the change as enqueue, delivery processing, channel driver behavior, preference evaluation, or operator tooling.
2. Keep delivery orchestration in `NotificationOutboxService` and provider-specific behavior in `app/Services/Notifications/*`.
3. Preserve `idempotency_key` as the hard uniqueness guard and `dedupe_key` plus cooldown as the near-duplicate guard.
4. If health or alerting output changes, combine with `restaurantpos-audit-observability`.
5. If commands or operational expectations move, combine with `restaurantpos-runbook-sync`.

## Guardrails

- Do not present SMS or Zalo as real delivery providers while the repo still uses stub drivers.
- Disabled preferences should suppress enqueue; quiet hours should delay delivery, not silently drop it.
- Keep `notification_delivery_attempts` evidence aligned with outbox state transitions.
- When changing reminder or retry timing, make command tests and health expectations move together.

## Verify

- `php artisan test tests/Feature/Notifications tests/Feature/Services/NotificationOutboxServiceRetryTest.php`
- `php artisan test tests/Feature/Console/NotificationOpsCommandsTest.php`
- `php artisan test tests/Unit/Services/NotificationOutboxHealthServiceTest.php tests/Unit/Services/NotificationOutboxServiceIdempotencyKeyTest.php`
- `php artisan notifications:outbox-health --json`
