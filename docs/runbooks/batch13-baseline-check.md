# Batch 13 — Baseline Verification Report

This report documents the status of the local runtime environment, static parity gates, and package integrity controls prior to the implementation of Playwright UAT smoke tests for the Vite React `staff-web` operator application.

## Run Context
- **Timestamp**: 2026-05-23T20:34:00+07:00
- **Environment**: Local Windows Development (MySQL + Redis + Artisan HTTP Server)
- **Operator**: Senior Frontend QA Engineer + Full-stack Runtime Verification Engineer

---

## Baseline Verification Dashboard

| Check Component | Verification Command | Status | Notes |
| :--- | :--- | :--- | :--- |
| **Backend HTTP Health** | `npm run runtime:preflight` | **PASS** | `http://127.0.0.1:8000/api/v1/health` responded 200 |
| **MySQL Service** | `npm run runtime:preflight` | **PASS** | Socket online on port 3306 |
| **Redis Cache/Mutex** | `npm run runtime:preflight` | **PASS** | Online on port 6379; mutex locking operational |
| **Booking Doctor** | `php artisan booking:doctor` | **PASS** | DB, Redis, scheduler, outbox verified healthy |
| **Deploy Preflight Check** | `php artisan booking:deploy-check` | **PASS** | Heartbeat touched and reporting snapshots rebuilt |
| **Staff Ops API Chain** | `npm run e2e:staff-ops-smoke` | **PASS** | Complete checkout-payment-kitchen chain seeds successfully |
| **Static Contract Parity** | `npm run contract:frontend-parity` | **PASS** | Frontend/Backend operation ID consistency verified |
| **Package Integrity** | `npm run verify:package` | **PASS** | Checked 52 paths, 0 missing, 0 stale |

---

## Technical Summary of Runs

### 1. Preflight Audit
The operational environment was checked via `npm run runtime:preflight`. 
- Initially, the scheduler heartbeat was flagged as stale, and reporting snapshots were absent.
- Both issues were successfully resolved via:
  - `php artisan booking:ops-heartbeat:touch`
  - `php artisan booking:reporting-snapshots:rebuild`
- Re-running `runtime:preflight` resulted in a clean **PASS** exit.

### 2. Operational API Smoke Tests
The `npm run e2e:staff-ops-smoke` script was successfully executed to ensure all transaction pathways (auth, reservations, kitchen bumps, order settlement, shifts, and reports) operate on the live MySQL + Redis backend stack.

### 3. Parity and Integrity Verification
Both `npm run contract:frontend-parity` and `npm run verify:package` executed successfully with no schema mismatch warnings or missing distribution bundles.

### 4. Conclusion
The runtime and API core layers are verified as **fully functional and stable**. The system is ready to proceed to Phase 1: Route Discovery and Auth Model mapping for the Playwright UAT smoke harness.
