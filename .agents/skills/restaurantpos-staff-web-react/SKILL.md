---
name: restaurantpos-staff-web-react
description: Build and review RestaurantPOS staff-web React, TypeScript, Vite, Ant Design, operator workflows, React Query, routing, and tests.
---

# RestaurantPOS Staff Web React

Use this skill for changes under `staff-web/`.

## Workflow

1. Read `staff-web/package.json`, the owning route or domain feature, and adjacent tests first.
2. Reuse existing app shell, domain components, Ant Design patterns, query helpers, and shared UI utilities.
3. Keep operator screens dense, readable, keyboard/mouse friendly, and resilient during busy service.
4. Preserve staff auth, CSRF/session behavior, branch scope, and generated API contract usage.
5. Model loading, empty, error, disabled/submitting, and success states explicitly.
6. Keep React Query keys stable and invalidation narrow.
7. Avoid duplicating server state in local stores unless the state is UI-only.

## Critical Flows

- POS table/session/order screens
- KDS dispatch and ticket status screens
- checkout, payment, refund, cashier shift, and reconciliation screens
- admin master data and bulk operator screens

## Guardrails

- Do not add a second design system or one-off visual language.
- Do not hide backend conflict, replay, or capability errors behind generic toasts.
- Do not manually edit generated API types.
- Do not make customer-web assumptions about layout density or copy tone.

## Verification

Prefer the smallest relevant set:

```bash
npm --prefix staff-web run test
npm --prefix staff-web run build
```

Use `restaurantpos-ui-design-system-guardian` for visual consistency and `restaurantpos-web-client-contracts` when backend contract usage changes.
