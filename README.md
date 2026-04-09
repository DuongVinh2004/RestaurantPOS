# RestaurantPOS Laravel Backend

Laravel 12 / PHP 8.2 backend for the restaurant booking, customer self-service, staff operations, and admin flows in this repo.

## Bootstrap contract

This project is not migration-first for environment provisioning.

Canonical release bootstrap uses:

1. `database/schema/mysql-schema.sql`
2. `database/patches/*.sql`
3. `tools/mysql/bootstrap_release.*`

Do not rely on `php artisan migrate` as the primary setup path for local, staging, or release validation.

## Runtime prerequisites

- PHP 8.2 with the normal Laravel extensions enabled, including `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `tokenizer`, `xml`, and `ctype`
- Redis plus the PHP Redis extension when booking flows run with `REQUIRE_REDIS_FOR_BOOKING_API=true`
- Composer 2
- Node.js / npm for frontend assets
- MySQL 8 compatible server and the `mysql` CLI in `PATH`

## Local bootstrap

1. Copy the environment file and set your database / Redis values.
   - `copy .env.example .env` on Windows
   - `cp .env.example .env` on macOS/Linux
2. Install PHP dependencies.
   - `composer install`
3. Generate the Laravel app key if `.env` does not already contain one.
   - `php artisan key:generate --ansi --force`
4. Run the SQL-first bootstrap flow.
   - `composer bootstrap:booking`
5. Install and build frontend assets when needed.
   - `npm install`
   - `npm run build`

`composer setup` wraps the same sequence end-to-end.

For a Windows `cmd.exe` daily-use runbook inside VSCode, see:

- `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`

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

Start with the changed-file selector instead of jumping straight to the full suite:

- `composer verify:select -- --path=app/Services/Staff/StaffCheckoutService.php`
- `php artisan booking:verify-select --base=origin/main --json`

The selector recommends the smallest deterministic ladder it can justify from the changed paths. It escalates automatically when the change set touches shared seams, schema/bootstrap artifacts, route/API contract surface, auth boundaries, payment/finance flows, feature flags, or runtime-sensitive CI/release wiring.

Representative outcomes:

- checkout/payment change: targeted checkout/payment tests plus `booking:round5-gate --json`
- route or request/resource change: API artifact regeneration plus `booking:route-gate --json`
- schema or CI/release contract change: console/infrastructure tests plus `booking:doctor --json`, `booking:deploy-check --mode=preflight`, and `booking:release-manifest --json`
- docs-only change: no automated commands selected; review command examples/runbook text manually

Run the core release checks after bootstrap:

- `php artisan notifications:outbox-health --json`
- `php artisan booking:launch-readiness --target=staging --json`
- `php artisan booking:dr-drill --mode=metadata-verify --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:deploy-check --mode=preflight`
- `php artisan booking:doctor --json`
- `php artisan test`

If MySQL, Redis, scheduler heartbeat, or backend HTTP are unavailable, treat the resulting JSON failures as runtime blockers. These checks should fail with structured blocker evidence; they are not proof of `runtime-green` until the live prerequisites are reachable.

For split-web aware packaging and CI evidence, use:

- `php artisan booking:release-build --json --uat-manifest=storage/app/uat/scenario-pack.json`
- `php artisan booking:harness:web-auth --json`
- `php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json`

For the canonical launch-readiness matrix, artifact layout, and limited-production manual evidence flow, see:

- `docs/runbooks/booking-launch-readiness.md`
- `docs/runbooks/booking-performance-verification.md`
- `docs/runbooks/booking-disaster-recovery-drill.md`
- `docs/runbooks/booking-release-packaging-runbook.md`

## Environment notes

- `.env.example` uses file-backed cache/session storage and sync queues because the release bootstrap contract does not provision Laravel's optional cache/session/job tables.
- Staging and production-like environments should set a dedicated `CUSTOMER_AUTH_JWT_SECRET` instead of relying on `APP_KEY`.
- If you enable `REQUIRE_REDIS_FOR_BOOKING_API=true`, full booking flows expect Redis to be reachable; do not disable that in staging/production-like rollout just to bypass missing infrastructure.
- The bootstrap flow primes one scheduler heartbeat, but long-lived environments still need `php artisan schedule:work` or another scheduler runner so `booking:doctor` stays green.
- Schema changes must be reflected in `database/schema/mysql-schema.sql`, `database/patches/*.sql`, and `db_all.sql` when the full dump is part of the release artifact set.

## Split frontend delivery

For `customer-web` and `staff-web` running as separate origins:

- set `CORS_ALLOWED_ORIGINS` on the backend to the exact frontend origins that may call `/api/*`
- point FE base URLs at the backend API origin, for example `http://localhost:8000/api/v1`
- keep auth header-based (`X-Customer-Token`, `X-Staff-Key`, `X-Session-Id`) and do not use cookie credentials mode

The canonical runbook for artifact generation, base URL setup, and CORS policy is:

- `docs/runbooks/api-consumer-artifacts.md`
