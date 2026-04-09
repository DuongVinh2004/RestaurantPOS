---
name: restaurantpos-kitchen-kds
description: Harden kitchen routing, dispatch, KDS ticket state, fire, bump, recall behavior, and station safety in RestaurantPOS. Use when Codex changes kitchen services, kitchen or admin controllers, feature-flagged kitchen behavior, order-item to ticket state mapping, or kitchen regression tests.
---

# RestaurantPOS Kitchen & KDS

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Review the kitchen slice end to end: routing -> dispatch -> fire -> bump -> recall -> terminal handling.
2. Validate consistency between order item state and ticket state before changing any status transition.
3. Check feature flags, unrouted item behavior, route changes during active tickets, and terminal-state safety.
4. Keep kitchen logic inside services; controller updates should remain thin and contract-preserving.
5. Add regression tests for active, terminal, flagged, and unrouted paths.

## Guardrails

- Do not invent a new kitchen module when the request is to harden the current foundation.
- Preserve current route surface unless a contract change is intentional and tested.
- Treat active ticket mutation while routes change as a production-sensitive case.
- Call out any dependency on order lifecycle or inventory if the change crosses domains.

## Verify

- `php artisan test tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php tests/Feature/Admin/AdminKitchenRoutingFoundationHttpFlowTest.php`
- Run related order lifecycle tests if kitchen status or routing can affect item transitions.
- Add focused tests for any new feature-flag or unrouted-item path.
