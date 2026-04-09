---
name: restaurantpos-customer-self-service
description: Harden customer token access, reservation self-service, waiting-list self-service, deposit and bill self-pay, and customer visibility rules in RestaurantPOS. Use when Codex touches customer access sessions, customer-auth middleware, owner-contract enforcement, session-scoped reads, self-service mutation guards, or tests for customer-facing flows.
---

# RestaurantPOS Customer Self-Service

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace token or session issuance, middleware resolution, visibility rules, and the target self-service service before editing.
2. Verify owner contract, session purpose and scope, token expiry, visibility filtering, and idempotent mutation behavior.
3. Keep customer response payloads stable and avoid leaking staff-only fields.
4. When payment sessions change, check webhook and financial sync downstream contracts.
5. Add tests for happy path and wrong-user, wrong-session, expired-session, or unauthorized cases.

## Guardrails

- Never reuse staff auth assumptions for customer flows.
- Avoid widening self-service visibility for convenience.
- Call out any change touching customer access session tables or JWT secrets.
- Prefer service-layer ownership checks over controller branching.

## Verify

- `php artisan test tests/Feature/Customer tests/Feature/WaitingList`
- `php artisan test tests/Feature/Reservation/CustomerReservation* tests/Feature/Auth/Customer*`
- Run payment session tests if deposit or bill self-pay behavior changes.
