# FE/BE Coverage Summary — RestaurantPOS

Static audit date: 2026-04-29. This is a static repository audit based on fetched GitHub files, not a live runtime proof. No application code was modified.

Release evidence update: 2026-04-29.

This follow-up run executed the roadmap batches against the local checkout. Customer preorder moved from static `GATED/PARTIAL` evidence to default-off gated UI + adapter + test coverage for show, preview, replace, clear, idempotency and row-version handling. Customer Wave-1, deposit/billing panels, waiting-list, benefits/privacy, and smoke selectors were refreshed to the current Vietnamese UI copy and contract semantics. Staff day-1, KDS, admin, inventory, reporting, audit, conversation, and release lanes were verified with focused and full Vitest/build checks.

Runtime proof is still blocked by local services, not by the frontend test suite: Docker Desktop is not running for `composer bootstrap:booking`; backend HTTP `127.0.0.1:8000`, MySQL `127.0.0.1:3306`, and Redis `127.0.0.1:6379` were unreachable during `staff-web npm run preflight:local` and `staff-web npm run smoke:live`; customer live E2E credentials were not present for `customer-web npm run test:e2e:live`.

## Executive scores

| Scope | Coverage | Confidence | Reasoning |
|---|---:|---|---|
| `customer-web` vs backend customer contract | 72% | Medium | Core Wave-1 chain has strong evidence: auth/session, menu browse, availability, table holds, reservation create/list/detail, and self-service cancel/reschedule adapters/UI. Preorder is now default-off gated with unit/smoke evidence. Deferred areas remain partial or gated: deposit payment sessions, bill self-pay, waiting-list, benefits, privacy, data export. |
| `staff-web` vs backend staff/admin/operator contract | 58% | Medium | Day-1 operational shell is much stronger than full back-office: table board, reservations, ordering, checkout, refund, cashier, finance, waiting-list are mounted/wired. Full admin, inventory/procurement, promotions/loyalty master data, privacy review, reporting, KDS promotion, and conversations remain partial/gated. |
| Both frontends vs entire backend surface | 55% | Medium-low | Backend exposes a large contract-visible RestaurantPOS platform. The two frontends cover the day-1 booking/POS path reasonably, but not the full admin/procurement/reporting/conversation/privacy/release surface. |
| Day-1 MVP only | 85% | Medium | Customer Wave-1 and staff day-1 operator flow now have local test/build/smoke evidence, but live runtime proof is blocked by missing backend/MySQL/Redis and customer live credentials. Several money-moving row-version flows still need live runtime smoke. |
| Full RestaurantPOS 100% target | 55% | Medium-low | Full completion requires many gated/deferred and back-office domains to move from partial/contract-visible to real UI + API + guard + tests + live proof. |

## Domain scorecard

| Domain | customer-web | staff-web | Day-1 relevance | Main gap |
|---|---:|---:|---|---|
| Auth / Identity / RBAC | 90% | 88% | Yes | Need live refresh/session-expiry proof. |
| Customer menu/catalog | 86% | N/A | Yes customer | Preorder preview is covered behind default-off rollout; live proof still pending. |
| Table availability | 88% | N/A | Yes customer | Runtime UAT availability proof not run. |
| Table holds | 86% | N/A | Yes customer | Expired/stale row_version proof should be rerun. |
| Reservation create/list/detail | 84% | 72% | Yes | Staff assignment/move/status runtime proof; customer cancel/reschedule not day-1 promise. |
| Customer preorder | 70% | N/A | No | Default-off gated replace/clear flow and tests are in place; live UAT proof still pending. |
| Customer deposit/self-pay | 62% | 45% | Not day-1 customer payment | Provider posture/live proof missing. |
| Bill/active order/customer self-pay | 58% | 76% | Staff settlement yes; customer self-pay no | Customer bill self-pay disabled by default; provider/webhook proof missing. |
| Waiting list | 42% | 70% | Staff manual yes; customer wave-2 | Customer gated; staff/customer coordination and realtime proof incomplete. |
| Staff table board/reservation handling | N/A | 78% | Yes staff | Stale-write and branch/capability runtime proof. |
| Walk-in/service sessions | N/A | 72% | Yes staff | Exact form/state proof not fully inspected. |
| Staff ordering/active order | 30% read-only customer active order | 76% | Yes staff | Add item/order live row_version proof. |
| Order item lifecycle | N/A | 55% | Yes staff | Edit/status controls and stale-write tests need completion. |
| Checkout/settlement/finalize | customer self-pay gated | 72% | Yes staff | Cashier-shift handoff + money-moving live proof. |
| Refund/refund-cancel | N/A | 68% | Yes staff | Refund-cancel and reconciliation proof. |
| Cashier shift | N/A | 74% | Yes staff | Shift close totals and checkout integration proof. |
| Finance invoice/reconciliation | N/A | 60% | Yes finance review | Invoice issue/export incomplete/unknown. |
| Kitchen/KDS | N/A | 46% | No mutation day-1 | Feature-gated; mutation proof missing. |
| Admin settings/branch/table/zones | N/A | 48% | No | Full CRUD/import/export/row_version not complete. |
| Menu admin | N/A | 50% | No | Update/import/export/price history partial. |
| Inventory/procurement | N/A | 38% | No | Inventory uplift gated; PO/receipt/recipe flows incomplete. |
| Promotions/loyalty | 40% | 35% | No customer | Gated/partial; admin master data missing. |
| Conversations | N/A | 40% | No | Inbox feature off day-1; workflow actions need proof. |
| Notifications/outbox | Indirect only | Indirect only | Backend gate | No direct FE outbox visibility. |
| Privacy/data export/audit | 35% | 42% | No customer | Customer/admin privacy lifecycle incomplete/gated. |
| Reporting | N/A | 42% | No | Reporting pages/exports/snapshot rebuild partial. |
| Master data import/export | N/A | 45% | No | Subset of domains only. |
| Ops health/metrics/release gates | 35% | 35% | Backend gate | Detailed health/metrics UI partial/missing. |

## What is already strong

1. Customer booking core has real SDK client/session/idempotency evidence and UI for availability, holds, and reservation creation/review.
2. Customer reservation detail already composes deposit, billing, preorder, and benefits panels, so the extension points exist.
3. Staff web has a clear workspace architecture and mounted day-1 route tree for ops, kitchen, and admin.
4. Staff API helper centralizes `X-Staff-Key` and idempotency options, reducing duplicate header bugs.
5. Backend capability map is explicit and broad enough to drive UI guards.

## What is not safe to call complete

1. Customer self-pay: deposit/bill payment sessions are contract-visible but provider/runtime conditional.
2. Kitchen mutations: backend exposes them, but feature flag says day-1 mutation rollout is held back.
3. Inventory uplift: backend/admin routes exist, but production-like default is off and UI coverage is partial.
4. Conversation inbox: mounted/adapter evidence exists, but feature flag holds it back from day-1.
5. Admin/master-data breadth: many backend admin routes exceed the inspected staff UI completeness.
6. Runtime proof: local build/unit/smoke checks were executed, but live backend/MySQL/Redis and customer live credential proof are still blocked.

## Top 10 gaps by release risk

1. Staff checkout/finalize/refund still needs end-to-end live proof with active cashier shift and stale `row_version` recovery.
2. Customer deposit/bill payment sessions need provider posture, disabled-state policy, and real/simulated-provider separation.
3. Order item edit/status lifecycle needs complete browser UI + row_version/idempotency conflict coverage.
4. Admin settings/table/branch/kitchen-routing CRUD needs row_version/idempotency-safe forms and tests.
5. Inventory/procurement UI needs completion for recipes, supplier/PO updates, receipts, and movements under `inventory.uplift` gate.
6. Customer preorder now has default-off gated replace/clear cart flow, row_version/idempotency tests, and UI coverage; it still needs live UAT proof before rollout.
7. Customer + staff waiting-list coordination needs gated customer owner actions, staff manual notify/seat proof, and change-feed UX.
8. Kitchen/KDS promotion needs feature-gated station assignment, ticket mutation row_version, and live KDS proof.
9. Promotions/loyalty admin and customer/staff benefits need full row_version-safe wallet/reservation/master-data flows.
10. Privacy/data export/audit/reporting/master-data/ops-health surfaces need full UI, access control, and tests before claiming 100% backend coverage.

## Verification status

Performed after the static audit: `composer api:artifacts`, `customer-web npm run sync:contracts`, customer lint/typecheck/full Vitest/build/Playwright smoke, staff integrity/build/full Vitest, and focused staff/customer domain suites from batches A-I.

Blocked in this environment: `composer bootstrap:booking` because Docker Desktop was unavailable; staff local preflight/live smoke because backend HTTP, MySQL, and Redis were not running; customer live E2E because `CUSTOMER_WEB_LIVE_IDENTIFIER` and `CUSTOMER_WEB_LIVE_PASSWORD` were missing. Payment provider/webhook proof remains out of scope until live runtime is available.
