# Project Elevation Roadmap
*Date: 2026-05-23*

## 1. Executive Summary
RestaurantPOS is an impressively mature, production-oriented backend leveraging a strict SQL-first architecture. It actively avoids Laravel's default `php artisan migrate` in favor of a robust release patch inventory (`database/patches/`) and frozen OpenAPI contracts. The domain boundaries (`app/Modules/`, `app/Platform/`) are well-defined.

The project is **not yet ready for limited-production launch**. While the codebase is extremely solid, the launch is deliberately blocked by stringent runtime gates (`booking:launch-readiness`) demanding real, manual evidence (Performance, UAT, Payments, Notifications, Concurrency). Additionally, local preflight checks failed because a required Redis instance was unavailable, proving that the runtime safety nets work as intended. The immediate goal is to freeze the baseline, resolve these evidence gaps, and achieve a "clean staging" before addressing any Wave 2 features.

## 2. Current Maturity Scorecard
- **Architecture**: 9/10. Modules are well-separated. `app/Platform` is appropriately cross-cutting. Controllers are thin. *To get 10/10*: Demonstrate live fault tolerance metrics.
- **SQL-first release discipline**: 9/10. Strict patching and `composer bootstrap:booking` workflow. *To get 10/10*: Ensure all patches have explicit idempotency notes.
- **Backend domain correctness**: 8.5/10. Excellent state-machine discipline and invariants. *To get 10/10*: Complete integration passes for payment lock contentions.
- **API contract maturity**: 9/10. Generated SDKs and mutation contracts protect the frontend from drift. *To get 10/10*: Zero stale artifact warnings.
- **Auth/RBAC/security**: 8.5/10. Customer/Staff boundaries and Branch contexts are solid. *To get 10/10*: Full capability map coverage audit across all staff routes.
- **Frontend customer-web**: 9/10. `verify:wave-1` and `verify:contracts` pass flawlessly. Wave 2 features are properly gated. *To get 10/10*: Full E2E coverage for Edge cases.
- **Frontend staff-web**: 8/10. Operator chain is coherent. *To get 10/10*: Final resolution of line-item edit gaps.
- **Testing**: 8.5/10. Over 300 tests. Proper mix of Feature and Unit. *To get 10/10*: Less reliance on SQLite for critical concurrency tests.
- **Runtime/release gates**: 9/10. `booking:doctor` and `deploy-check` correctly block when runtime dependencies (Redis) fail. *To get 10/10*: Fully automated test environments with MySQL/Redis CI services.
- **Observability/ops**: 7/10. Outbox and audit logs exist, but staging is blocked by missing `notification_provider_external_e2e`. *To get 10/10*: Real Datadog/Grafana dashboards.
- **Documentation/portfolio**: 9/10. `PROJECT_HANDOFF.md` and `known-limitations.md` are honest and highly professional. *To get 10/10*: Archival of live launch evidence.
- **Limited-production readiness**: 6/10. Code is there, but blocked by 5 missing pieces of evidence and pending baseline freezes.

## 3. Critical Risks
- **Runtime Dependency Failure**: `php artisan booking:doctor` currently fails due to a missing Redis connection. This actively blocks `deploy-check` and `launch-readiness`.
- **Missing External Evidence**: We cannot claim production readiness without genuine `payment_provider_external_e2e` and `notification_provider_external_e2e`. Simulated local providers are insufficient for launch.
- **Stale Evidence Track**: `staging` is showing as `ready_with_warnings` solely because of missing UAT and Performance manual evidence files.
- **Customer Web Wave 2 Drift**: High risk of accidentally merging Wave 2 features (Preorders, waiting-list automation) into the day-1 launch promise.

## 4. Evidence-backed Findings
- **Git State**: 6 changed files identified by `git status --short`. The baseline is currently "dirty" and must be frozen before execution.
- **Contracts**: `npm --prefix customer-web run verify:contracts` and `verify:wave-1` passed, meaning the frontend successfully adheres to the backend contract without Wave 2 bleed.
- **Static Analysis**: `phpstan analyse` returned `[OK] No errors`, confirming strong typing discipline across the backend.
- **Launch Readiness Gates**: Execution of `booking:launch-readiness` for `limited-production` failed and strictly required manual evidence injection (`--manual-evidence=...`), preventing a fake launch.

## 5. Prioritized Roadmap

This roadmap strictly follows the strategy outlined in `codex-accelerated-execution-roadmap.md`.

### Tầng A — Must do trước khi claim limited-production (Launch Path S0 & S1)
- **Batch 0**: Freeze baseline and provenance cleanup.
- **Batch 1**: Customer-web Wave 1 closure.
- **Batch 2**: Staff-web operator closure.

### Tầng B — Professional hardening (Launch Path S3)
- **Batch 3**: Backend launch hardening (Auth/RBAC, FOH/Reservations, Ordering, Finance).

### Tầng C — Portfolio/interview polish (Evidence Path S4)
- **Batch 4**: Staging evidence closure.
- **Batch 5**: Limited-production evidence pack.
- **Batch 6**: Release pack and rollback verification.

### Tầng D — Wave 2 sau launch
- *Out of Scope*: QR self-pay, advanced preorders, loyalty analytics, Datadog wiring. Do not touch until Launch Phase is complete.

## 6. Batch-by-batch Execution Plan

### Batch 001: Freeze baseline and provenance cleanup
- **Goal**: Resolve dirty worktree, sync API generated artifacts, and clearly demarcate lane ownership.
- **Why**: Protects against merge drifts and false positives in subsequent batches.
- **Scope**: `build/api-consumer/**`, `customer-web/src/lib/contracts/generated/**`, `storage/app/booking_release/**`.
- **Out of Scope**: Any UI or business logic modifications.
- **Shared seams to avoid**: `routes/api.php`, `database/schema/mysql-schema.sql`.
- **Verify Commands**: `composer api:artifacts`, `node scripts/release/check-package-integrity.mjs --json`, `npm --prefix customer-web run verify:contracts`.
- **Exit Criteria**: Package integrity passes, zero untracked generated files, 0 warnings on provenance.
- **Difficulty**: Low | **Value**: Launch Readiness

### Batch 002: Customer-web Wave 1 closure
- **Goal**: Lock the customer-web lane for day-1 promise.
- **Scope**: Auth/session, menu, table availability, holds, reservation flows.
- **Out of Scope**: Waiting-list automation, benefits, data-export, preorder features.
- **Verify Commands**: `npm --prefix customer-web run verify:wave-1`, `npm --prefix customer-web run verify:release`.
- **Exit Criteria**: Wave 1 tests pass, release gate passes.
- **Difficulty**: Medium | **Value**: Launch Readiness

*(Further batches map exactly to Phases 2-7 of `codex-execution-pack.md`)*

## 7. First batch copy-paste prompt

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc doc:
- AGENTS.md
- .codex/AGENTS.md
- docs/codex-accelerated-execution-roadmap.md
- docs/codex-execution-pack.md

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

Nguyen tac:
- doc code hien co truoc khi sua
- khong mo rong scope
- shared seams can toi thieu diff: routes/api.php, config/booking.php, config/staff_capabilities.php, database/schema/mysql-schema.sql

Output cuoi bat buoc:
1. Intent
2. Changed files
3. Added/updated tests
4. Verification run
5. Remaining risks
```

## 8. Commands to verify
To continuously measure progress, run the following:
```bash
node scripts/release/check-package-integrity.mjs --json
php artisan booking:doctor --json
php artisan booking:deploy-check --mode=preflight --strict --json
npm --prefix customer-web run verify:wave-1
```

## 9. Remaining unknowns
- Will the production environment support Redis, or do we need to pivot to a different cache driver? (Currently failing locally without it).
- How heavy is the refactoring required for line-item edits in `staff-web`?
- Which real-world payment provider is selected for `payment_provider_external_e2e` evidence?

## 10. Honest recommendation
**Chưa nên launch (Not ready to launch).**
At its current state, the repository is an outstanding **portfolio-grade** showcase of high-end backend engineering, SQL-first discipline, and contract-driven frontend integration. However, attempting a live limited-production launch is blocked because we lack genuine external integration evidence (payments, notifications) and performance validation records. The immediate priority must be freezing the baseline, ensuring the local Redis dependency is addressed, and executing the Evidence Collection batches. Wait until `php artisan booking:launch-readiness --target=limited-production` runs entirely green.
