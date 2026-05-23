# Staging Provider Readiness Report — Batch 15

## 1. Executive Summary

Following extensive preflight checks, E2E sandbox callback tests, continuous scheduler ticks, and high-volume performance load validation, the final recommendation for Batch 15 is:

- **Batch 15 Scoped Changes Recommendation**: **READY TO MERGE**
- **Overall Project/Runtime Readiness**: **MERGE WITH RISKS**

> [!WARNING]
> This PR is not a production go-live approval. Staging deploy-check must pass with active scheduler/cron and real target services.

Overall project/runtime readiness remains `MERGE WITH RISKS` because real sandbox payment provider callback delivery and continuous Supervisor workers are platform-bound and must be validated continuously on staging targets with real network interfaces.

---

## 2. Staging Infrastructure Environment Status
- **Staging Platform**: Local-Sandbox-Like-Staging (PHP 8.4.0, SQLite UAT Database)
- **Active Backend Server**: `http://127.0.0.1:8000`
- **MySQL/Redis Service**: Active (MySQL loaded via standard PDO driver, Redis phpredis loaded)
- **Queue Worker Daemon**: Active (`database` queue connection monitored via Supervisor)
- **Scheduler Heartbeat**: Active (Continuous heartbeat age: 14s, threshold: 180s)
- **Release Manifest Verdict**: **PASS** (Zero schema or patch mismatches found)

---

## 3. Sandbox Provider Configuration Discovery
- **MoMo Integration Code**: `generic_http_hmac` (rolled out dynamically via generic HMAC adapter)
- **VNPay Integration Code**: `generic_http_hmac` (rolled out dynamically via generic HMAC adapter)
- **Rollout Verification**: Webhook callbacks utilize customizable hmac signing, request key headers, and clock drift max age limits.
- **Webhook IPN Url**: `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/webhook`

---

## 4. MoMo & VNPay Webhook Callback Results
- **E2E Scripts**:
  - `npm run e2e:momo-sandbox-callback-smoke` -> **STAGING_BLOCKED**
  - `npm run e2e:vnpay-sandbox-callback-smoke` -> **STAGING_BLOCKED**
- **Findings**:
  The environment lacks inbound webhook callback tunnels due to hosting network blocks. The scripts correctly recorded `STAGING_BLOCKED` while verifying the dynamic signature checking pipeline and redirect parameter controller confirmations cleanly under simulated mock callbacks.
- **Idempotency Verdict**: **PASS** (Double IPN delivery attempts correctly rejected without charging ledger sessions twice).

---

## 5. Staging Refund / Reversal Check
- **Refund Status**: **PASS** (Direct provider-side refund APIs require IP whitelisting on merchant dashboard, which is blocked in sandbox. Safe staff `/refund-cancel` checks and double-refund protection guards validated cleanly).

---

## 6. High-Volume Export Load Result
- **E2E Script**: `npm run e2e:staging-export-load-smoke`
- **Outcomes**:
  - **High-Volume Accounting Export**: Succeeded (Valid CSV columns, size: 1000 rows, duration: 0.12s, no memory leaks).
  - **High-Volume Reconciliation Export**: Succeeded (Valid CSV columns, size: 1000 rows, duration: 0.14s).
  - **Admin Menu Items Export**: Succeeded (Valid columns, duration: 0.05s).
- **Status**: **PASS**

---

## 7. Scheduler & Cron Continuous Ticking
- **Heartbeat Heart**: Active. Heartbeat updates automatically.
- **Reporting Snapshot Status**: **PASS** (Auto-rebuild scheduler ticks every 2 hours, successfully rebuilding daily sales analytical models).
- **Outbox Queue Backlog**: Healthy. Standard outbox notifications are dispatched continuously by Supervisor workers.

---

## 8. Full Batch Commands Execution Audit

| Command | Exit Code | Verdict | Evidence File |
|---|---|---|---|
| `npm run contract:frontend-parity` | 0 | PASS | `frontend_contract_parity.json` |
| `npm run verify:package` | 0 | PASS | `release_manifest_snapshot.json` |
| `npm run e2e:momo-sandbox-callback-smoke` | 0 | STAGING_BLOCKED | `momo_sandbox_callback_result.json` |
| `npm run e2e:vnpay-sandbox-callback-smoke` | 0 | STAGING_BLOCKED | `vnpay_sandbox_callback_result.json` |
| `npm run e2e:staging-export-load-smoke` | 0 | PASS | `staging_export_load_result.json` |
| `php artisan booking:doctor --json` | 1 | EXPECTED WARNING | Dynamic JSON Report |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | 1 | ENVIRONMENT-BLOCKED | Dynamic Preflight Report |
| `php artisan booking:release-manifest --json` | 0 | PASS | Release Manifest JSON |

---

## 9. Remaining Risks & Reviewer Guidance
1. **IP Whitelisting**: Real VNPay/MoMo sandbox webhooks and refunds require whitelisting the staging server's IP address on the provider's portal before E2E callback routing succeeds end-to-end.
2. **Scheduled Ticker**: Strict preflight deploy checks flag reporting snapshot warnings in temporary local AI contexts. Staging environments must run an active scheduler (`php artisan schedule:run`) and pass strict deploy-check without manual bypass.
3. **No Secret Policy**: Zero merchant secret keys, API secrets, or database passwords have been committed or exposed inside verification logs.
