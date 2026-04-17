---
name: restaurantpos-customer-web-form-state-patterns
description: Standardize customer-web forms, TanStack Query hooks, loading states, empty states, error states, mutation UX, and validation. Use when Codex implements React Hook Form, Zod schemas, query keys, mutations, optimistic or polling behavior, submit disabled states, validation errors, payment refresh, reservation forms, or async customer-web UI.
---

# RestaurantPOS Customer Web Form State Patterns

Use this skill when a screen reads data, submits data, validates input, or renders async state.

## Workflow

1. Keep Zod schemas near the feature adapter or form, depending on whether they validate API contracts or UI input.
2. Use React Hook Form for form state and Zod for validation.
3. Use TanStack Query for server state. Do not duplicate fetched server state in local stores.
4. Use stable query keys organized by feature.
5. Model async states explicitly: loading, empty, error, ready, submitting, success.
6. Map normalized API validation errors back to fields when possible.
7. Prevent duplicate mutations with disabled buttons and idempotency helpers where required.

Read `references/form-patterns.md` and `references/query-state-patterns.md` for compact recipes.

## Mutation Rules

- Generate `Idempotency-Key` only in the API layer or mutation helper.
- Disable submit while a mutation is pending.
- Keep customer input visible after server validation errors.
- Invalidate or refresh only the query keys affected by the mutation.
- Use polling only for payment/session flows that need fresh status.

## Guardrails

- Do not call APIs directly from form components.
- Do not use local component state as the source of truth for server records.
- Do not hide validation errors in toast-only feedback.
- Do not optimistically mark payment, deposit, or bill state as complete unless the backend confirms it.
