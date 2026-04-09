---
name: restaurantpos-workstream-orchestrator
description: Plan and split RestaurantPOS hardening into safe, reviewable Codex workstreams with minimal shared-file collisions. Use when Codex needs to break a large request into parallel or sequential batches, assign scope boundaries, protect shared files, pick verification depth, or produce batch reports that follow the repo's AGENTS.md.
---

# RestaurantPOS Workstream Orchestrator

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before planning or executing a large batch.

## Workflow

1. Classify the request against the repo priority order before writing code.
2. Split the work into narrow, reviewable batches that own a small file set and a clear test surface.
3. Treat `routes/api.php`, `config/booking.php`, `config/staff_capabilities.php`, and `database/schema/mysql-schema.sql` as shared seams that need extra coordination.
4. Prefer service-layer implementation batches first, then schema or integration sync, then a final pass for seams and regressions.
5. End each batch with the required report shape: Intent, Changed files, Added or updated tests, Remaining risks.

## Guardrails

- Avoid broad refactors and speculative cleanup during orchestration.
- Defer large shared-file collisions to a later integration or schema-sync batch when possible.
- Keep bootstrap, API contract, and operational gate changes visible instead of burying them inside domain batches.
- If there is no Git metadata available, still maintain strict batch boundaries and explicit ownership.

## Verify

- Run targeted tests per batch, not only one full-suite pass at the end.
- Use `docs/codex-parallel-agent-prompts.md` as the default decomposition map for large hardening asks.
- Run an integration-focused test slice after shared seams are touched.
