# RestaurantPOS Laravel Backend

SQL-first Laravel 12 backend for RestaurantPOS. This repository covers reservation and front-of-house flows, dine-in POS and order lifecycle, kitchen dispatch foundations, checkout and refunds, inventory basics, reporting, and release/ops hardening.

The repo also includes:

- `staff-web/`: React + TypeScript operator client
- `build/api-consumer/`: generated API consumer artifacts
- `docs/runbooks/`: operational and release runbooks

## Core scope

- customer and staff authentication / RBAC
- reservation, table hold, waiting-list, and service-session flows
- dine-in ordering and kitchen handoff
- checkout, payment webhook, refund, and cashier shift flows
- inventory and purchasing foundations
- reporting, release packaging, launch-readiness, and backup/restore checks

## Tech stack

- PHP 8.2
- Laravel 12
- MySQL 8
- Redis
- Vite + Tailwind CSS
- `staff-web/` with React, TypeScript, and Vite

## Repository layout

- `app/`: backend application code
- `database/schema/mysql-schema.sql`: canonical schema dump
- `database/patches/`: required SQL patch inventory
- `tools/mysql/`: bootstrap, verify, backup, and restore helpers
- `build/api-consumer/`: frozen/generated API consumer artifacts
- `storage/app/booking_release/`: tracked release-contract snapshots
- `staff-web/`: staff-facing web client
- `docs/runbooks/`: setup, deployment, DR, and launch-readiness guides

## Bootstrap contract

This project is not migration-first for environment provisioning.

Canonical bootstrap uses:

1. `database/schema/mysql-schema.sql`
2. `database/patches/*.sql`
3. `tools/mysql/bootstrap_release.*`

Do not treat `php artisan migrate` as the primary path for local, staging, or release validation.

## Runtime prerequisites

- PHP 8.2 with standard Laravel extensions, including `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `tokenizer`, `xml`, and `ctype`
- MySQL 8 compatible server and the `mysql` CLI in `PATH`
- Redis plus the PHP Redis extension when `REQUIRE_REDIS_FOR_BOOKING_API=true`
- Composer 2
- Node.js / npm for frontend assets

## Quick start

1. Copy the environment file and configure MySQL / Redis values.
   - Windows: `copy .env.example .env`
   - macOS / Linux: `cp .env.example .env`
2. Install backend dependencies.
   - `composer install`
3. Generate the app key if `.env` does not already contain one.
   - `php artisan key:generate --ansi --force`
4. Run the SQL-first bootstrap flow.
   - `composer bootstrap:booking`
5. Install and build root frontend assets when needed.
   - `npm install`
   - `npm run build`
6. If you are working on the staff client, bootstrap it separately.
   - `cd staff-web`
   - `npm install`
   - `npm run build`

`composer setup` wraps the backend bootstrap plus the root frontend asset build.

For a Windows `cmd.exe` daily-use runbook inside VSCode, see `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`.

For the Windows local daily runtime lane, use:

- `npm run runtime:up` to ensure repo-local MySQL, Redis, backend HTTP, and `schedule:work` are running and to prime the scheduler heartbeat once
- `npm run runtime:down` to stop the same repo-local runtime processes
- `npm run runtime:preflight` when you want the full doctor/outbox readiness gate after startup

## What `composer bootstrap:booking` does

- imports `database/schema/mysql-schema.sql`
- applies every patch in `database/patches/*.sql`
- seeds `ReferenceDataSeeder`
- clears Laravel caches
- runs `booking:bootstrap-site`
- rebuilds reporting snapshots
- normalizes release artifacts
- refreshes the release manifest snapshot
- primes the scheduler heartbeat once for immediate runtime verification

## Verification

For repo handoff or snapshot triage, verify package shape before spending time on builds or smoke:

- `npm run verify:package`
- `node scripts/release/check-package-integrity.mjs --json`
- `cd staff-web && npm run integrity:check`

The package-integrity gate reports three explicit buckets:

- `required to run`
- `required for build/test/smoke`
- `useful for handover`

Missing items in the first two buckets block the command. Missing handover-only items return `decision=warn` so reviewers can fix setup or contract notes without mistaking them for runtime blockers.

Canonical local FE-facing artifacts for this snapshot are:

- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/mutation-contracts.md`
- `storage/app/booking_release/openapi-v1.json`
- `storage/app/booking_release/release_manifest_snapshot.json`

When validating `staff-web`, remember that `npm run smoke:live` is read-only by default. Order create, kitchen dispatch, settlement finalize, refund execute, and cashier open/close remain mutation-gated until the corresponding `STAFF_WEB_SMOKE_ALLOW_*` flags are enabled or the manifest-backed gate explicitly allows them.

Start with the changed-file selector instead of jumping straight to the full suite:

- `composer verify:select -- --path=app/Services/Staff/StaffCheckoutService.php`
- `php artisan booking:verify-select --base=origin/main --json`

The selector escalates automatically when a change touches shared seams, SQL/bootstrap artifacts, route or API contract surface, auth boundaries, finance flows, feature flags, or release/runtime wiring.

Representative outcomes:

- checkout/payment change: targeted checkout/payment tests plus `booking:round5-gate --json`
- route or request/resource change: API artifact regeneration plus `booking:route-gate --json`
- schema or CI/release contract change: console/infrastructure tests plus `booking:doctor --json`, `booking:deploy-check --mode=preflight`, and `booking:release-manifest --json`
- docs-only change: review the docs manually instead of defaulting to the full test suite

Core release and runtime checks after bootstrap:

- `php artisan notifications:outbox-health --json`
- `php artisan booking:launch-readiness --target=staging --json`
- `php artisan booking:dr-drill --mode=metadata-verify --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:deploy-check --mode=preflight`
- `php artisan booking:doctor --json`
- `php artisan test`

If MySQL, Redis, scheduler heartbeat, or backend HTTP are unavailable, treat the resulting failures as runtime blockers rather than false positives.

## Split frontend delivery

For separate `customer-web` and `staff-web` deployments:

- set `CORS_ALLOWED_ORIGINS` on the backend to the exact frontend origins allowed to call `/api/*`
- point frontend base URLs at the backend API origin, for example `http://localhost:8000/api/v1`
- keep auth header-based (`X-Customer-Token`, `X-Staff-Key`, `X-Session-Id`) instead of cookie credential mode

The canonical artifact and CORS runbook is `docs/runbooks/api-consumer-artifacts.md`.

## Important runbooks

- `docs/runbooks/booking-launch-readiness.md`
- `docs/runbooks/booking-performance-verification.md`
- `docs/runbooks/booking-disaster-recovery-drill.md`
- `docs/runbooks/booking-release-packaging-runbook.md`
- `docs/runbooks/booking-ci-cd-runbook.md`
- `docs/runbooks/booking-backup-restore-runbook.md`

## Environment notes

- `.env.example` uses file-backed cache/session storage and sync queues because the SQL-first bootstrap does not provision Laravel's optional cache/session/job tables.
- Staging and production-like environments should set a dedicated `CUSTOMER_AUTH_JWT_SECRET` instead of relying on `APP_KEY`.
- If you enable `REQUIRE_REDIS_FOR_BOOKING_API=true`, full booking flows expect Redis to be reachable.
- Long-lived environments still need `php artisan schedule:work` or another scheduler runner so `booking:doctor` stays green.
- Schema changes must be reflected in `database/schema/mysql-schema.sql`, `database/patches/*.sql`, and `db_all.sql` when the full dump is part of the release artifact set.

## License

This repository currently declares `MIT` in `composer.json`, and the top-level `LICENSE` file matches that declaration.
