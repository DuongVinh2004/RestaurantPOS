# Real Staging Evidence Closure Runbook

## Scope
This runbook validates real staging evidence only. It does not approve production cutover.

## Required staging components
| Area | Required | Source | Status | Evidence |
|---|---:|---|---|---|
| Staging base URL | yes | APP_URL / STAGING_BASE_URL | missing | frontend smoke |
| MySQL staging DB | yes | server env / secret manager | set | booking:doctor |
| Redis staging | yes | server env / secret manager | set | booking:doctor |
| Scheduler/queue | yes | staging worker/scheduler | set | doctor heartbeat |
| S3 backup bucket/prefix | yes | IAM role / secret manager | missing | DR restore |
| SMTP relay | yes | secret manager | missing/placeholder | delivery smoke |
| Sentry | yes | secret manager | missing | alert event |
| Slack/Ops webhook | yes | secret manager | missing | alert delivery |
| VNPay sandbox | yes | provider dashboard + secrets | missing | signed callback |
| MoMo sandbox | yes | provider dashboard + secrets | missing | signed callback |
| Operator go/no-go | yes | manual review | blocked | JSON approval |

## Evidence Summary
| Area | Result | Evidence | Blocker / Next action |
|---|---|---|---|
| Credential sanity | PARTIAL | scripts/ops/check-staging-credentials.ps1 | Core runtime OK, external SaaS credentials missing |
| Runtime doctor | PASS | php artisan booking:doctor | Doctor baseline OK |
| Deploy preflight | PASS | php artisan booking:deploy-check | Deploy preflight OK |
| S3 DR restore | BLOCKED | run-dr-rehearsal.sh | AWS/S3 credentials missing |
| SMTP delivery | BLOCKED | notifications:delivery-smoke | SMTP real credentials missing |
| Sentry/Slack alerting | BLOCKED | check-alerting-health.sh | Webhook/DSN missing |
| VNPay sandbox callback | PARTIAL | tests/Feature/Payments/ | Adapter tests pass, sandbox keys missing |
| MoMo sandbox callback | PARTIAL | tests/Feature/Payments/ | Adapter tests pass, sandbox keys missing |
| Frontend staging smoke | BLOCKED | | STAGING_BASE_URL missing |
| Load smoke | NOT_AVAILABLE | | STAGING_BASE_URL missing |
| Launch readiness | blocked | booking:launch-readiness | Missing staging credentials |
| Operator go/no-go | blocked | operator-staging-go-no-go.json | Missing real staging external credentials |

* Local-only test không phải real staging evidence.
* Dry-run alert không phải real alert evidence.
* Log mailer không phải real SMTP evidence.
* Adapter/unit test không phải real provider callback.
* `production_cutover_approved` luôn false trong staging review.
* Production cutover cần batch riêng.
