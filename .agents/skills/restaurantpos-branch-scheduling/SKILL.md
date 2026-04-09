---
name: restaurantpos-branch-scheduling
description: Harden RestaurantPOS branch-local business hours, closure windows, booking policy overrides, and branch timezone resolution. Use when Codex changes `BranchSchedulingPolicyService`, branch policy payloads, admin branch settings, or downstream reservation, hold, availability, waiting-list, or self-service behavior that depends on branch-local scheduling rules.
---

# RestaurantPOS Branch Scheduling

Read `AGENTS.md`, `.codex/AGENTS.md`, `docs/runbooks/branch-scheduling-policy-resolution.md`, and `references/paths.md` before patching.

## Workflow

1. Decide whether the change belongs to branch policy definition, policy resolution, or a downstream flow that consumes effective branch rules.
2. Resolve policy in branch timezone before changing validation logic.
3. Keep override-only semantics: `null` branch JSON means fall back to `config('booking.branch_policy_defaults.*')`.
4. Update downstream availability, hold, reservation, waiting-list, and self-service guards together when their shared policy meaning changes.
5. If branch settings payloads or defaults move, call out shared-file risk on `config/booking.php`.

## Guardrails

- Do not backfill global defaults into every branch row unless the branch is meant to detach from future fallback changes.
- Do not evaluate branch windows in server timezone.
- Preserve the current allowance for realtime windows that should not fail on zero-minute lead time.
- Keep closure windows and same-day cutoffs consistent across admin, customer, and staff surfaces.

## Verify

- `php artisan test tests/Unit/Services/Branch/BranchSchedulingPolicyServiceTest.php`
- `php artisan test tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php`
- Expand to FOH or waiting-list tests if the change altered downstream runtime behavior
