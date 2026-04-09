---
name: restaurantpos-auth-rbac
description: Harden RestaurantPOS authentication, identity boundaries, staff capability mapping, staff API key access, and customer/staff scope separation. Use when Codex works on auth middleware, capability config, route authorization, access sessions, legacy auth compatibility, or regression tests for allow/deny paths on staff, admin, or customer endpoints.
---

# RestaurantPOS Auth & RBAC

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace the request path from `routes/api.php` through middleware, actor resolution, config, and the target service before changing code.
2. Prefer auth fixes in middleware, actor resolution, or capability mapping instead of pushing logic into controllers.
3. For each staff or admin mutation route you touch, verify capability coverage, branch scope, deny-path behavior, and customer/staff separation.
4. Keep env fallback and legacy alias behavior explicit. Production-like environments must remain strict.
5. Add or update regression tests for both allowed and denied access.

## Guardrails

- Touch `routes/api.php` and `config/staff_capabilities.php` only when the route surface or capability map actually changes.
- Do not widen role-name or env-based fallbacks just to make tests pass.
- Treat missing unauthorized coverage as a bug.
- Call out any auth change that can affect bootstrap credentials or API consumers.

## Verify

- `php artisan test tests/Feature/Auth tests/Feature/Staff/StaffCapabilityHttpGuardTest.php`
- `php artisan test tests/Unit/Http/Middleware tests/Unit/Config/StaffAuthConfigContractTest.php tests/Unit/Config/StaffCapabilitiesConfigContractTest.php tests/Unit/Config/CustomerAuthConfigContractTest.php`
- Run `php artisan booking:doctor --json` after auth changes that affect runtime access.
