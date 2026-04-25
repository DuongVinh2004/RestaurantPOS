# RestaurantPOS

[![Backend CI](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-ci.yml/badge.svg?branch=main)](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-ci.yml)
[![Release Gate](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-release-gate.yml/badge.svg?branch=main)](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-release-gate.yml)

SQL-first RestaurantPOS monorepo built around a Laravel 12 backend, branch-aware operational workflows, and release/runtime gates that are meant to survive real deployment conditions.

This repository is not a demo scaffold. It owns:

- reservation, table hold, waiting-list, and service-session flows
- dine-in ordering, cashier settlement, refunds, and payment webhook handling
- kitchen dispatch and branch routing foundations
- inventory, purchasing, and reporting foundations
- operator-facing `staff-web/`, customer-facing `customer-web/`, and generated API consumer artifacts
- deploy, release, launch-readiness, DR, and runtime health contracts

## Project Status

The codebase is being hardened toward a production-grade RestaurantPOS backend. Some domains are fully contract-visible before they are fully launch-enabled; use feature flags, runbooks, and launch-readiness evidence instead of assuming that every exposed route is day-1 enabled.

The most important repository rule is unchanged: environment provisioning is SQL-first. Do not treat `php artisan migrate` as the default bootstrap path for local, staging, or release validation.

## Repository Standards

- [Contributing guide](./CONTRIBUTING.md)
- [Security policy](./SECURITY.md)
- [PR template](./.github/PULL_REQUEST_TEMPLATE.md)
- [Issue templates](./.github/ISSUE_TEMPLATE/)
- [MIT license](./LICENSE)

## What Is Included

- `app/`: Laravel application code and domain modules
- `routes/`: API and console route entry points
- `database/schema/mysql-schema.sql`: canonical schema dump used for provisioning
- `database/patches/`: ordered SQL patch inventory that completes the release contract
- `tools/mysql/`: bootstrap, verification, and MySQL release helpers
- `staff-web/`: operator-facing React + TypeScript client
- `customer-web/`: customer-facing web client
- `build/api-consumer/`: generated SDK, Postman, enum state, and mutation contract artifacts
- `docs/runbooks/`: operator and release documentation
- `docs/architecture/`: module ownership, structure, and decomposition references
- `storage/app/booking_release/`: release evidence and manifest snapshots

## Core Capabilities

- Auth / RBAC for staff, customer access sessions, and web auth session hardening
- Front-of-house reservations, board views, branch scope, check-in, move-table, and release flows
- Dine-in ordering, active-order read models, and kitchen dispatch foundations
- Checkout, deposit capture, bill self-pay support, refunds, invoices, cashier shifts, and reconciliation
- Waiting-list orchestration and customer response flows
- Inventory, purchasing, supplier receiving, and reporting read models
- Release packaging, launch-readiness checks, doctor/outbox health, and DR verification

## Stack

- PHP 8.2
- Laravel 12
- MySQL 8
- Redis
- Node.js / npm
- Vite + Tailwind CSS
- React + TypeScript in `staff-web/` and `customer-web/`

## Architecture References

- [Refactored app structure guide](./docs/architecture/refactored-app-structure-guide.md)
- [Module ownership map](./docs/architecture/module-ownership-map.md)
- [Module dependency rules](./docs/architecture/module-dependency-rules.md)
- [API contract runbook](./docs/runbooks/booking-api-contract.md)
- [Launch readiness runbook](./docs/runbooks/booking-launch-readiness.md)

## Bootstrap Contract

Canonical provisioning uses this sequence:

1. `database/schema/mysql-schema.sql`
2. `database/patches/*.sql`
3. `tools/mysql/bootstrap_release.php` or `composer bootstrap:booking`

`composer bootstrap:booking` imports the schema, applies every required patch, seeds reference data, refreshes release artifacts, rebuilds reporting snapshots, and primes the scheduler heartbeat once so runtime gates can run immediately.

If you change schema-sensitive behavior, keep these files aligned:

- `database/schema/mysql-schema.sql`
- `database/patches/*.sql`
- `db_all.sql`

## Prerequisites

- PHP 8.2 with common Laravel extensions, including `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `tokenizer`, `xml`, and `ctype`
- MySQL 8 compatible server plus the `mysql` CLI in `PATH`
- Redis and the PHP Redis extension when `REQUIRE_REDIS_FOR_BOOKING_API=true`
- Composer 2
- Node.js and npm

## Quick Start

1. Copy the environment file and configure MySQL and Redis values.
   - Windows: `copy .env.example .env`
   - macOS / Linux: `cp .env.example .env`
2. Install backend dependencies.
   - `composer install`
3. Generate the application key if `.env` does not already contain one.
   - `php artisan key:generate --ansi --force`
4. Run the SQL-first bootstrap flow.
   - `composer bootstrap:booking`
5. Build root frontend assets when needed.
   - `npm install`
   - `npm run build`
6. Build the web clients you are actively changing.
   - `cd staff-web && npm install && npm run build`
   - `cd customer-web && npm install && npm run build`

`composer setup` wraps backend install/bootstrap plus the root frontend build. It is useful for a first machine bootstrap, but release validation should still use the SQL-first contract explicitly.

## Local Development

For a runtime-like local lane:

- `npm run runtime:up`
- `npm run runtime:preflight`
- `npm run runtime:down`

This path brings up repo-local MySQL, Redis, backend HTTP, and scheduler work, then validates the runtime lane with doctor and related health checks.

For faster UI-focused iteration:

- `npm run dev:be`
- `npm run dev:all`
- `npm run dev:smoke`

The simple dev lane is convenient for browser iteration, but it does not replace the runtime lane when scheduler heartbeat freshness, outbox health, Redis, or release evidence matters.

The dev bootstrap refreshes demo credentials into `storage/app/uat/scenario-pack.json`. See [local login accounts](./docs/runbooks/local-login-accounts.md) and [UAT scenario pack](./docs/runbooks/uat-demo-scenario-pack.md) for the expected accounts and seeded flows.

For Windows `cmd.exe` usage in VSCode, use [booking-local-windows-vscode-cmd-runbook.md](./docs/runbooks/booking-local-windows-vscode-cmd-runbook.md).

## Verification

Start with the selector instead of jumping straight to the full suite:

- `composer verify:select -- --path=app/Services/Staff/StaffCheckoutService.php`
- `php artisan booking:verify-select --base=origin/main --json`

Typical verification layers:

- formatting: `vendor/bin/pint --test`
- static analysis: `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- targeted tests: `php artisan test ...`
- full automated suite: `php artisan test`
- runtime and release gates:
  - `php artisan booking:doctor --json`
  - `php artisan notifications:outbox-health --json`
  - `php artisan booking:deploy-check --mode=preflight`
  - `php artisan booking:release-manifest --json`
  - `php artisan booking:launch-readiness --target=staging --json`

If MySQL, Redis, scheduler heartbeat, or backend HTTP are unavailable, treat those failures as runtime blockers, not as false positives.

## CI And Release

This repository already carries two important GitHub workflows:

- `booking-backend-ci`: fast contracts, smoke, and full-gate CI lanes
- `booking-release-gate`: release evidence and packaging gate

Release and operator documentation lives in:

- [CI/CD runbook](./docs/runbooks/booking-ci-cd-runbook.md)
- [Release packaging runbook](./docs/runbooks/booking-release-packaging-runbook.md)
- [Deploy runbook](./docs/runbooks/booking-deploy-runbook.md)
- [Performance verification](./docs/runbooks/booking-performance-verification.md)
- [Backup and restore](./docs/runbooks/booking-backup-restore-runbook.md)
- [Disaster recovery drill](./docs/runbooks/booking-disaster-recovery-drill.md)

## Generated Artifacts

Frontend and external consumers should rely on the generated contract artifacts instead of reverse-engineering routes by hand.

Key artifacts:

- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/mutation-contracts.md`
- `build/api-consumer/postman/`
- `storage/app/booking_release/openapi-v1.json`
- `storage/app/booking_release/release_manifest_snapshot.json`

See [api-consumer-artifacts.md](./docs/runbooks/api-consumer-artifacts.md) for regeneration and delivery rules.

## Split Frontend Delivery

When `customer-web` and `staff-web` are deployed separately:

- set `CORS_ALLOWED_ORIGINS` to exact frontend origins
- point `customer-web` to the backend origin, for example `http://127.0.0.1:8000`
- point `staff-web` to the API base URL, for example `http://127.0.0.1:8000/api/v1`
- keep auth header-based with `X-Customer-Token`, `X-Staff-Key`, and `X-Session-Id`

Exact origin means `scheme://host:port` with no path and no trailing slash.

## Contribution Workflow

Use the repository standards before opening a PR:

1. Read [CONTRIBUTING.md](./CONTRIBUTING.md).
2. Keep changes aligned with the SQL-first bootstrap and release contract.
3. Run the smallest verification set that proves the change.
4. Update docs or runbooks when operator behavior changes.
5. Fill the existing PR template with intent, changed files, verification, tests, and remaining risks.

## Security

Please do not open public issues for exploitable vulnerabilities. Use [SECURITY.md](./SECURITY.md) and GitHub Security Advisories for responsible reporting.

## License

This repository is licensed under MIT. See [LICENSE](./LICENSE).
