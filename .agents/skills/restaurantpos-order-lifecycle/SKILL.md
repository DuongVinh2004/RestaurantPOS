---
name: restaurantpos-order-lifecycle
description: Harden dine-in order creation, mutation, item lifecycle, service-session behavior, table order concurrency, and kitchen handoff in RestaurantPOS. Use when Codex works on staff order services, item status transitions, order read models, idempotent mutation paths, or regression tests around ordering and service flow.
---

# RestaurantPOS Order Lifecycle

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace table or service-session context, order mutation service, item lifecycle service, read model, and downstream kitchen or checkout handoff before patching.
2. Verify idempotent mutation paths, branch scope, table occupancy requirements, and stale or concurrent edit handling.
3. Keep read models separate from mutation logic; prefer service-layer guards over controller conditionals.
4. When item states or totals change, check checkout, inventory, and kitchen assumptions in the same batch.
5. Add regression tests for duplicate request replay, item status transitions, and branch mismatch.

## Guardrails

- Do not bypass table or branch guards just to simplify order creation.
- Treat kitchen routing and inventory consumption as downstream contracts that must remain consistent.
- Avoid broad refactors of order reads unless the request is explicitly about read-model design.
- Keep API payload shape stable unless contract artifacts and tests are updated together.

## Verify

- `php artisan test tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php tests/Feature/Staff/StaffOrderReadFlowTest.php`
- `php artisan test tests/Feature/Staff/StaffTableOrderBranchScopeTest.php tests/Feature/Staff/StaffTableOrderConcurrencyGuardServiceTest.php tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php`
- Run related checkout or kitchen tests if item state or totals feed those domains.
