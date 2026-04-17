---
name: restaurantpos-customer-web-contract-router
description: Classify RestaurantPOS customer-web backend surfaces before a Next.js frontend consumes them. Use when Codex starts or changes customer-web flows, chooses live versus scaffolded API integration, reads frozen OpenAPI or consumer artifacts, builds a route support matrix, or needs to avoid fallback-only customer routes.
---

# RestaurantPOS Customer Web Contract Router

Use this skill before wiring customer-web to Laravel routes. Its job is to decide what is safe to integrate now, what should be scaffolded behind a feature boundary, and what should be avoided.

## Workflow

1. Read `AGENTS.md`, `.codex/AGENTS.md`, and the relevant domain skill before broad code inspection.
2. Load `restaurantpos-web-client-contracts` for artifact rules. Also load the domain skill that owns the flow: `restaurantpos-web-auth-session-contract`, `restaurantpos-customer-self-service`, `restaurantpos-foh-reservations`, `restaurantpos-checkout-finance`, or `restaurantpos-data-lifecycle`.
3. Treat `storage/app/booking_release/openapi-v1.json` and generated consumer artifacts under `build/api-consumer/` as the frontend-facing source of truth when present.
4. Inspect routes and controllers only to resolve ambiguity. Do not replace artifact evidence with controller guesses.
5. Produce a support matrix before implementing a major flow. Use `references/support-matrix-template.md`.
6. Route live integrations only through surfaces classified `stable/live`.
7. For `scaffolded/flagged` surfaces, create the UI and adapter boundary without enabling live submission.
8. For `blocked/unavailable` surfaces, avoid adding a main user journey dependency.

## Classification Rules

Mark a route `stable/live` only when the contract is visible in frozen OpenAPI or generated consumer artifacts, request and response envelopes are clear, auth and session headers are explicit, and error behavior is not ambiguous.

Mark a route `scaffolded/flagged` when the product flow is needed but the backend surface is partial, fallback-only, dependent on blocked subflows, missing SDK coverage, or not ready for a customer-critical path.

Mark a route `blocked/unavailable` when the route is absent, clearly fallback-only, owner/session semantics are unclear, 401 versus 403 behavior is unresolved, or live use would require guessing request shape.

## Frontend Stability Bias

Prefer live integration first for customer auth, current customer reads, clear reservation reads, clear deposit/payment-session flows, bill preview and bill payment-session flows, stable loyalty reads, and stable menu item list routes.

Default to scaffolded or blocked for menu categories, menu item detail, preorder preview, table availability, table holds, voucher apply/remove, loyalty redeem/release, vouchers list, data export, privacy requests, waiting-list flows, and any route with unclear customer owner/session semantics.

## Output

Before coding, leave a compact matrix in the working notes or implementation summary:

- Route or feature
- Status: `stable/live`, `scaffolded/flagged`, or `blocked/unavailable`
- Evidence source
- Required headers: `X-Customer-Token`, `X-Session-Id`, `Idempotency-Key`
- Frontend decision

## Guardrails

- Do not use fallback-only routes as production paths.
- Do not infer customer-web contract shape from localized messages or incidental controller internals.
- Do not wire one live flow through a blocked dependency such as table holds or voucher redemption.
- Do not update generated API artifacts by hand.
- Keep shared backend files untouched unless the user explicitly asks for backend changes.
