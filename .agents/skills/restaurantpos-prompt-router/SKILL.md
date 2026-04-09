---
name: restaurantpos-prompt-router
description: Route free-form RestaurantPOS requests to the smallest correct context before reading code. Use when Codex receives a natural-language task description and needs to choose the primary project-local skill, add only the necessary supporting skills, read 3 to 6 files first, and derive likely verification hints without broad repo scans.
---

# RestaurantPOS Prompt Router

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/router-map.md` before routing a large or ambiguous request.

## Workflow

1. Run `python .agents/skills/restaurantpos-prompt-router/scripts/route_prompt.py "<request>"` from the repo root.
2. Accept one primary domain unless the script clearly reports a multi-domain tie.
3. Add no more than two supporting skills before the first code read.
4. Read only the first-pass files returned by the router.
5. If the router emits representative paths, use `restaurantpos-targeted-verification` to turn them into real commands.

## Guardrails

- Route by the invariant owner, not the loudest noun in the prompt.
- If privacy, retention, or redaction is the real invariant, prefer `restaurantpos-data-lifecycle` even when the surface route is customer-facing.
- If branch policy or timezone drives downstream validation, prefer `restaurantpos-branch-scheduling` before reservation or waiting-list skills.
- If branch settings and reporting snapshots move together, note the tie and switch to `restaurantpos-workstream-orchestrator`.
- If no domain scores cleanly, fall back to `restaurantpos-context-router` and keep the first read under eight files.

## Commands

```bash
python .agents/skills/restaurantpos-prompt-router/scripts/route_prompt.py "Fix privacy request dry-run blockers"
python .agents/skills/restaurantpos-prompt-router/scripts/route_prompt.py "Add quiet hours to notification preferences" --json
python .agents/skills/restaurantpos-prompt-router/scripts/route_prompt.py "Investigate branch reporting snapshot drift" --path app/Services/Reporting/ReportingSnapshotService.php
```
