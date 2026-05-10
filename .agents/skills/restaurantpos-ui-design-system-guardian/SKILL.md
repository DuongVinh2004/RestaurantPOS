---
name: restaurantpos-ui-design-system-guardian
description: Enforce RestaurantPOS staff-web and customer-web UI consistency, states, responsiveness, accessibility, tables, forms, modals, and status patterns.
---

# RestaurantPOS UI Design System Guardian

Use this skill for UI changes in `staff-web/` or `customer-web/`.

## Workflow

1. Identify the app, route, existing component pattern, API types involved, and required UI states before editing.
2. Reuse the app's current primitives and tokens: Ant Design patterns in `staff-web`, shadcn/Radix/Tailwind patterns in `customer-web`.
3. Keep page files thin; move reusable UI state and domain composition into existing feature/component locations.
4. Handle loading, empty, error, success, disabled/submitting, and permission-denied states.
5. Check responsive behavior, long text, focus visibility, accessible labels, and color-not-only status signals.
6. Run the narrow frontend verification that matches the app and changed surface.

## Staff-Web Principles

- Optimize for fast operator scanning and repeated action.
- Prefer dense but readable layouts, stable tables, clear filters, visible status badges, and low visual noise.
- Keep destructive and payment/cashier actions guarded and easy to audit.

## Customer-Web Principles

- Optimize for mobile-first booking, menu, account, payment, and reservation flows.
- Keep copy short, non-technical, and clear about payment, deposit, conflict, and retry states.
- Avoid admin density, decorative effects, and unstable backend surfaces that look live.

## Component Rules

- Forms need labels, validation messages, submitting protection, API error rendering, and cancel/back behavior where appropriate.
- Tables and lists need loading, empty, error, stable columns, long-text handling, pagination or scrolling, and consistent date/time/price/status display.
- Modals need title, concise body, primary action, cancel action, async loading state, safe close behavior, and destructive confirmation when needed.
- Status badges must use consistent labels and include text, not color alone.

## Guardrails

- Do not introduce random colors, spacing, shadows, radii, fonts, or one-off widgets.
- Do not nest cards, create marketing-style operator screens, or use decorative blobs/orbs.
- Do not manually change generated API types or hide backend error envelopes.

## Verification

```bash
npm --prefix staff-web run test
npm --prefix staff-web run build
npm --prefix customer-web run typecheck
npm --prefix customer-web run test:journeys
```

Run only the commands that match the changed app, then escalate for critical booking/payment/operator flows.
