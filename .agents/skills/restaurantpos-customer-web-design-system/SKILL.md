---
name: restaurantpos-customer-web-design-system
description: Keep RestaurantPOS customer-web visuals consistent, polished, and token-efficient. Use when Codex defines or applies Tailwind tokens, shadcn/ui composition, layout rhythm, color, typography, spacing, component variants, payment or reservation status visuals, or reviews UI for visual consistency and customer-facing quality.
---

# RestaurantPOS Customer Web Design System

Use this skill when making customer-web look cohesive instead of assembling one-off Tailwind.

## Workflow

1. Start from existing `customer-web` tokens and shadcn setup when present.
2. Apply a small visual system: type scale, spacing rhythm, radius, surfaces, status colors, and action hierarchy.
3. Use shadcn primitives first, then local wrappers only when repeated patterns justify them.
4. Keep new components close to the domain until they are reused at least twice.
5. Check the UI against `references/visual-rules.md` before finishing.

## Design Direction

Customer-web should feel calm, quick to scan, and useful on a phone. Favor light surfaces, clear action hierarchy, readable status treatment, and a small accent set. Avoid admin density and novelty effects.

## Component Rules

- Use stable dimensions for repeated cards, menu items, payment panels, and reservation rows.
- Keep buttons and cards at 8px radius or less.
- Use one primary action per screen area.
- Use badges for status, not for every piece of metadata.
- Use separators and whitespace before adding more containers.
- Prefer simple list sections over card grids on narrow mobile screens.

Read `references/visual-rules.md` and `references/status-visuals.md` when implementing visual details.

## Guardrails

- Do not create a one-note palette.
- Do not use dominant purple, purple-blue gradients, beige/tan, dark blue/slate, or brown/orange/espresso themes.
- Do not nest cards.
- Do not create decorative blobs, gradient orbs, or bokeh backgrounds.
- Do not invent a design system outside shadcn and Tailwind tokens.
