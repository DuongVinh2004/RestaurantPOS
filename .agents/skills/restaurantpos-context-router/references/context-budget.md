# Context Budget

Use this file when a task may involve many modules, generated artifacts, schema files, or frontend/backend contracts.

## First Pass Budget

- Read `AGENTS.md` and `.codex/AGENTS.md`.
- Read at most one routing or architecture reference.
- Read at most three production files before forming a plan.
- Read at most two adjacent test files before editing.
- Read generated artifacts only when consumer-visible API or contract behavior changes.
- Read SQL schema, patches, or dumps only when schema-sensitive behavior changes.

If more context is needed, state the reason and expected payoff before expanding.

## Fast Discovery

Prefer exact, path-limited searches:

```bash
rg "class ClassName|function methodName" app/Modules tests
rg "route_name|uri|controller|capability" routes app/Modules tests
rg "table_name" app/Modules database tests
rg "endpoint|error_code|sdkFunction" staff-web/src customer-web/src build/api-consumer
```

Avoid broad terms such as `reservation`, `order`, `payment`, `status`, or `service` unless combined with a module path or exact symbol.

## Compression Checkpoint

Before editing, summarize:

- module owner
- current behavior
- change seam
- invariants to preserve
- focused verification
