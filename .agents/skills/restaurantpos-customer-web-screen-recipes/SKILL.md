---
name: restaurantpos-customer-web-screen-recipes
description: Build RestaurantPOS customer-web screens faster with reusable page and section recipes. Use when Codex creates or refactors auth, account, menu, reservation, deposit, billing, payment status, waiting-list, blocked-feature, loading, empty, or error screens and needs consistent composition without rethinking layout from scratch.
---

# RestaurantPOS Customer Web Screen Recipes

Use this skill to compose screens from proven structures instead of custom layouts every time.

## Workflow

1. Pick the closest recipe from `references/screen-recipes.md`.
2. Pair with `restaurantpos-customer-web-design-system` for visual rules.
3. Pair with `restaurantpos-customer-web-copy-ux` for labels and state text.
4. Pair with `restaurantpos-customer-web-form-state-patterns` when the screen includes forms or mutations.
5. Keep the recipe recognizable unless the product task requires a real variation.

## Recipe Principles

- Put the primary customer task near the top.
- Keep one obvious next action per state.
- Use compact metadata rows for reservation, bill, deposit, and order status.
- Show blocked features as intentional product states, not empty developer placeholders.
- Keep page components thin by moving repeated sections into `features/*/components`.

## Guardrails

- Do not build a marketing landing page when the task is an app flow.
- Do not fake data to make a recipe look full.
- Do not make a blocked route look live.
- Do not create a new recipe for a one-off screen until reuse is likely.
