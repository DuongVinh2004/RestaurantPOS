---
name: restaurantpos-customer-web-auth-session
description: Protect customer-web authentication and session behavior in the Next.js client. Use when Codex implements or changes login, current customer bootstrap, refresh, logout, protected routes, token storage, X-Customer-Token, X-Session-Id handling, auth providers, or customer-only route guards.
---

# RestaurantPOS Customer Web Auth Session

Use this skill for customer-web auth flows. Customer auth is header-based and must stay separate from staff or admin auth.

## Workflow

1. Use `restaurantpos-web-auth-session-contract` and `restaurantpos-customer-web-contract-router` before changing auth integration.
2. Confirm the auth routes are contract-worthy in frozen OpenAPI or generated consumer artifacts.
3. Store customer token and optional session id through a small `lib/auth` storage abstraction.
4. Bootstrap app auth state with a current-customer query, not by decoding token contents in UI code.
5. Implement refresh only when the backend contract clearly supports it.
6. Clear token, session id, and auth query cache on logout.
7. Keep dev-only diagnostics behind environment checks and never render tokens in production.

Read `references/storage-policy.md` before choosing storage behavior.

## Expected Boundaries

- `lib/auth/`: token storage, session id storage, auth state helpers, bootstrap helpers.
- `features/auth/`: login form, auth mutations, auth API adapter.
- `providers/`: query provider and auth/session provider if needed.
- Route groups: public auth routes remain separate from protected customer routes.

## Guardrails

- Do not use cookies or `credentials: include`.
- Do not mix `X-Staff-Key` or admin bearer assumptions into customer-web.
- Do not use localStorage directly in random components.
- Do not persist more customer data than needed for auth bootstrap.
- Do not treat a missing token and a forbidden owner/session response as the same UX case.

## UX Requirements

Login, expired-session, and logout states should be clear and non-technical. Protected routes should avoid flashing sensitive content before bootstrap completes.

## Verify

Run focused tests for storage, guards, and auth adapters:

```bash
npm run lint
npm run typecheck
npm run test -- auth
```
