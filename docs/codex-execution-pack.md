# Codex Execution Pack

## Purpose

Tai lieu nay bien roadmap launch trong repo `C:\Users\Duong Vinh\RestaurantPOS-Laravel` thanh bo huong dan thuc thi dung ngay cho Codex.

Dung cung voi:

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-accelerated-execution-roadmap.md`
- `docs/codex-parallel-agent-prompts.md`

Tai lieu nay tra loi 4 cau hoi thuc thi:

1. Tao branch nao truoc
2. Mo chat Codex bang prompt nao cho tung batch lon
3. Verify gi o moi pha
4. Co the chay song song lane nao ma khong va cham nguy hiem

## Verified Snapshot

Snapshot nay duoc xac nhan trong workspace ngay `2026-04-20`:

- `node scripts/release/check-package-integrity.mjs --json` pass
- `php artisan booking:doctor --json` pass
- `php artisan booking:launch-readiness --target=staging --json` = `ready_with_warnings`
- `php artisan booking:launch-readiness --target=limited-production --json` = `not_ready`
- `npm --prefix customer-web run verify:release` pass
- `npm --prefix staff-web run build` pass
- `npm --prefix staff-web run test` pass
- `240` runtime routes
- `90` staff routes
- `77` admin routes
- `34` customer reservation routes
- `328` PHP test files:
  - `174` feature
  - `124` unit
- worktree risk lon nhat hien tai la `customer-web` voi `107` changed/new paths

Hotspot can tranh broad refactor trong launch lane:

- `app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php`
- `app/Platform/Release/Services/LaunchReadinessService.php`
- `app/Modules/Conversations/Application/Services/StaffConversationWorkflowService.php`
- `app/Modules/FloorOperations/Application/Queries/StaffTableBoardService.php`
- `app/Modules/Notifications/Application/Services/NotificationOutboxService.php`

## Non-Negotiable Rules

- Giu dung thu tu uu tien trong `AGENTS.md`.
- Khong mo rong scope sang Wave 2 khi Wave 1 launch path chua dong.
- Khong broad-refactor giant services chi de "dep code".
- Moi batch phai co test hoac gate ro rang.
- Shared seams phai toi thieu diff:
  - `routes/api.php`
  - `config/booking.php`
  - `config/staff_capabilities.php`
  - `database/schema/mysql-schema.sql`

## Branch Program

### 1. `codex/freeze-baseline`

Intent:
- Don provenance cho generated artifacts
- Chot file ownership trong worktree
- Bien baseline dirty thanh baseline co the dieu phoi

Primary writes:
- `build/api-consumer/**`
- `customer-web/src/lib/contracts/generated/**`
- `storage/app/booking_release/**`
- docs/runbook hoac note provenance lien quan

Exit gates:

```bash
git status --short
node scripts/release/check-package-integrity.mjs --json
composer api:artifacts
npm --prefix customer-web run sync:contracts
npm --prefix customer-web run verify:contracts
```

Stop if:
- artifact refresh tao diff lon ngoai expected contract chain
- contract copies khong con byte-aligned

### 2. `codex/customer-wave1-closure`

Intent:
- Dong `customer-web` thanh Wave 1 lane co the merge va release-proof

Primary writes:
- `customer-web/**`
- generated customer-web contract copies
- customer-web docs/test/e2e/scripts

In scope:
- auth/session
- menu
- availability and holds
- reservations create/list/detail
- deposit preview + payment sessions
- bill/active-order + bill payment sessions

Out of scope:
- default-on waiting-list
- default-on benefits/privacy/data-export
- preorder launch promise

Exit gates:

```bash
npm --prefix customer-web run verify:contracts
npm --prefix customer-web run verify:wave-1
npm --prefix customer-web run verify:release
```

### 3. `codex/staff-operator-closure`

Intent:
- Dong operator lane cho `staff-web` ma khong dua vao workaround

Primary writes:
- `staff-web/**`
- staff-web tests
- chi cham backend contract copies neu build that su can

In scope:
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

Residual gap duoc chap nhan:
- line-item edit/status tiep tuc blocked neu backend chua expose item-level `row_version`

Exit gates:

```bash
npm --prefix staff-web run build
npm --prefix staff-web run test
```

### 4. `codex/backend-launch-hardening`

Intent:
- Harden backend chi tai diem launch path con rui ro

Recommended internal slices:

1. auth-rbac
2. foh-reservations
3. ordering-kitchen
4. checkout-finance
5. inventory-reporting-ops

Primary writes:
- `app/Modules/**`
- `app/Platform/**` chi khi la release/runtime truth
- domain tests lien quan

Exit gates:

```bash
php artisan booking:doctor --json
php artisan booking:core-ops-gate --json
php artisan booking:round5-gate --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json
```

Rule:
- Uu tien targeted tests theo changed module truoc
- Chi chay full integration lane khi nhieu slices da merge

### 5. `codex/staging-evidence-closure`

Intent:
- Bien `staging` tu `ready_with_warnings` thanh readiness that

Current missing evidence:
- `performance_verification_report`
- `notification_provider_external_e2e`

Primary writes:
- `app/Platform/Release/**`
- `scripts/release/**`
- `docs/runbooks/**`
- manual evidence templates

Exit gates:

```bash
php artisan booking:launch-readiness --target=staging --json
php artisan booking:release-loop --target=staging --json
```

### 6. `codex/limited-prod-evidence-pack`

Intent:
- Thu du va chuan hoa support pack cho limited-production evidence

Current missing evidence:
- `uat_scenario_pack_replay`
- `performance_verification_report`
- `payment_provider_external_e2e`
- `notification_provider_external_e2e`
- `concurrency_rehearsal`

Primary writes:
- runbooks
- manual evidence templates
- release/evidence scripts
- launch-readiness helpers

Exit gates:

```bash
php artisan booking:launch-readiness --target=limited-production --json
php artisan booking:launch-readiness --target=limited-production --manual-evidence=storage/app/booking_release/manual_evidence/<candidate>.json --json
```

### 7. `codex/release-pack-and-rollback`

Intent:
- Freeze candidate, package, manifest, rollback notes

Primary writes:
- package/release artifacts
- release docs and rollback docs

Exit gates:

```bash
php artisan booking:package-release --verify-frozen --json
php artisan booking:release-manifest --verify-frozen --json
```

### 8. `codex/post-launch-cleanup`

Intent:
- Chi don backlog nho sau launch

Allowed work only:
- regression fix
- rollout cleanup
- doc cleanup
- backlog capture cho Wave 2

## Merge Order

Merge theo thu tu nay:

1. `codex/freeze-baseline`
2. `codex/customer-wave1-closure`
3. `codex/staff-operator-closure`
4. `codex/backend-launch-hardening`
5. `codex/staging-evidence-closure`
6. `codex/limited-prod-evidence-pack`
7. `codex/release-pack-and-rollback`
8. `codex/post-launch-cleanup`

## Prompt Prefix

Dung prefix nay cho moi batch lon:

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc doc:
- AGENTS.md
- .codex/AGENTS.md
- docs/codex-accelerated-execution-roadmap.md
- docs/codex-execution-pack.md

Nguyen tac:
- doc code hien co truoc khi sua
- keep controllers thin
- dat logic trong service/domain layer
- add/update tests cho moi thay doi co y nghia
- bao ve authorization, branch scope, idempotency, locking, audit, row_version
- khong overbuild
- khong broad-refactor
- shared seams can toi thieu diff: routes/api.php, config/booking.php, config/staff_capabilities.php, database/schema/mysql-schema.sql

Output cuoi bat buoc:
1. Intent
2. Changed files
3. Added/updated tests
4. Verification run
5. Remaining risks
```

## Copy-Paste Prompts

### Prompt - Freeze Baseline

```text
[PREPEND PROMPT PREFIX]

Batch: Freeze baseline and provenance cleanup
Branch: codex/freeze-baseline

Muc tieu:
- chot provenance cho generated artifacts
- phan loai dirty worktree thanh lane customer-web, release/docs, runtime scripts
- dam bao generated copies dong bo dung chain backend -> customer-web

Scope uu tien:
- build/api-consumer/**
- customer-web/src/lib/contracts/generated/**
- storage/app/booking_release/**
- docs/runbooks/** neu can ghi provenance

Mandatory gates:
- git status --short
- node scripts/release/check-package-integrity.mjs --json
- composer api:artifacts
- npm --prefix customer-web run sync:contracts
- npm --prefix customer-web run verify:contracts

Stop neu artifact refresh tao drift lon ngoai expected generator chain.
Khong mo rong sang UI hoac business logic trong batch nay.
```

### Prompt - Customer Wave 1 Closure

```text
[PREPEND PROMPT PREFIX]

Batch: Customer-web Wave 1 closure
Branch: codex/customer-wave1-closure

Muc tieu:
- dong customer-web thanh lane co the merge cho launch path
- giu support matrix truthful
- khong de Wave 2 surfaces tro thanh default-on

Wave 1 phai xanh:
- auth/session
- menu
- table availability/holds
- reservation create/list/detail
- deposit preview + payment sessions
- bill/active-order + bill payment sessions

Out of scope:
- waiting-list default-on
- benefits/privacy/data-export default-on
- preorder launch promise

Mandatory gates:
- npm --prefix customer-web run verify:contracts
- npm --prefix customer-web run verify:wave-1
- npm --prefix customer-web run verify:release

Neu can sua generated copies, chi sua qua chain artifact chinh thuc.
```

### Prompt - Staff Operator Closure

```text
[PREPEND PROMPT PREFIX]

Batch: Staff-web operator closure
Branch: codex/staff-operator-closure

Muc tieu:
- dong operator chain ma khong dua vao stale context hoac workaround

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
- neu backend chua expose item-level row_version cho line-item edit/status thi giu blocked that, khong gia vo da xong

Mandatory gates:
- npm --prefix staff-web run build
- npm --prefix staff-web run test
```

### Prompt - Backend Launch Hardening

```text
[PREPEND PROMPT PREFIX]

Batch: Backend launch hardening
Branch: codex/backend-launch-hardening

Muc tieu:
- harden backend chi tai diem launch path con rui ro
- khong rewrite backend

Thu tu domain:
1. auth-rbac
2. foh-reservations
3. ordering-kitchen
4. checkout-finance
5. inventory-reporting-ops

Rule:
- moi lan chi om mot slice domain nho
- uu tien test targeted theo changed files
- shared seams de lai cho integration pass neu co the

Mandatory gates truoc khi dong batch:
- php artisan booking:doctor --json
- php artisan booking:core-ops-gate --json
- php artisan booking:round5-gate --json
- php artisan booking:deploy-check --mode=preflight --strict --json
- php artisan booking:release-manifest --json
```

### Prompt - Staging Evidence Closure

```text
[PREPEND PROMPT PREFIX]

Batch: Staging evidence closure
Branch: codex/staging-evidence-closure

Muc tieu:
- dong 3 manual evidence dang giu staging o ready_with_warnings

Current gaps:
- uat_scenario_pack_replay
- performance_verification_report
- notification_provider_external_e2e

Scope:
- release/evidence scripts
- runbooks
- manual evidence templates
- launch-readiness helper logic

Khong fake evidence.
Khong xem local simulated provider la production proof.

Mandatory gates:
- php artisan booking:launch-readiness --target=staging --json
- php artisan booking:release-loop --target=staging --json
```

### Prompt - Limited-Production Evidence Pack

```text
[PREPEND PROMPT PREFIX]

Batch: Limited-production evidence pack
Branch: codex/limited-prod-evidence-pack

Muc tieu:
- chuan hoa support pack de operator thu du 5 evidence block limited-production

Current gaps:
- `performance_verification_report`
- `payment_provider_external_e2e`
- notification_provider_external_e2e
- concurrency_rehearsal

Scope:
- template JSON
- scripts thu evidence
- runbook step-by-step rat ngan
- archive/output convention

Khong fake manual evidence.

Mandatory gates:
- php artisan booking:launch-readiness --target=limited-production --json
- php artisan booking:launch-readiness --target=limited-production --manual-evidence=storage/app/booking_release/manual_evidence/<candidate>.json --json
```

### Prompt - Release Pack And Rollback

```text
[PREPEND PROMPT PREFIX]

Batch: Release pack and rollback
Branch: codex/release-pack-and-rollback

Muc tieu:
- freeze candidate that su co the deploy va rollback

Scope:
- package artifact
- frozen manifest
- checksum/meta
- rollback note
- release/deploy docs lien quan

Mandatory gates:
- php artisan booking:package-release --verify-frozen --json
- php artisan booking:release-manifest --verify-frozen --json

Khong them feature moi trong batch nay.
```

## Verification Checklist By Phase

### Phase 0 - Freeze Baseline

- `git status --short`
- `node scripts/release/check-package-integrity.mjs --json`
- `composer api:artifacts`
- `npm --prefix customer-web run sync:contracts`
- `npm --prefix customer-web run verify:contracts`

### Phase 1 - Customer Wave 1

- `npm --prefix customer-web run verify:contracts`
- `npm --prefix customer-web run verify:wave-1`
- `npm --prefix customer-web run verify:release`

### Phase 2 - Staff Operator Closure

- `npm --prefix staff-web run build`
- `npm --prefix staff-web run test`

### Phase 3 - Backend Hardening

- targeted `php artisan test ...`
- `php artisan booking:doctor --json`
- `php artisan booking:core-ops-gate --json`
- `php artisan booking:round5-gate --json`
- `php artisan booking:deploy-check --mode=preflight --strict --json`
- `php artisan booking:release-manifest --json`

### Phase 4 - Staging Evidence

- `php artisan booking:launch-readiness --target=staging --json`
- `php artisan booking:release-loop --target=staging --json`

### Phase 5 - Limited Production Evidence

- `php artisan booking:launch-readiness --target=limited-production --json`
- rerun voi `--manual-evidence=...` sau khi co evidence that

### Phase 6 - Release Pack

- `php artisan booking:package-release --verify-frozen --json`
- `php artisan booking:release-manifest --verify-frozen --json`

## Safe Parallel Order

Nguyen tac:

- Khong parallelize khi chua freeze baseline.
- Khong de backend contract-shape drift chay dong thoi voi customer-wave1 merge lane.
- Khong de nhieu lane cung sua generated artifacts.
- Khong de lane release-evidence sua shared seams dong thoi voi lane backend domain.

### Wave S0 - Sequential only

Chay duy nhat:

- `codex/freeze-baseline`

### Wave S1 - Safe two-lane split

Co the mo song song:

- `codex/customer-wave1-closure`
- `codex/staging-evidence-closure` chi neu lane nay gioi han trong `docs/runbooks/**`, `scripts/uat/**`, `scripts/release/**`, `app/Platform/Release/**`, `app/Platform/Uat/**`

Khong parallel trong Wave S1:

- backend contract changes
- route shape changes
- generated artifact refresh ngoai lane freeze/customer

### Wave S2 - Safe two-lane split

Sau khi customer-wave1 on dinh:

- `codex/staff-operator-closure`
- backend `auth-rbac` slice

Dieu kien:

- auth-rbac slice khong doi response shape cua route dang duoc staff-web closure dung
- staff-web khong sua backend route contract

### Wave S3 - Safe backend fan-out

Sau khi staff operator closure da on dinh:

- lane A: `foh-reservations`
- lane B: `ordering-kitchen`
- lane C: `checkout-finance`

Lane D `inventory-reporting-ops` chi mo khi:

- A-C da xanh hoac
- launch-readiness/alert snapshot chi ra no la blocker that

### Wave S4 - Sequential integration

Phai dong tuan tu:

1. integration pass cho backend slices
2. staging evidence closure
3. limited-production evidence pack
4. release pack and rollback

## File Ownership Hints

Dung bang nay de giam merge collision:

- customer-web lane:
  - `customer-web/**`
  - generated customer-web contract copies
- staff-web lane:
  - `staff-web/**`
- auth-rbac lane:
  - auth middleware, resolver, capability tests
  - `config/staff_capabilities.php` toi thieu diff neu bat buoc
- foh-reservations lane:
  - `app/Modules/BranchScheduling/**`
  - `app/Modules/Reservations/**`
  - `app/Modules/FloorOperations/**`
- ordering-kitchen lane:
  - `app/Modules/Ordering/**`
  - `app/Modules/KitchenDispatch/**`
- checkout-finance lane:
  - `app/Modules/Billing/**`
  - `app/Modules/Payments/**`
  - `app/Modules/Cashiering/**`
- evidence/release lane:
  - `app/Platform/Release/**`
  - `scripts/release/**`
  - `docs/runbooks/**`

## Review Checklist For Any Batch

- scope co nam tren critical path khong
- co dung branch lane khong
- co sua shared seam khong; neu co, da ghi ro chua
- co them hoac cap nhat tests chua
- co chay dung gate cua pha khong
- remaining risks co noi that va nho scope khong

## Bottom Line

Execution pack nay mac dinh uu tien:

1. freeze baseline
2. land customer-web
3. land staff-web
4. harden backend theo slices nho
5. clean staging evidence
6. support limited-production evidence
7. freeze package and rollback

Neu mot batch khong rut ngan chuoi nay, uu tien cua no phai giam.
