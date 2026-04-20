# Codex Accelerated Execution Roadmap

## Purpose

Tai lieu nay la roadmap thuc thi danh rieng cho Codex trong repo `C:\Users\Duong Vinh\RestaurantPOS-Laravel`.

Muc tieu:

- dua du an den limited-production launch nhanh nhat co the
- giu dung thu tu uu tien trong `AGENTS.md`
- khong overbuild them feature ngoai launch path
- bien cac gate hien co thanh execution contract ro rang cho Codex

Roadmap nay khong phai product roadmap.
Day la execution roadmap de Codex co the lam viec theo batch lon, ro gate, ro stop condition, va merge an toan.

Tai lieu di cung:

- `docs/codex-execution-pack.md`
- `docs/codex-parallel-agent-prompts.md`

## Current Snapshot

Snapshot xac nhan tu workspace ngay `2026-04-20`:

- Backend core da o muc production-lean, khong con o pha scaffold:
  - SQL-first bootstrap qua `composer bootstrap:booking`
  - `php artisan booking:doctor --json` pass
  - `node scripts/release/check-package-integrity.mjs --json` pass
  - `php artisan booking:launch-readiness --target=staging --json` = `ready_with_warnings`
  - `php artisan booking:launch-readiness --target=limited-production --json` = `not_ready`
- Runtime surface da lon va ro:
  - `240` runtime routes
  - `90` routes staff
  - `77` routes admin
  - `34` routes reservations
- Domain module da rat day:
  - `BenefitsLoyalty`
  - `BranchScheduling`
  - `CheckoutPayments`
  - `Conversations`
  - `FloorOps`
  - `KitchenDispatch`
  - `Notifications`
  - `Ordering`
  - `PrivacyAudit`
  - `Reporting`
  - `Reservations`
  - `WaitingList`
- Test surface sau:
  - `300` PHP test files tong
  - `173` Feature tests
  - `122` Unit tests
- Split web clients deu da o muc co the verify:
  - `npm --prefix customer-web run verify:release` pass
  - `npm --prefix staff-web run build` pass
  - `npm --prefix staff-web run test` pass
- Risk lon nhat trong worktree hien tai:
  - `customer-web` dang chiem `107` changed/new paths

## Core Reading of the Situation

Du an nay khong bi ket o missing backend foundations.
No dang bi ket o 3 lop closure:

1. lane `customer-web` con lon va dang mo, can dong bang va merge an toan
2. staging va limited-production con thieu evidence that, khong phai thieu core code
3. can mot execution order ro de Codex khong mo rong scope va khong tu tao va cham voi shared seams

## Non-Negotiable Rules For Codex

- Luon doc code hien co truoc khi sua.
- Tuan thu priority trong `AGENTS.md`:
  1. Auth / Identity / RBAC
  2. Walk-in + service session + dine-in POS flow
  3. Order lifecycle
  4. Kitchen / KDS
  5. Checkout / payment / refund / cashier shift
  6. Inventory basic but useful
  7. Reporting / ops / go-live hardening
- Khong mo rong launch scope chi vi route da ton tai.
- Khong lam refactor rong neu khong gop phan dong launch path.
- Moi batch phai co verify target ro, khong doi full suite o moi lan.
- Moi thay doi vao shared seams phai toi thieu hoa.

## Shared Seams To Protect

Day la cac file Codex phai coi la high-collision seams:

- `routes/api.php`
- `config/booking.php`
- `config/staff_capabilities.php`
- `database/schema/mysql-schema.sql`

Neu mot batch buoc phai sua mot file trong danh sach nay:

- chi sua toi thieu
- neu ro ly do trong bao cao batch
- khong tranh thu mo rong scope

## What Counts As Done

Codex chi duoc coi la hoan thanh chuong trinh nay khi tat ca dieu kien sau dung:

- `customer-web` Wave 1 la mot lane clean, merged, co release proof that
- `staff-web` operator chain dung voi backend contract va khong dua vao phan UI bi khoa scope
- backend core flow giu xanh:
  - availability
  - hold
  - reservation
  - check-in
  - order
  - kitchen
  - checkout
  - refund
  - cashier shift
- release artifact va contract artifact la truthful
- staging launch-readiness khong con warning "manual proof missing"
- limited-production launch-readiness co du evidence that
- package + rollback pack duoc dong bang voi artifact ro rang

## Critical Path

Critical path ma Codex phai ton trong:

1. freeze va land `customer-web`
2. dong Wave 1 launch path cho customer
3. dong operator path cho staff
4. dong residual backend seam gaps co lien quan launch path
5. clean staging evidence
6. collect limited-production manual evidence
7. package + rollback + launch pack

Neu mot task khong rut ngan critical path nay, uu tien cua no phai giam.

## Execution Program

## Phase 0 - Freeze The Current Baseline

### Objective

Bien worktree dirty hien tai thanh mot baseline co the dieu phoi duoc.

### Why this is first

Neu khong freeze baseline, moi batch sau deu co nguy co merge drift, nhat la o `customer-web` va generated artifacts.

### Codex scope

- xac dinh nhom thay doi nao la:
  - source code that
  - generated artifact expected
  - runtime evidence file
  - docs/runbook update
- don lai provenance cho generated contract outputs:
  - `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
  - `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
  - `build/api-consumer/mutation-contracts.md`
  - `storage/app/booking_release/release_manifest_snapshot.json`
  - `customer-web/src/lib/contracts/generated/*`
- chot ro file nao thuoc lane `customer-web`, file nao thuoc lane release/docs, file nao thuoc lane runtime scripts

### Mandatory commands

```bash
git status --short
node scripts/release/check-package-integrity.mjs --json
composer api:artifacts
npm --prefix customer-web run sync:contracts
npm --prefix customer-web run verify:contracts
```

### Exit criteria

- package integrity pass
- generated artifacts co provenance ro rang
- khong con tinh trang "khong biet file nay do nguoi sua tay hay do generator"
- ready de tach batch ma khong mat dau vet

### Stop conditions

- neu artifact refresh lam thay doi rat nhieu ngoai du kien, dung va doi chieu contract truoc khi merge

## Phase 1 - Land Customer-Web Wave 1

### Objective

Dong `customer-web` thanh shipping lane cho customer launch path.

### Scope

Chi giu Wave 1:

- auth va session restore
- menu browse
- availability
- hold create/refresh/cancel
- reservation create/list/detail/cancel/reschedule
- deposit preview va deposit payment-session flow
- bill preview/read va bill payment-session flow

### Explicitly out of scope

- khong mo default-on cho:
  - waiting-list
  - benefits
  - privacy tools
  - data export
- khong day preorder vao launch promise
- khong mo them mock-only behavior trong release lane

### Codex tasks

- clean support matrix va feature flag boundaries
- loai bo phu thuoc vao fallback-only routes
- chot typed SDK boundary va envelope/error handling
- dong test + smoke cho Wave 1
- giu `customer-web` docs dung voi thuc te launch promise

### Mandatory commands

```bash
npm --prefix customer-web run verify:contracts
npm --prefix customer-web run verify:wave-1
npm --prefix customer-web run verify:release
```

### Exit criteria

- `customer-web` release lane pass
- Wave 1 promise ro, khong ambiguously tied to Wave 2
- generated contract copy synchronized
- customer-web khong con la worktree risk lon nhat

### Recommended ownership

- frontend shell/layout/auth/session
- booking + reservations + payment session UX
- contract/governance/support matrix
- e2e smoke + live preflight scripts

## Phase 2 - Lock Staff-Web Operator Closure

### Objective

Chot day-1 operator lane de staff co the lam viec end-to-end ma khong can "mental workaround".

### Scope

- login
- table board
- reservation handling
- walk-in/service-session
- active order
- kitchen handoff va kitchen board
- checkout/finalize
- refund/refund-cancel
- cashier shift
- finance review reads can thiet

### Codex tasks

- xoa cac diem nhay vo ly giua workspace
- chot branch context propagation
- giu stale-write protection that trong checkout/refund/reopen reservation paths
- quyet dut diem order item line-level editing:
  - neu backend chua expose du contract can thiet, giu blocked that
  - neu expose duoc voi diff nho, lam trong batch rieng va them tests

### Mandatory commands

```bash
npm --prefix staff-web run build
npm --prefix staff-web run test
```

### Exit criteria

- operator chain thong suot
- khong co page quan trong nao phu thuoc vao stale guessed context
- route/workspace guard dung voi auth/capability/branch scope

### Residual acceptable gap

- line-item edit/status co the tiep tuc bi blocked neu backend contract chua du
- nhung gap nay phai duoc ghi ro la out-of-launch, khong duoc "gia vo sap xong"

## Phase 3 - Final Backend Hardening Only Where Launch Depends On It

### Objective

Khong rewrite backend. Chi harden cac diem con anh huong truc tiep den launch path.

### Batch order inside this phase

1. Auth / RBAC hardening
2. FOH / walk-in / check-in / move-table / release safety
3. Order lifecycle hardening
4. Kitchen / KDS invariants
5. Checkout / refund / cashier / reconciliation hardening
6. Inventory foundation chi o muc can thiet cho launch truth
7. Reporting / ops / release hardening

### Codex tasks by domain

#### 1. Auth / RBAC

- review capability coverage against runtime routes
- verify allow/deny path cho staff, admin, customer boundaries
- verify branch scope khong bleed
- keep `config/staff_capabilities.php` toi thieu diff

#### 2. FOH / walk-in / check-in

- row_version
- table lock va reservation lock
- hold conflict
- assignment idempotency
- branch scheduling / timezone / closure window interactions

#### 3. Ordering

- lock active order on occupied table only
- block order mutation after bill lock
- idempotency replay
- branch alignment giua reservation, table va order

#### 4. Kitchen

- order item status <-> kitchen ticket status consistency
- active vs terminal ticket transitions
- unrouted item behavior
- station routing drift detection

#### 5. Checkout / finance

- bill lock correctness
- payment idempotency
- refund lineage
- cancel-after-payment safety
- cashier shift readiness and branch scope

#### 6. Inventory

- stock movement invariants
- purchase receiving over-receive guard
- recipe and consumption consistency
- only harden what launch/read-model/reporting is using

#### 7. Ops / reporting / release

- reporting snapshot freshness
- outbox health
- realtime feed consistency
- release manifest sync
- package + doctor + deploy-check truth

### Mandatory backend gates

```bash
php artisan booking:doctor --json
php artisan booking:core-ops-gate --json
php artisan booking:round5-gate --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json
```

### Test policy

- uu tien targeted tests theo changed domain
- chi chay `php artisan test` full khi cham nhieu domain hoac truoc integration cut

### Exit criteria

- khong con high-risk unresolved seam trong launch path
- core gates xanh sau integration pass
- mo ta risk con lai phai nho va khong nam tren critical path

## Phase 4 - Make Staging Fully Honest

### Objective

Bien `staging` tu "ready_with_warnings" thanh readiness pass thuc chat.

### Current blockers seen in workspace

`staging` hien dang warning vi thieu 3 manual evidence:

- `uat_scenario_pack_replay`
- `performance_verification_report`
- `notification_provider_external_e2e`

### Codex tasks

- hoan thien tai lieu va script de team co the thuc hien 3 evidence nay nhanh nhat
- xoa nhung warning local-only khong can thiet trong staging lane
- dam bao `release-loop` artifact gom du backend + split-web evidence
- neu notification lane can mot provider that, uu tien email truoc

### Mandatory commands

```bash
php artisan booking:launch-readiness --target=staging --json
php artisan booking:release-loop --target=staging --json
```

### Exit criteria

- staging launch-readiness khong con warning thieu manual proof
- release-loop cho ket qua co the dung de release review

## Phase 5 - Collect Limited-Production Evidence

### Objective

Thu du 5 evidence that dang block limited-production.

### Current blocking evidence seen in workspace

`limited-production` hien dang block vi thieu:

- `uat_scenario_pack_replay`
- `performance_verification_report`
- `payment_provider_external_e2e`
- `notification_provider_external_e2e`
- `concurrency_rehearsal`

### Codex tasks

- chuan hoa file manual evidence JSON va huong dan thu evidence
- tao step-by-step runbook cuc ngan cho tung evidence
- toi uu script va command sao cho operator chi can dien dung input va chay
- dam bao output duoc archive dung noi

### What Codex should not do here

- khong fake manual evidence
- khong thay fixture file cho evidence that
- khong xem simulated provider local la production proof

### Mandatory commands

```bash
php artisan booking:launch-readiness --target=limited-production --json
```

Sau khi da co real evidence:

```bash
php artisan booking:launch-readiness --target=limited-production --manual-evidence=storage/app/booking_release/manual_evidence/<candidate>.json --json
```

### Exit criteria

- limited-production launch-readiness khong con blocking manual evidence gap

## Phase 6 - Package, Rollback, And Release Pack

### Objective

Dong bang candidate that su co the deploy va rollback.

### Codex tasks

- build immutable package
- verify frozen manifest
- verify package sidecars
- chot package id, metadata, checksum story
- dong bo package runbook, deploy runbook, launch-readiness runbook

### Mandatory commands

```bash
php artisan booking:package-release --verify-frozen --json
php artisan booking:release-manifest --verify-frozen --json
```

### Exit criteria

- co mot candidate ro rang
- co rollback note ro rang
- artifact family du de operator dung ma khong hoi lai team dev

## Phase 7 - Launch Cut And Immediate Cleanup

### Objective

Chot rollout duoc kiem soat va cleanup nho ngay sau do.

### Codex tasks

- chi nhan fix regression, docs, rollout cleanup
- khong mo feature moi
- lap backlog nho cho:
  - alias retirement
  - launch-only guard cleanup
  - notification/provider follow-up
  - deferred Wave 2 scope

### Exit criteria

- rollout artifact duoc archive
- first post-launch backlog nho va ro

## Merge Order For Codex

Thu tu merge khuyen nghi:

1. Phase 0 artifact/provenance freeze
2. `customer-web` Wave 1 closure
3. `staff-web` operator closure
4. backend auth / FOH / ordering / kitchen / finance residual hardening
5. staging evidence cleanup
6. limited-production evidence support
7. package + rollback + launch pack
8. post-launch cleanup

## Recommended Branch Layout

- `codex/freeze-baseline`
- `codex/customer-wave1-closure`
- `codex/staff-operator-closure`
- `codex/backend-launch-hardening`
- `codex/staging-evidence-closure`
- `codex/limited-prod-evidence-pack`
- `codex/release-pack-and-rollback`

## Verification Matrix

### Always first

```bash
git status --short
node scripts/release/check-package-integrity.mjs --json
```

### Backend baseline

```bash
php artisan booking:doctor --json
php artisan booking:deploy-check --mode=preflight --strict --json
```

### Core backend flow

```bash
php artisan booking:core-ops-gate --json
php artisan booking:round5-gate --json
```

### Customer web

```bash
npm --prefix customer-web run verify:contracts
npm --prefix customer-web run verify:wave-1
npm --prefix customer-web run verify:release
```

### Staff web

```bash
npm --prefix staff-web run build
npm --prefix staff-web run test
```

### Release truth

```bash
php artisan booking:launch-readiness --target=staging --json
php artisan booking:launch-readiness --target=limited-production --json
php artisan booking:release-loop --target=staging --json
```

## Stop Doing List

Codex khong duoc tu y mo rong vao:

- full Wave 2 customer-web rollout
- AI feature expansion
- optional analytics UI
- ERP-level inventory broadening
- kitchen expansion ngoai launch path
- provider integration thu hai khi provider hien tai chua co real rehearsal

## Codex Prompt Template

Dung prefix nay cho moi batch thuc thi:

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Muc tieu cua batch nay la phuc vu launch path, khong mo rong scope.

Bat buoc tuan thu:
- AGENTS.md cua repo
- .codex/AGENTS.md
- docs/codex-accelerated-execution-roadmap.md

Nguyen tac:
- doc code hien co truoc khi sua
- keep controllers thin
- dua logic vao service/domain layer
- add/update tests cho moi thay doi co y nghia
- protect authorization, branch scope, idempotency, locking, audit, row_version
- khong overbuild
- khong rewrite rong
- shared seams can toi thieu diff: routes/api.php, config/booking.php, config/staff_capabilities.php, database/schema/mysql-schema.sql

Output cuoi phai co:
1. Intent
2. Changed files
3. Added/updated tests
4. Remaining risks
```

## Bottom Line

Roadmap nay toi uu cho Codex theo mot su that rat ro cua repo hien tai:

- core code da kha sau
- core gates da xanh
- split web da co release lane that
- launch bi block boi closure, evidence, va discipline

Vi vay, Codex phai uu tien:

1. freeze baseline
2. land customer-web
3. close staff/operator truth
4. harden backend chi o diem launch can
5. clean staging evidence
6. collect limited-production evidence
7. package va chot rollback

Moi viec khac deu la secondary.
