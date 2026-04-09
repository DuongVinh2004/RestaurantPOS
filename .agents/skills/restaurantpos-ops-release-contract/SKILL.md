---
name: restaurantpos-ops-release-contract
description: Protect RestaurantPOS SQL-first bootstrap, schema and patch contract, release artifacts, health checks, outbox, scheduler expectations, and operational readiness flows. Use when Codex changes database contract inspection, bootstrap commands, notification outbox, reporting or ops services, disaster recovery, or docs and tests tied to release safety.
---

# RestaurantPOS Ops & Release Contract

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Use the SQL-first contract: schema -> patches -> bootstrap tool -> runtime checks.
2. For changes touching operational health, outbox, reporting, or release packaging, inspect both app services and release or runbook docs.
3. Distinguish test-only green from runtime-ready green; MySQL, Redis, and scheduler assumptions matter.
4. Keep docs and release contract in sync when bootstrap or readiness behavior changes.
5. Add or update console or infrastructure tests whenever operational commands or checks change.

## Guardrails

- Do not switch the repo back to migration-first assumptions.
- Touch `database/schema/mysql-schema.sql`, `database/patches/*`, and `db_all.sql` deliberately and in sync.
- Avoid broad edits under `build/` or `storage/` unless the task is about generated release evidence.
- Treat notification outbox health, scheduler heartbeat, and deploy checks as release blockers, not optional diagnostics.

## Verify

- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:deploy-check --mode=preflight`
- `php artisan booking:release-manifest --json`
- Run targeted console and infrastructure tests covering the changed checks.
