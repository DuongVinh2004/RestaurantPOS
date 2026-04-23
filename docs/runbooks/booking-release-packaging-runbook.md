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

## Final release handoff checklist

Use this exact checklist for final packaging and handoff:

1. Generate before packaging.
   - `php artisan booking:api-contract --write`
   - `php artisan booking:api-artifacts:generate`
   - `php artisan booking:release-manifest --write`
   - If the handoff needs demo/API-client variables, refresh `storage/app/uat/scenario-pack.json` first and pass it to the artifact generator or release loop.
2. Source-only package.
   - Source-only exports are review artifacts only. Label them `SOURCE-ONLY`, do not deploy them, and do not cite them as release evidence because they can omit generated artifacts, frozen manifests, package sidecars, and smoke reports.
3. Release/handoff package.
   - Use only `build/booking-release/*.tar.gz` plus matching `.metadata.json`, `.inventory.json`, `.checksums.sha256`, `.package.sha256`, and `latest-package.json` after `booking:package-release --verify-frozen` or `booking:release-build`.
   - Treat `build/booking-release/stage/<package>` as transient local staging only. It is not release evidence and may be deleted immediately after packaging.
   - Local package retention keeps only the two newest package sets under `build/booking-release/`. Archive promoted candidates elsewhere if you need a longer rollback history than current-plus-previous.
4. Authoritative smoke proof.
   - Cite the exact timestamped smoke file for the target, not an old failure copied from the folder listing. Current pointers live at `storage/app/booking_release/staff_web_smoke/latest-local.*`, `latest-staging.*`, and release-loop step artifacts under `storage/app/booking_release/release_loop/steps/<target>/<timestamp>/staff-web-smoke/`.
   - Timestamped reports that are not the cited target evidence are historical only unless the release ticket explicitly promotes them.
5. Contract-critical generated artifacts.
   - `storage/app/booking_release/openapi-v1.json`
   - `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
   - `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
   - `build/api-consumer/enum-state-map.json`
   - `build/api-consumer/mutation-contracts.md`
   - `build/api-consumer/postman/RestaurantPOS.postman_collection.json`
   - `storage/app/booking_release/release_manifest_snapshot.json`
6. Gates that must fail the release.
   - Package integrity reports blocking missing files or blocking stale generated artifacts.
   - `booking:release-manifest --verify-frozen` reports stale, missing, or invalid frozen manifest state.
   - `booking:deploy-check --mode=preflight --strict` fails.
   - Backend, staff-web, or target smoke tests fail for lanes enabled in the release ticket.
   - `booking:package-release --verify-frozen` cannot create the package or sidecars deterministically.
   - The package inventory contains install-time garbage such as `node_modules`, `vendor`, `.env`, `.git`, caches, or local temp output.
7. Domain maturity label for this handoff.
   - Mature / strong: auth/identity/RBAC, dine-in service session, order lifecycle, checkout/refund/cashier shift.
   - Hardened but still higher risk: branch-scope operational consistency, kitchen/KDS, conversation inbox, staff-web branch shell context.
   - Foundation-usable only: inventory basics, reporting/ops dashboards, notification/provider external E2E, advanced realtime collaboration.
8. Day-1 scope note for the release ticket.
   - Customer day-1 promise is auth/session, menu browse, availability/holds, and reservation create/list/detail.
   - Customer deposit or bill payment-session routes are contract-visible only until real provider evidence promotes them.
   - Staff day-1 promise is board, reservations, manual waiting-list ops, active order, checkout/refund, cashier shift, and required finance review reads.
   - Kitchen/KDS dispatch, inventory uplift, and conversation inbox stay outside the day-1 promise even if their routes or shells remain mounted.

## Canonical package shape

Blocking files that must exist for the repo to run at all:

- `composer.json`
- `artisan`
- `.env.example`
- `public/index.php`
- `routes/`
- `bootstrap/`
- `config/`
- `database/`
- `tools/mysql/`
- `tools/bootstrap_booking.php`
- `staff-web/package.json`
- `staff-web/vite.config.ts`
- `staff-web/index.html`

Blocking files that must exist for build/test/smoke and FE contract verification:

- `tests/`
- `phpunit.xml`
- `package.json`
- `vite.config.js`
- `scripts/`
- `staff-web/tsconfig.json`
- `staff-web/vitest.config.ts`
- `staff-web/scripts/live-smoke.mjs`
- `staff-web/src/api/sdk.ts`
- `staff-web/src/core/api/sdk.ts`

Generated artifacts that must stay aligned with the current backend contract:

- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/mutation-contracts.md`
- `storage/app/booking_release/openapi-v1.json`
- `storage/app/booking_release/release_manifest_snapshot.json`

Useful handover files that should stay in the snapshot even though they are advisory-only:

- `README.md`
- `docs/runbooks/`
- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-release-packaging-runbook.md`
- `staff-web/.env.example`
- `staff-web/STAFF_WEB_SETUP.md`
- `build/api-consumer/sdk/typescript/README.md`

## Package integrity gate

Run the integrity gate before handoff, before FE build/test, and inside release-loop:

- `node scripts/release/check-package-integrity.mjs`
- `node scripts/release/check-package-integrity.mjs --json`
- `npm run verify:package`

For FE-only handoff validation from `staff-web/`:

- `npm run integrity:check`
- `node ../scripts/release/check-package-integrity.mjs --staff-web-only --root-dir=..`

This gate is intentionally narrow. It does not replace runtime checks; it fails early when the backend roots, `staff-web` roots, or generated FE-facing artifacts are missing from the package shape that release tooling expects.

The gate now emits three explicit groups:

- `required to run`
- `required for build/test/smoke`
- `useful for handover`

Missing items in the first two groups return `decision=block` and a non-zero exit code. Missing handover-only items return `decision=warn` and exit cleanly so reviewers can separate documentation drift from real package blockers.

## Focused pre-release regression slice

When the candidate touches reporting, audit, shell scope messaging, or FE handoff reliability, run this smaller but high-signal slice before the broader release loop:

1. Backend reporting and audit regression
   - `php artisan test tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest tests/Feature/Staff/StaffAuditTrailHttpFlowTest tests/Unit/Services/OperationalInsightsServiceTest`
2. Contract and package shape
   - `php artisan booking:api-contract --write`
   - `php artisan booking:api-artifacts:generate`
   - `php artisan booking:release-manifest --write`
   - `npm run verify:package`
3. Staff-web regression for the affected surfaces from `staff-web/`
   - `npm run integrity:check`
   - `npm run test -- --run src/features/reporting/reporting-hub.test.ts src/features/audit/audit-trail.test.ts src/features/audit/AuditTrailPage.test.tsx src/features/dashboard/DashboardPage.test.tsx src/app/layout/route-scope.test.ts`

This slice is meant to answer three questions quickly:

- are reporting freshness semantics still consistent between backend and staff-web
- is audit investigation still filterable by branch and request correlation
- is the release package still shipping the backend, `staff-web`, and generated consumer artifacts together

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
- step `status=warn` means the command returned warning-only evidence, for example `booking:deploy-check --mode=preflight --strict` with warnings but no errors or `booking:launch-readiness` with `decision=ready_with_warnings`
- overall `decision=pass` means the release loop saw no blocking `fail` steps; review the `warnings` list before promotion whenever any step is `warn` or `skip`

Treat `preview.status=unconfigured` and `observability.status=missing-configuration` as external promotion blockers, not as soft green evidence.

The canonical release loop now also runs `package_integrity` immediately after `contract_artifacts`, so missing FE/BE roots or generated SDK artifacts block the candidate before backend harnesses or `staff-web` build spend time on a broken handoff shape.

The release-loop report is written under:

- `storage/app/booking_release/release_loop/reports/`

`staff-web` smoke evidence created inside that loop is written under the step artifact tree, currently:

- `storage/app/booking_release/release_loop/steps/<target>/<timestamp>/staff-web-smoke/`

Historical failed smoke reports remain in `storage/app/booking_release/staff_web_smoke/` for audit context. Do not delete them to make a folder look green. Current release truth is the latest pointer for the target plus the exact timestamped file cited by the release ticket after the current candidate was generated.

## Order And Kitchen Mutation Smoke

For the canonical dine-in order mutation lane, reset the local UAT pack first so the manifest-backed `dine_in_checkout` reservation is not already consumed by an older run:

- `php artisan booking:uat-pack:bootstrap --base-url=http://127.0.0.1:8000 --json`

Then run the targeted `staff-web` mutation smoke with the order and kitchen gates enabled:

- PowerShell:
  `$env:STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE='1'; $env:STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM='1'; $env:STAFF_WEB_SMOKE_ALLOW_KITCHEN_DISPATCH='1'; npm run smoke:live`

Expected happy-path evidence in the summary:

- `reservation check-in`
- `order create`
- `order add-item`
- `kitchen dispatch`
- `kitchen ticket read`

If kitchen actions look inconsistent after the smoke lane, inspect the KDS lifecycle and reconciliation guidance in [`docs/runbooks/kitchen-kds-lifecycle.md`](./kitchen-kds-lifecycle.md) before collecting release evidence.

If the manifest-backed reservation has already moved to `Completed`, the smoke lane should be reset with `booking:uat-pack:bootstrap` before release evidence is collected.

## Finance Mutation Smoke

Before collecting checkout, cashier, or refund evidence, reset the SQL-first contract and the UAT pack in that order:

- `composer bootstrap:booking`
- `php artisan booking:uat-pack:bootstrap --base-url=http://127.0.0.1:8000 --json`

`booking:deploy-check --mode=preflight --strict` must stay green before finance mutation smoke. If it reports `data.payment_refund_trigger_compatibility`, the target database still has the legacy `payments` refund triggers that MySQL rejects with `ERROR 1442` during refund inserts. Re-run `composer bootstrap:booking`; do not keep collecting refund evidence on that drifted schema.

Then run the deterministic money lane with the upstream order prerequisites enabled:

- PowerShell:
  `$env:STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE='1'; $env:STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM='1'; $env:STAFF_WEB_SMOKE_ALLOW_KITCHEN_DISPATCH='1'; $env:STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE='1'; $env:STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION='1'; $env:STAFF_WEB_SMOKE_ALLOW_CASHIER_OPEN='1'; $env:STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE='1'; npm run smoke:live`

Expected happy-path evidence in the summary:

- `cashier current`
- `cashier open`
- `cashier show`
- `cashier close`
- `settlement finalize`
- `refund preview`
- `refund execute`

`settlement finalize` depends on the canonical order-create lane. If order mutation gates are off, finalize will skip because there is no deterministic open order to settle.

Refund preview and refund execute must agree on the same manifest-backed reservation and `row_version` returned by preview. If refund preview passes but refund execute fails after bootstrap, treat that as a money-flow blocker and archive the request id from `storage/logs/laravel.log` with the release evidence.

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
- `.env.example`
- `app/`
- `bootstrap/`
- `build/api-consumer/`
- `config/`
- `database/`
- `package.json`
- `phpunit.xml`
- `public/index.php`
- `routes/`
- `scripts/`
- `staff-web/`
- `storage/app/booking_release/`
- `tests/`
- `tools/bootstrap_booking.php`
- `tools/mysql/`
- `vite.config.js`
- `db_all.sql`
- optional `README.md` and `docs/runbooks/`

## Operator checks after build

1. Confirm the package exists at the expected `build/booking-release/*.tar.gz` path.
2. Confirm sidecars exist next to the package.
3. Confirm `latest-package.json` points at the newly built package.
4. Archive the package and sidecars with the release ticket or CI artifact store.
5. Deploy only from this immutable package, not from a mutable working tree.

When launch-readiness or release-loop is part of the same candidate review, prefer copying the package handoff data from the artifact's `release_handoff` block instead of digging through raw source payloads. The operator should lift these exact fields into the release ticket:

- `release_handoff.candidate.package_basename`
- `release_handoff.candidate.package_path`
- `release_handoff.candidate.sidecars.*`
- `release_handoff.candidate.release_manifest_snapshot_path`
- `release_handoff.archive_paths[]`

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
