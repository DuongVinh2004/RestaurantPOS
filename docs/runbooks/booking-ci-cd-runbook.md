# Booking CI/CD runbook

## Purpose

This repository now carries first-class GitHub Actions workflows for booking backend validation:

- `.github/workflows/booking-ci.yml`
- `.github/workflows/booking-release-gate.yml`

The CI workflow is the default push / pull-request gate.
The release-gate workflow is a manual promotion evidence run that preserves machine-readable release artifacts.

Canonical release build path used by local tooling:

1. `php artisan booking:api-contract --write`
2. `php artisan booking:api-artifacts:generate`
3. `php artisan booking:release-manifest --write`
4. `php artisan booking:package-release --verify-frozen`

`php artisan booking:release-build`, `composer release:package`, and `scripts/release/package_release.*` all follow that path explicitly.

## Required repository prerequisites

Apply this patch set in the full backend repository root. The workflows expect these files to exist:

- `artisan`
- `composer.json`
- `tools/mysql/bootstrap_release.sh`
- `scripts/ci/booking-full-gate.sh`
- `scripts/ci/booking-release-gate.sh`
- `scripts/release/package_release.sh`
- patch 1 release freeze support in `ReleaseArtifactManifestService`
- patch 6 immutable release packaging in `ReleasePackageService`

The guard script `scripts/ci/booking-repo-prereq-check.sh` fails fast when those prerequisites are missing.

## Targeted verification selector

Use the selector before broad CI gates when you want a deterministic changed-file based ladder:

- local, explicit paths: `php artisan booking:verify-select --path=app/Services/Staff/StaffCheckoutService.php --json`
- local, composer alias: `composer verify:select -- --path=routes/api.php`
- CI/base diff: `bash scripts/ci/booking-verify-select.sh --base=origin/main`
- CI via env override: set `BOOKING_VERIFY_BASE=origin/main` and run `bash scripts/ci/booking-verify-select.sh`

The wrapper writes `build/booking-ci/booking-verify-select.json` so the selected ladder is easy to inspect in failed CI jobs.

Escalation behavior:

- `routes/api.php`, `app/Http/Requests/*`, or `app/Http/Resources/*`: add API artifact regeneration and `booking:route-gate`
- `database/schema/*`, `database/patches/*`, `db_all.sql`, `tools/mysql/*`, `scripts/ci/*`, or `composer.json`: add release/runtime checks such as `booking:doctor`, `booking:deploy-check`, and `booking:release-manifest`
- auth/capability seams: add auth/RBAC suites
- payment/checkout seams: add checkout/payment suites and `booking:round5-gate`
- docs-only changes: selector returns notes only and does not default to `php artisan test`

The selector does not replace `scripts/ci/booking-full-gate.sh` or `scripts/ci/booking-release-gate.sh`. It is the practical first rung for local work and selective CI workflows, while the full gate and release gate remain the canonical broad evidence steps.

## CI job flow

`booking-backend-ci` performs:

1. checkout
2. PHP toolchain setup
3. MySQL client install
4. repository prerequisite verification
5. composer install + release DB bootstrap via `scripts/ci/booking-ci-bootstrap.sh`
6. `scripts/ci/booking-full-gate.sh`
7. artifact upload from:
   - `build/booking-ci/`
   - `build/booking-release/`
   - `storage/app/booking_release/`
   - `storage/logs/`

## Release gate flow

`booking-release-gate` is manual (`workflow_dispatch`) and performs:

1. the same bootstrap sequence as CI
2. `scripts/ci/booking-release-gate.sh`
3. canonical release build via `booking:release-build`
4. artifact upload with 30-day retention

This workflow is the canonical machine-readable evidence producer for release sign-off because it captures:

- doctor JSON
- deploy preflight JSON
- release manifest JSON with `--verify-frozen`
- postflight JSON
- canonical release loop JSON
- package sidecars from `build/booking-release/`
- `staff-web` live smoke evidence under `storage/app/booking_release/release_loop/`

For limited-production sign-off, attach one operator-supplied manual evidence JSON to the same release record. That file must record `pass` for `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`. The workflow does not fabricate those external rehearsals on its own.

## Environment model

The workflows assume ephemeral CI services:

- MySQL 8.0
- Redis 7
- Node.js 20 for `staff-web` test/build/smoke in the manual release gate

Important defaults baked into the workflow env:

- `DB_CONNECTION=mysql`
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=database`
- `SESSION_DRIVER=database`
- `MAIL_MAILER=log`
- `BOOKING_CI_BOOTSTRAP_STAFF_WEB=true` on the manual release-gate workflow so `staff-web/node_modules` is installed before the release loop runs

Preview/observability truth rules:

- `booking:release-loop` only records preview proof when a real preview URL or preview deploy command is supplied for the candidate build
- if no linked preview project exists and no preview input is supplied, the report records `preview.status=unconfigured`
- if `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, or `SENTRY_PROJECT` are absent, the report records `observability.status=missing-configuration`
- those statuses are explicit external blockers for preview/staging evidence, even when the rest of the release loop is green

## Promotion guidance

Recommended promotion sequence:

1. merge only after `booking-backend-ci` is green
2. run `booking-release-gate` on the intended release ref
3. optionally pass `preview_url` / `preview_label` into the workflow dispatch form so the release evidence records the preview candidate explicitly
4. if staging observability is wired, make sure the same candidate also has runtime logs and release-tag evidence attached to the release record
5. for limited production, attach the manual evidence JSON that marks the five required manual checks as `pass`
6. archive the uploaded release evidence together with the immutable package and sidecars
7. keep the previous known-good package plus sidecars available as the rollback kit
8. deploy the frozen package artifact to the target environment
9. run post-deploy verification from `docs/runbooks/booking-deploy-runbook.md`

Inside the manual release-gate workflow, `scripts/ci/booking-release-gate.sh` now:

1. runs the backend full gate
2. starts `php artisan serve` for local live smoke
3. runs `php artisan booking:release-loop --json`
4. persists the combined backend + `staff-web` evidence bundle

## Notes

- The workflows intentionally stop short of server deployment because deployment transport, secret storage, and environment rollout are infrastructure-specific.
- The release gate therefore acts as the non-negotiable CI evidence boundary before any environment-specific CD step is added later.
- The immutable package is `.tar.gz` so the workflow only requires PHP `PharData`, not `ZipArchive`.
