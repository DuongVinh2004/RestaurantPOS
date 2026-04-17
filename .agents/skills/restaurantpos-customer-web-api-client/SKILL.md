---
name: restaurantpos-customer-web-api-client
description: Build and protect the RestaurantPOS customer-web API client boundary for Next.js. Use when Codex creates or changes frontend API adapters, typed HTTP helpers, X-Customer-Token, X-Session-Id, Idempotency-Key handling, response envelope parsing, normalized API errors, or live-versus-mock adapter boundaries for customer-web.
---

# RestaurantPOS Customer Web API Client

Use this skill when writing `customer-web/lib/api` or feature adapters that call the Laravel API.

## Workflow

1. Use `restaurantpos-customer-web-contract-router` first when adding a new backend surface.
2. Keep fetch logic centralized under `lib/api/`; do not call `fetch` directly from page components.
3. Build typed feature adapters under `features/*` that depend on the shared HTTP client.
4. Validate request and response shapes with TypeScript and Zod where contract drift would be costly.
5. Keep mock adapters and live adapters separated behind an explicit feature boundary.
6. Add unit tests for header injection, error parsing, and mutation idempotency helpers.

## Required Client Behavior

- Read base URL from parsed env config.
- Inject `X-Customer-Token` from the auth storage abstraction when present.
- Inject `X-Session-Id` only when the feature contract requires a session-bound route.
- Generate or accept `Idempotency-Key` for unsafe mutations that require replay protection.
- Never rely on cookies, browser session cookies, or `credentials: include`.
- Preserve `X-Request-Id` from responses when available.
- Normalize API failures into a frontend error model.

Read `references/header-contract.md` for header rules and `references/response-envelope.md` for envelope parsing.

## Adapter Rules

Feature adapters should expose business operations, not raw endpoint names. For example, prefer `getActiveBill()` or `createDepositPaymentSession()` over a generic `post(path, body)` call outside `lib/api`.

Adapters may be live only when the route is classified `stable/live`. For scaffolded surfaces, export an adapter boundary that returns a typed unavailable result or is wired to a deliberate mock only behind a feature flag.

## Guardrails

- Do not scatter auth, session, idempotency, or response parsing across components.
- Do not swallow backend `error_code`, validation details, or `request_id`.
- Do not fake success in a live adapter.
- Do not let unstable routes leak into stable app navigation without a feature flag.
- Keep page components focused on rendering, form wiring, and mutations through adapters.

## Verify

Run the smallest relevant frontend checks after changes:

```bash
npm run lint
npm run typecheck
npm run test -- api
```
