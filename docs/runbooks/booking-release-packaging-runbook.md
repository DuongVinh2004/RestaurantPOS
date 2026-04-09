# Booking immutable release packaging runbook

## Purpose

Build a single immutable release artifact for the booking backend, together with machine-readable sidecars:

- release package: `build/booking-release/<package>.tar.gz`
- metadata sidecar: `*.metadata.json`
- inventory sidecar: `*.inventory.json`
- checksum sidecar: `*.checksums.sha256`
- package checksum sidecar: `*.package.sha256`
- latest pointer: `build/booking-release/latest-package.json`

## Preconditions

1. Release manifest passes.
   - `php artisan booking:release-manifest --verify-frozen --json`
2. Preflight deploy gate passes.
   - `php artisan booking:deploy-check --mode=preflight --strict`
3. The repository is the full backend root, not a source-only export.
4. `storage/app/booking_release/release_manifest_snapshot.json` is already frozen and fresh.

## Canonical build path

The canonical release pipeline is:

1. `php artisan booking:api-contract --write`
2. `php artisan booking:api-artifacts:generate`
3. `php artisan booking:release-manifest --write`
4. `php artisan booking:package-release --verify-frozen`

Use the explicit orchestration command when you want that whole path to run in one step:

- `php artisan booking:release-build --json`
- `composer release:package`
- `bash scripts/release/package_release.sh --json`
- `scripts\release\package_release.cmd --json`

`booking:release-build` now also embeds lightweight harness context for split-web and runtime follow-up:

- `harness.web_auth`: header-based auth/session contract summary for `customer-web` and `staff-web`
- `harness.golden_flows`: canonical scenario keys, manifest availability, and runtime gate commands
- `harness.recommended_commands`: the next release gates to run after packaging

For the full release-candidate loop that also verifies `staff-web`, use `php artisan booking:release-loop` or `composer release:loop`. `booking:release-build` remains the packaging-centric command; `booking:release-loop` is the broader backend + split-web evidence chain.

## Build the package

Default generated package id:

- `php artisan booking:package-release --verify-frozen --json`

Explicit package id:

- `php artisan booking:package-release --verify-frozen --package-id=staging-20260321-r1 --json`

Shell wrapper:

- `bash scripts/release/package_release.sh --json`

Windows wrapper:

- `scripts\release\package_release.cmd --json`

`booking:package-release` remains available as the frozen-input packaging step. It does not regenerate OpenAPI, consumer artifacts, or the frozen manifest by itself.

To include resolved golden-flow context in the release build output, pass the same UAT manifest that FE and operator tooling consume:

- `php artisan booking:release-build --json --uat-manifest=storage/app/uat/scenario-pack.json`

## Release candidate evidence loop

Use this higher-level path when you need one bundle that covers contract regeneration, backend harnesses, `staff-web` verification, preview metadata, live smoke, and launch-readiness evidence:

- `php artisan booking:release-loop --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --bootstrap-uat`
- `composer release:loop -- --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --bootstrap-uat`

Optional preview metadata:

- `--preview-url=https://preview.example.com`
- `--preview-label=vercel-preview`
- `--preview-command="<platform-specific preview deploy command>"`

The release-loop report now records preview and observability context explicitly:

- `preview.status=url-recorded` means a preview URL was supplied, but runtime logs and release tagging still need to come from the target platform
- `preview.status=unconfigured` means no linked preview project was detected and no preview URL/command was supplied
- `observability.status=missing-configuration` means release/runtime evidence is still missing required provider credentials such as `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, and `SENTRY_PROJECT`

Treat `preview.status=unconfigured` and `observability.status=missing-configuration` as external promotion blockers, not as soft green evidence.

The release-loop report is written under:

- `storage/app/booking_release/release_loop/reports/`

`staff-web` smoke evidence created inside that loop is written under the step artifact tree, currently:

- `storage/app/booking_release/release_loop/steps/<target>/<timestamp>/staff-web-smoke/`

For limited-production go/no-go, archive the release-loop report together with the launch-readiness manual evidence JSON that records these checks as `pass` for the same candidate:

- `uat_scenario_pack_replay`
- `performance_verification_report`
- `payment_provider_external_e2e`
- `notification_provider_external_e2e`
- `concurrency_rehearsal`

That manual evidence JSON remains operator-supplied. The release loop does not synthesize those external rehearsals by itself.

## What gets packaged

Configured release roots come from `config/booking_release.php` and currently include:

- `artisan`
- `composer.json`
- `app/`
- `bootstrap/`
- `build/api-consumer/`
- `config/`
- `database/`
- `routes/`
- `storage/app/booking_release/`
- optional runbooks / CI scripts / MySQL tools / `db_all.sql`

## Operator checks after build

1. Confirm the package exists at the expected `build/booking-release/*.tar.gz` path.
2. Confirm sidecars exist next to the package.
3. Confirm `latest-package.json` points at the newly built package.
4. Archive the package and sidecars with the release ticket or CI artifact store.
5. Deploy only from this immutable package, not from a mutable working tree.

Before promotion, also retain the previous known-good package tarball plus its matching `.metadata.json`, `.inventory.json`, `.checksums.sha256`, and `.package.sha256` sidecars. `latest-package.json` is only a pointer to the most recently built package; it is not the rollback decision record by itself.

When using `scripts/ci/booking-release-gate.sh`, also archive:

- `build/booking-ci/booking-release-build.json`
- `build/booking-ci/booking-harness-web-auth.json`
- `build/booking-ci/booking-harness-golden-flows.json`

## Failure cases that must block promotion

- frozen manifest is stale / missing / invalid
- package path already exists and overwrite was not intentional
- a required release root is missing from the repository
- package inventory or checksum sidecar was not produced
- release package build fails before `*.tar.gz` is created
