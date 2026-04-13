# AGENTS.md

You are working inside a RestaurantPOS project.

Your job is not to produce random pretty screens.
Your job is to produce **production-lean, reusable, maintainable UI** that follows the active `DESIGN.md` in the project root.

## Hard rules
1. Treat `DESIGN.md` as the visual contract.
2. Prioritize usability, consistency, accessibility, and implementation realism.
3. Build reusable primitives before building pages.
4. Keep naming clean and domain-based.
5. Do not introduce visual drift across screens.
6. Prefer incremental refactor over chaotic rewrite unless the codebase is clearly unstructured.
7. Do not create “marketing-like” layouts inside staff-web.
8. Do not create “admin-like” density inside customer-web unless a screen explicitly needs it.
9. Use TypeScript, reusable components, clear props, and composable page sections.
10. Keep spacing, radius, shadows, borders, hover states, and typography consistent with `DESIGN.md`.

## Required workflow
1. Read current frontend structure.
2. Map existing pages/components.
3. Identify reusable primitives to standardize first:
   - App shell
   - Sidebar
   - Header / top bar
   - Page header
   - Section card
   - Table
   - Filter bar
   - Search input
   - Tabs
   - Modal / drawer
   - Form fields
   - Status badge
   - Empty state
   - Toast / inline alerts
   - KPI card
4. Create or refactor shared UI primitives.
5. Refactor pages module by module.
6. Ensure each screen is visually aligned to `DESIGN.md`.
7. Avoid dead code, duplicate components, or one-off style hacks.

## Delivery expectation
For every major UI task:
- state current problems
- propose component/page refactor plan
- implement reusable primitives
- implement pages
- explain what changed
- list follow-up improvements

## If working on staff-web
Optimize for:
- scanability
- dense information
- table productivity
- operations visibility
- keyboard-friendly workflows
- clear status communication
- reliable form UX

## If working on customer-web
Optimize for:
- conversion
- mobile-first usability
- menu discovery
- cart clarity
- checkout trust
- visual warmth
- high readability
