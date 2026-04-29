# Booking operational alerting runbook

## Goal

Convert booking operational health regressions into deduplicated alerts that can be delivered to Slack, a generic webhook, the local `ops_alerts` log, and the audit log.

## Main command

- `php artisan booking:alert-check`
- dry run / CI preview: `php artisan booking:alert-check --dry-run --json`
- fail CI when any active alert exists: `php artisan booking:alert-check --dry-run --fail-on-alert --json`

## What is evaluated

- MySQL runtime reachability
- Redis set/get and lock reachability
- scheduler heartbeat freshness
- notification outbox health
- payment integrity
- inventory and purchasing integrity
- Kitchen/KDS freshness and drift
- staff operational realtime backend health
- voucher lock staleness
- customer session linkage cleanup debt
- staff API key hygiene
- table state audit coverage
- row version contract integrity
- branch default-state drift
- database contract snapshot

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

Store webhook URLs and auth tokens in the deployment secret manager. Do not paste raw webhook URLs, auth tokens, provider secrets, customer tokens, or staff API keys into alert tickets.

## Scheduler behavior

When `OPS_ALERTS_SCHEDULER_ENABLED=true`, the application scheduler runs `booking:alert-check` every five minutes with overlap protection.

## Operator actions

1. Review the alert section and reasons.
2. Open the matching operational snapshot (`booking:ops-snapshot --json`) if more context is required.
3. Resolve the underlying issue.
4. Re-run `booking:alert-check --dry-run --json` and confirm the alert is gone.
5. If the same alert should be re-sent immediately after mitigation work, clear the relevant cache key or wait for cooldown expiry.

## Alert triage matrix

| Alert section | Meaning | First commands | Likely owner | Escalation path |
| --- | --- | --- | --- | --- |
| `mysql_runtime` | MySQL cannot be proven reachable, so deploy/data guards and runtime APIs are unsafe. | `php artisan booking:doctor --strict --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` | Ops/DBA on-call | Escalate to DBA or infrastructure lead immediately if connection restoration is not obvious. |
| `redis_runtime` | Redis set/get or locking failed; distributed locks, idempotency, throttles, and scheduler heartbeat storage are degraded. | `php artisan booking:doctor --strict --json`; `php artisan booking:ops-snapshot --json` | Ops/infrastructure on-call | Escalate to infrastructure lead if Redis health or network reachability is not restored promptly. |
| `scheduler_heartbeat` | Scheduler heartbeat is missing or stale, so scheduled maintenance/outbox jobs are not proven live. | `php artisan booking:doctor --strict --json`; `php artisan schedule:list` | Ops on-call | Escalate to application owner if restarting the scheduler does not refresh the heartbeat. |
| `notification_outbox` | Failed/stuck/backlogged notifications can hide customer or staff communication loss. | `php artisan notifications:outbox-health --json`; `php artisan booking:doctor --strict --json` | Ops/app on-call | Escalate to notification/channel owner if backlog keeps growing or provider errors continue. |
| `payment_integrity` | Payment/refund integrity drift can affect settlement correctness. | `php artisan booking:deploy-check --mode=preflight --strict --json`; `php artisan booking:ops-snapshot --json` | Finance engineering owner | Escalate to finance engineering and data owner before accepting new settlement/refund traffic. |
| `inventory_purchasing` | Inventory or purchasing lineage/backlog drift can affect stock correctness. | `php artisan booking:deploy-check --mode=preflight --strict --json`; `php artisan booking:ops-snapshot --json` | Inventory operations owner | Escalate to inventory engineering/data owner if reconciliation does not identify a safe repair. |
| `kitchen_kds` | KDS backlog, routing drift, or ticket status drift may affect kitchen board correctness. | `php artisan booking:ops-snapshot --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` | Kitchen/KDS owner | Escalate to KDS engineering owner when drift or stale backlog persists after operational triage. |
| `staff_operational_realtime` | Realtime operational feeds are degraded, so staff boards may miss fresh table/order/KDS events. | `php artisan booking:ops-snapshot --json`; `php artisan booking:doctor --strict --json` | Realtime/platform owner | Escalate to platform owner if cache/backend readiness cannot be restored quickly. |
| `table_state_audit` | Table state transitions are missing actor/context evidence. | `php artisan booking:ops-snapshot --json` | FOH/platform owner | Escalate to FOH engineering owner if recent transitions continue without actor/context. |
| `row_version_contract` | Staff mutation surfaces are missing stale-write guard coverage. | `php artisan booking:ops-snapshot --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` | Platform/API owner | Escalate to API/platform owner before releasing write-path changes. |
| `branch_defaults` | Branch defaults are missing, ambiguous, inactive, or scheduling-incomplete. | `php artisan booking:ops-snapshot --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` | Branch operations owner | Escalate to branch/data owner if default branch repair is not immediately clear. |

Alerting evidence is not a substitute for external-provider proof. For limited-production readiness, pair `booking:alert-check` and `notifications:outbox-health --json` with one real Email delivery rehearsal recorded as `notification_provider_external_e2e` in the manual evidence JSON.
