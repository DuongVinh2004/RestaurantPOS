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

## Required Checklist

| Area | Required proof | Source |
| --- | --- | --- |
| Repository hygiene | `git status --porcelain` is clean, or dirty artifacts are listed in the release ticket and passed through `--allow-dirty=<reason>` | go-live script |
| P0/P1 disposition | Every P0/P1 is fixed or explicitly mitigated with owner/date/rollback note | `--p0p1-evidence` |
| App environment | `APP_ENV` is production-like (`staging`, `production`, or `limited-production`) | go-live script |
| Debug mode | `APP_DEBUG=false` | go-live script |
| Customer auth secret | `CUSTOMER_AUTH_JWT_SECRET` is non-placeholder and at least 32 characters | go-live script |
| Staff auth key source | `STAFF_AUTH_DATABASE_STORE_ENABLED=true`; env fallback and role-name fallback are disabled | go-live script plus `booking:doctor` |
| DB reachability | MySQL ping and DB-dependent deploy checks pass | `php artisan booking:doctor --strict --json`; `php artisan booking:deploy-check --mode=preflight --strict --json` |
| Redis reachability | Redis set/get and lock probe pass | `php artisan booking:doctor --strict --json` |
| Scheduler heartbeat | `runtime.scheduler.ok=true`; Redis blocker is cleared first if scheduler is dependency-blocked | `php artisan booking:doctor --strict --json` |
| Outbox health | Outbox health has no failed/stale blocker | `php artisan notifications:outbox-health --json`; `booking:doctor` |
| SQL bootstrap/verifier | Scratch bootstrap applies `database/schema/mysql-schema.sql`, all `database/patches/*.sql`, and `tools/mysql/verify_release_contract.sql` | `--run-sql-bootstrap` or `--sql-bootstrap-evidence` |
| Route gate | Runtime API surface matches the locked route inventory | `php artisan booking:route-gate --json` |
| Release manifest | Required artifacts, SQL patches, OpenAPI, and FK fragments are present | `php artisan booking:release-manifest --json` |
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
php artisan booking:release-manifest --json
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

## Sign-Off Rule

The candidate is go-live eligible only when:

- `go-live-check-latest.json` has `decision=pass`
- every no-go condition above is absent
- manual evidence references are reachable from the release ticket
- the release ticket links the exact generated artifact directory or CI artifact bundle
