---
name: restaurantpos-customer-web-verification
description: Choose focused verification for RestaurantPOS customer-web frontend changes. Use when Codex finishes or reviews Next.js customer-web work, changes API adapters, auth/session storage, feature flags, forms, payment flows, reservation flows, UI states, or needs to report commands run and residual backend blockers.
---

# RestaurantPOS Customer Web Verification

Use this skill before closing a customer-web batch.

## Workflow

1. Inspect changed files and map them to the smallest meaningful checks.
2. Run static checks for every frontend code batch.
3. Run focused tests for the changed domains.
4. Run a production build when routing, env parsing, providers, or App Router structure changed.
5. If a dev server is started, visually verify the app with browser automation.
6. If backend contract assumptions changed, also use `restaurantpos-web-client-contracts` and report the backend gates that remain relevant.

Read `references/verification-ladder.md` for command selection.

## Minimum Frontend Checks

Use these when a `customer-web` package exists and scripts are available:

```bash
npm run lint
npm run typecheck
npm run test
```

Add `npm run build` for route/layout/provider/env changes.

## Domain Checks

- API client: header injection, envelope parsing, normalized errors, idempotency helper tests.
- Auth/session: token storage, session id storage, bootstrap, logout, protected route tests.
- Feature flags: env parsing, conservative defaults, blocked route behavior tests.
- Forms: Zod validation, accessible errors, mutation disabled/loading states.
- Payment and reservation UI: loading, empty, error, status refresh, and unavailable states.

## Final Report Requirements

Report:

- Intent.
- Changed files.
- Added or updated tests.
- Commands run.
- Live-integrated flows.
- Scaffolded-only flows.
- Remaining backend blockers.
- Residual risks.

## Guardrails

- Do not claim a flow is live-integrated unless its adapter hits a stable route.
- Do not treat a passing build as proof of API contract stability.
- Do not skip tests for auth, payment, or feature-flag boundaries when those files changed.
