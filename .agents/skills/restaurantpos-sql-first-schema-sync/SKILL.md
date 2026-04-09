---
name: restaurantpos-sql-first-schema-sync
description: Keep RestaurantPOS schema, patches, dumps, and bootstrap contract aligned when database behavior changes. Use when Codex adds or changes tables, columns, indexes, constraints, triggers, row_version behavior, bootstrap SQL, or any service logic that depends on new SQL-first artifacts.
---

# RestaurantPOS SQL-First Schema Sync

Read `README.md`, `AGENTS.md`, `.codex/AGENTS.md`, and `references/sync-checklist.md` before changing database-facing behavior.

## Workflow

1. Prove that the change is truly schema-affecting rather than a pure service or test fix.
2. Update SQL-first artifacts together:
   - `database/schema/mysql-schema.sql`
   - `database/patches/*`
   - `db_all.sql` when the full dump is part of the release artifact set
3. Review `tools/mysql/*` and `app/Services/DatabaseContractInspector.php` if bootstrap or contract inspection assumptions move.
4. Update related tests and runbooks in the same batch when operator behavior changes.
5. Call out schema or patch changes explicitly in the final report.

## Guardrails

- Do not default to Laravel migrations as the repo's setup path
- Do not change only code or only SQL when both need to move together
- Minimize schema edits to the tables and constraints required for the request
- If a schema change cascades into route, config, or runtime health behavior, also use `$restaurantpos-shared-file-discipline` or `$restaurantpos-ops-release-contract`
