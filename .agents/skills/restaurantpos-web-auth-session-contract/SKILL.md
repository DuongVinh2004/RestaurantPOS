---
name: restaurantpos-web-auth-session-contract
description: Harden header-based auth and session contracts for RestaurantPOS split web clients. Use when Codex changes customer or staff login, refresh, logout, X-Customer-Token, X-Staff-Key, X-Session-Id, session-bound route contracts, CORS auth headers, unauthorized or forbidden behavior, or frontend auth docs and tests for customer-web and staff-web.
---

# RestaurantPOS Web Auth & Session Contract

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace the flow from route to controller, middleware, config, actor resolution, and session or access service before patching.
2. Keep customer and staff auth models separate: customer uses dedicated access sessions and selected session-bound routes; staff and admin use staff API keys plus capability gates.
3. Preserve header-based auth for browser clients. Customer auth is `X-Customer-Token` plus `X-Session-Id` where required; staff or admin auth is `X-Staff-Key`.
4. When session-bound route behavior changes, update `config/customer_auth.php`, the relevant middleware, and the matching tests together.
5. When login, refresh, logout, or auth headers change, review `config/cors.php` and the FE-facing runbooks in the same batch.
6. Add or update allow-path and deny-path coverage for wrong actor, revoked or expired session, missing header, and cross-surface misuse.

## Guardrails

- Do not switch split web clients to cookie-session assumptions or `credentials: 'include'`.
- Do not widen legacy bearer or env fallback behavior just to satisfy local tests.
- Do not let customer session access bypass staff capability or owner-contract boundaries.
- Keep unauthorized and forbidden responses on the standardized API error envelope.

## Verify

- `php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Unit/Config/CustomerAuthConfigContractTest.php tests/Unit/Config/StaffAuthConfigContractTest.php`
- `php artisan test tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php`
- `php artisan test tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php`
