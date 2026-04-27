# Contributing to RestaurantPOS

Thanks for contributing. This repository is treated as a production-oriented codebase, so the standard is closer to release engineering than to a demo Laravel app.

## Ground Rules

- Read `AGENTS.md` before changing code.
- Respect the priority order in `AGENTS.md`: auth and RBAC first, then core floor/POS flows, then supporting domains.
- Keep controllers thin and business logic in services or domain modules.
- Add or update tests for meaningful behavior changes.
- Do not default to `php artisan migrate` for provisioning. This repo is SQL-first.
- If a change affects runtime or operator behavior, update the owning runbook or README.

## SQL-First Contract

Environment provisioning and release validation rely on:

1. `database/schema/mysql-schema.sql`
2. `database/patches/*.sql`
3. `tools/mysql/bootstrap_release.php`
4. `composer bootstrap:booking`

If your change touches schema-sensitive behavior, keep these artifacts aligned:

- `database/schema/mysql-schema.sql`
- `database/patches/*.sql`
- `db_all.sql`

## Local Setup

1. Copy `.env.example` to `.env`.
2. Run `composer install`.
3. Run `php artisan key:generate --ansi --force`.
4. Run `composer bootstrap:booking`.
5. Build only the frontend surfaces you are changing.
   - root: `npm install && npm run build`
   - `staff-web`: `npm install && npm run build`
   - `customer-web`: `npm install && npm run build`

For runtime-like local verification, prefer:

- `npm run runtime:up`
- `npm run runtime:preflight`

## Verification Expectations

Start with the selector:

- `composer verify:select -- --path=...`
- `php artisan booking:verify-select --base=origin/main --json`

Typical ladder:

- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- targeted `php artisan test ...`
- `php artisan test`
- runtime gates when needed:
  - `php artisan booking:doctor --json`
  - `php artisan notifications:outbox-health --json`
  - `php artisan booking:deploy-check --mode=preflight`
  - `php artisan booking:release-manifest --json`
  - `php artisan booking:launch-readiness --target=staging --json`

Do not claim runtime safety from SQLite-backed tests alone.

## Shared Seams

Treat these as shared integration seams and touch them narrowly:

- `routes/api.php`
- `config/booking.php`
- `config/staff_capabilities.php`
- `database/schema/mysql-schema.sql`

If you change one of them, call it out explicitly in the PR and explain the integration risk.

## Pull Requests

Use the existing [PR template](./.github/PULL_REQUEST_TEMPLATE.md). Every PR should state:

- intent
- changed files
- verification performed
- added or updated tests
- remaining risks

Prefer small, reviewable batches over broad cleanup commits.

## Documentation

Update the smallest owning document:

- root onboarding and repository contract: `README.md`
- operator or release behavior: `docs/runbooks/`
- architecture and module boundaries: `docs/architecture/`
- generated consumer surfaces: `build/api-consumer/` and the related runbooks

## Security

If you find a real vulnerability, follow [SECURITY.md](./SECURITY.md) instead of opening a public issue.
