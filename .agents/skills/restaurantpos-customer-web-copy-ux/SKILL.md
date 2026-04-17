---
name: restaurantpos-customer-web-copy-ux
description: Write consistent customer-facing copy for RestaurantPOS customer-web. Use when Codex creates or reviews labels, button text, helper text, validation messages, empty states, blocked-feature copy, auth messages, reservation status text, deposit or bill payment status, errors, toasts, and dev-only diagnostics wording.
---

# RestaurantPOS Customer Web Copy UX

Use this skill when text affects customer trust, clarity, or flow completion.

## Workflow

1. Write for customers, not operators or developers.
2. Keep sentences short and specific.
3. Explain what happened and what the customer can do next.
4. Use consistent labels for reservations, bills, deposits, sessions, and payments.
5. Keep technical details behind dev-only diagnostics or support request ids.
6. Check `references/copy-bank.md` before inventing new state text.

## Tone

Use calm, direct copy. Avoid cheerleading, technical jargon, vague apologies, and promises the backend cannot support.

## Label Rules

- Use `Reservation`, `Bill`, `Deposit`, `Payment`, `Order`, and `Visit` consistently.
- Prefer `Continue`, `Try again`, `Refresh`, `Cancel reservation`, and `Pay bill` over clever labels.
- Use `Not available yet` for blocked features.
- Use `Sign in again` for expired auth.
- Include request id only in support-oriented text.

## Guardrails

- Do not expose route names, exception names, token values, or session ids.
- Do not say a feature is coming soon if the team has not committed to it.
- Do not turn backend uncertainty into customer-facing promises.
- Do not use lorem ipsum or filler restaurant copy.
