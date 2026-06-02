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

* Local-only test khÃ´ng pháº£i real staging evidence.
* Dry-run alert khÃ´ng pháº£i real alert evidence.
* Log mailer khÃ´ng pháº£i real SMTP evidence.
* Adapter/unit test khÃ´ng pháº£i real provider callback.
* `production_cutover_approved` luÃ´n false trong staging review.
* Production cutover cáº§n batch riÃªng.

## Operator Credential Handoff
* [Staging Credential Operator Handoff](staging-credential-operator-handoff.md)
* [Staging Env Template](templates/staging.env.template)

## Tooling
Cách ch?y PowerShell evidence pack:
`powershell
powershell -ExecutionPolicy Bypass -File scripts/ops/build-staging-evidence-pack.ps1 -Target staging -Strict
`

Cách ch?y launch-readiness sau dó:
`ash
php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/manual_evidence.json --json
`

> **Note**: 
> - N?u credentials chua có, expected decision là locked_missing_staging_credentials.
> - Không du?c coi pack generated là evidence pass.
> - Pack generated ch? là container; evidence bên trong m?i quy?t d?nh readiness.
