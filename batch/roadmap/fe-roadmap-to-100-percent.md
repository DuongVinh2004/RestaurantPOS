# FE Roadmap to 100% Backend Coverage

Goal: bring `customer-web` and `staff-web` to 100% of the existing Laravel backend contract without rewriting architecture, without hard-coding routes where generated SDK/adapter patterns exist, and without promoting gated features before runtime evidence exists.

## Batch 1 — Customer booking core hardening

**Goal**: Make Wave-1 customer chain release-proof: login/session, menu browse, availability, hold create/refresh/cancel, reservation create/list/detail.

**Why important**: This is the customer day-1 promise and the shortest path to production value.

**Backend capability**: auth customer; menu; tables/available; table-holds; reservations create/list/detail.

**Frontend work**:
- `customer-web`: harden booking, reservation new/detail/list, session recovery, expired hold recovery.
- `staff-web`: no direct change except smoke coordination if staff-created state is used.

**Files/khu vực**:
- `customer-web/src/lib/api/sdk-client.ts`
- `customer-web/src/features/table-booking/*`
- `customer-web/src/features/reservations/*`
- `customer-web/e2e/customer-live.spec.ts`

**API/route**:
- `POST /api/v1/auth/customer/login`
- `GET /api/v1/menu/items`
- `GET /api/v1/tables/available`
- `POST|GET|PATCH|DELETE /api/v1/table-holds/*`
- `POST|GET /api/v1/reservations/*`

**State management**: ensure session id is stable across booking -> reservation; keep hold row_version in page/query cache; invalidate reservation list/detail after mutation.

**Validation**: datetime in future, guest_count, duration, table_ids, hold_id/session_id consistency.

**Auth/header/idempotency/row_version**: `X-Customer-Token` when logged in; `X-Session-Id` for guest/session flows; stable `Idempotency-Key`; hold refresh/cancel row_version when known.

**Tests**: table booking API/page, reservation new/list/detail, stale hold, expired hold, live Wave-1 Playwright.

**Smoke/live proof**: `npm run verify:wave-1`, `npm run test:e2e:live`, repo `npm run dev:smoke`.

**Risks**: stale UAT pack, CORS host mismatch, clock skew, expired holds.

**Definition of Done**:
- Wave-1 deterministic and live tests pass.
- No dev mock used in live proof.
- Stale/expired hold and reservation error states are visible and recoverable.

## Batch 2 — Customer preorder behind rollout gate

**Goal**: Complete preorder show/preview/replace/clear without making it day-1 default.

**Backend capability**: `POST /menu/preorder/preview`, `GET|POST|PUT|DELETE /reservations/{id}/preorder`.

**Frontend work**:
- Build/finish preorder panel cart state, preview totals, quota/cutoff display, replace and clear.
- Keep `NEXT_PUBLIC_FEATURE_PREORDER=false` by default.

**Files/khu vực**:
- `customer-web/src/features/preorder/*`
- `customer-web/src/features/menu/api.ts`
- `customer-web/src/features/reservations/reservation-detail-page.tsx`

**State management**: reservation row_version + pre_order_row_version; cart draft; preview result; conflict refresh.

**Validation**: item quantity, preorder-enabled items only, cutoff/quota messages.

**Auth/header/idempotency/row_version**: customer/session owner auth; `X-Session-Id`; `Idempotency-Key`; `row_version`; conditional `pre_order_row_version`.

**Tests**: gated UI off by default, preview, replace, clear, stale row_version conflict.

**Smoke/live proof**: gated Playwright with `NEXT_PUBLIC_FEATURE_PREORDER=true` only against seeded fixtures.

**Risks**: preorder aliases must not cause hard-coded duplicate paths; use generated SDK canonical methods.

**Definition of Done**:
- Feature remains closed unless flag is enabled.
- Replace/clear cannot run without latest row_version.
- Tests prove disabled and enabled states.

## Batch 3 — Customer deposit, bill visibility, and self-pay posture

**Goal**: Make deposit and bill surfaces truthful and safe: read visibility default, payment-session controls provider-gated.

**Backend capability**: deposit preview/intent/payment sessions; active-order/bill preview/bill; bill payment sessions.

**Frontend work**:
- Customer deposit panel: preview, acknowledge/intent/revoke, payment session create/show/refresh/confirm when provider posture allows.
- Billing panel: active-order and bill reads; self-pay controls remain disabled in production-like until provider proof.

**Files/khu vực**:
- `customer-web/src/features/deposit/*`
- `customer-web/src/features/billing/*`
- `customer-web/src/lib/config/support-matrix.ts`

**State management**: reservation row_version; payment_session id/status; refresh/polling state; disabled reason from support matrix.

**Validation**: payment availability, amount/currency, provider/session status, stale reservation.

**Auth/header/idempotency/row_version**: `X-Customer-Token`/`X-Session-Id`; idempotency on all payment-session mutations; row_version required.

**Tests**: deposit panel, billing panel, disabled self-pay, provider-enabled mock, conflict refresh.

**Smoke/live proof**: `CUSTOMER_WEB_LIVE_EXERCISE_DEPOSIT_PAYMENT_SESSION=true` and bill equivalent only when provider/stub is intentionally configured.

**Risks**: simulated provider is not production launch proof; bill self-pay kill switch defaults false outside local/testing.

**Definition of Done**:
- Reads are clear and truthful.
- Money-moving buttons are not exposed when provider posture is not launch-approved.
- Provider proof is separated from CI-safe smoke.

## Batch 4 — Staff POS core money path

**Goal**: Make day-1 staff chain robust: board, reservation handling, walk-in, active order, order items, checkout, refund, cashier shift, finance review.

**Backend capability**: staff board/reservations/service-sessions/orders/order-items/checkout/refund/cashier/finance.

**Frontend work**:
- Harden route guards/capabilities.
- Finish line item update/status controls.
- Ensure cashier shift handoff blocks finalize safely.
- Complete refund/refund-cancel and reconciliation refresh.

**Files/khu vực**:
- `staff-web/src/workspaces/ops/pages/tables/*`
- `staff-web/src/workspaces/ops/pages/reservations/*`
- `staff-web/src/workspaces/ops/pages/orders/*`
- `staff-web/src/workspaces/ops/pages/checkout/*`
- `staff-web/src/workspaces/ops/pages/refunds/*`
- `staff-web/src/workspaces/ops/pages/cashier-shift/*`
- `staff-web/src/workspaces/ops/pages/finance-review/*`
- `staff-web/src/shared/api/staff-api.ts`
- `staff-web/src/shared/mutations/mutation-ux.ts`

**API/route**:
- `/staff/tables/board`, `/staff/reservations`, `/staff/service-sessions/walk-in`, `/staff/tables/{table_id}/orders`, `/staff/orders/{order_id}/items`, `/staff/orders/{order_id}/items/{order_item_id}`, `/staff/orders/{order_id}/bill-snapshot`, `/settlement-preview`, `/pay`, `/settlement/finalize`, refunds, cashier shifts, finance reconciliation/invoices.

**State management**: branch context, selected table/reservation/order, latest order row_version, latest item row_version, cashier shift status.

**Validation**: capability/branch scope, item quantity/notes/status, tender amount, refund amount, shift totals.

**Auth/header/idempotency/row_version**: `X-Staff-Key`; capability guards; idempotency on every mutation; row_version for order/reservation/cashier/refund operations.

**Tests**: line item lifecycle, checkout no-shift/with-shift, settlement finalize, refund, cashier open/close, finance invoice issue.

**Smoke/live proof**: staff live smoke: login -> board -> check-in/walk-in -> order -> add item -> bill snapshot -> pay/finalize -> refund -> finance review.

**Risks**: money-moving flow must fail closed; stale writes are high-risk.

**Definition of Done**:
- Day-1 staff money path has deterministic + live proof.
- No mutation without idempotency and latest row_version.
- Capability-denied roles see safe disabled/empty states.

## Batch 5 — Staff waiting-list manual coordination

**Goal**: Complete staff manual waiting-list day-1 path and customer wave-2 owner actions without claiming realtime/automation.

**Backend capability**: staff waiting-list create/notify/seat/cancel/advance/changes; customer list/create/show/accept/confirm-arrival/decline/cancel.

**Frontend work**:
- Staff queue UI: manual notify, table selection/fallback, seat, cancel, changes feed.
- Customer gated owner UI: accept/confirm/decline/cancel.
- Keep advanced automation off by default.

**Files/khu vực**:
- `staff-web/src/workspaces/ops/pages/waiting-list/*`
- `customer-web/src/features/waiting-list/*`
- `staff-web/src/shared/api/staff-api.ts`
- `customer-web/src/lib/config/support-matrix.ts`

**State management**: waiting row_version, selected table, invite state, change feed version.

**Tests**: staff manual flow, customer gated flow, no table.board.view fallback, stale row_version.

**Smoke/live proof**: gated customer + staff coordination fixture.

**Risks**: notification delivery and final seating are runtime prerequisites and must not be faked.

**Definition of Done**:
- Staff manual queue is day-1 safe.
- Customer wave-2 stays closed unless flag is enabled.
- Change feed and row_version refresh are tested.

## Batch 6 — Kitchen/KDS promotion

**Goal**: Promote KDS only when feature/capability/station proof exists.

**Backend capability**: kitchen stations, station tickets, dispatch/fire/bump/recall, changes.

**Frontend work**:
- Station lock and branch context.
- Read-only ticket board when mutations disabled.
- Mutations behind `staff.kitchen_dispatch` posture and capabilities.

**Files/khu vực**:
- `staff-web/src/workspaces/kitchen/*`
- `staff-web/src/domains/kitchen/*`
- `staff-web/src/shared/api/staff-api.ts`

**Auth/header/idempotency/row_version**: `X-Staff-Key`, `kitchen.manage`/`order.manage`, idempotency, ticket/order row_version.

**Tests**: station missing, capability denied, read-only gate, fire/bump/recall success/conflict.

**Smoke/live proof**: gated KDS smoke with station/ticket fixtures.

**Risks**: kitchen mutation rollout is explicitly held back day-1.

**Definition of Done**:
- Production-like default is safe/read-only or hidden.
- Gated mutation live proof exists before promotion.

## Batch 7 — Admin settings/menu/table/branch flows

**Goal**: Complete safe admin configuration UI for branches, tables, zones, templates, kitchen station config, tax profile, menu categories/items/prices.

**Backend capability**: admin settings + menu routes.

**Frontend work**:
- Full CRUD forms with row_version.
- Import/export dry-run/commit for supported domains.
- Delete guards for tables with active links.

**Files/khu vực**:
- `staff-web/src/workspaces/admin/pages/settings/*`
- `staff-web/src/workspaces/admin/pages/catalog/*`
- `staff-web/src/domains/admin/*`
- `staff-web/src/shared/api/staff-api.ts`

**Tests**: CRUD, stale row_version, import dry-run/commit, export download.

**Risks**: admin configuration errors can break operations.

**Definition of Done**:
- All admin settings/menu routes have adapter + UI + validation + tests.
- Mutations use idempotency and row_version where required.

## Batch 8 — Inventory/procurement UI

**Goal**: Complete inventory uplift while keeping rollout gate respected.

**Backend capability**: ingredients, recipes, movements, suppliers, purchase orders, receipts.

**Frontend work**:
- Ingredient/supplier/PO CRUD, recipe sync, stock movement, receipt creation.
- Gate advanced workflows behind inventory posture.

**Files/khu vực**:
- `staff-web/src/workspaces/admin/pages/inventory/*`
- `staff-web/src/domains/inventory/*`
- `staff-web/src/shared/api/staff-api.ts`

**Tests**: list/create/update/movement/receipt/recipe conflict tests.

**Risks**: inventory writes affect procurement/accounting accuracy.

**Definition of Done**:
- All inventory routes are covered by adapter + UI + tests.
- Production-like gate remains safe until rollout proof.

## Batch 9 — Promotions/loyalty UI

**Goal**: Complete customer, staff, and admin benefits/loyalty without stale reservation mutations.

**Backend capability**: customer benefits, staff vouchers/loyalty, admin voucher/tier/settings.

**Frontend work**:
- Customer wallet and reservation-level apply/remove/redeem/release behind flag.
- Staff reservation benefits panel.
- Admin vouchers, loyalty tiers, benefit settings CRUD/import/export.

**Tests**: row_version conflict, wallet read, voucher apply/remove, loyalty redeem/release, admin master data.

**Risks**: Benefits mutate money/settlement state.

**Definition of Done**:
- Customer benefits remain wave-2 gated.
- All mutations use latest reservation row_version and idempotency.

## Batch 10 — Privacy, conversations, reporting, audit, ops proof

**Goal**: Complete non-day-1 but contract-visible governance/ops surfaces.

**Backend capability**: conversations, privacy/data export/audit, reporting, ops health/metrics, master data.

**Frontend work**:
- Staff conversation inbox gated by feature flag.
- Admin privacy review and audit trail.
- Reporting dashboards and exports.
- Ops health/metrics access screen or documented backend-only proof.
- Master-data import/export coverage for all backend domains.

**Tests**: access control, signed files, privacy request lifecycle, report filters, export downloads, metrics guard.

**Risks**: Sensitive data and metrics exposure.

**Definition of Done**:
- Every backend route has a deliberate FE status: DONE, GATED, or documented backend-only NOT_APPLICABLE.
- Full release verification commands pass.

## Batch 11 — Release/runtime proof for both frontends

**Goal**: Convert static coverage to release evidence.

**Work**:
- Run SQL-first backend bootstrap.
- Sync contracts after backend artifacts regenerate.
- Run customer and staff deterministic tests.
- Run live UAT smoke for day-1 and gated feature suites separately.
- Save evidence in docs/runbooks or release artifacts.

**Commands**:

```bash
composer bootstrap:booking
composer api:artifacts
cd customer-web && npm run sync:contracts && npm run verify:release
cd ../staff-web && npm run integrity:check && npm run build
```

```powershell
npm run dev:all
npm run dev:smoke
cd customer-web
npm run test:e2e:live
cd ../staff-web
npm run live:smoke
```

**Definition of Done**:
- Static matrix is updated with runtime evidence links.
- No gated feature is promoted without explicit proof.
