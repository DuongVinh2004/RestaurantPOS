# Operational UAT Report — BATCH 13: Browser-Level Staff-Web Automation

This report documents the design, implementation, and successful execution of the Playwright-based browser-level UAT smoke automation suite for the Vite React `staff-web` operator application.

---

## 1. Executive Summary

- **Batch 13 Recommendation**: **READY TO MERGE**
  The `staff-web` browser-level UAT smoke automation suite successfully covers all 10 core pages. It executes with a clean **PASS**, showing 0 un-allowlisted console fatals, 0 page errors, and no blank screens under active UAT flow.
- **Overall Project/Runtime Readiness**: **MERGE WITH RISKS**
  While all local preflight controls, static gates, and Playwright automated tests pass cleanly, overall production readiness remains bounded by the following staging-scope and destructive dependencies:
  - Staging verification of live notification providers (SMS/Zalo/Email).
  - Staging verification of live signature calculations for payment gateways (MoMo/VNPay).
  - High-privilege destructive actions (such as closing cashier shifts or initiating final database-level inventory resets) are kept out of automated sandboxes to preserve seeded data integrity.

---

## 2. Environment Configuration

- **Backend Status**: **PASS** (Laravel Artisan serving at `http://127.0.0.1:8000`)
- **Redis Cache/Mutex**: **PASS** (Connection online on standard port `6379`)
- **Vite React Dev Server**: **PASS** (Hot-reload container active on `http://localhost:5173`)
- **Browser Automation Engine**: Playwright Chromium (headless)
- **Required Env Keys**:
  - `API_BASE_URL` (resolves backend HTTP requests)
  - `STAFF_WEB_BASE_URL` (points to staff Vite instance)
- **Missing Services/Blocks**: None. Local sandbox dependencies are fully operational.

---

## 3. Browser Test Walkthrough Results

The automated UAT suite (`staff-runtime-smoke.spec.ts`) evaluates the app shell and lazy-loading boundaries using client-side soft-navigation to maintain the in-memory operator auth session.

| Step # | Page Name | UAT Target URL | Expected Shell Render | Actual Shell Render | Console Errors | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Step 1** | **Login Gate** | `/login` | `.staff-auth-card` visible | Renders credentials form | None | **PASS** |
| **Step 2** | **Dashboard** | `/ops/dashboard` | `.staff-dashboard-page` + "Vận hành" | Stats overview online | None | **PASS** |
| **Step 3** | **Reservations Inbox** | `/ops/reservations` | Header "Danh sách đặt bàn" | Renders scrollable list | None | **PASS** |
| **Step 4** | **Reservation Detail** | `/ops/reservations?reservation=84` | Timeline drawer or schedule visible | Profile sidepanel online | None | **PASS** |
| **Step 5** | **Table Board** | `/ops/tables` | `.staff-table-board-main` visible | Interactive table grid renders | None | **PASS** |
| **Step 6** | **POS Ordering Workspace** | `/ops/orders?order_id=27&source=order` | Menu panel and cart card visible | Dish list & basket render | None | **PASS** |
| **Step 7** | **KDS Kitchen Board** | `/kitchen/board` | Select station; lanes load | Station selected; lanes active | None | **PASS** |
| **Step 8** | **Checkout Settlement** | `/ops/checkout?order_id=27&source=order` | Bill breakdown card visible | Subtotal, discounts, tax load | None | **PASS** |
| **Step 9** | **Cashier Shift** | `/ops/cashier-shift` | Header "Trung tâm ca thu ngân" | active ca float check rendering | None | **PASS** |
| **Step 10** | **Finance Review** | `/ops/finance-review` | Header "Đối soát và hóa đơn" | Reconcile grid loaded | None | **PASS** |
| **Step 11** | **Admin Settings & Hub** | `/admin/settings` & `/admin/reporting` | "Chi nhánh và thiết lập" / "Hub báo cáo" | Settings grid / daily snapshots load | None | **PASS** |

---

## 4. Operational Staff-Ops Data Used

Dynamic identifiers were retrieved dynamically from `staff_ops_runtime_smoke_result.json` (seeded via `npm run e2e:staff-ops-smoke`):
- **Reservation ID**: `84` (successfully checked-in in Step 4)
- **Order ID**: `27` (successfully queried in Step 6, 8)
- **Cashier Shift ID**: `4` (verified in Step 9)
- **Source Evidence File**: [staff_ops_runtime_smoke_result.json](file:///c:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/staff_ops_runtime_smoke_result.json)

---

## 5. Resilience & Redirection Scenarios

- **Scenario**: Unauthenticated session request (simulating direct link navigation or token expiry).
- **Behavior**: Playwright test clears local storage, then navigates to `/ops/dashboard` directly.
- **Expected Outcome**: Instant redirect to `/login` with `.staff-auth-card` visible.
- **Actual Outcome**: Safely redirects; no white/black screens or fatal script lockups encountered.
- **Status**: **PASS**

---

## 6. Contract and Packaging Sanity Gates

All static release gates were executed synchronously to prevent contract drift or bundle degradation:
- **`npm run contract:frontend-parity`**: **PASS** (Zero mismatched route schemas or operationIds).
- **`npm run verify:package`**: **PASS** (All 52 critical paths are active, none missing or stale).
- **Production Builds**:
  - `npm --prefix staff-web run build`: **PASS** (TS compilation successfully bundles 4692 Vite modules).
  - `npm --prefix customer-web run build`: **PASS** (Next.js server-side pages optimization compiles cleanly).

---

## 7. Remaining Risks & Action Plan

1. **Staging Signature Integration**: Live VNPay/MoMo webhook signature calculations remain deferred to the staging release candidate loop.
2. **Cashier Shift destructive actions**: Closing cashier shifts has been mocked/validated via read-only checks during preflight to protect the sandbox database from accidental closures during E2E iterations.
3. **Multi-branch exports**: stabilizing large CSV downloads is advisory-only for now; live dashboard stats are prioritized.
