# Staging Credential Operator Handoff

## Purpose
This document tells the operator exactly what to provision before real staging evidence can pass.

## Credential groups
| Group | Required values | Owner | Source system | Configure in | Validation command | Evidence |
|---|---|---|---|---|---|---|
| Runtime DB | DB_* | Operator | staging database | secret manager/server env | booking:doctor | doctor JSON |
| Redis | REDIS_* | Operator | staging redis | secret manager/server env | booking:doctor | doctor JSON |
| S3 DR | BACKUP_S3_BUCKET, BACKUP_S3_PREFIX, AWS_REGION, IAM role or AWS keys | Cloud operator | AWS | IAM/secret manager | run-dr-rehearsal | DR evidence |
| SMTP | MAIL_* + NOTIFICATION_SMOKE_EMAIL | Ops/email owner | SMTP relay | secret manager | notifications:delivery-smoke | delivery evidence |
| Sentry | SENTRY_LARAVEL_DSN, SENTRY_ENVIRONMENT | Observability owner | Sentry | secret manager | alert-check | event evidence |
| Slack/Ops | OPS_ALERTS_WEBHOOK_URL | Ops owner | Slack/Ops channel | secret manager | check-alerting-health | alert evidence |
| VNPay | VNPAY_* | Payment owner | VNPay sandbox dashboard | secret manager + dashboard | provider callback | callback evidence |
| MoMo | MOMO_* | Payment owner | MoMo sandbox dashboard | secret manager + dashboard | provider callback | callback evidence |
| Staging URL | APP_URL, STAGING_BASE_URL | Platform owner | DNS/proxy | server env | frontend smoke | smoke evidence |

## Do not do
- Do not paste secrets into chat.
- Do not commit `.env`.
- Do not use production merchant/payment callbacks.
- Do not send notification to real customers.
- Do not restore into production.
- Do not mark operator approval automatically.

## Minimum condition for next phase
- Credential validation strict PASS.
- At least S3, SMTP, alerting, payment sandbox callback, and staging URL evidence PASS.
- launch-readiness decision ready or ready_with_warnings.
- production_cutover_approved remains false.
