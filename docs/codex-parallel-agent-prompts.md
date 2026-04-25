# Codex Parallel Agent Prompts

## Purpose

Tai lieu nay la bo prompt copy-paste de mo nhieu chat Codex song song cho repo `C:\Users\Duong Vinh\RestaurantPOS-Laravel` ma van giam xung dot merge.

Tai lieu nay phuc vu `launch path`, khong phuc vu broad cleanup.

Doc cung voi:

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-accelerated-execution-roadmap.md`
- `docs/codex-execution-pack.md`

## Coordination Rules

- Khong mo chat song song truoc khi `codex/freeze-baseline` xong.
- Moi chat chi sua trong vung so huu cua minh.
- Shared seams phai tranh toi da:
  - `routes/api.php`
  - `config/booking.php`
  - `config/staff_capabilities.php`
  - `database/schema/mysql-schema.sql`
- Generated artifact chain chi duoc refresh trong lane phu hop. Khong de nhieu chat cung sua:
  - `build/api-consumer/**`
  - `customer-web/src/lib/contracts/generated/**`
  - `storage/app/booking_release/**`

## Suggested Parallel Waves

### Wave 0

Mo 1 chat duy nhat:

- `codex/freeze-baseline`

### Wave 1

Co the mo toi da 2 chat:

1. `codex/customer-wave1-closure`
2. `codex/staging-evidence-closure` chi neu lane nay khong sua route shape, khong refresh generated artifacts ngoai pham vi release/evidence

### Wave 2

Sau khi customer lane on dinh:

1. `codex/staff-operator-closure`
2. `codex/backend-auth-rbac`

### Wave 3

Sau khi staff lane on dinh:

1. `codex/backend-foh-reservations`
2. `codex/backend-ordering-kitchen`
3. `codex/backend-checkout-finance`

Mo them `codex/backend-inventory-reporting-ops` chi khi:

- 3 lane tren da xanh, hoac
- launch-readiness chi ra blocker that trong inventory/reporting/ops

### Wave 4

Sequential only:

1. `codex/integration-pass`
2. `codex/limited-prod-evidence-pack`
3. `codex/release-pack-and-rollback`

## Shared Prompt Prefix

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc doc:
- AGENTS.md
- .codex/AGENTS.md
- docs/codex-accelerated-execution-roadmap.md
- docs/codex-execution-pack.md
- docs/codex-parallel-agent-prompts.md

Nguyen tac:
- doc code hien co truoc khi sua
- khong overbuild
- keep controllers thin
- dua logic vao service/domain layer
- add/update tests cho moi thay doi co y nghia
- protect authorization, branch scope, idempotency, locking, audit, row_version
- shared seams can toi thieu diff: routes/api.php, config/booking.php, config/staff_capabilities.php, database/schema/mysql-schema.sql

Bao cao cuoi:
1. Intent
2. Changed files
3. Added/updated tests
4. Verification run
5. Remaining risks
```

## Prompt - Customer Wave 1 Closure

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Close customer-web Wave 1 launch lane.
Branch: codex/customer-wave1-closure

Owned files:
- customer-web/**
- customer-web docs/tests/e2e/scripts
- generated customer-web contract copies chi khi dung chain artifact

In scope:
- auth/session
- menu
- table availability and holds
- reservations create/list/detail
- deposit preview and deposit payment sessions
- bill/active-order and bill payment sessions

Out of scope:
- waiting-list default-on
- benefits/privacy/data-export default-on
- preorder launch promise

Mandatory gates:
- npm --prefix customer-web run verify:contracts
- npm --prefix customer-web run verify:wave-1
- npm --prefix customer-web run verify:release
```

## Prompt - Staff Operator Closure

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Close staff-web day-1 operator lane.
Branch: codex/staff-operator-closure

Owned files:
- staff-web/**

Core chain:
- login
- table board
- reservations
- walk-in/service-session
- active order
- kitchen board
- checkout/refund
- cashier shift
- finance review
- conversation inbox

Rule:
- neu backend chua expose item-level row_version cho line-item edit/status thi giu blocked that

Mandatory gates:
- npm --prefix staff-web run build
- npm --prefix staff-web run test
```

## Prompt - Backend Auth / RBAC

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Harden auth, identity boundaries, and capability coverage.
Branch: codex/backend-auth-rbac

Owned files:
- auth middleware/resolver/capability mapping
- auth-focused tests
- config/staff_capabilities.php chi neu bat buoc va diff phai toi thieu

In scope:
- allow/deny paths
- customer/staff/admin boundary
- branch scope bleed
- route-capability coverage

Out of scope:
- khong sua business logic reservation/order/checkout neu khong can thiet cho auth boundary

Mandatory verify:
- targeted php artisan test cho auth/staff guard tests
- neu cham capability map, them regression tests deny-path
```

## Prompt - Backend FOH / Reservations

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Harden FOH core flows without changing launch contract.
Branch: codex/backend-foh-reservations

Owned files:
- app/Modules/BranchScheduling/**
- app/Modules/Reservations/**
- app/Modules/FloorOperations/**
- tests/Feature/Reservation/**
- tests/Feature/Table/**
- tests/Feature/Staff/StaffTableBoard*
- tests/Feature/Staff/StaffCheckInFlowTest.php
- tests/Feature/Staff/StaffMoveTableFlowTest.php

In scope:
- availability
- hold conflict
- reservation write safety
- check-in
- move-table
- release safety
- row_version, locking, branch scheduling interactions

Out of scope:
- checkout/payment
- kitchen dispatch
```

## Prompt - Backend Ordering / Kitchen

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Harden active order and kitchen handoff invariants.
Branch: codex/backend-ordering-kitchen

Owned files:
- app/Modules/Ordering/**
- app/Modules/KitchenDispatch/**
- tests/Feature/Staff/StaffOrder*
- tests/Feature/Staff/StaffKitchen*
- kitchen/order unit tests lien quan

In scope:
- active order ownership
- order mutation after bill lock
- item lifecycle consistency
- dispatch/fire/bump/recall invariants
- order item status <-> kitchen ticket status mapping

Out of scope:
- checkout settlement logic
- inventory expansion
```

## Prompt - Backend Checkout / Finance

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Harden checkout, refunds, payment sessions, webhook, cashier shift, and reconciliation.
Branch: codex/backend-checkout-finance

Owned files:
- app/Modules/Billing/**
- app/Modules/Payments/**
- app/Modules/Cashiering/**
- tests/Feature/Staff/StaffCheckout*
- tests/Feature/Staff/StaffCashierShift*
- tests/Feature/Staff/StaffFinance*
- tests/Feature/Payments/**
- tests/Feature/Financial/**

In scope:
- bill lock correctness
- finalize/refund/refund-cancel safety
- payment idempotency and replay
- webhook duplicate/reorder handling
- cashier shift readiness
- reconciliation/invoice branch scope

Out of scope:
- FOH board logic
- waiting-list/conversation workflow
```

## Prompt - Backend Inventory / Reporting / Ops

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Harden inventory, reporting, and ops only where launch truth depends on them.
Branch: codex/backend-inventory-reporting-ops

Owned files:
- app/Modules/Loyalty/** chi neu launch truth can
- app/Modules/Reporting/**
- app/Modules/Notifications/**
- app/Modules/PrivacyCompliance/** chi neu readiness can
- inventory services/tests lien quan
- ops/realtime/health tests lien quan

In scope:
- stock movement invariants can thiet cho launch truth
- reporting snapshot freshness
- outbox health
- operational realtime consistency
- alert/readiness truth

Out of scope:
- ERP-level inventory broadening
- Wave 2 customer features
```

## Prompt - Release Evidence Closure

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Close release evidence and operator support tooling.
Branch: codex/staging-evidence-closure hoac codex/limited-prod-evidence-pack

Owned files:
- app/Platform/Release/**
- app/Platform/Uat/**
- scripts/uat/**
- scripts/release/**
- docs/runbooks/**
- manual evidence templates

In scope:
- staging warning cleanup
- limited-production evidence support pack
- release-loop truth
- operator step-by-step docs

Rule:
- khong fake manual evidence
- khong doi runtime route shape
```

## Prompt - Integration Pass

```text
[PREPEND SHARED PROMPT PREFIX]

Task: Integrate merged workstreams without adding new features.
Branch: codex/integration-pass

Inputs da merge:
- customer-wave1-closure
- staff-operator-closure
- backend auth/rbac
- backend foh/reservations
- backend ordering/kitchen
- backend checkout/finance
- backend inventory/reporting/ops neu co
- release evidence closure neu co

Viec can lam:
- resolve collisions
- sua regression integration
- toi thieu hoa shared seam changes
- chay integration gates

Mandatory gates:
- php artisan booking:doctor --json
- php artisan booking:core-ops-gate --json
- php artisan booking:round5-gate --json
- php artisan booking:deploy-check --mode=preflight --strict --json
- php artisan booking:release-manifest --json
- npm --prefix customer-web run verify:release
- npm --prefix staff-web run build
- npm --prefix staff-web run test
```

## Merge Order

1. `codex/freeze-baseline`
2. `codex/customer-wave1-closure`
3. `codex/staff-operator-closure`
4. `codex/backend-auth-rbac`
5. `codex/backend-foh-reservations`
6. `codex/backend-ordering-kitchen`
7. `codex/backend-checkout-finance`
8. `codex/backend-inventory-reporting-ops` neu can
9. `codex/staging-evidence-closure`
10. `codex/integration-pass`
11. `codex/limited-prod-evidence-pack`
12. `codex/release-pack-and-rollback`

## Quick Review Checklist

- chat co sua ngoai vung so huu khong
- co cham generated artifacts khong; neu co da dung lane chua
- co touched shared seam khong; neu co da noi ro chua
- co them tests khong
- co chay gate dung muc khong
- remaining risk co noi that khong

## Bottom Line

Parallelism o repo nay co loi nhat khi:

- customer-web, staff-web, backend domain slices, va release evidence duoc tach lane ro
- generated artifacts va shared seams duoc khoa ky
- integration pass de lai cho cuoi, khong de moi lane tu merge shared files theo cach rieng
