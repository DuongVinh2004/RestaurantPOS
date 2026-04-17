---
name: restaurantpos-customer-web-feature-boundaries
description: Gate unstable RestaurantPOS customer-web features behind explicit flags and support-matrix decisions. Use when Codex adds or changes feature flags, placeholder flows, mock adapters, disabled UI, waiting-list, vouchers, privacy requests, data export, menu detail, table availability, table holds, or any customer-web surface whose backend support may be partial or fallback-only.
---

# RestaurantPOS Customer Web Feature Boundaries

Use this skill when customer-web needs a product surface that the backend may not safely support yet.

## Workflow

1. Classify the backend surface with `restaurantpos-customer-web-contract-router`.
2. Add or update a flag in `lib/config/feature-flags`.
3. Add the route to the support matrix in code with one of: `stable/live`, `scaffolded/flagged`, or `blocked/unavailable`.
4. Keep live adapters disabled for scaffolded or blocked surfaces.
5. Render a polished disabled, coming-soon, or unavailable state instead of fake live behavior.
6. Add tests for default flag values and boundary behavior.

Read `references/flag-defaults.md` before choosing default states.

## Required Flags

Define these flags when the frontend config is created:

- `NEXT_PUBLIC_FEATURE_MENU_CATEGORIES`
- `NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL`
- `NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY`
- `NEXT_PUBLIC_FEATURE_TABLE_HOLDS`
- `NEXT_PUBLIC_FEATURE_WAITING_LIST`
- `NEXT_PUBLIC_FEATURE_VOUCHERS`
- `NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS`
- `NEXT_PUBLIC_FEATURE_DATA_EXPORT`

Defaults should be conservative unless contract evidence proves the feature is ready.

## Boundary Pattern

A flagged feature should have:

- A route or component boundary.
- A support-matrix entry.
- A disabled or scaffolded UI state.
- A feature adapter interface.
- No live mutation path unless classified `stable/live`.

## Guardrails

- Do not use a mock adapter on an enabled live production path.
- Do not hide unstable backend dependencies inside a reusable hook.
- Do not make waiting-list, table holds, vouchers, privacy, or data export part of the core happy path until their contracts are stable.
- Do not let environment flags override a `blocked/unavailable` support-matrix decision without code changes.
