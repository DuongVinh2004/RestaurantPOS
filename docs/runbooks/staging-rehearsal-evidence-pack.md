# Staging Rehearsal & Operator Manual Evidence Pack Runbook

This runbook standardizes how RestaurantPOS release operators collect, redact, package, and verify staging evidence to satisfy launch-readiness gates before a controlled production promotion.

---

## 1. Scope
This runbook applies to **staging and staging-like local rehearsal environments only**.
It establishes how to verify the runtime components (scheduler, disaster recovery, notifications, alerting, payments, and performance baselines) under staging-level configurations without performing live production deployments.

> [!WARNING]
> **This build is NOT ready for production cutover.** Production promotion remains explicitly blocked until all staging evidence is successfully collected, validated, and signed off by release operators and reviewers.

---

## 2. What Counts as Evidence
Valid evidence must represent a real runtime state or successful execution on staging (or a staging-like local rehearsal environment):
- Raw console outputs from native Artisan commands (`booking:doctor`, `booking:dr-drill`, `notifications:outbox-health`, etc.) captured in a JSON payload.
- Standardized manual checks logged in `manual_evidence.json` matching the schemas in `docs/runbooks/templates/`.
- Timestamps recorded in UTC format.
- Sign-offs containing initials only (no full names or contact details).

---

## 3. What Does NOT Count as Evidence
- Fake, hardcoded, or mocked passes for components that were not actually executed.
- Output from local manual testing labeled as "Production Evidence".
- Scenarios executed with hardcoded sandbox bypasses that differ from the actual target environment configurations.
- Any document containing plain credentials, private S3 buckets, real API keys, or raw customer PII.

---

## 4. Evidence Matrix

| Evidence Area | Required? | Source Command / Manual Step | Evidence File | Secret/PII Redaction | Status |
|---|---:|---|---|---|---|
| **Scheduler heartbeat** | Yes | `booking:doctor` | `manual_evidence.json` checks | No PII | `pending` |
| **DR restore drill** | Yes | `booking:dr-drill` / `run-dr-rehearsal.sh` | `dr-restore-evidence.json` | No secrets | `pending` |
| **Notification delivery** | Yes | `notifications:delivery-smoke` | `notification-delivery-evidence.json` | Email/phone redacted | `pending` |
| **Alerting channel** | Yes | `booking:alert-check` / `check-alerting-health.sh` | `alerting-evidence.json` | Webhook URL hidden | `pending` |
| **Payment callback rehearsal** | Conditional | simulated signed callback | `payment-callback-evidence.json` | Txn refs redacted | `pending` |
| **Load test baseline** | Yes | `booking:performance-verify` | `load-test-evidence.json` | No PII | `pending` |
| **Operator approval** | Yes | Manual operator review & sign-off | `operator-approval.json` | Initials only | `pending` |

---

## 5. Evidence File Locations
All generated release evidence must reside under:
`storage/app/booking_release/manual_evidence/`

The primary compiled file consumed by launch readiness is:
`storage/app/booking_release/manual_evidence/manual_evidence.json`

Templates are located in:
`docs/runbooks/templates/`

---

## 6. Redaction Policy
All evidence files must satisfy the following strict redaction policies:
1. **No Raw Secrets**: Under no circumstances should `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `SENTRY_DSN`, Momo/VNPAY `access_key`/`secret_key`, or Slack webhooks be present in any file. Use placeholders like `[redacted]` or `[hidden]`.
2. **No Raw PII**: Scrub all customer emails, full names, phone numbers, and address details. Use mock initials and obfuscated IDs.
3. **No Unencrypted Tokens**: Sensitive authentication headers must be omitted or stubbed.

---

## 7. How to Build Staging Evidence Pack
Operators should run the staging evidence pack builder script:
```bash
./scripts/ops/build-staging-evidence-pack.sh [options]
```
Supported options:
- `--dry-run`: Evaluate dependencies and state without generating files.
- `--local-only`: Ignore remote S3 or third-party connections and run only local checks.
- `--metadata-only`: Inspect the integrity of the release manifest without running the probes.

This script aggregates check metadata, scrubs PII/secrets, and saves a canonical `manual_evidence.json` file.

---

## 8. How to Run Staging Rehearsal
To run a complete staging rehearsal (including doctor, outbox, preflight deploy, and launch-readiness gates):
```bash
./scripts/ops/run-staging-rehearsal.sh [options]
```
This performs a full run of the baseline verification commands, compiles individual manual evidence reports, and prints the launch readiness matrix status.

---

## 9. How to Run Launch Readiness with Evidence
To consume the compiled staging manual evidence pack and run the launch-readiness gate:
```bash
php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/manual_evidence.json
```
To run in machine-readable format:
```bash
php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/manual_evidence.json --json
```

---

## 10. How to Interpret PASS/WARNING/BLOCKED

### PASS (Exit Code `0`)
- The target launch-readiness baseline is completely satisfied.
- Automated tests are green, and all required manual evidence fields are valid, recent, and passing.

### WARNING (Exit Code `2`)
- The blocking baseline remains green, but recommended manual checks (or day-1 feature flag scopes) emitted warnings.
- *Remediation*: Review warnings explicitly, adjust configurations if needed, or secure approvals from the tech lead.

### BLOCKED (Exit Code `1`)
- A critical or blocking check has failed (e.g., active Sentry alerts, missing UAT scenario evidence, outdated DR drills).
- *Remediation*: Fix the root cause, Touch the touchpoint files, rebuild the package, and rerun the rehearsal.

---

## 11. Required Credentials Not Stored in Repo
To complete real staging operations, release managers must inject the following secure values in the staging environment (never in the repo):
- `MAIL_PASSWORD` (SMTP auth for real notification delivery smoke)
- `SENTRY_DSN` (Staging error monitoring channel verification)
- `OPS_ALERTS_WEBHOOK_URL` (Slack/Telegram webhook for operational alert tests)
- `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_API_SECRET` & `SIGNING_SECRET` (Momo/VNPAY credentials)
- AWS credentials (S3 buckets for real DR backups)

---

## 12. Remaining Production Blockers
Before proceeding to a controlled production cutover, the following items are strictly required:
1. Live S3 backup-restore drill evidence.
2. Signed callback webhook verification from Momo/VNPAY production sandbox.
3. Outbound SMS/email provider delivery confirmation using a live staging address.
4. Formal, double-signed operator approval recorded in the evidence pack.
5. Rebuilding of the immutable release package (`booking:package-release`) with production signatures.

---

## 13. No Production-Ready Claim
This package is **not production-ready**. All evidence collected during these runs is purely for staging rehearsal. The production environment remains locked until explicit production cutover gates are authorized by engineering leadership.
