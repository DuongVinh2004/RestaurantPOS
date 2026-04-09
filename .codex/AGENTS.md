# Codex Project Supplement

Read the repository root `AGENTS.md` first. This file only adds Codex-specific execution details for this workspace.

## Project contract

- This backend is `SQL-first`.
- Canonical bootstrap is `composer bootstrap:booking`.
- Do not treat `php artisan migrate` as the default setup path for local, staging, or release validation.
- Before changing database behavior, review:
  - `database/schema/mysql-schema.sql`
  - `database/patches/*.sql`
  - `database/README_release_bootstrap.md`
  - `tools/mysql/*`

## Runtime model

- Automated tests use `sqlite` in-memory by default through `phpunit.xml`.
- Runtime and release flows depend on MySQL 8 compatible bootstrap artifacts.
- Booking flows may also depend on Redis and a live scheduler heartbeat.
- Do not assume a passing `php artisan test` result alone proves runtime readiness.

## Change guidance

- Prefer extending existing service/domain foundations in `app/Services` and `app/Support`.
- Keep controllers thin and avoid pushing orchestration into route/controller layers.
- For production-sensitive changes, explicitly check:
  - authorization and branch scope
  - idempotency and replay behavior
  - transactions and locking
  - row_version or stale-write guards
  - audit trail coverage
  - schema and release-contract drift

## Shared files

These files are shared integration seams. Touch them minimally and call them out in the final report when changed:

- `routes/api.php`
- `config/booking.php`
- `config/staff_capabilities.php`
- `database/schema/mysql-schema.sql`

## Verification ladder

Use the lightest verification that proves the change, then expand when risk is higher:

1. Targeted `php artisan test ...`
2. `vendor/bin/pint --test`
3. `vendor/bin/phpstan analyse`
4. `php artisan test`
5. `php artisan booking:doctor --json`
6. `php artisan booking:deploy-check --mode=preflight`
7. `php artisan booking:release-manifest --json`

When a task affects release bootstrap, also review `db_all.sql` and release artifacts under `build/` only as needed.

## Parallel work

- Use `.codex/agents/*` roles for focused exploration, review, and documentation verification.
- For larger parallel hardening passes, align with `docs/codex-parallel-agent-prompts.md`.
- Project-local skills live under `.agents/skills/`; use them explicitly when the task maps cleanly to auth/RBAC, split web auth/session contract, FOH reservations, order lifecycle, customer self-service, checkout/finance, kitchen/KDS, inventory/purchasing, web-client contract and DX, API contract gates, ops/release contract, data lifecycle, notification platform, conversation inbox, branch scheduling, multi-branch reporting, or workstream orchestration.
- Use the process skills to keep context lean:
  - `restaurantpos-web-client-contracts`
  - `restaurantpos-web-auth-session-contract`
  - `restaurantpos-context-router`
  - `restaurantpos-prompt-router`
  - `restaurantpos-targeted-verification`
  - `restaurantpos-git-aware-verify`
  - `restaurantpos-skill-pack-quality`
  - `restaurantpos-sql-first-schema-sync`
  - `restaurantpos-shared-file-discipline`
  - `restaurantpos-runbook-sync`
  - `restaurantpos-runtime-smoke`
  - `restaurantpos-feature-flag-rollout`
  - `restaurantpos-audit-observability`
  - `restaurantpos-performance-budget`
- Avoid broad scans through `vendor/`, `node_modules/`, `storage/`, or `build/` unless the task is specifically about dependencies, generated artifacts, or release evidence.
