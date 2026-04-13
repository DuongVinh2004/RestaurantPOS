# Backend + Staff Web Completion Roadmap

## Snapshot

As of 2026-04-08, the repo already has a strong production-lean base:

- Backend:
  - SQL-first bootstrap via `composer bootstrap:booking`
  - release gates via `booking:doctor`, `booking:deploy-check`, `booking:release-manifest`
  - frozen OpenAPI + generated consumer artifacts
  - canonical UAT manifest at `storage/app/uat/scenario-pack.json`
  - harness coverage for split-web auth and golden flows
- Staff web:
  - working auth/session shell
  - board, orders, checkout/refund, cashier shift, conversations
  - generated SDK integration
  - `npm run test`, `npm run build`, and `npm run smoke:live`

Current completion gap is no longer "build the whole backend". The gap is:

1. finish the operator-ready integration surface between backend and `staff-web`
2. bring missing staff-web domains online in repo priority order
3. turn preview, smoke, deploy, and regression signals into a repeatable release loop

## 2026-04-12 Revision

This section supersedes the older Batch 2-7 plan below when planning the next user-facing batches.

Repo reality on 2026-04-12:

- the active `staff-web` shell already mounts `dashboard`, `tables`, `reservations`, `orders`, `kitchen`, `checkout`, `waiting-list`, `cashier-shift`, `finance-review`, `conversations`, `audit-trail`, and `reporting`
- `inventory`, `settings`, standalone `refunds`, and standalone `settlement` remain intentionally outside the current route tree
- local runtime proof is currently infrastructure-blocked, not purely code-blocked
  - `npm run smoke:live` fails immediately when `http://127.0.0.1:8000/api/v1/health` is unavailable
  - `php artisan booking:doctor --json` is red when MySQL and Redis are not reachable
- the highest-value remaining work is now contract closure, operator handoff polish, and honest release/runbook alignment

### Batch 2 - Runtime Truth + Smoke Alignment

Intent:
- keep the batch plan anchored to the real repo and not the stale prompt pack
- make local smoke and release claims honest when backend HTTP, MySQL, Redis, or scheduler readiness is missing

Likely changed files:
- `docs/backend-staff-web-completion-roadmap.md`
- `staff-web/README.md`
- `staff-web/STAFF_WEB_SETUP.md`

Verification:
- `cd staff-web && npm run smoke:live`
- `php artisan booking:doctor --json`

Done when:
- batch planning explicitly distinguishes green test slices from red runtime prerequisites
- operators and reviewers can see whether a failure is code, backend HTTP reachability, or runtime dependency drift

Remaining risks:
- this batch does not by itself fix missing local services
- smoke evidence can still go stale if UAT manifests drift from live data

### Batch 3 - Active Scope Declaration

Intent:
- document the real mounted scope of the new shell and explicitly mark deferred surfaces
- stop the repo from pretending that inventory, settings, or standalone finance pages are still part of the active route tree

Likely changed files:
- `staff-web/README.md`
- `staff-web/STAFF_WEB_BACKEND_CONTRACTS.md`

Verification:
- `cd staff-web && npx vitest run src/app/router/navigation.test.ts`

Done when:
- active routes and deferred routes are both spelled out in staff-web docs
- operator docs no longer imply a standalone `/refunds`, `/settlement`, `/inventory`, or `/settings` shell path

Remaining risks:
- old historical docs lower in this roadmap still exist for reference and can confuse readers if quoted out of context

### Batch 4 - Finance Handoff Closure

Intent:
- close the finance-review to reservation-workspace handoff gap with real stale-write context
- expose reservation `row_version` across reconciliation and invoice envelopes so reopen flows keep mutation guards intact

Likely changed files:
- `app/Services/Staff/StaffFinancialReconciliationService.php`
- `tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php`
- `tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php`
- `staff-web/src/core/api/staff-api.ts`
- `staff-web/src/features/finance/FinanceReviewPage.tsx`
- `staff-web/src/features/finance/FinanceReviewPage.test.tsx`
- `staff-web/STAFF_WEB_BACKEND_CONTRACTS.md`

Verification:
- `php artisan test tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php`
- `cd staff-web && npx vitest run src/features/finance/FinanceReviewPage.test.tsx`

Done when:
- reconciliation list/detail and finance invoice envelopes return `reservation.row_version`
- finance review can reopen `/reservations` with `reservation_row_version` on the URL journey context

Remaining risks:
- this does not prove live payment or cashier runtime without MySQL, Redis, and backend HTTP up

### Batch 5 - Conversation -> Waiting Linkage

Intent:
- make conversation detail links land staff on the exact linked waiting-list entry
- preserve the queue item context instead of dumping the operator onto the default first row

Likely changed files:
- `staff-web/src/features/conversations/ConversationInboxPage.tsx`
- `staff-web/src/features/conversations/conversation-inbox.ts`
- `staff-web/src/features/conversations/conversation-inbox.test.ts`
- `staff-web/src/features/waiting/WaitingListPage.tsx`
- `staff-web/src/features/waiting/WaitingListPage.test.tsx`
- `docs/runbooks/staff-conversation-inbox.md`
- `staff-web/STAFF_WEB_BACKEND_CONTRACTS.md`

Verification:
- `cd staff-web && npx vitest run src/features/conversations/conversation-inbox.test.ts src/features/waiting/WaitingListPage.test.tsx`

Done when:
- conversation detail opens `/waiting-list?focus=<waiting_id>` for linked queue items
- waiting list respects and preserves that `focus` state through selection and local refresh

Remaining risks:
- this closes FE linkage only; it does not change backend branch-consistency enforcement for conversation links

### Batch 6 - Docs + Release Confidence Sync

Intent:
- align roadmap, setup, and contract docs with the current repo after the finance and conversation handoff fixes
- make local smoke evidence paths and active-shell assumptions explicit before the next release pass

Likely changed files:
- `docs/backend-staff-web-completion-roadmap.md`
- `staff-web/README.md`
- `staff-web/STAFF_WEB_SETUP.md`
- `staff-web/STAFF_WEB_BACKEND_CONTRACTS.md`
- `docs/runbooks/staff-conversation-inbox.md`

Verification:
- doc review against current mounted routes and latest targeted tests

Done when:
- the revised Batch 2-6 plan matches the repo as of 2026-04-12
- docs point to the active shell, current handoff contracts, and local smoke artifact behavior without stale route claims

Remaining risks:
- generated API artifacts still need a separate refresh batch if frontend-consumer bundles are being republished from this workspace

## Planning Rules

- Follow repo priority order from `AGENTS.md`.
- Keep batches narrow and reviewable.
- Protect shared seams:
  - `routes/api.php`
  - `config/booking.php`
  - `config/staff_capabilities.php`
  - `database/schema/mysql-schema.sql`
- Do not edit generated consumer artifacts directly.
- Regenerate FE contract artifacts from backend source of truth after contract changes.

## Plugin Lane

Use the plugins as a standing operating model, not as five equal tools for every task.

- `@github`
  - source of truth for issue, PR, review, CI, milestone, and merge workflow
  - should be used in every batch once this workspace is backed by a real Git repo
- `@build-web-apps`
  - primary plugin for `staff-web` UX, React structure, page flows, and frontend implementation review
  - should be used in batches that change operator workflows or page architecture
- `@vercel`
  - preview deployment, build logs, runtime logs, and feedback loop for `staff-web`
  - should be used from the first frontend integration batch onward
- `@sentry`
  - release regression and production/staging error triage
  - should become mandatory once preview/staging deploys exist and source maps are wired
- `@hugging-face`
  - keep out of the critical path until the core operator stack is complete
  - only use for later AI augmentation such as OCR, classification, recommendations, or forecasting

## Batch 0 - Enablement Lane

Intent:
- create the delivery loop needed to make the later batches efficient
- keep this lane small and parallel to Batch 1 instead of blocking core work

Likely changed files:
- `staff-web/.env.example`
- `staff-web/README.md`
- `staff-web/STAFF_WEB_SETUP.md`
- `staff-web/scripts/live-smoke.mjs`
- `docs/runbooks/booking-ci-cd-runbook.md`
- `docs/runbooks/booking-deploy-runbook.md`

Verification:
- `npm run test`
- `npm run build`
- `npm run smoke:live`
- `php artisan booking:harness:web-auth --json`

Plugin workflow:
- `@github`: create milestone + issue breakdown for Batches 1-6
- `@vercel`: connect `staff-web` preview deploys and verify build/runtime visibility
- `@sentry`: wire FE and BE staging error capture, release tags, and source maps
- `@build-web-apps`: review smoke script ergonomics and environment setup copy

Done when:
- each batch can land through a predictable issue -> PR -> preview -> smoke path
- preview deploys and runtime logs exist for `staff-web`
- Sentry is ready to catch regressions before limited rollout

Remaining risks:
- if the workspace remains non-Git, `@github` value stays limited
- if Vercel and Sentry are wired too late, later frontend batches lose fast feedback

## Batch 1 - Staff Bootstrap + Auth/RBAC Completion

Intent:
- finish the staff startup surface so the operator can log in once and immediately get branch, session, capability, and readiness context
- remove avoidable manual fallback from the initial operator path

Likely changed files:
- `routes/api/auth.php`
- `config/staff_capabilities.php`
- `config/cors.php`
- `config/api_artifacts.php`
- `app/Services/Auth`
- `app/Services/Harness`
- `staff-web/src/app/session.tsx`
- `staff-web/src/app/router.tsx`
- `staff-web/src/api/client.ts`
- `staff-web/src/components/Login.tsx`
- `staff-web/src/features/access/AccessPage.tsx`

Verification:
- `php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Feature/CorsContractTest.php`
- `php artisan booking:harness:web-auth --json`
- `php artisan booking:harness:fe-contract --json`
- `npm run test`

Plugin workflow:
- `@github`: issue + PR scope for auth/bootstrap contract
- `@build-web-apps`: review login/access/bootstrap UX and route gating
- `@vercel`: preview login and session restore/refresh behavior
- `@sentry`: confirm auth failures carry `request_id` and surface cleanly

Done when:
- login lands in an operator-ready shell with no hidden bootstrap steps
- FE gates only on granted capability state and explicit readiness data
- the bootstrap payload is documented and covered by harness tests

Remaining risks:
- touching `config/staff_capabilities.php` and auth routes can spill into other domains
- over-expanding the bootstrap payload can create a long-term contract burden

## Batch 2 - FOH + Waiting + Check-in Operator Loop

Intent:
- make the host/front-of-house loop fully usable from `staff-web`
- close the gap between read-only board visibility and real operator task completion

Likely changed files:
- `routes/api/staff_pos.php`
- `app/Services/Reservation`
- `app/Services/WaitingList`
- `app/Services/Staff`
- `staff-web/src/features/board/BoardPage.tsx`
- `staff-web/src/features/board/boardPolling.ts`
- `staff-web/src/api/client.ts`
- `staff-web/src/lib/conflicts.ts`

Verification:
- `php artisan test tests/Feature/Table tests/Feature/WaitingList tests/Feature/Staff/StaffTableBoard*.php tests/Feature/Staff/StaffCheckInFlowTest.php`
- `php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php`
- `npm run test`
- `npm run smoke:live`

Plugin workflow:
- `@github`: track FOH bugs by concrete operator step
- `@build-web-apps`: improve board layout, waiting actions, and stale-row-version UX
- `@vercel`: preview and validate polling/load behavior in a real browser session
- `@sentry`: watch for action-specific errors on check-in/notify/seat

Done when:
- board, waiting, and check-in cover the normal host workflow without operator guesswork
- stale row_version and idempotency failures are actionable, not opaque
- change-cursor polling stays stable under real preview usage

Remaining risks:
- `routes/api/staff_pos.php` is a shared seam
- FOH flows can still drift if branch-scope or hold conflict handling changes elsewhere

## Batch 3 - Order Lifecycle + Service Session Completion

Intent:
- complete the dine-in service-session flow from check-in to order mutation and bill snapshot
- reduce reliance on manual IDs and page-scoped lookup hacks

Likely changed files:
- `routes/api/staff_pos.php`
- `app/Services/Staff`
- `app/Services/Reservation`
- `staff-web/src/features/orders/OrdersPage.tsx`
- `staff-web/src/features/settlement/SettlementPage.tsx`
- `staff-web/src/api/client.ts`
- `staff-web/src/lib/idempotency.ts`
- `staff-web/src/lib/conflicts.ts`

Verification:
- `php artisan test tests/Feature/Staff/StaffOrder*.php tests/Feature/Staff/StaffCheckoutHttpFlowTest.php`
- `php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php`
- `npm run test`
- `npm run smoke:live`

Plugin workflow:
- `@github`: split service-session/order issues by mutation slice
- `@build-web-apps`: review order create/add-item/bill flow so it feels like one operator journey
- `@vercel`: preview the multi-step dine-in flow and inspect runtime logs
- `@sentry`: verify errors group cleanly by operator action and not by generic HTTP status

Done when:
- operator can check in, create order, add items, and reach bill snapshot without manual state reconstruction
- canonical lookup sources beat manual `reservation_id` and `order_id` for normal cases
- row_version sourcing is stable across reloads

Remaining risks:
- service-session and order state still depend on tight backend invariants
- broad changes under `app/Services/Staff` can collide with later kitchen and finance work

## Batch 4 - Kitchen/KDS Web Surface

Intent:
- add the missing kitchen domain to `staff-web` in the same production-lean style as the existing operator surfaces
- keep scope tight: dispatch visibility and safe ticket actions first

Likely changed files:
- `routes/api/staff_pos.php`
- `app/Services/Kitchen`
- `staff-web/src/api/client.ts`
- `staff-web/src/app/router.tsx`
- `staff-web/src/app/sections.tsx`
- `staff-web/src/features/kitchen`

Verification:
- `php artisan test tests/Feature/Staff/*Kitchen*.php tests/Feature/Admin/*Kitchen*.php`
- `npm run test`
- `npm run build`

Plugin workflow:
- `@github`: drive KDS work as a bounded feature epic
- `@build-web-apps`: design station/ticket UI and action affordances
- `@vercel`: preview ticket flow with real payloads
- `@sentry`: watch for state-transition errors once preview/staging traffic exists

Done when:
- staff-web exposes the kitchen slice needed for day-1 operations
- ticket state transitions are safe and understandable
- feature-flagged kitchen behavior is visible and test-covered

Remaining risks:
- `routes/api/staff_pos.php` remains a shared seam
- kitchen state drift can surface if order item semantics are not held stable

## Batch 5 - Checkout + Refund + Cashier Go-Live Hardening

Intent:
- turn existing finance-facing pages from "already present" into "go-live safe"
- complete lookup ergonomics, mutation proof, and runtime smoke for the money paths

Likely changed files:
- `routes/api/staff_pos.php`
- `app/Services/Finance`
- `app/Services/PaymentIntegration`
- `app/Services/Staff`
- `staff-web/src/features/checkout/CheckoutPage.tsx`
- `staff-web/src/features/cashier/CashierShiftPage.tsx`
- `staff-web/src/api/client.ts`
- `staff-web/scripts/live-smoke.mjs`

Verification:
- `php artisan test tests/Feature/Staff/StaffCheckout*.php tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php tests/Feature/Payments`
- `php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php`
- `php artisan booking:doctor --json`
- `php artisan booking:deploy-check --mode=preflight --strict`
- `npm run test`
- `npm run smoke:live`

Plugin workflow:
- `@github`: attach every finance defect to one concrete mutation path
- `@build-web-apps`: reduce operator confusion around outstanding amount, refundability, and cashier context
- `@vercel`: inspect preview/staging runtime logs during mutation smoke
- `@sentry`: make finance regressions high-priority and release-scoped

Done when:
- settlement, refund, and cashier can be smoke-tested against live backend paths with bounded mutation gates
- lookup flows prefer canonical backend sources over manual IDs
- operator-facing errors clearly distinguish auth, capability, idempotency, and stale version failures

Remaining risks:
- finance work has the highest production sensitivity
- changes can interact with backend payment-provider and release-gate behavior

## Batch 6 - Inventory + Reporting + Admin Operations Surface

Intent:
- bring the remaining lower-priority but still operationally useful staff/branch surfaces into the web app
- favor read-first views and only add writes that remove obvious manual operator pain

Likely changed files:
- `routes/api/admin.php`
- `routes/api/staff_pos.php`
- `app/Services/Inventory`
- `app/Services/Reporting`
- `app/Services/Admin`
- `staff-web/src/app/router.tsx`
- `staff-web/src/app/sections.tsx`
- `staff-web/src/features/inventory`
- `staff-web/src/features/reporting`
- `staff-web/src/features/settings`

Verification:
- `php artisan test tests/Feature/Admin tests/Feature/Performance tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php`
- `php artisan booking:reporting-snapshots:rebuild --days=7 --json`
- `php artisan booking:deploy-check --mode=preflight --strict`
- `npm run test`
- `npm run build`

Plugin workflow:
- `@github`: break inventory, reporting, and settings into separate issues to avoid a mega-PR
- `@build-web-apps`: review information density and operator readability on dashboard/report screens
- `@vercel`: validate real preview load/performance on read-heavy pages
- `@sentry`: watch query-heavy or stale-data errors after preview deploys

Done when:
- branch lead workflows no longer depend on direct API/manual admin fallbacks for core reads
- reporting and inventory surfaces respect backend contract and snapshot freshness
- page architecture can scale without collapsing into one giant operator screen

Remaining risks:
- reporting and inventory can quietly expand scope
- shared seams under admin/staff routes need tight change control

## Batch 7 - Release Loop, Runbooks, and Final Integration

Intent:
- turn the repo into a repeatable release candidate loop for backend + `staff-web`
- freeze the launch process before any optional AI layer is attempted

Likely changed files:
- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-launch-readiness.md`
- `docs/runbooks/booking-release-packaging-runbook.md`
- `docs/runbooks/booking-ci-cd-runbook.md`
- `config/api_artifacts.php`
- `staff-web/README.md`
- `staff-web/STAFF_WEB_SETUP.md`
- `staff-web/scripts/live-smoke.mjs`

Verification:
- `composer api:artifacts`
- `php artisan booking:harness:fe-contract --json`
- `php artisan booking:harness:web-auth --json`
- `php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json`
- `php artisan booking:doctor --json`
- `php artisan booking:deploy-check --mode=preflight --strict`
- `php artisan booking:launch-readiness --target=staging --json`
- `npm run test`
- `npm run build`

Plugin workflow:
- `@github`: merge checklist, release PR template, and final integration tracking
- `@vercel`: final preview validation, deployment logs, and comment feedback loop
- `@sentry`: release watch, top-regression summary, and rollout/no-rollout signal
- `@build-web-apps`: final UX polish pass only after contract and smoke are stable

Done when:
- one documented path exists from local bootstrap to preview to staging evidence
- FE contract artifacts, backend readiness gates, and web smoke all agree
- release and rollback evidence is fast to collect

Remaining risks:
- without disciplined issue/PR ownership, late integration can still cause shared-file collisions
- launch-readiness can look green while browser-only UX regressions still exist unless preview review stays part of the gate

## Deferred Until Core Completion

Do not schedule these ahead of the seven batches above:

- AI augmentation for staff conversations, OCR, forecasting, or recommendations
- major design-system rewrite in `staff-web`
- broad backend refactors that are not directly tied to one batch outcome

`@hugging-face` should become active only after the operator stack is complete enough that AI is clearly an add-on and not a distraction from core go-live work.

## Recommended Execution Order

1. Batch 0 in parallel with Batch 1
2. Batch 1
3. Batch 2
4. Batch 3
5. Batch 4
6. Batch 5
7. Batch 6
8. Batch 7

This order keeps the repo aligned with the priority order in `AGENTS.md` while respecting the fact that some later-stage `staff-web` surfaces already exist and now need hardening rather than first-time scaffolding.

## Prompt Kit - User Batch Numbering

Use this section when you want to hand one batch to Codex, another agent, or a plugin-assisted workflow.

Important numbering note:

- the prompts below follow the user-facing numbering discussed in planning:
  - Batch 2 = FOH + service session + order lifecycle in one operator flow
  - Batch 3 = settlement + refund + cashier go-live hardening
  - Batch 4 = kitchen/KDS
  - Batch 5 = inventory + reporting + minimal admin operations
  - Batch 6 = release loop + preview + smoke + release evidence
  - Batch 7 = AI augmentation
- this means user-facing Batch 2 is broader than the internal roadmap split where FOH and order lifecycle can still be implemented in smaller reviewable slices

### Shared Prefix

Copy this prefix above any batch prompt.

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc tuan thu AGENTS.md cua repo:
- Read existing code before changing anything.
- Prefer extending current foundations over large rewrites.
- Keep controllers thin.
- Put business logic in services/domain logic.
- Add or update tests for every meaningful change.
- Protect production safety: transactions, idempotency, locking, authorization, audit.
- Avoid cosmetic or speculative changes.
- Work in small reviewable batches.

Project priority order phai duoc ton trong:
1. Auth / Identity / RBAC
2. Walk-in + service session + dine-in POS flow
3. Order lifecycle
4. Kitchen / KDS
5. Checkout / payment / refund / cashier shift
6. Inventory basic but useful
7. Reporting / ops / go-live hardening

Shared seams can than trong:
- routes/api.php
- config/booking.php
- config/staff_capabilities.php
- database/schema/mysql-schema.sql

Operational rules:
- Khong edit generated consumer artifacts bang tay.
- Neu contract thay doi, regenerate artifact tu backend source of truth.
- Neu runtime-sensitive, phai phan biet test xanh voi runtime xanh.
- Khong claim smoke/runtime gate neu MySQL/Redis/scheduler chua san sang.

Plugin lane phai duoc ap dung hop ly:
- @github cho issue / PR / milestone / review / checks
- @build-web-apps cho UX, React flow, page architecture, frontend implementation review
- @vercel cho preview deploy, build logs, runtime logs, toolbar feedback
- @sentry cho regression triage sau deploy
- @hugging-face chi duoc dung o Batch 7, khong chen vao critical path truoc do

Yeu cau thuc thi:
- Khong dung lai o phan tich; hay thuc su implement trong scope duoc giao.
- Neu scope qua lon cho mot patch, tu chia thanh 2-4 sub-batches reviewable nhung van tiep tuc den khi xong batch.
- Cuoi cung bao cao theo dung format:
  1. Intent
  2. Changed files
  3. Added/updated tests
  4. Remaining risks
```

### Batch 2 Prompt - FOH + Service Session + Order Lifecycle

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 2 cho backend + staff-web.

Muc tieu batch:
- Gom board, waiting, check-in, create order, add items, bill snapshot thanh mot flow thao tac lien mach trong staff-web.
- Khong de operator phai reconstruct state bang tay giua nhieu page hoac manual ID trong normal path.
- Khep cac khe ho UX va contract quanh row_version, Idempotency-Key, stale data, polling/change cursor, va canonical lookup sources.

Business outcome:
- Host/cashier phai xu ly duoc dine-in day-1 flow hoan toan tu staff-web cho normal cases.

Implementation expectations:
1. Review current BoardPage, OrdersPage, session shell, SDK wrappers, realtime polling, check-in path, create-order path, add-item path, va bill-snapshot path.
2. Xac dinh state transfer nao dang page-scoped, manual, hoac de stale data.
3. Bien no thanh mot operator journey mach lac:
   - board / waiting selection
   - reservation or table context resolution
   - check-in neu can
   - create order
   - add items
   - load/reload order detail
   - bill snapshot
4. Uu tien canonical backend sources hon manual `reservation_id`, `order_id`, `table_id` trong normal path.
5. Lam cho stale row_version va idempotency failures surfacing ro rang, action-oriented, khong generic.
6. Giu polling/change cursor stable, visibility-aware, va khong refetch full slices vo toi va.
7. Neu can, tach implementation thanh 2-3 patch-sized sub-batches, nhung van chot du user-facing exit cua Batch 2.

Likely backend areas:
- routes/api/staff_pos.php
- app/Services/Reservation
- app/Services/WaitingList
- app/Services/Staff
- app/Services/Order

Likely frontend areas:
- staff-web/src/features/board/*
- staff-web/src/features/orders/*
- staff-web/src/features/settlement/*
- staff-web/src/app/session.tsx
- staff-web/src/app/router.tsx
- staff-web/src/api/client.ts
- staff-web/src/lib/conflicts.ts
- staff-web/src/lib/idempotency.ts

Verification floor:
- php artisan test tests/Feature/Table tests/Feature/WaitingList tests/Feature/Staff/StaffTableBoard*.php tests/Feature/Staff/StaffCheckInFlowTest.php tests/Feature/Staff/StaffOrder*.php
- php artisan booking:harness:web-auth --json
- php artisan booking:harness:fe-contract --json
- npm run test
- npm run build
- npm run smoke:live

Plugin workflow:
- @github: mo issue hoac sub-issues theo operator steps, khong theo file structure
- @build-web-apps: review operator flow, page transitions, stale-state UX, error handling, information density
- @vercel: validate preview load, realtime behavior, and multi-step dine-in flow in browser
- @sentry: check grouping cho check-in / create-order / add-item / bill-snapshot errors sau preview

Definition of done:
- Board, waiting, check-in, create order, add items, va bill snapshot chay thanh mot flow operator-ready.
- Normal path khong can manual ID.
- Row_version, idempotency, stale data, va cursor drift duoc xu ly ro rang trong UI.
- Smoke read-only va cac flow normal xac nhan duoc contract Batch 2.

Out of scope:
- Khong sang settlement/refund/cashier go-live hardening ngoai nhung gi can de bill snapshot flow hoan chinh.
- Khong mo rong scope sang kitchen, inventory, reporting, hay admin.
```

### Batch 3 Prompt - Settlement + Refund + Cashier Go-Live Hardening

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 3 cho backend + staff-web.

Muc tieu batch:
- Nang settlement, refund, va cashier tu “da co UI” len “du go-live”.
- Lam ro lookup lich su, reconciliation ergonomics, va mutation-safe smoke cho finalize / refund / close shift.
- Bien staff-web/scripts/live-smoke.mjs thanh gate that cho preview/staging, it nhat cho read-only va mot so mutation duoc phep.

Business outcome:
- Settlement, refund, cashier co bang chung smoke that, co staging-safe write paths, va operator khong bi lac trong lookup lich su.

Implementation expectations:
1. Review SettlementPage, RefundsPage, CashierPage, live-smoke.mjs, SDK wrappers, va backend finance services.
2. Giam su phu thuoc vao manual IDs trong historical lookup, nhung van giu explicit fallback cho case khong tranh duoc.
3. Make mutation UX safe:
   - row_version source phai ro
   - Idempotency-Key phai on by default cho mutation wrappers
   - operator-facing messages phai distinguish auth, capability, stale version, validation, va finance invariant failures
4. Improve reconciliation ergonomics:
   - current/open/show/close shift context
   - preview before finalize/refund
   - cash / outstanding / refundability cues ro rang
5. Upgrade live smoke:
   - read-only gate stable
   - cho phep mot so mutation co gate ro rang bang env / manifest
   - output fail details phai phuc vu release decision
6. Neu can, chia thanh sub-batches theo `settlement`, `refund`, `cashier`, `smoke gate`, nhung van dong mot Batch 3 report cuoi.

Likely backend areas:
- routes/api/staff_pos.php
- app/Services/Finance
- app/Services/PaymentIntegration
- app/Services/Staff
- app/Services/Checkout

Likely frontend areas:
- staff-web/src/features/checkout/*
- staff-web/src/features/cashier/*
- staff-web/src/api/client.ts
- staff-web/scripts/live-smoke.mjs

Verification floor:
- php artisan test tests/Feature/Staff/StaffCheckout*.php tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php tests/Feature/Payments
- php artisan booking:doctor --json
- php artisan booking:deploy-check --mode=preflight --strict
- npm run test
- npm run build
- npm run smoke:live

Plugin workflow:
- @github: track each finance defect or enhancement by mutation path and business risk
- @build-web-apps: review clarity of outstanding amount, refundability, cashier status, and reconciliation UI
- @vercel: use preview/staging logs to inspect finalize/refund/close-shift failures
- @sentry: group finance regressions by action and release, not generic HTTP bucket

Definition of done:
- Settlement/refund/cashier flows are operator-clear and mutation-safe.
- Live smoke becomes a real gate with stable read-only mode and controlled mutation mode.
- Preview/staging can prove finalize/refund/close-shift behavior without unsafe ad hoc testing.

Out of scope:
- Khong redesign payment-provider architecture.
- Khong mo rong kitchen, inventory, reporting, hay AI.
```

### Batch 4 Prompt - Kitchen / KDS Web Surface

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 4 cho backend + staff-web.

Muc tieu batch:
- Dua kitchen/KDS vao staff-web theo huong production-lean.
- Neu can, bat dau read-first, sau do moi them fire / bump / recall.
- Giu scope gon: station list, ticket state, action an toan, khong mo rong thanh kitchen suite lon.

Business outcome:
- Core kitchen operations khong con phu thuoc Postman hoac admin fallback.

Implementation expectations:
1. Review kitchen routes, kitchen services, station routing, ticket state, feature flags, va current admin/staff kitchen payloads.
2. Design a narrow staff-web kitchen surface:
   - station list
   - ticket list/detail
   - state badges
   - safe actions only
3. If action mutations are added, must protect:
   - allowed transitions
   - idempotency where needed
   - stale-state reload behavior
   - feature-flag off path
4. Prefer read-first completion over broad mutation expansion.
5. Keep order item semantics and kitchen ticket semantics aligned.

Likely backend areas:
- routes/api/staff_pos.php
- app/Services/Kitchen
- app/Http/Controllers/Api/Staff/*Kitchen*
- app/Http/Controllers/Api/Admin/*Kitchen*

Likely frontend areas:
- staff-web/src/app/router.tsx
- staff-web/src/app/sections.tsx
- staff-web/src/api/client.ts
- staff-web/src/features/kitchen/*

Verification floor:
- php artisan test tests/Feature/Staff/*Kitchen*.php tests/Feature/Admin/*Kitchen*.php
- php artisan booking:harness:fe-contract --json
- npm run test
- npm run build
- npm run smoke:live

Plugin workflow:
- @github: bounded epic with separate tasks for read surface, safe actions, and feature-flag behavior
- @build-web-apps: review ticket density, station scanning ergonomics, and action affordances
- @vercel: validate kitchen preview behavior with realistic payloads
- @sentry: watch kitchen state-transition errors by station/action after deploy

Definition of done:
- Kitchen surface exists in staff-web and covers day-1 KDS visibility.
- Safe ticket actions work without admin fallback.
- Feature-flag behavior and transition errors are test-covered and operator-readable.

Out of scope:
- Khong mo rong thanh inventory production planning, recipe management, hay large kitchen analytics.
- Khong redesign order lifecycle domain just to serve kitchen UI.
```

### Batch 5 Prompt - Inventory + Reporting + Minimal Admin Operations

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 5 cho backend + staff-web.

Muc tieu batch:
- Dua inventory + reporting + admin toi thieu len web theo huong read-first.
- Chi them write paths nho neu chung giai quyet ro rang viec operator phai nhay qua backend thu cong.
- Giup staff lead hoac branch manager nhin va xu ly duoc chi so/nguc canh quan trong tu staff-web.

Business outcome:
- Branch lead workflows khong con phu thuoc API/manual fallback cho core operational reads.

Implementation expectations:
1. Review current admin, inventory, reporting routes/contracts va xac dinh surface toi thieu co gia tri van hanh that.
2. Uu tien cac read-first screens:
   - stock snapshot
   - receiving summary
   - daily sales / operations
   - branch state / settings can thiet
3. Chi them write path nho neu:
   - no remove manual backend step ro rang
   - contract da du an toan
   - khong mo scope thanh admin suite rong
4. Watch performance and query shape on read-heavy pages.
5. Keep page architecture coherent; khong de tat ca collapse vao mot mega dashboard.

Likely backend areas:
- routes/api/admin.php
- routes/api/staff_pos.php
- app/Services/Inventory
- app/Services/Reporting
- app/Services/Admin

Likely frontend areas:
- staff-web/src/app/router.tsx
- staff-web/src/app/sections.tsx
- staff-web/src/features/inventory/*
- staff-web/src/features/reporting/*
- staff-web/src/features/settings/*

Verification floor:
- php artisan test tests/Feature/Admin tests/Feature/Performance tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php
- php artisan booking:reporting-snapshots:rebuild --days=7 --json
- php artisan booking:deploy-check --mode=preflight --strict
- npm run test
- npm run build

Plugin workflow:
- @github: split into issue set by domain, not one mega-PR
- @build-web-apps: review information density, comparative layouts, filters, and operator readability
- @vercel: validate read-heavy preview pages for load/performance/runtime log noise
- @sentry: watch query-heavy or stale-data issues after preview deploys

Definition of done:
- Staff lead / branch manager can access core inventory, reporting, and branch-state context from web.
- Read paths are useful, accurate, and contract-safe.
- Any added writes are narrow, intentional, and justified by operator pain removal.

Out of scope:
- Khong build full admin console replacement.
- Khong introduce speculative analytics or broad BI features.
```

### Batch 6 Prompt - Release Loop + Preview + Smoke + Release Evidence

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 6 cho backend + staff-web.

Muc tieu batch:
- Khoa release loop cho backend + staff-web.
- Tao mot pipeline duy nhat cho regenerate contract artifacts, backend harness, staff-web test/build, live smoke, preview deploy, va release evidence.
- Hoan tat runbook rollout/rollback thay vi tiep tuc them feature.

Business outcome:
- Moi lan ra ban moi deu co preview, smoke, release evidence, va rollback path ro rang.

Implementation expectations:
1. Review current artifact generation, harness commands, doctor/deploy-check/release-manifest, live smoke, preview expectations, va runbooks.
2. Bien no thanh mot release loop ro rang:
   - contract artifacts
   - backend verification
   - frontend verification
   - preview deploy
   - smoke
   - release evidence
   - rollback instructions
3. Lam cho `staff-web/scripts/live-smoke.mjs` phu hop hon voi preview/staging gate.
4. Dong bo docs/runbooks voi command that, env that, artifact paths that.
5. Khong giau release-contract changes trong unrelated domain edits.

Likely areas:
- docs/runbooks/api-consumer-artifacts.md
- docs/runbooks/booking-launch-readiness.md
- docs/runbooks/booking-release-packaging-runbook.md
- docs/runbooks/booking-ci-cd-runbook.md
- config/api_artifacts.php
- staff-web/scripts/live-smoke.mjs
- staff-web/README.md
- staff-web/STAFF_WEB_SETUP.md

Verification floor:
- composer api:artifacts
- php artisan booking:harness:fe-contract --json
- php artisan booking:harness:web-auth --json
- php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json
- php artisan booking:doctor --json
- php artisan booking:deploy-check --mode=preflight --strict
- php artisan booking:launch-readiness --target=staging --json
- npm run test
- npm run build
- npm run smoke:live

Plugin workflow:
- @github: release issue template, merge checklist, PR checklist, final integration tracking
- @vercel: preview deploy validation, build/runtime logs, comment threads, and release candidate review surface
- @sentry: release watch, regression summary, and rollout/no-rollout signal
- @build-web-apps: final UX polish chi sau khi contract va smoke gates da on dinh

Definition of done:
- Co mot duong documented tu local bootstrap den preview/staging evidence.
- FE contract artifacts, backend readiness gates, preview deploy, va smoke deu agree.
- Rollout / rollback runbook ngan gon, exact, va executable.

Out of scope:
- Khong them feature domain moi.
- Khong chuyen sang mot CI/CD platform moi neu khong bat buoc.
```

### Batch 7 Prompt - AI Augmentation Only After Core Completion

```text
[PREPEND SHARED PREFIX]

Task: Hoan tat Batch 7 cho backend + staff-web.

Dieu kien tien quyet:
- Batch 1-6 phai da on dinh.
- AI la lop ho tro, khong duoc chan critical path van hanh.

Muc tieu batch:
- Explore va implement AI augmentation nho, ro ROI, khong pha vo core operator stack.
- Candidate examples:
  - conversation summarization
  - anomaly hints
  - menu / OCR support
  - demand forecasting

Business outcome:
- AI giup operator nhanh hon hoac nhin ra van de som hon, nhung khong tro thanh dependency de van hanh co ban hoat dong.

Implementation expectations:
1. Chon 1 use case AI nho, bounded, co output ro rang, co fallback khi model/service khong san sang.
2. Khong chen AI vao auth, FOH core, settlement, refund, cashier, hay release loop theo kieu bat buoc phai co AI moi dung duoc.
3. Define:
   - input contract
   - output contract
   - failure fallback
   - latency/cost/risk constraints
4. Prefer read-assist / recommendation-first before autonomous mutation.
5. Bao dam observability va release-safety cho AI path.

Likely areas:
- staff-web/src/features/conversations/*
- staff-web/src/features/reporting/*
- app/Services/Conversation
- app/Services/Reporting
- app/Services/AI or equivalent bounded integration layer

Verification floor:
- targeted backend tests for the chosen AI integration
- frontend tests for fallback and rendering behavior
- runtime smoke for the chosen AI slice in non-blocking mode
- release note / runbook update for model, cost, and fallback behavior

Plugin workflow:
- @hugging-face: chon model / dataset / evaluation direction / inference tradeoff
- @build-web-apps: integrate AI output vao UI theo huong operator-first, khong noisy
- @vercel: preview behavior, latency/log visibility, deployment safety
- @sentry: monitor AI-path regressions and fallback frequency
- @github: capture evaluation notes, prompt/version history, and rollout plan

Definition of done:
- AI feature is optional, bounded, and observable.
- Fallback path khong lam operator bi block.
- Cost, latency, and release risk duoc doc/measure ro rang.

Out of scope:
- Khong build full agentic system tranh chan voi core product.
- Khong dua AI vao checkout/refund/cashier mutation authorization path.
```

## Continuation After Batch 7 (2026-04-09)

The original Batches 0-7 established the core backend + `staff-web` feature surface. The remaining gap is no longer "build missing pages". The remaining gap is proving that the stack is truly production-complete:

1. runtime-green, not only test-green
2. preview/staging evidence with real diagnostics
3. external-provider rehearsal for money and notification paths
4. contention/performance/security hardening on the highest-risk seams
5. a limited-production launch pack with exact rollback evidence

Known blockers at the time this continuation was written:

- local runtime is not green yet:
  - MySQL `127.0.0.1:3307` unavailable
  - Redis `127.0.0.1:6379` unavailable
  - backend HTTP `http://127.0.0.1:8000` unavailable
- `npm run smoke:live` is now fail-safe and truthful, but still blocked until backend HTTP is reachable
- preview lane remains provider-agnostic unless a real preview deployment platform is linked
- limited-production manual evidence still needs explicit replay/rehearsal artifacts

Completion bar for this continuation:

- test-green
- contract-green
- build-green
- runtime-green
- launch-readiness green for staging
- launch-readiness green for limited-production with manual evidence

Do not reopen completed Batches 1-7 unless a new blocker is found in the course of Batches 8-13.

### Batch 8 - Runtime Baseline Closure

Status on 2026-04-09:
- repo-local hardening complete; remaining work is external runtime bring-up

Done:
- `notifications:outbox-health`, `booking:deploy-check`, and `booking:launch-readiness` now emit structured blocker evidence instead of raw exception crashes when MySQL/Redis are unavailable
- `booking:launch-readiness` short-circuits heavy downstream suites once `booking:doctor` already proves runtime prerequisites are missing
- `staff-web` live smoke remains fail-safe and read-only by default, with clearer startup/runtime blocker output
- runtime/readiness docs were updated to distinguish blocker evidence from true `runtime-green`

Current external blockers in this workspace:
- MySQL `127.0.0.1:3307` unavailable
- Redis `127.0.0.1:6379` unavailable
- backend HTTP `http://127.0.0.1:8000` unavailable

Keep using this proof set when those services are brought up:
- `composer bootstrap:booking`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:deploy-check --mode=preflight --strict --json`
- `php artisan booking:launch-readiness --target=staging --json`
- `cd staff-web && npm run smoke:live`

### Batch 9 - Preview, Observability, and Staging Evidence Closure

Status on 2026-04-09:
- repo-local hardening complete; remaining work is external preview / observability bring-up

Done:
- `booking:release-loop` now records explicit preview provider/status/linkage reason instead of collapsing missing platform setup into a generic preview skip
- release-loop and `staff-web` smoke artifacts now record preview status separately from preview label/URL
- release-loop now records explicit Sentry observability configuration state and release-tag candidate metadata, and the runbooks explain that missing preview/Sentry setup is not preview-proof
- `booking:release-loop` console output now surfaces preview status, observability status, and release-tag candidate in one place

Current external blockers in this workspace:
- no linked preview project metadata (`.vercel/project.json` missing) and no explicit `--preview-url` or `--preview-command`
- Sentry release/runtime env missing: `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT`
- runtime prerequisites from Batch 8 remain unavailable locally: MySQL `127.0.0.1:3307`, Redis `127.0.0.1:6379`, backend HTTP `http://127.0.0.1:8000`

Keep using this proof set when those services/platform creds are available:
- `php artisan booking:release-loop --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --json`
- preview deploy command or preview URL verification on the chosen platform
- staging runtime logs and release-tag evidence for the same candidate build
- `cd staff-web && npm run smoke:live`

### Batch 10 - External Integration Rehearsal (Payments + Notifications)

Status on 2026-04-09:
- repo-local hardening complete; remaining work is external provider rehearsal

Done:
- limited-production launch-readiness now treats real notification delivery rehearsal as a first-class manual gate alongside real payment-provider callback rehearsal
- the canonical manual evidence fixture, runbooks, and alerting guidance now require `notification_provider_external_e2e` instead of implying outbox health alone is enough
- staging now surfaces missing external notification rehearsal as a warning, while limited-production blocks until that evidence exists

Current external blockers in this workspace:
- no real payment-provider sandbox/target credentials were available to record `payment_provider_external_e2e`
- no real email-provider or target mailer rehearsal evidence was available to record `notification_provider_external_e2e`
- runtime prerequisites from Batch 8 remain unavailable locally: MySQL `127.0.0.1:3307`, Redis `127.0.0.1:6379`, backend HTTP `http://127.0.0.1:8000`

Keep using this proof set when those external systems are available:
- `php artisan test tests/Feature/Payments tests/Feature/Staff/StaffCheckout*.php tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php`
- `php artisan notifications:outbox-health --json`
- provider sandbox/manual callback rehearsal evidence for payments and real email delivery
- `php artisan booking:launch-readiness --target=limited-production --manual-evidence=<path> --json`

### Batch 11 - Concurrency, Performance, and Failure-Recovery Hardening

Status on 2026-04-09:
- repo-local hardening complete; remaining work is external staging/runtime performance rehearsal

Done:
- launch-readiness now treats the candidate-specific `booking:performance-verify` report as first-class rollout evidence instead of relying only on local query-budget tests
- the canonical manual evidence fixture and runbooks now require `performance_verification_report` for limited production and surface it as a staging warning when missing
- local concurrency/idempotency/query-budget coverage remains in place while the staging performance report is called out as the real runtime boundary

Current external blockers in this workspace:
- no candidate-specific `booking:performance-verify` staging report was captured for this environment
- operator-assisted staging probes in the performance matrix still depend on real infra windows and target URLs
- runtime prerequisites from Batch 8 remain unavailable locally: MySQL `127.0.0.1:3307`, Redis `127.0.0.1:6379`, backend HTTP `http://127.0.0.1:8000`

Keep using this proof set when those runtime/staging prerequisites are available:
- targeted `php artisan test` slices for stale-state/idempotency/concurrency cases
- `php artisan booking:core-ops-gate`
- `php artisan booking:round5-gate`
- `php artisan booking:performance-verify --profile=staging --run --base-url=<staging-url> --manifest-path=storage/app/uat/scenario-pack.json --promote-baseline`
- manual `concurrency_rehearsal` evidence if still required for limited rollout

### Batch 12 - Security, Capability, Audit, and Rollout Final Pass

Status on 2026-04-09:
- repo-local hardening complete; no new security/audit blockers were found beyond the existing runtime/platform gaps

Done:
- auth/session/CORS/feature-flag regression slices were rerun against the current stack
- representative capability guard coverage now includes the newer staff reporting, staff kitchen, and admin kitchen surfaces
- customer/staff/admin boundary tests continue to prove that wrong-actor access is rejected before capability fallback can blur the surface

Current external blockers in this workspace:
- runtime/platform blockers from earlier batches remain unresolved locally: MySQL `127.0.0.1:3307`, Redis `127.0.0.1:6379`, backend HTTP `http://127.0.0.1:8000`
- preview/observability providers from Batch 9 remain unavailable in this workspace

Keep using this proof set when those runtime/platform prerequisites are available:
- `php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Feature/CorsContractTest.php`
- capability/route-surface tests for the touched domains
- feature-flag regression tests
- `php artisan booking:doctor --json`

### Batch 13 - Limited-Production Launch Pack and Go/No-Go Closure

Status on 2026-04-09:
- repo-local hardening complete; remaining work is live runtime/platform/manual evidence only

Done:
- launch-readiness artifacts now enumerate all active manual evidence sources in both JSON and markdown, including the evidence file path plus performed-by/performed-at context
- limited-production runbooks now require the full five-check manual evidence chain: `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`
- rollback guidance is now executable against the real immutable package contract, and release-loop artifacts no longer imply that `latest-package.json` alone is a safe rollback selector
- the full contract/build/runtime/preview/manual evidence floor was rerun from the current candidate, producing fresh manifest, package, launch-readiness, release-loop, and `staff-web` evidence artifacts

Current external blockers in this workspace:
- MySQL `127.0.0.1:3307` unavailable
- Redis `127.0.0.1:6379` unavailable
- backend HTTP `http://127.0.0.1:8000` unavailable
- no linked preview project metadata (`.vercel/project.json` missing) and no explicit preview URL/command for the candidate build
- Sentry release/runtime env missing: `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT`
- no real operator-captured candidate evidence for `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, or `concurrency_rehearsal`; only the canonical test fixture is present locally

Keep using this proof set when those external/runtime prerequisites are available:
- `composer api:artifacts`
- `php artisan booking:release-manifest --verify-frozen --json`
- `php artisan booking:package-release --verify-frozen --json`
- `php artisan booking:release-loop --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --json`
- `php artisan booking:launch-readiness --target=limited-production --manual-evidence=<real-manual-evidence.json> --json`
- `cd staff-web && npm run test && npm run build && npm run smoke:live`
- preview deploy evidence plus runtime logs/release-tag evidence from the chosen platform

## Master Execution Prompt - Historical Reference

The continuation roadmap above is now closed in this workspace except for the explicit external/runtime blockers called out in Batches 8-13. Keep the prompt below only as a rerun template for a future candidate; it is no longer the current state snapshot.

Use this prompt when you want another model to execute the continuation roadmap above.

```text
You are working in:

C:\Users\Duong Vinh\RestaurantPOS-Laravel

Read and obey:
1. AGENTS.md at the repo root
2. docs/backend-staff-web-completion-roadmap.md

Mission:
Continue the backend + staff-web completion effort from the continuation roadmap dated 2026-04-09.
Assume Batches 1-7 are already implemented.
Your job is to execute Batches 8-13 until the repo is genuinely complete or blocked by real external/runtime dependencies.

Non-negotiable rules:
- Do not stop at review or planning.
- If you find an incomplete area inside the active batch, patch it fully.
- Add/update tests for every meaningful change.
- Keep controllers thin and business logic in services/domain logic.
- Do not overbuild or reopen completed feature batches unless a concrete blocker is discovered.
- Treat backend, generated API artifacts, runbooks, release evidence, and staff-web as one integrated product surface.
- Distinguish clearly between:
  - test-green
  - contract-green
  - build-green
  - runtime-green
- Never claim runtime-green from unit/integration tests alone.
- If a real runtime prerequisite is missing, finish every non-runtime code/doc/test gap first, then report the exact blocker.
- Do not edit generated artifacts by hand when they should be regenerated from backend source of truth.
- After you fully complete a batch, compact the roadmap by removing or clearly marking the completed batch section so the file stays current.

Execution order:
1. Batch 8 - Runtime Baseline Closure
2. Batch 9 - Preview, Observability, and Staging Evidence Closure
3. Batch 10 - External Integration Rehearsal (Payments + Notifications)
4. Batch 11 - Concurrency, Performance, and Failure-Recovery Hardening
5. Batch 12 - Security, Capability, Audit, and Rollout Final Pass
6. Batch 13 - Limited-Production Launch Pack and Go/No-Go Closure

Batch discipline:
- Work one batch at a time.
- Finish the active batch before starting the next one unless a blocker forces a stop.
- Use the smallest correct set of project-local skills and available plugins for the active batch.
- Prefer targeted verification first; escalate only when shared seams, contracts, runtime, or release evidence are touched.
- If you touch docs/runbooks/operator behavior, update the owning doc in the same batch.

Priority review lens inside every batch:
1. Real bugs and operator regressions
2. Runtime truthfulness and release evidence accuracy
3. RBAC / branch scope / assignment scope / feature-flag safety
4. row_version / idempotency / concurrency / locking
5. Backend resource -> OpenAPI -> generated SDK -> staff-web consistency
6. Missing tests or overclaimed safety
7. Docs/runbook drift

Important files and areas:
- app/Services/**
- app/Http/Resources/**
- app/Http/Middleware/**
- config/feature_flags.php
- config/staff_capabilities.php
- config/booking_release.php
- routes/console/**
- routes/api/**
- docs/runbooks/**
- docs/backend-staff-web-completion-roadmap.md
- storage/app/booking_release/**
- build/api-consumer/**
- build/booking-release/**
- staff-web/src/**
- staff-web/scripts/live-smoke.mjs
- tests/Feature/**
- tests/Unit/**

Completion standard:
Do not declare the repo complete unless all of the following are true, except where impossible because of a real external dependency:
1. Code for the continuation batches is complete
2. Tests are updated and meaningful
3. Docs/runbooks match actual behavior
4. Contract artifacts are consistent
5. Release evidence is truthful
6. Remaining failures are true runtime/platform blockers, not repo drift

Required final report format for each batch:
1. Intent
2. Changed files
3. Added/updated tests
4. Remaining risks

Required final report format when you stop:

## Batches Completed
- one bullet per finished batch

## Remaining Batches
- one bullet per unfinished batch or `- None`

## Verification
- list every command you ran
- say pass/fail and what it proves

## Completion Judgment
- COMPLETE
- COMPLETE EXCEPT FOR RUNTIME BLOCKERS
- NOT COMPLETE

## Remaining Risks
- only real residual risks

Critical instruction:
Patch first, verify second, report last.
Do not end with analysis only if the workspace can still be improved.
```
