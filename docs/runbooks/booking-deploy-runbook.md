# Booking deploy runbook

## Pre-deploy

1. Run the canonical launch-readiness gate
   - `php artisan booking:launch-readiness --target=staging --json`
   - for limited production, re-run with `--target=limited-production --manual-evidence=<path>`
   - the limited-production manual evidence file must record `pass` for `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`
2. Drill into failing sources only when the aggregated gate is not clean
   - `php artisan booking:doctor --strict`
   - `php artisan booking:deploy-check --mode=preflight --strict`
   - `php artisan booking:release-manifest --verify-frozen --json`
   - `php artisan booking:package-release --verify-frozen --json`
   - if `booking:deploy-check --mode=preflight --strict` reports `data.purchase_receipt_lineage_uniqueness`, stop the rollout and deduplicate the listed `ingredient_stock_movements.reference_id` values before applying `database/patches/2026_04_13_000051_inventory_stock_movement_reference_uniqueness.sql`
3. Run smoke suite
   - `bash scripts/ci/booking-smoke-gate.sh`
   - `bash scripts/ci/booking-reliability-smoke.sh`
   - `bash scripts/ci/booking-ops-smoke.sh`
4. Confirm Redis and DB credentials point to the intended environment.
5. Confirm scheduler is enabled on only the intended node set.
6. If this is a first-site rollout, prepare the initial bootstrap inputs:
   - branch code/name/timezone/currency
   - bootstrap admin/staff usernames
   - whether the first staff API key should be rotated or freshly issued
7. Decide payment day-1 mode explicitly:
   - `PAYMENT_CUSTOMER_SELF_PAY_ENABLED=false` keeps the rollout in staff-settlement-only mode.
   - Enabling customer self-pay requires `generic_http_hmac` to be configured with base URL, request signing secret, and webhook secret.

## Deploy

1. Put the immutable package artifact in place.
2. Extract into a clean target directory.
3. Run migrations.
4. Clear stale caches before warming fresh ones:
   - `php artisan config:clear`
   - `php artisan cache:clear`
   - `php artisan route:clear`
   - `php artisan view:clear`
5. First-site bootstrap only:
   - `php artisan booking:bootstrap-site --json`
   - capture the returned bootstrap staff API key plaintext once if `staff_api_key.action` is `issued` or `rotated`
6. Rebuild reporting snapshots immediately after bootstrap or after importing production data:
   - `php artisan booking:reporting-snapshots:rebuild --days=7 --json`
7. Warm config/routes cache if your deployment model requires it.
8. Run postflight gate
   - `php artisan booking:deploy-check --mode=postflight --strict`
9. Run ops diagnostics
   - `php artisan booking:doctor --strict`
   - `php artisan booking:ops-snapshot --json`
   - `php artisan notifications:outbox-health --json`
10. Hit health endpoints with staff auth for detailed checks.

## Runtime topology

- Required services:
  - web app / PHP runtime
  - MySQL
  - Redis
  - scheduler on one active node set
- Required scheduled tasks for business closure:
  - table hold expiry
  - no-show marking
  - waiting-list notified expiry
  - reservation reminder enqueue
  - notification outbox processing
  - reservation expiry
  - reporting snapshot rebuild
- Worker model:
  - no separate queue worker is required for the current booking outbox path
  - notification outbox processing is scheduler-driven through `notifications:process-outbox`
- Logging and audit:
  - keep the `audit` log channel writable
  - retain `booking:doctor`, `booking:deploy-check`, `booking:release-manifest`, `booking:launch-readiness`, `booking:ops-snapshot`, and `notifications:outbox-health` output as release evidence for staging/UAT
  - `booking:ops-snapshot` is the fastest place to confirm non-core readiness for kitchen/KDS, inventory/purchasing, and conversation inbox before drilling into domain runbooks
  - when inventory/purchasing is the blocker, `booking:ops-snapshot --json` now exposes `duplicate_purchase_receipt_reference_count`, `duplicate_purchase_receipt_movement_count`, and `duplicate_purchase_receipt_reference_examples[]` so duplicate receipt lineage can be cleaned up before the uniqueness patch is applied
  - `booking:doctor` writes JSON/Markdown artifacts under `storage/app/booking_release/doctor/reports/`
  - `booking:deploy-check` writes JSON/Markdown artifacts under `storage/app/booking_release/deploy_checks/reports/`
  - `booking:release-manifest` writes JSON/Markdown report artifacts under `storage/app/booking_release/release_manifest/reports/` while keeping the frozen snapshot at `storage/app/booking_release/release_manifest_snapshot.json`
  - `booking:launch-readiness`, `booking:performance-verify`, and `booking:dr-drill` all keep their timestamped and latest evidence under `storage/app/booking_release/*/reports/`

## Day-1 bootstrap operations

- Customer self-service credentials:
  - `php artisan customer-auth:access-sessions:issue <user_id> --json`
  - `php artisan customer-auth:access-sessions:list --include-revoked --json`
  - `php artisan customer-auth:access-sessions:show <access_session_id> --json`
  - `php artisan customer-auth:access-sessions:revoke <access_session_id> --json`
- Staff internal credentials:
  - `php artisan staff-auth:api-keys:issue <user_id> "<label>" --json`
  - `php artisan staff-auth:api-keys:list --include-revoked --json`
  - `php artisan staff-auth:api-keys:revoke <staff_api_key_id> --json`
  - `php artisan staff-auth:api-keys:rotate <staff_api_key_id> --json`

## Rollback triggers

Rollback immediately if any of these are true:
- postflight reports pending migrations
- health status becomes `fail`
- outbox stale processing count keeps rising
- scheduler heartbeat becomes stale
- refund or checkout smoke requests begin returning 5xx
- immutable package checksum does not match the expected sidecar

## Rollback package kit

Before promotion, retain the previous known-good immutable package together with:

- matching `.metadata.json`
- matching `.inventory.json`
- matching `.checksums.sha256`
- matching `.package.sha256`
- the release record that says why that package was last known-good

Do not rely on `build/booking-release/latest-package.json` alone during rollback triage. It points at the most recently built package, not necessarily the previous good deployment candidate.
Before promotion, copy the exact `package_basename`, `package_path`, and sidecar paths from the promoted candidate's `.metadata.json` or `build/booking-release/latest-package.json` into the release ticket. The rollback kit must point at that recorded known-good basename, not a hand-written shortcut.

## Rollback procedure

1. Select the previous known-good package basename from the archived rollback kit and release record.
   - example basename shape: `restaurantpos-backend-release-20260420t004220z`
2. Verify the tarball checksum against the sidecar:
   - `sha256sum -c build/booking-release/<recorded-known-good-package-basename>.package.sha256`
3. Extract the package into a clean rollback directory:
   - `tar -xzf build/booking-release/<recorded-known-good-package-basename>.tar.gz -C <rollback-root>`
4. From the extracted package root, verify the staged file checksums:
   - `sha256sum -c release_checksums.sha256`
5. Re-point the deployment to that rollback directory and clear stale caches before warming the restored release:
   - `php artisan config:clear`
   - `php artisan cache:clear`
   - `php artisan route:clear`
   - `php artisan view:clear`
6. Re-run the postflight/runtime gates on the rollback candidate:
   - `php artisan booking:deploy-check --mode=postflight --strict`
   - `php artisan booking:doctor --strict`
   - `php artisan notifications:outbox-health --json`
7. Archive the rollback verification output beside the rollback package evidence.

## CI / release workflows

- Default repository gate: `.github/workflows/booking-ci.yml`
- Manual release evidence gate: `.github/workflows/booking-release-gate.yml`
- Bootstrap helper: `scripts/ci/booking-ci-bootstrap.sh`
- Repository prerequisite guard: `scripts/ci/booking-repo-prereq-check.sh`
- Packaging helper: `scripts/release/package_release.sh`
- Canonical local release build command: `php artisan booking:release-build`

Run the manual release evidence workflow before promoting a build to staging or production so that `build/booking-ci/`, `build/booking-release/`, and `storage/app/booking_release/` artifacts are archived from CI, not only from an operator laptop.

See also:
- `docs/runbooks/booking-alerting-runbook.md`
- `docs/runbooks/booking-release-packaging-runbook.md`
