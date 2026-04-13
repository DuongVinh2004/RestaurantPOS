# CODEX_EXECUTION_PROMPTS.md

## Prompt 1 — Staff-web foundation
Use the `AGENTS.md` and the active `DESIGN.md` in the project root as hard constraints.

Target:
- RestaurantPOS staff-web
- React + TypeScript
- operational, dense, scalable UI

What to do:
1. Audit the current frontend structure.
2. Identify all shared UI primitives that are inconsistent or duplicated.
3. Refactor the design foundation first:
   - app shell
   - sidebar
   - top bar
   - page header
   - section/card container
   - button variants
   - form fields
   - modal/drawer
   - table
   - filter bar
   - badges
   - alerts/toasts
   - empty states
4. Align all primitives to `DESIGN.md`.
5. Then refactor pages module by module.

Output:
- a structured audit
- a component refactor plan
- implementation changes
- a list of follow-up UI improvements

## Prompt 2 — Staff-web page refactor
Use the project-root `DESIGN.md` as the visual contract.

Refactor existing staff-web pages so they become:
- visually consistent
- easier to scan
- more productive for operators
- table/filter/form friendly
- responsive enough for laptop and desktop usage

For every page:
- identify UX issues
- identify visual inconsistency
- identify reusable patterns that should be abstracted
- implement the refactor using shared components
- remove duplicate styling logic

Do not turn staff screens into marketing pages.
Do not oversize headers or waste vertical space.
Keep the UI calm, precise, and operational.

## Prompt 3 — Customer-web foundation
Use the `AGENTS.md` and the active `DESIGN.md` in the project root as hard constraints.

Target:
- RestaurantPOS customer-web
- storefront + ordering + cart + checkout
- mobile-first, premium, clear, conversion-oriented

What to do:
1. Audit current frontend or create the design foundation if missing.
2. Build shared storefront primitives:
   - layout shell
   - hero / promo section
   - menu category nav
   - product card
   - combo / promo card
   - cart drawer / cart page
   - quantity stepper
   - checkout form
   - payment summary
   - trust / info blocks
   - order timeline / status
3. Align all visuals to `DESIGN.md`.
4. Keep the flow warm, polished, and easy to use.

Output:
- design foundation summary
- primitive inventory
- page composition plan
- implementation details
- follow-up improvements

## Prompt 4 — Customer-web journey implementation
Use the project-root `DESIGN.md` as the visual contract.

Implement or refactor the core customer journey:
- landing / discovery
- menu listing
- menu detail / customization
- cart
- checkout
- success / receipt / tracking

Rules:
- mobile-first first, desktop second
- strong visual hierarchy
- clear CTA placement
- payment trust
- minimal friction
- image-friendly but not cluttered
- readable typography
- consistent spacing and states

## Prompt 5 — Cross-check consistency
Review the entire frontend against:
- `AGENTS.md`
- `DESIGN.md`
- existing domain flows

Find and fix:
- inconsistent radius, border, spacing, typography, shadows
- duplicated components
- awkward empty states
- poor table ergonomics
- weak form validation UX
- visually inconsistent dialogs and drawers
- inaccessible contrast or focus states
- pages that drift away from the design system
