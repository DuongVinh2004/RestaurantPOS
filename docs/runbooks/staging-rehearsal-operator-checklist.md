# Staging Rehearsal Operator Manual & Checklist

This document is the official release checklist for RestaurantPOS operators.
Follow these steps systematically to collect, redact, and confirm staging manual evidence prior to proposing a production promotion.

---

## 1. Pre-Rehearsal Checklist
Before initiating the rehearsal, ensure that:
- [ ] You are on the correct Git branch: `staging-rehearsal-operator-evidence-pack`
- [ ] Local `main` is fresh and updated with no unmerged stack drifts.
- [ ] Staging environment configuration files (`.env`) contain non-production credentials.
- [ ] Docker or background runtime engines are running MySQL and Redis locally/on staging.

---

## 2. Required Staging Credentials
Confirm the following non-production configurations are securely defined in your staging environment variables:
- [ ] `MAIL_MAILER=smtp` (or a sandbox/simulated email driver like Mailhog/Mailtrap)
- [ ] `SENTRY_DSN` (staging-specific active DSN)
- [ ] `OPS_ALERTS_WEBHOOK_URL` (Slack/Telegram rehearsal channel)
- [ ] Momo/VNPAY test credentials (with `PAYMENT_CUSTOMER_SELF_PAY_ENABLED=false` by default for staff-only day-1 path)
- [ ] Isolated AWS S3 bucket: `BACKUP_S3_BUCKET=restaurantpos-staging-backups` (never use the production bucket)

---

## 3. Scheduler & Worker Check
Run the doctor baseline check to confirm the scheduler and background workers are healthy:
```bash
php artisan booking:doctor
```
- [ ] Check that `scheduler` status probe is `OK` and touchpoints are updated.
- [ ] Review any warnings emitted and record the outcome in the master evidence JSON checks block.

---

## 4. DB & Redis Health
Inside the `booking:doctor` output, verify that:
- [ ] Redis client connectivity is active and ready.
- [ ] MySQL schemas are fully synchronized with no pending migrations or schema drift.

---

## 5. Disaster Recovery Restore Drill Sign-Off
Run the automated non-destructive database restore rehearsal:
```bash
./scripts/ops/run-dr-rehearsal.sh --local-only --dry-run
```
For a full staging execution (against a safe scratch database, e.g., `restaurant_pos_restore_drill`):
```bash
./scripts/ops/run-dr-rehearsal.sh --local-only --target-db=restaurant_pos_restore_drill
```
- [ ] Verify that the scratch database is dropped, recreated, and successfully imported.
- [ ] Copy the generated `disaster_recovery_restore_evidence` JSON block from `storage/app/booking_release/launch_readiness/manual_evidence.json` into your local evidence pack.

---

## 6. Notification Delivery Sign-Off
Trigger a safe end-to-end notification delivery smoke run to verify SMTP routing:
```bash
php artisan notifications:delivery-smoke --recipient=smoke-test@example.com --channel=Email
```
- [ ] Check Mailtrap or the designated staging inbox for receipt.
- [ ] In the `notification_provider_external_e2e` JSON evidence, log the `delivery_attempt_id` and redact the specific recipient address details.

---

## 7. Alerting Sign-Off
Validate the operational alert snapshot and error delivery:
```bash
php artisan booking:alert-check
```
Verify that Slack/Telegram channel webhooks receive a test notification if alerts are triggered:
```bash
./scripts/ops/check-alerting-health.sh
```
- [ ] Review alert sections to make sure there are no active `CRITICAL` backlogs.
- [ ] Ensure webhook URLs are redacted in the alerting manual evidence JSON.

---

## 8. Payment Callback Sign-Off
Staging is defaulted to customer self-pay disabled, utilizing staff-settlement only.
- [ ] Run the canonical financial checks suite to verify staff payment mutations stay green:
  ```bash
  php artisan booking:round5-gate
  ```
- [ ] Verify that Momo/VNPAY callback routes are properly registered and accept simulated signed headers.
- [ ] Log this in `payment_provider_external_e2e` manual evidence checks using the template placeholder.

---

## 9. Load Test Sign-Off
Collect the release candidate baseline performance profile:
```bash
php artisan booking:performance-verify --profile=staging --run --base-url=http://localhost:8000 --promote-baseline
```
- [ ] Ensure that latency p95 percentiles do not exceed thresholds for critical paths like table board load and checkout.
- [ ] Merge the generated `performance_verification_report` JSON details into the consolidated evidence.

---

## 10. Final Go/No-Go Checklist
Before generating the final evidence pack, confirm:
- [ ] [ ] All required checks are filled out with `"status": "pass"`.
- [ ] [ ] All timestamps are in UTC format.
- [ ] [ ] Secrets (passwords, S3 keys, Sentry DSNs) have been successfully redacted.
- [ ] [ ] PII (emails, phone numbers, customer names) are scrubbed.

Run the Staging Evidence Pack Builder:
```bash
./scripts/ops/build-staging-evidence-pack.sh
```

---

## 11. Operator Approval Template
Operator manual approvals are logged under `operator_approval` inside `manual_evidence.json`:
```json
{
  "operator_approval": {
    "status": "pass",
    "operator_initials": "OP",
    "reviewer_initials": "REV",
    "verified_at": "2026-06-01T04:00:00Z",
    "notes": "Verified that all staging preflight gates and DR drills are passing successfully."
  }
}
```

---

## 12. Rollback & Abort Conditions
If any of the following occur, abort the rehearsal immediately and revert configurations:
1. DB schema changes executed on the active production database.
2. Hardcoded staging credentials committed to VCS.
3. Active, unredacted customer notifications dispatched to real clients.
4. Latency regressions exceeding `2.5x` the established baseline p95 percentiles.
5. Inability to rollback migrations cleanly using the native patch rollback process.
