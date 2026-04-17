---
name: restaurantpos-customer-web-ui-flow
description: Compose mobile-first RestaurantPOS customer-web pages and components with Next.js App Router, React, Tailwind, shadcn/ui, forms, loading states, empty states, and error states. Use when Codex builds or reviews customer-facing auth, account, menu, reservation, deposit, billing, payment, waiting-list, or blocked-feature UI.
---

# RestaurantPOS Customer Web UI Flow

Use this skill when building customer-facing pages for `customer-web`. Pair it with the available Next.js, React, shadcn, and UI design skills when those trigger.

## Workflow

1. Start from the real product task, not a marketing landing page.
2. Keep route groups clear: public auth, protected customer app, and flagged/scaffolded routes.
3. Build mobile-first layouts with large tap targets and stable spacing.
4. Use shadcn primitives for buttons, inputs, labels, dialogs, alerts, skeletons, badges, tabs, and sheets.
5. Use React Hook Form and Zod for meaningful forms.
6. Add loading, empty, disabled, and error states as first-class UI.
7. Route all backend interactions through feature adapters and hooks, never direct API calls in page components.
8. Use `restaurantpos-customer-web-feature-boundaries` for blocked or scaffolded surfaces.
9. Use `restaurantpos-customer-web-design-system` for visual consistency, `restaurantpos-customer-web-screen-recipes` for page structure, `restaurantpos-customer-web-form-state-patterns` for forms and async states, and `restaurantpos-customer-web-copy-ux` for customer-facing text.

Read `references/ui-checklist.md` before finishing a UI batch.

## Product Tone

Write customer-facing copy only. Keep text short, helpful, and non-technical. Payment, reservation, and session states should be easy to scan on a phone.

## Visual Guardrails

- Prefer a light customer-facing theme with restrained restaurant accents.
- Avoid admin-dashboard density for customer flows.
- Do not nest cards inside cards.
- Keep card and button radius at 8px or less.
- Avoid one-note palettes, dominant purple or purple-blue gradients, beige/tan themes, dark blue/slate themes, and brown/orange/espresso themes.
- Keep text within its parent on mobile and desktop.
- Use stable dimensions for repeated tiles, actions, counters, and payment status panels.

## Component Boundaries

Keep page files thin. Put reusable states in `components/states`, product sections in `components`, and domain-specific composition in `features/*/components`.

## Guardrails

- Do not ship lorem ipsum or fake production data.
- Do not make an unstable backend surface look live.
- Do not expose tokens or session ids except sanitized dev-only diagnostics.
- Do not use raw form controls when shadcn primitives already exist.
