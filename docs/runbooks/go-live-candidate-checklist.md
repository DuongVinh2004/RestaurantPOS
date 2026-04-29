# Go-Live Candidate Checklist

## Intent

This is the single release-candidate gate for promoting RestaurantPOS from hardening into a go-live candidate.

Current audit baseline:

- Production-readiness: 6/10
- Security-readiness: 6/10
- Data-integrity: 7/10
- Operational-readiness: 5/10

A candidate is eligible only when the automated gate passes and every manual evidence item below is attached to the release ticket or artifact bundle.

## Scripted Gate

Run from the repository root:

```bash
composer release:go-live-check -- \
  --target=staging \
  --p0p1-evidence=<release-ticket-or-file> \
  --sql-bootstrap-evidence=<scratch-bootstrap-json-or-ticket> \
  --backup-restore-evidence=<drill-report-or-ticket> \
  --rollback-plan=<rollback-ticket-or-file>
```

Equivalent npm entrypoint:

```bash
npm run release:go-live-check -- \
  --target=staging \
  --p0p1-evidence=<release-ticket-or-file> \
  --sql-bootstrap-evidence=<scratch-bootstrap-json-or-ticket> \
  --backup-restore-evidence=<drill-report-or-ticket> \
  --rollback-plan=<rollback-ticket-or-file>
```

The gate writes evidence to `build/booking-go-live/`:

- `go-live-check-latest.json`
- `go-live-check-latest.md`
- `<step>.stdout.log`
- `<step>.stderr.log`

If SQL bootstrap must be run by the gate instead of supplied as existing scratch evidence, point `.env` or process env at a scratch MySQL database and opt in explicitly:

```bash
composer release:go-live-check -- \
  --run-sql-bootstrap \
  --p0p1-evidence=<release-ticket-or-file> \
  --backup-restore-evidence=<drill-report-or-ticket> \
  --rollback-plan=<rollback-ticket-or-file>
```

Do not use `--run-sql-bootstrap` against production data. It invokes `tools/mysql/bootstrap_release.php`, which imports the canonical schema and patches before running `tools/mysql/verify_release_contract.sql`.

Use `--allow-dirty=<release-ticket-note>` only when the release ticket explicitly lists the dirty artifacts and explains why they are intentionally present.

## Staging Or Scratch Runtime Gate

Use this procedure for a staging candidate or an isolated scratch runtime. It is intentionally non-destructive except for the SQL-first bootstrap step, which must point only at a scratch/staging database selected for candidate validation. Do not record a production GO decision from this procedure unless MySQL, Redis, and scheduler heartbeat were actually verified on the target runtime.

Before running commands, record environment posture without pasting secret values into the ticket:

- `APP_ENV` is `staging`, `production`, or another documented production-like target.
- `APP_DEBUG=false`.
- `APP_KEY` is present and non-placeholder.
- `CUSTOMER_AUTH_JWT_SECRET` is present, non-placeholder, and at least 32 characters when customer auth is enabled.
- `CORS_ALLOWED_ORIGINS` lists exact origins only; no wildcard, path suffix, query/fragment, credentials, or trailing slash.
- Staff env API-key fallback, staff role-name fallback, and operational branch fallback are disabled for the target.
- Queue, cache, and session drivers are durable/intentional for the target; local-only `sync`, `file`, `array`, and cookie session drivers are not accepted.
- Notification outbox is enabled and production-like notification channels use real providers, not stub/log delivery.
- Staff auth, customer auth, payment, notification, database, and Redis secrets are present in the secret manager or target environment. Record only present/missing, never raw secret values.
- `.env` or process environment points to the intended scratch/staging MySQL and Redis hosts.

Run from the repository root and archive stdout/stderr for each command:

```bash
git status --short
composer install --no-interaction --prefer-dist
composer bootstrap:booking
php artisan booking:doctor --strict --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json --no-write
php artisan booking:launch-readiness --target=staging --json
php artisan schedule:list
```

Runtime proof requirements:

- MySQL connectivity is proven only when `booking:doctor --strict --json` reports the database runtime check as passing and `booking:deploy-check --mode=preflight --strict --json` is not dependency-blocked by DB runtime.
- Redis connectivity is proven only when `booking:doctor --strict --json` reports Redis and lock/cache probes as passing.
- Scheduler installation is proven by `php artisan schedule:list` showing `scheduler-heartbeat` every minute and the target process manager or cron entry running the scheduler lane.
- Scheduler heartbeat freshness is proven only when `booking:doctor --strict --json` reports `runtime.scheduler.ok=true` after the scheduler lane has had time to run. `booking:ops-heartbeat:touch scheduler --json` may prime a local smoke after cache clear, but it is not production scheduler proof.
- Queue/outbox health is proven only when `notifications:outbox-health --json` passes or the release ticket documents that notification outbox is intentionally disabled for the target.

`booking:release-manifest --json --no-write` is the read-only manifest inspection mode. It emits the manifest JSON to stdout, sets `meta.no_write_requested=true`, and does not create `storage/app/booking_release/release_manifest/reports/*` files or refresh the frozen `release_manifest_snapshot.json`. Omit `--no-write` only when you intentionally want report artifacts as release evidence.

### Evidence Template

| Command | Timestamp UTC | Environment | Pass/Fail/Not run | Artifact path or stdout log | Operator | Skipped reason | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `git status --short` |  |  |  |  |  |  |  |
| `composer install --no-interaction --prefer-dist` |  |  |  |  |  |  |  |
| `composer bootstrap:booking` |  |  |  |  |  |  | Scratch/staging DB only. |
| `php artisan booking:doctor --strict --json` |  |  |  |  |  |  | Record DB, Redis, scheduler, outbox statuses. |
| `php artisan notifications:outbox-health --json` |  |  |  |  |  |  | Mark skipped only if command is absent or outbox disabled with evidence. |
| `php artisan booking:deploy-check --mode=preflight --strict --json` |  |  |  |  |  |  |  |
| `php artisan booking:release-manifest --json --no-write` |  |  |  | stdout log |  |  | No report files should be created. |
| `php artisan booking:launch-readiness --target=staging --json` |  |  |  |  |  |  |  |
| `php artisan schedule:list` |  |  |  |  |  |  | Confirm `scheduler-heartbeat`. |
| Scheduler heartbeat observation |  |  |  |  |  |  | Link `booking:doctor` output with `runtime.scheduler.ok=true`. |

## Required Checklist

| Area | Required proof | Source |
| --- | --- | --- |
| Repository hygiene | `git status --porcelain` is clean, or dirty artifacts are listed in the release ticket and passed through `--allow-dirty=<reason>` | go-live script |
| P0/P1 disposition | Every P0/P1 is fixed or explicitly mitigated with owner/date/rollback note | `--p0p1-evidence` |
| App environment | `APP_ENV` is production-like (`staging`, `production`, or `limited-production`) | go-live script |
| Debug mode | `APP_DEBUG=false` | go-live script |
| CORS exact origins | `CORS_ALLOWED_ORIGINS` has no wildcard, path/query/fragment, credentials, or trailing slash | `booking:doctor`; go-live script |
| Runtime drivers | Queue/cache/session drivers are durable and intentional for the target | `booking:doctor`; go-live script |
| Customer auth secret | `CUSTOMER_AUTH_JWT_SECRET` is non-placeholder and at least 32 characters | go-live script |
| Staff auth key source | `STAFF_AUTH_DATABASE_STORE_ENABLED=true`; env fallback and role-name fallback are disabled | go-live script plus `booking:doctor` |
| DB reachability | MySQL ping and DB-dependent deploy checks pass | `php artisan booking:doctor --strict --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` |
| Redis reachability | Redis set/get and lock probe pass | `php artisan booking:doctor --strict --json` |
| Scheduler heartbeat | `runtime.scheduler.ok=true`; Redis blocker is cleared first if scheduler is dependency-blocked | `php artisan booking:doctor --strict --json` |
| Outbox health | Outbox health has no failed/stale blocker | `php artisan notifications:outbox-health --json`; `booking:doctor` |
| SQL bootstrap/verifier | Scratch bootstrap applies `database/schema/mysql-schema.sql`, all `database/patches/*.sql`, and `tools/mysql/verify_release_contract.sql` | `--run-sql-bootstrap` or `--sql-bootstrap-evidence` |
| Route gate | Runtime API surface matches the locked route inventory | `php artisan booking:route-gate --json` |
| Release manifest | Required artifacts, SQL patches, OpenAPI, and FK fragments are present | `php artisan booking:release-manifest --json` for release evidence; `php artisan booking:release-manifest --json --no-write` for read-only staging/scratch inspection |
| Deploy check | Preflight deploy guardrails pass in strict mode | `php artisan booking:deploy-check --mode=preflight --strict --json` |
| Package verify | Package integrity and freshness checks pass | `npm run verify:package` |
| Security ladder | Auth/RBAC/staff capability/branch isolation ladder passes | `composer test:security` |
| Order ladder | Order lifecycle, row-version, idempotency, and bill-lock ladder passes | `composer test:orders` |
| KDS ladder | Kitchen/KDS routing, dispatch/action, branch, row-version, and idempotency ladder passes | `composer test:kitchen` |
| Money ladder | Checkout/payment/refund/cashier shift ladder passes | `composer test:money` |
| Inventory ladder | Inventory/purchasing/served-item movement ladder passes | `composer test:inventory` |
| Release contract ladder | Console, artifact, route, deploy, doctor, manifest, and SQL contract tests pass | `composer test:release-contract` |
| Staff-web build | Staff web integrity check, TypeScript compile, and Vite build pass | `cd staff-web && npm run build` |
| Staff-web smoke | Day-1 staff live smoke runs against the candidate runtime | `cd staff-web && npm run smoke:live` |
| Backup/restore drill | Backup and restore rehearsal evidence is attached | `--backup-restore-evidence`; `php artisan booking:dr-drill --mode=full-isolated-restore --target-db=<scratch_db> --drop-target-first --json` |
| Rollback plan | Previous known-good package, sidecars, checksum verification, and post-rollback gates are recorded | `--rollback-plan`; `docs/runbooks/booking-deploy-runbook.md` |

## No-Go Conditions

Any item below blocks promotion until fixed or explicitly mitigated by the release owner:

- Cross-branch data leak, branch-scope bypass, or staff capability denial regression.
- `booking:doctor` fails, including DB, Redis, scheduler, or outbox runtime blockers.
- `booking:deploy-check --mode=preflight --strict` fails.
- Missing cashier-shift FK verifier or cashier-shift row-version trigger verifier in `tools/mysql/verify_release_contract.sql`, `database/schema/mysql-schema.sql`, `db_all.sql`, `database/patches/2026_04_27_000059_cashier_shift_row_version.sql`, or `booking:release-manifest`.
- Idempotency gaps on production mutation routes, including missing keys, replay mismatch gaps, or in-progress lock gaps.
- Money flow tests not run or failing.
- Redis locks/idempotency not verified by `booking:doctor` and the order/money ladders.
- Staff-web day-1 smoke not run against the candidate runtime.
- SQL bootstrap/verifier proof missing for a scratch MySQL candidate database.
- Backup/restore drill evidence missing.
- Rollback plan missing the previous known-good package basename and checksum sidecars.

## Runtime Failure Classification

The go-live script keeps running later safe checks after a failure and classifies failures in the JSON artifact:

- `runtime.db`: DB connection, MySQL runtime, or SQL probe failed.
- `runtime.redis`: Redis, lock, or cache-store probe failed.
- `runtime.scheduler`: scheduler heartbeat missing or stale.
- `runtime.outbox`: notification outbox failed or is dependency-blocked.
- `deploy_check_failed`: deploy preflight failed after runtime was reachable enough to inspect.
- `manual_evidence_missing`: required P0/P1, SQL, backup/restore, or rollback evidence was not supplied.
- `sql_bootstrap_not_run`: neither `--run-sql-bootstrap` nor `--sql-bootstrap-evidence` was supplied.

Dependency-blocked checks remain no-go. For example, scheduler blocked by Redis is still a failed runtime candidate; fix Redis first, then rerun the gate to prove scheduler freshness.

## Drill-Down Commands

Use these commands to isolate the failing source before rerunning the full gate:

```bash
php artisan booking:release-manifest --json --no-write
php artisan booking:route-gate --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:doctor --strict --json
php artisan notifications:outbox-health --json
npm run verify:package
composer test:security
composer test:orders
composer test:kitchen
composer test:money
composer test:inventory
composer test:release-contract
cd staff-web && npm run build
cd staff-web && npm run smoke:live
```

## Backup, Restore, And Rollback Proof

Backup and restore proof should come from the canonical DR drill:

```bash
php artisan booking:dr-drill --mode=metadata-verify --json
php artisan booking:dr-drill --mode=dry-restore --target-db=<scratch_db> --json
php artisan booking:dr-drill --mode=full-isolated-restore --target-db=<scratch_db> --drop-target-first --json
```

Rollback proof must include:

- previous known-good package basename
- `.metadata.json`
- `.inventory.json`
- `.checksums.sha256`
- `.package.sha256`
- checksum verification command output
- post-rollback commands:
  - `php artisan booking:deploy-check --mode=postflight --strict`
  - `php artisan booking:doctor --strict`
  - `php artisan notifications:outbox-health --json`

## Batch 17 Local Stabilization Evidence

Run date: 2026-04-27 12:31 +07:00.

Scope: local final release-candidate stabilization only. No feature scope was added. Commands were run from the repository root unless a `staff-web` working directory is shown. This local run does not approve promotion because runtime services and manual release evidence are still no-go.

### Command Results

| Command | Result | Classification | Evidence |
| --- | --- | --- | --- |
| `git status --short` | No-go | Known accepted risk pending release-owner documentation | 137 dirty entries were present before the checklist update. Promotion requires a clean tree or a release-ticket dirty artifact list passed with `--allow-dirty=<reason>`. |
| `vendor/bin/pint --test` | Pass | None | `{"result":"pass"}`. |
| `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | Pass | None | `[OK] No errors`. |
| `php artisan booking:route-gate --json` | Pass | None | `ok=true`; 240 runtime routes, 236 expected routes, 0 errors, 0 warnings. |
| `php artisan booking:release-manifest --json` | Pass | None | `ok=true`; no missing fragments; required SQL patch `2026_04_26_000058_cashier_shift_user_fk_contract.sql` present. |
| `npm run verify:package` | Pass | None | `decision=pass`; checked 52 package/artifact items with 0 missing and 0 stale blocking artifacts. |
| `cd staff-web && npm run integrity:check` | Pass | None | `decision=pass`; checked 25 staff-web/package artifact items with 0 missing and 0 stale blocking artifacts. |
| `cd staff-web && npm run build` | Pass | None | UI text encoding guard, staff-web integrity, TypeScript, and Vite production build passed. |
| `php artisan booking:doctor --json` | Fail | Runtime/env blocker | MySQL refused `127.0.0.1:3306` for `restaurantdb`; Redis refused `127.0.0.1:6379`; scheduler blocked by Redis; outbox blocked by DB. |
| `php artisan booking:deploy-check --mode=preflight` | Fail | Runtime/env blocker | `runtime.database` error because DB runtime was unavailable. Artifact checks passed; migration/data/ops runtime inspections were skipped as dependency-blocked warnings. |
| `php artisan booking:verify-select --json` | Pass | None | `ok=true`; selector recorded dirty/staged/untracked paths and recommended release/runtime escalation. |
| SQL-first verifier through `tools/mysql` | Not run | Runtime/env blocker | Local MySQL runtime was unavailable and `mysql` CLI was not found in `PATH`. Do not run `tools/mysql/bootstrap_release.php` against a non-scratch database; provide scratch bootstrap evidence or a reachable scratch MySQL lane. |
| `php artisan notifications:outbox-health --json` | Fail | Runtime/env blocker | DB-backed outbox inspection failed because MySQL refused `127.0.0.1:3306`. |
| `composer test:security` | Pass | None | 47 tests, 321 assertions. |
| `composer test:orders` | Pass | None | 43 tests, 182 assertions. |
| `composer test:kitchen` | Pass | None | 43 tests, 503 assertions. |
| `composer test:money` | Pass | None | 69 tests, 406 assertions. |
| `composer test:inventory` | Pass | None | 21 tests, 220 assertions. |
| `composer test:release-contract` | Pass | None | 193 tests, 3215 assertions. |
| `cd staff-web && npm run smoke:live` | Fail | Runtime/env blocker | Read-only smoke failed at backend health: network failure at `http://127.0.0.1:8000/api/v1/health`. |

### Batch 17 No-Go Classification

| No-go condition | Status | Classification | Required release action |
| --- | --- | --- | --- |
| Dirty worktree | Open | Known accepted risk pending release-owner documentation | Clean the tree or attach a release-ticket dirty artifact inventory and rerun the gate with `--allow-dirty=<reason>`. |
| `booking:doctor` fail | Open | Runtime/env blocker | Start or recover MySQL, Redis, and scheduler heartbeat, then rerun `php artisan booking:doctor --json`. |
| `booking:deploy-check --mode=preflight` fail | Open | Runtime/env blocker | Rerun after DB runtime is reachable; current failure is dependency-blocked, not an artifact-contract failure. |
| SQL bootstrap/verifier proof missing | Open | Runtime/env blocker | Run scratch SQL-first bootstrap/verifier with `tools/mysql/bootstrap_release.php` or attach existing scratch evidence. |
| Redis locks/idempotency not runtime-verified | Open | Runtime/env blocker | Redis refused connection; rerun `booking:doctor` and go-live gate after Redis lock probes pass. |
| Staff-web day-1 smoke not run to pass | Open | Runtime/env blocker | Start backend runtime at `http://127.0.0.1:8000/api/v1` or point smoke env vars at staging and rerun `npm run smoke:live`. |
| Backup/restore drill evidence missing | Open | Known accepted risk pending release-owner evidence | Attach `booking:dr-drill` metadata/dry/full isolated restore evidence or rerun the drill against a scratch target. |
| Rollback plan evidence missing | Open | Known accepted risk pending release-owner evidence | Attach previous known-good package basename, sidecars, checksums, and post-rollback gate plan. |
| P0/P1 disposition evidence missing from this local run | Open | Known accepted risk pending release-owner evidence | Attach the release ticket or file proving every P0/P1 is fixed or explicitly mitigated. |

### Batch 17 Cleared Conditions

- No code blocker was identified by the commands that completed.
- Cross-branch/security/capability ladder passed through `composer test:security`.
- Money flow tests were run and passed through `composer test:money`.
- Order, KDS, inventory, and release-contract ladders passed.
- Route inventory and release manifest passed; cashier-shift FK verifier artifacts are present in the manifest and release-contract ladder.
- Package integrity, staff-web integrity, and staff-web production build passed.

## Release Candidate Evidence - 2026-04-29

Run date: 2026-04-29 10:03 +07:00.

Scope: local release-candidate evidence pass after P1/P2 hardening batches. This report is safe to hand to an operator/reviewer, but it is a no-go for both staging and limited production because P1 runtime blockers remain open and limited-production manual evidence is missing.

### Candidate Identity

| Field | Value |
| --- | --- |
| Commit SHA | `fc99d8f5b25d4ea928276ea0e8b480c1d8d74a2f` |
| Branch | `main` |
| Last commit subject | `update` |
| Last commit date | `2026-04-28T17:29:53+07:00` |
| Environment target evaluated | `staging`; `limited-production` also checked without manual evidence |
| Local environment classification | `local`, non-production |
| Worktree hygiene | No-go: 86 dirty entries were present before this report section was appended |
| Promotion decision | Not ready for staging; not ready for limited production |

### Command Results

| Command | Result | Classification | Evidence |
| --- | --- | --- | --- |
| `git rev-parse --abbrev-ref HEAD` | Pass | Metadata | `main`. |
| `git rev-parse HEAD` | Pass | Metadata | `fc99d8f5b25d4ea928276ea0e8b480c1d8d74a2f`. |
| `git status --short` | No-go | Release hygiene blocker | 86 dirty entries were present before this report update. Promotion requires a clean tree or a release-ticket dirty artifact inventory with owner acceptance. |
| `vendor/bin/pint --test` | Pass | Formatting | `{"result":"pass"}`. |
| `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | Pass | Static analysis | `[OK] No errors`; used `phpstan.neon.dist`. |
| `php artisan test` | Blocked | Full suite not proven | Timed out after 60 minutes without a final PHPUnit summary. Timed-out PHP processes were stopped before later gates. Do not count full-suite CI as passed. |
| `composer test:critical` | Pass | Critical local CI ladder | Security, orders, kitchen/KDS, money, inventory, and release-contract ladders completed successfully. Release-contract tail summary: 202 tests, 3394 assertions. |
| `staff-web: npm run integrity:check` | Pass | Split-web contract/build gate | `decision=pass`; 25 checked items; 0 missing, stale, or contract failures. |
| `staff-web: npm run build` | Pass | Frontend build | Encoding guard, integrity check, TypeScript, and Vite production build passed. |
| `customer-web: npm run build` | Pass with warning | Frontend build | Contract governance and Next build passed. Warning: `customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts` has local generated-contract changes that must trace back to `npm --prefix customer-web run sync:contracts`. |
| `php artisan booking:doctor --strict --json` | Fail | P1 runtime blocker | DB refused `127.0.0.1:3306`; Redis refused `127.0.0.1:6379`; scheduler blocked by Redis; outbox blocked by DB. Artifact: `storage/app/booking_release/doctor/reports/booking-doctor-strict-20260429t025954z.json`. |
| `php artisan notifications:outbox-health --json` | Fail | P1 runtime blocker | DB-backed outbox inspection failed because MySQL refused `127.0.0.1:3306`. |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | Fail | P1 runtime blocker | `runtime.database` failed. Schema dump, full dump, patch inventory, release manifest, and temporary artifact checks passed. Artifact: `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260429t030024z.json`. |
| `php artisan booking:release-manifest --json` | Pass | Release artifact integrity | `ok=true`; 59 SQL patches present, 52 required, 0 missing; no missing contract fragments. Artifact: `storage/app/booking_release/release_manifest/reports/booking-release-manifest-snapshot-20260429t030030z.json`. |
| `php artisan booking:launch-readiness --target=staging --json` | Fail | Staging readiness blocker | `decision=not_ready`; 5 blocking failures, 12 major warnings. Artifact: `storage/app/booking_release/launch_readiness/reports/launch-readiness-staging-20260429t030036z.json`. |
| `php artisan booking:launch-readiness --target=limited-production --json` | Fail | Limited-production readiness blocker | `decision=not_ready`; 11 blocking failures, 7 major warnings. Artifact: `storage/app/booking_release/launch_readiness/reports/launch-readiness-limited-production-20260429t030235z.json`. |
| `php artisan booking:deploy-check --mode=postflight --strict --json` | Fail | Postflight target blocker | No deployed target runtime was available locally; command failed on `runtime.database`. Artifact: `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-postflight-strict-20260429t030110z.json`. |
| GitHub Actions full CI | Blocked | External evidence unavailable | `gh` is not installed in this local environment, so no remote workflow run was inspected. Local monolithic `php artisan test` timed out; local `composer test:critical` passed. |

### Readiness Summary

| Target | Decision | Evidence |
| --- | --- | --- |
| Staging | Not ready | `launch-readiness-staging-20260429t030036z.json` reports 5 blocking failures and 12 major warnings. |
| Limited production | Not ready | `launch-readiness-limited-production-20260429t030235z.json` reports 11 blocking failures and 7 major warnings. |

### Passed Gates

| Gate | Evidence |
| --- | --- |
| Formatting | `vendor/bin/pint --test` passed. |
| Static analysis | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` passed. |
| Critical local CI ladder | `composer test:critical` passed. |
| Staff-web integrity/build | `npm run integrity:check` and `npm run build` passed under `staff-web`. |
| Customer-web build | `npm run build` passed under `customer-web`, with generated SDK dirty-warning retained. |
| API route and OpenAPI contract inside launch-readiness | Staging and limited-production launch-readiness both report `api_surface_contract=pass`. |
| Day-1 feature flag posture | Staging and limited-production launch-readiness both report `feature_flag_posture=pass`. |
| Release artifact integrity | `booking:release-manifest --json` passed; launch-readiness reports release artifact integrity as pass. |

### Failed Gates

| Gate | Failure |
| --- | --- |
| Runtime doctor | MySQL and Redis were unreachable; scheduler and outbox were dependency-blocked. |
| Notification outbox health | DB-backed inspection could not run because MySQL was unreachable. |
| Deploy preflight | Failed on `runtime.database`; DB-dependent migration, data, and ops guards were skipped as dependency-blocked. |
| Deploy postflight | Failed on `runtime.database`; no deployed target runtime was available locally. |
| Staging launch-readiness | `not_ready` due runtime blockers; manual UAT, DR, performance, payment, and notification evidence remain major warnings. |
| Limited-production launch-readiness | `not_ready` due runtime blockers and missing manual UAT, DR, performance, payment, notification, and concurrency evidence. |

### Blocked Or Skipped Gates

| Gate | Status | Reason |
| --- | --- | --- |
| Full PHPUnit suite | Blocked | `php artisan test` timed out after 60 minutes without a final summary. |
| GitHub Actions full CI | Blocked | `gh` CLI is unavailable locally; no remote CI run was inspected. |
| Core ops, Round 5, alert snapshot, package-release inside launch-readiness | Skipped by command | Launch-readiness skipped heavier downstream checks because `booking:doctor` reported runtime dependency blockers. |
| SQL-first scratch bootstrap/verifier | Not run | No scratch MySQL runtime was available in this local evidence pass. Do not run bootstrap or restore commands against non-scratch data. |
| Staff-web live smoke | Not run | Backend runtime at the target API URL was unavailable. |
| Customer-web browser smoke | Not run | Not part of the requested command chain, and no deployed target runtime was available. |

### Manual Evidence References

| Evidence item | Staging status | Limited-production status | Required follow-up |
| --- | --- | --- | --- |
| UAT scenario replay | Missing, major warning | Missing, blocking | Run the day-1 UAT scenario pack and record schema-valid evidence without demo credentials or tokens. |
| Performance verification | Missing, major warning | Missing, blocking | Run candidate-specific `booking:performance-verify` using the staging profile; local smoke timing is not enough. |
| Payment provider / customer self-pay readiness | Missing, major warning | Missing, blocking | If customer self-pay stays disabled, record explicit staff-settlement-only evidence. If enabled, attach provider mode, webhook, signature, idempotency, failure/cancel, and settlement reconciliation proof without secrets. |
| DR / backup restore | Missing, major warning | Missing, blocking | Run metadata/dry/full isolated DR drill against a scratch restore target or attach reviewed restore evidence. |
| Notification provider rehearsal | Missing, major warning | Missing, blocking | Run one real external notification delivery rehearsal and archive outbox attempt plus recipient confirmation. |
| Multi-process concurrency rehearsal | Not required for staging by launch-readiness | Missing, blocking | Run Redis/MySQL multi-process contention rehearsal for limited production. |

### Known Accepted Risks

No risk is accepted by this local report. The dirty worktree, missing manual evidence, unavailable runtime services, and unproven full CI remain open blockers until a release owner explicitly signs and links evidence.

### Owner Signoff Checklist

- [ ] Release owner: approve or reject this candidate after reviewing the failed and blocked gates.
- [ ] Backend owner: provide a clean tree or dirty-artifact release inventory, then rerun the evidence chain.
- [ ] Ops owner: provide reachable MySQL, Redis, scheduler heartbeat, outbox health, and SQL-first scratch bootstrap evidence.
- [ ] QA/UAT owner: attach UAT scenario replay evidence.
- [ ] Performance owner: attach staging performance verification report.
- [ ] Payments owner: attach staff-settlement-only or provider readiness evidence.
- [ ] DR owner: attach backup/restore drill evidence.
- [ ] Frontend owner: confirm generated customer SDK changes came from the canonical sync chain and rerun split-web build/smoke against the target runtime.
- [ ] Release owner: archive the exact launch-readiness, deploy-check, doctor, release-manifest, CI, UAT, performance, payment, and DR artifacts in the release ticket.

## Sign-Off Rule

## Batch 2 Release Evidence - 2026-04-29

### Scope

This evidence pass covers the Batch 1 finance/security hardening follow-up:

- `payments.cashier_shift_id` nullable FK/index and required SQL patch `2026_04_29_000062_payment_cashier_shift_link.sql`
- payment, refund, and staff deposit capture rows persist the open cashier shift id
- cashier-shift reconciliation uses `cashier_shift_id` first and falls back to the old cashier/branch/time-window/currency model only for legacy rows where `cashier_shift_id` is `NULL`
- `generic_http_hmac` webhook verification rejects missing timestamp headers when `max_age_seconds > 0`

### Dirty Worktree Classification

| Class | Paths |
| --- | --- |
| Batch 1 owned | `app/Modules/Cashiering/Application/UseCases/Shifts/StaffCashierShiftService.php`, `app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php`, `app/Modules/Payments/Application/UseCases/Capture/PaymentCaptureService.php`, `app/Modules/Payments/Application/UseCases/Capture/StaffReservationDepositService.php`, `app/Modules/Payments/Application/UseCases/Refunds/RefundExecutionService.php`, `app/Modules/Payments/Domain/Models/Payment.php`, `app/Modules/Payments/Infrastructure/Integrations/PaymentProviders/GenericHttpHmacPaymentProviderAdapter.php`, `database/schema/mysql-schema.sql`, `db_all.sql`, `database/patches/2026_04_29_000062_payment_cashier_shift_link.sql`, `config/booking_release.php`, `tools/mysql/verify_release_contract.sql`, `storage/app/booking_release/release_manifest_snapshot.json`, and the related finance/webhook/release tests. |
| Generated or evidence artifacts | `storage/app/booking_release/release_manifest_snapshot.json`, `storage/phpstan/resultCache.php`, existing generated API/SDK/package artifacts under `build/` or split-web generated contract paths when already dirty. |
| Outside Batch 1/2 scope | Existing dirty platform health/release services, CI scripts, split-web files, config files outside the cashier/payment change, broad runbook edits, reporting/inventory changes, and untracked runtime/CI/service additions. These were not deleted or reset in this pass. |

### Command Results

| Command | Result | Notes |
| --- | --- | --- |
| `git rev-parse --short HEAD` | Pass | `fc99d8f5`. |
| `git status --short` | Blocked hygiene | 103 dirty entries after local evidence commands and doc updates; release owner must approve a dirty artifact inventory or provide a clean tree. |
| `composer test` | Blocked | Composer process timeout at 300 seconds while `php artisan test` was still running. No assertion failure was reported before timeout. |
| `php artisan test` | Blocked | Timed out after 60 minutes without a final PHPUnit summary. Do not count full suite as passed. |
| `vendor/bin/pint --test` | Pass | `{"result":"pass"}`. |
| `vendor/bin/phpstan analyse` | Pass | `[OK] No errors`; 990 files analysed with `phpstan.neon.dist`. |
| `php artisan booking:release-manifest --json --no-write` | Pass | `ok=true`, `issues=[]`, 60 SQL patches present, 53 required, 0 missing. |
| `php artisan booking:release-manifest --json --verify-frozen --no-write` | Pass | Frozen snapshot matched live manifest; `mismatch_paths=[]`. |
| `php artisan booking:route-gate --json` | Pass | Runtime route inventory matched locked inventory; error and warning counts were 0. |
| `php artisan booking:api-contract --json` | Pass | Runtime OpenAPI generation completed without write. |
| `npm run verify:package` | Pass | `decision=pass`, 52 checked, 0 missing/stale/contract failures. |
| `php artisan booking:doctor --json` | Fail | MySQL refused `127.0.0.1:3306`; Redis refused `127.0.0.1:6379`; scheduler blocked by Redis; outbox blocked by DB. |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | Fail | `runtime.database` failed; DB-dependent migration/data/ops guards were skipped. SQL artifacts and release manifest checks passed. |
| `php artisan test tests/Feature/Runtime/RuntimeMysqlRedisSmokeTest.php --stop-on-failure` | Fail / environment blocked | Runtime smoke intentionally failed because the test process used SQLite; the lane requires `DB_CONNECTION=mysql` against a SQL-first bootstrapped MySQL database plus Redis. |
| `php artisan schedule:list` | Partial | Scheduler entries are registered, including `scheduler-heartbeat`, but no live heartbeat can be proven without Redis and a running scheduler process. |
| `Test-NetConnection 127.0.0.1:3306` and `127.0.0.1:6379` | Fail | Both TCP probes returned `TcpTestSucceeded=False`. |

### Production Env Posture

| Item | Local evidence | Production-readiness status |
| --- | --- | --- |
| `APP_ENV=production` | `.env` has `APP_ENV=local`. | Blocked for production proof. |
| `APP_DEBUG=false` | `.env` has `APP_DEBUG=true`. | Blocked for production proof. |
| `APP_KEY` | Present, redacted. | Pass for presence only. |
| `CUSTOMER_AUTH_JWT_SECRET` | Missing in `.env`; `booking:doctor` warned it is not configured with valid length. | Blocked. |
| Staff DB-backed keys | Runtime config reports `database_store_enabled=true`. | Pass, but actual active DB key count could not be proven without MySQL. |
| Env fallback disabled | Runtime config reports staff env fallback and role-name fallback disabled. | Pass. |
| CORS exact origins | Doctor reported 6 exact origins and 0 invalid origins for local config. | Pass shape; operator must set production origins explicitly. |
| Redis/cache/session/queue | Local `.env` uses file cache/session and sync queue; Redis host configured but unreachable. | Blocked for production runtime. |
| Scheduler | Schedule entries exist, but heartbeat check is blocked by Redis. | Blocked until scheduler process and Redis are live. |

### Operator Follow-Up

- Provide a production-like `.env` or target runtime with `APP_ENV=production` or approved production-like target, `APP_DEBUG=false`, real `APP_KEY`, valid `CUSTOMER_AUTH_JWT_SECRET`, exact CORS origins, durable cache/session/queue choices, and Redis required for booking APIs.
- Bootstrap a scratch or staging MySQL database through the SQL-first path, not `php artisan migrate`, then apply/verify `2026_04_29_000062_payment_cashier_shift_link.sql`.
- Start Redis and exactly one scheduler lane, then rerun `booking:doctor`, deploy preflight, outbox health, and `RuntimeMysqlRedisSmokeTest`.
- Rerun full CI or `php artisan test` in an environment that can finish the monolithic suite.
- Archive the `booking:doctor`, `booking:deploy-check`, `booking:release-manifest`, route gate, package integrity, runtime smoke, UAT, DR, performance, and payment-provider evidence in the release ticket.

The candidate is go-live eligible only when:

- `go-live-check-latest.json` has `decision=pass`
- every no-go condition above is absent
- manual evidence references are reachable from the release ticket
- the release ticket links the exact generated artifact directory or CI artifact bundle
