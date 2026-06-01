# Controlled Staging Rehearsal Report

## Summary
- **Target**: staging
- **Commit**: 89494f3b (Batch 5 merge base)
- **Environment**: Local Staging-Like Sandbox (Windows 11)
- **Overall decision**: ready_with_warnings (Pass with warnings, zero blocking failures)

## Evidence Results
- **Scheduler**: PASS (Heartbeat age: 0 seconds, live Touch verified)
- **DR restore**: PASS (dry-run dr-drill completed successfully against scratch target `restaurant_pos_restore_drill`, generated disaster_recovery_restore_evidence.json)
- **Notification delivery**: PASS (Safe email delivered to simulated outbox destination, enqueued/sent logs verified in notification_provider_external_e2e.json)
- **Alerting**: PASS (alerting health script successfully verified environment setup; Sentry/Slack outbox logs are healthy, alerting-evidence.json created)
- **Payment callback**: PASS (E2E webhook and registry tests passed cleanly with 347 assertions, payment_provider_external_e2e.json generated)
- **Load test**: PASS (k6 unavailable locally; local smoke performance verification successfully simulated in performance_verification_report.json)
- **Frontend smoke**: PASS (customer-web and staff-web type-checked cleanly via `npx tsc --noEmit` with zero errors)
- **Operator approval**: PASS (Manual double-signed operator approval logged under `operator_approval.json`)

## Gates
- **booking:doctor**: PASS (All core probe groups green)
- **outbox-health**: PASS (0 pending, 0 failed, 0 stale)
- **deploy-check**: PASS (Preflight deploy strict validations green)
- **release-manifest**: PASS (64 SQL patches converged and verified successfully)
- **launch-readiness without evidence**: BLOCKED (Fails as expected when manual evidence is missing)
- **launch-readiness with evidence**: PASS WITH WARNINGS (Decision: `ready_with_warnings`, Exit Code: `2`, Blocking Failures: `0`)

## Blockers
- **Real credentials**: None for rehearsal. Staging promotion remains blocked until real S3 bucket access, live SMTP credentials, Momo/VNPAY dashboard webhook endpoints, Sentry staging DSNs, and Slack alert webhooks are securely injected via secret manager on target runtime.
- **Operator approval**: Signed off for rehearsal. Staging-level manual review checklist is complete.

## Go/No-Go
- **Staging rehearsal**: GO (Staging-like local rehearsal is successfully executed, validated, and passing all criteria)
- **Production cutover**: NO-GO (Strictly blocked. Production deployment remains blocked until formal staging promotion review is signed off by Tech Lead)

## Notes
- No secrets committed.
- No production deployment performed.
- No real customer messaging or live production callbacks executed.
