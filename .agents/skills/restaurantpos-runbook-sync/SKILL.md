---
name: restaurantpos-runbook-sync
description: Keep RestaurantPOS docs and runbooks aligned with code and operator behavior. Use when Codex changes bootstrap, runtime commands, rollout behavior, API consumer artifacts, disaster recovery, payments, notifications, feature flags, UAT steps, or any operator-facing contract described in docs or runbooks.
---

# RestaurantPOS Runbook Sync

Read `README.md`, `AGENTS.md`, `.codex/AGENTS.md`, and `references/runbook-map.md` before updating docs.

## Workflow

1. Decide whether the code change altered developer, operator, or API consumer behavior.
2. Update the smallest existing document that already owns that behavior.
3. Keep command names, flags, env vars, and example paths exact.
4. If the change affects bootstrap or runtime health, make sure docs preserve the repo's SQL-first contract.
5. Mention doc or runbook updates in the final report whenever they are part of the batch.

## Guardrails

- Prefer updating existing docs over creating new top-level documents
- Do not describe `php artisan migrate` as the default bootstrap path
- Keep Windows runbook examples in `cmd.exe` syntax where that document already expects it
- If operator behavior changed but no docs moved, state that as a remaining risk
