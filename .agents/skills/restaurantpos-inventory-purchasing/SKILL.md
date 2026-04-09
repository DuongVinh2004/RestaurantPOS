---
name: restaurantpos-inventory-purchasing
description: Harden inventory, recipe, stock movement, purchase order, supplier, receiving, and inventory-to-order consumption flows in RestaurantPOS. Use when Codex touches admin inventory or purchasing services, stock invariants, branch scope, unit consistency, over-receive protection, or inventory regression tests.
---

# RestaurantPOS Inventory & Purchasing

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Review the operational chain: ingredient and recipe setup -> stock movement -> purchase order -> partial or final receiving -> order-item consumption.
2. Validate branch scope, unit consistency, over-receive protection, idempotent receiving, and stock movement correctness before patching.
3. Keep inventory rules in services and avoid pushing stock math into controllers.
4. If the change affects order consumption or kitchen readiness, check downstream tests in the same batch.
5. Add regression coverage for negative cases and inventory invariants.

## Guardrails

- Do not broaden this into a full inventory suite when the request is to harden the foundation.
- Preserve current admin API surface unless contract changes are intentional and tested.
- Treat partial receiving and stock adjustments as financial or operational records, not mutable scratch data.
- Call out shared-file changes if inventory config or schema contracts move.

## Verify

- `php artisan test tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php tests/Feature/Admin/AdminPurchasingFoundationHttpFlowTest.php`
- `php artisan test tests/Feature/Admin/AdminInventoryKitchenPurchasing*.php`
- Run related order lifecycle tests if inventory consumption behavior changes.
