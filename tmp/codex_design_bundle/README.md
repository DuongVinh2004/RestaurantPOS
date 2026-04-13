# Codex Design Bundle for RestaurantPOS

This bundle is a **ready-to-drop package** for Codex or other AI coding agents.

Why this bundle exists:
- The original `awesome-design-md` repository is mostly a **catalog of links**.
- The uploaded ZIP does **not** include full offline `DESIGN.md` source files for each style.
- So this package provides the **practical files you actually need** to put into your project and let Codex execute UI work.

## What is included

### Root guidance
- `AGENTS.md` — execution rules for Codex
- `CODEX_EXECUTION_PROMPTS.md` — paste-ready prompts
- `STYLE_SELECTION_SUMMARY.md` — why these style mixes were chosen
- `MANIFEST.json` — bundle manifest

### Staff web
- `staff-web/DESIGN.md`
- `staff-web/UI_SCOPE.md`
- `staff-web/REFERENCE_LINKS.md`

### Customer web
- `customer-web/DESIGN.md`
- `customer-web/UI_SCOPE.md`
- `customer-web/REFERENCE_LINKS.md`

### References
- `references/original-repo/...` — selected README files copied from the uploaded repository for traceability

## Recommended use

### If you are working on staff-web first
1. Copy `AGENTS.md` to your project root.
2. Copy `staff-web/DESIGN.md` to your project root as `DESIGN.md`.
3. Optionally also copy `staff-web/UI_SCOPE.md` and `CODEX_EXECUTION_PROMPTS.md`.
4. Ask Codex to audit current UI and refactor it to match `DESIGN.md`.

### If you are working on customer-web
1. Copy `AGENTS.md` to your project root.
2. Copy `customer-web/DESIGN.md` to your project root as `DESIGN.md`.
3. Optionally also copy `customer-web/UI_SCOPE.md` and `CODEX_EXECUTION_PROMPTS.md`.
4. Ask Codex to design and implement storefront flows based on `DESIGN.md`.

## Important note
The `DESIGN.md` files in this bundle are **original synthesis documents** tailored for RestaurantPOS and Codex execution.
They are based on the style directions selected from the original repo:
- Staff-web: Linear + Airtable + IBM + Sentry
- Customer-web: Airbnb + Apple + Stripe + Uber
