# Booking operational alerting runbook

## Goal

Convert booking operational health regressions into deduplicated alerts that can be delivered to Slack, a generic webhook, the local `ops_alerts` log, and the audit log.

## Main command

- `php artisan booking:alert-check`
- dry run / CI preview: `php artisan booking:alert-check --dry-run --json`
- fail CI when any active alert exists: `php artisan booking:alert-check --dry-run --fail-on-alert --json`

## What is evaluated

- notification outbox health
- payment integrity
- voucher lock staleness
- customer session linkage cleanup debt
- staff API key hygiene
- table state audit coverage
- row version contract integrity
- database contract snapshot
- scheduler heartbeat freshness

## Delivery model

Configured in `config/ops_alerts.php`.

Supported destinations:

- local `ops_alerts` log channel
- audit log channel
- Slack webhook
- generic JSON webhook

Each alert has a fingerprint and is suppressed during the configured cooldown window after a successful send.

## Recommended environment variables

- `OPS_ALERTS_ENABLED=true`
- `OPS_ALERTS_COOLDOWN_SECONDS=1800`
- `OPS_ALERTS_SCHEDULER_ENABLED=true`
- `OPS_ALERTS_SLACK_ENABLED=true`
- `OPS_ALERTS_SLACK_WEBHOOK_URL=...`
- `OPS_ALERTS_WEBHOOK_ENABLED=false`
- `OPS_ALERTS_WEBHOOK_URL=...`
- `OPS_ALERTS_WEBHOOK_AUTH_TOKEN=...`

## Scheduler behavior

When `OPS_ALERTS_SCHEDULER_ENABLED=true`, the application scheduler runs `booking:alert-check` every five minutes with overlap protection.

## Operator actions

1. Review the alert section and reasons.
2. Open the matching operational snapshot (`booking:ops-snapshot --json`) if more context is required.
3. Resolve the underlying issue.
4. Re-run `booking:alert-check --dry-run --json` and confirm the alert is gone.
5. If the same alert should be re-sent immediately after mitigation work, clear the relevant cache key or wait for cooldown expiry.

Alerting evidence is not a substitute for external-provider proof. For limited-production readiness, pair `booking:alert-check` and `notifications:outbox-health --json` with one real Email delivery rehearsal recorded as `notification_provider_external_e2e` in the manual evidence JSON.
