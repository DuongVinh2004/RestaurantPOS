---
name: restaurantpos-performance-budget
description: Protect RestaurantPOS hot-path query budgets and performance regression gates. Use when Codex changes read-heavy services, query shape, eager loading, reporting or timeline reads, performance verification commands, or any flow likely to increase query count or request latency.
---

# RestaurantPOS Performance Budget

Read `docs/performance-hot-paths.md` and `references/hot-path-map.md` before patching hot paths.

## Workflow

1. Decide whether the change touches a read-heavy or latency-sensitive path.
2. Review the current hotspot evidence and query-budget tests before optimizing or refactoring.
3. Prefer query-count reductions, better hydration, and batched reads over speculative caching.
4. Add or update budget tests when the read shape intentionally changes.
5. If runtime-like load verification matters, also use `$restaurantpos-runtime-smoke` and the performance runbook.

## Guardrails

- Do not add caching without a credible invalidation story
- Do not optimize write-path correctness away for a minor read win
- Keep performance claims tied to existing harnesses or explicit measurements
