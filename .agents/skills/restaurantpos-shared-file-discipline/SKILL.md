---
name: restaurantpos-shared-file-discipline
description: Protect RestaurantPOS shared integration seams and reduce merge or behavior collisions. Use when Codex may touch routes, booking config, staff capability config, mysql schema, or other high-coupling files that affect multiple domains and require minimal diffs plus explicit coordination.
---

# RestaurantPOS Shared File Discipline

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/shared-files.md` before editing any shared seam.

## Workflow

1. Confirm that the request really requires a shared-file change.
2. Isolate the exact reason the shared file must move.
3. Keep the diff as small as possible and avoid opportunistic cleanup in the same file.
4. Check the collateral domains listed in `references/shared-files.md`.
5. Mention the shared-file touch explicitly in the final report.

## Guardrails

- If the change can stay inside a service, request, middleware, or test, keep it there
- If multiple workstreams need the same shared file, prefer a later integration pass instead of mixing domains in one batch
- Shared-file touches should come with targeted tests proving the seam did not drift
- For `database/schema/mysql-schema.sql`, also use `$restaurantpos-sql-first-schema-sync`
