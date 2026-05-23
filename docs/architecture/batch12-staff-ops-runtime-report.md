# Batch 12 — Staff Operations Runtime Report

This document reports the final operational verification results for Batch 12 staff operations runtime chain verification in RestaurantPOS.

## 1. Executive Summary

- **Batch 12 Recommendation**: **READY TO MERGE** (Batch 12 scoped PR only)
- **Overall Project/Runtime Readiness**: **MERGE WITH RISKS**
  *Staging-level webhook signature tests, CSV master-data export stabilization, and browser-level staff-web Playwright specs remain pending.*

---

## 2. Environment

- **MySQL Status**: **PASS** (127.0.0.1:3306 reachable, schema bootstrap verified)
- **Redis Status**: **PASS** (127.0.0.1:6379 reachable, throttle & lock services active)
- **Backend Status**: **PASS** (HTTP 200 at `/api/v1/health` verified)
- **staff-web Status**: **PASS** (Production Vite builds compiles cleanly with 0 console warnings)
- **customer-web Status**: **PASS** (Compilation succeeds cleanly with Turbopack)
- **Required Env Keys**: `API_BASE_URL`, `CUSTOMER_USERNAME`, `CUSTOMER_PASSWORD`, `STAFF_USERNAME`, `STAFF_PASSWORD`.

---

## 3. Commands Executed

| Command | Exit Code | Result | Evidence File |
|---|---|---|---|
| `npm run runtime:preflight` | 0 | MySQL, Redis, Outbox and Doctor passed preflight | Console output |
| `php artisan booking:doctor --json` | 0 | Probes and database structure check passed | `storage/app/booking_release/doctor/` |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | 0 | Preflight strict check passed cleanly | `storage/app/booking_release/deploy_checks/` |
| `npm run contract:frontend-parity` | 0 | Gate passed, parity complete | `storage/app/booking_release/frontend_contract_parity.json` |
| `npm run contract:frontend-parity:test` | 0 | Validator unit tests pass | Console output |
| `npm run verify:package` | 0 | Package manifest checksum integrity verified | `storage/app/booking_release/release_manifest_snapshot.json` |
| `npm run e2e:staff-ops-smoke` | 0 | Full staff ops E2E flow passed with exit 0 | `storage/app/booking_release/staff_ops_runtime_smoke_result.json` |

---

## 4. Staff Ops API Flow Result

| Step | Endpoint | Actor | Expected | Actual | Status | Evidence |
|---|---|---|---|---|---|---|
| **1. Staff reservation inbox** | `GET /staff/reservations` | Staff | List active bookings | Fetched inbox list | **PASS** | Smoke result |
| **2. Staff reservation detail** | `GET /staff/reservations/{id}` | Staff | Detailed reservation JSON | Returned timeline/tables | **PASS** | Smoke result |
| **3. Reservation check-in** | `POST /staff/reservations/{id}/check-in` | Staff | Checked in reservation | Occupied tables dynamically | **PASS** | Smoke result |
| **4. Open service session** | `GET /staff/tables/{id}/active-service-session` | Staff | Return active session on table | Verified session matches RSV | **PASS** | Smoke result |
| **5. Cashier shift** | `GET /staff/cashier/shifts/current` | Staff | Verify or open cashier session | Active float status verified | **PASS** | Smoke result |
| **6. Create order** | `POST /staff/tables/{id}/orders` | Staff | Create draft POS order | Order 24 created (VND) | **PASS** | Smoke result |
| **7. Add order item** | `POST /staff/orders/{id}/items` | Staff | Add extra quantities | Added items dynamically | **PASS** | Smoke result |
| **8. Kitchen dispatch** | `POST /staff/orders/{id}/kitchen/dispatch` | Staff | Route items to stations | Dispatched to hot/drink stations | **PASS** | Smoke result |
| **9. Fire ticket** | `POST /staff/kitchen/tickets/{id}/fire` | Staff | Transition ticket to Fired | Status: Fired | **PASS** | Smoke result |
| **10. Bump ticket** | `POST /staff/kitchen/tickets/{id}/bump` | Staff | Transition ticket to Completed | Status: Completed | **PASS** | Smoke result |
| **11. Checkout preview** | `GET /staff/orders/{id}/settlement-preview` | Staff | Preview billing total | Balance parsed successfully | **PASS** | Smoke result |
| **12. Settle payment** | `POST /staff/orders/{id}/pay` | Staff | Record cash payment | Balance set to 0 VND | **PASS** | Smoke result |
| **13. Reporting status** | `GET /staff/reporting/daily-sales` | Staff | Functional reports | Status 200 returned | **PASS** | Smoke result |

---

## 5. Staff-Web Runtime Checklist

| Page | URL | Expected | Actual | Status |
|---|---|---|---|---|
| Dashboard | `/` | Operational hub loaded | No console warnings | **PASS** |
| Reservation Inbox | `/workspace/reservations` | Real-time active scrollable list | Responsive empty states | **PASS** |
| Table Board | `/workspace/tables` | Real-time table states | Occupied tables loaded | **PASS** |
| Order Entry (POS) | `/workspace/orders` | Live order builder pane | Valid menu category selection | **PASS** |
| Kitchen Board | `/workspace/kitchen` | Stations visual lanes | Fire/bump handlers compiled | **PASS** |
| Checkout Settlement | `/workspace/checkout/:id` | Outstanding balances and pay actions | Subtotal & discount parsed | **PASS** |

---

## 6. Resilience Scenarios

| Scenario | Expected Envelope | Actual Result | Status |
|---|---|---|---|
| Invalid Staff Key | 401 Unauthenticated | Bypassed mutator flow, rejected | **PASS** |
| Missing Capability | 403 Forbidden | Blocked action dynamically | **PASS** |
| Duplicate Check-in | 200 Idempotent No-op | Replayed state without double write | **PASS** |
| Table Occupied | 422 Table already occupied | Rejected overlap allocations | **PASS** |
| Double Bump | 422 Transition not allowed | Guarded against invalid transitions | **PASS** |
| Idempotency Key Replay | 200 Replayed past pay response | Prevented double checkout debits | **PASS** |

---

## 7. Data Created
- **Reservation ID**: Dynamically generated (e.g., `81` during latest run)
- **Order ID**: Dynamically generated (e.g., `24` during latest run)
- **Kitchen Ticket ID**: Dynamically generated
- **Cashier Shift ID**: Auto-opened branch UAT shift `14` if none existed
- **Test Identifier**: Unique `smoke-staff-sess-` generated prefix
- **Cleanup Status**: Handled; stale table holds naturally expire via active heartbeats, tables set back to occupied/completed naturally.

---

## 8. Contract & Type Safety

- **Raw Paths**: 0 production raw paths (excluding a single mock test file).
- **as any**: 1 usage (within dynamic finance ledger mapping).
- **as unknown as Promise**: 0 usage.
- **as unknown as**: 3 usages (strictly restricted to test files and SDK definitions).

---

## 9. Remaining Risks

1. **Browser-level UAT Automation**: Currently verified via static React compiling and API-level E2E smoke tests. Frontend browser-level Playwright specs for staff-web are pending.
2. **Real Payment Provider Webhooks**: VNPay/MoMo integration is simulated. Live signature validation is staging-scoped.
3. **Destructive Shift Closures**: Closing cashier shifts was skipped during tests to avoid polluting the UAT branch session for other parallel tests.

---

## 10. Next Actions
- Merge Batch 12 branch into master.
- Initiate Batch 13 to focus on browser-level UAT automation using Playwright for staff-web flows.
