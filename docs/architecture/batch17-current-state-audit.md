# Batch 17 — Current State Audit of Staging Infrastructure Readiness

This document audits the current state of staging infrastructure verification for **RestaurantPOS** after the completion of Batches 9 through 16. It outlines existing local staging capabilities, reusable verification components, and critical infrastructure blockers.

---

## 1. Current State Audit Summary

### Reusable Scripts and Tools Already in Place
1. **API Parity Contract Verification (`npm run contract:frontend-parity`)**:
   - Compares frontend API adapters, typed client contracts, and OpenAPI specifications to ensure zero contract drift. Fully functional, clean, and highly robust.
2. **Local Payment Callback Smoke Tests**:
   - `scripts/e2e/momo-sandbox-callback-smoke.mjs`: Simulates a dynamic reservation payment via the `generic_http_hmac` provider and executes HMAC signature callback ingestion.
   - `scripts/e2e/vnpay-sandbox-callback-smoke.mjs`: Simulates a VNPay payment and confirms redirect validation logic.
3. **Casher Shift and Export Smoke Tests**:
   - `scripts/e2e/cashier-shift-close-smoke.mjs` and `scripts/e2e/staging-export-load-smoke.mjs` successfully execute non-destructive UAT and performance checks for cashier shifts and accounting, reconciliation, and menu CSV exports.
4. **Strict System Doctor and Preflight Checks**:
   - `php artisan booking:doctor --json`: Validates DB ping, environments, and outbox status.
   - `php artisan booking:deploy-check --mode=preflight --strict --json`: Validates strict preflight status, including database constraints, scheduler heartbeats, and report projections freshness.

---

## 2. Critical Infrastructure Blockers (NOT YET RESOLVED)

While the codebase is extremely mature and fully prepared to interface with staging networks, several key infrastructural parts remain unconfigured:

1. **No Public HTTPS Webhook Base URL**:
   - All tests currently target `http://localhost:8000`. Actual external payment gateways (MoMo/VNPay sandbox) cannot deliver Webhook callbacks or IPN requests to a local loopback address. A secure public tunnel or static staging domain is missing.
2. **Missing Real Provider Sandbox Callbacks**:
   - Callbacks have only been validated locally via simulated signature payloads generated within our own harness scripts. Packets originating from actual MoMo/VNPay sandbox infrastructure have not yet been routed and verified.
3. **No Automatic Scheduler/Cron Setup**:
   - The strict preflight check (`booking:deploy-check`) requires a fresh scheduler heartbeat. Locally, this was touched manually (`booking:ops-heartbeat:touch`). On real staging, this must be continuously triggered by a system crontab without manual console intervention.
4. **Missing Production-Like Secret Management**:
   - No vault or external secret manager injection has been verified. Critical keys, such as merchant access codes and webhook signature keys, have not been loaded from a secure staging runtime environment.

---

## 3. Reusable Assets Mapping

| Component | File Path | Staging Applicability |
|---|---|---|
| API Parity Check | `scripts/ci/frontend-contract-parity.mjs` | 100% reusable, does not require external network. |
| MoMo Hook Smoke | `scripts/e2e/momo-sandbox-callback-smoke.mjs` | Reusable; requires actual `API_BASE_URL` and `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET`. |
| VNPay Hook Smoke | `scripts/e2e/vnpay-sandbox-callback-smoke.mjs` | Reusable; requires actual `API_BASE_URL` and `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET`. |
| Preflight Audits | `app/Platform/Console/Commands/` | 100% reusable via Artisan CLI in deployment scripts. |
| DB Migrations Parity | `db_all.sql` & `scripts/release/check-package-integrity.mjs` | 100% reusable. Ensures bootstrap schema and migrations are perfectly aligned. |

---

## 4. Current State Verdict
- **Real Staging Infrastructure**: **NOT VERIFIED**
- **Staging Verification Harnesses**: **READY FOR INTEGRATION**

The project is structurally perfect but remains **blocked** from final live validation by the lack of external infrastructure (HTTPS public URL, scheduler daemons, and provider dashboard configurations).
