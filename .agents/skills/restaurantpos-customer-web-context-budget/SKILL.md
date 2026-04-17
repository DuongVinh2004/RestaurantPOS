---
name: restaurantpos-customer-web-context-budget
description: Reduce token use and broad scans during RestaurantPOS customer-web work. Use when Codex needs to start a customer-web task, decide which files or skills to read first, avoid loading unrelated Laravel or staff-web context, produce a small implementation plan, or recover from context-heavy frontend exploration.
---

# RestaurantPOS Customer Web Context Budget

Use this skill first for customer-web tasks when the next step is not obvious. Its job is to keep context small and make the first read set intentional.

## Workflow

1. Classify the task: contract, auth, UI, form, screen recipe, feature flag, or verification.
2. Load at most three project-local skills first, including this one only while planning.
3. Read 3 to 7 files before deciding the implementation path.
4. Prefer `rg --files`, targeted `Get-Content`, and artifact paths over broad recursive scans.
5. Stop exploration when the target files, adapters, tests, and verification commands are clear.
6. Leave a compact note: selected context, skipped context, and reason.

Read `references/read-map.md` for first-read choices by task type.

## Skill Selection

- Contract or route readiness: use `restaurantpos-customer-web-contract-router`.
- API client work: use `restaurantpos-customer-web-api-client`.
- Auth/session work: use `restaurantpos-customer-web-auth-session`.
- Feature flag or blocked surface: use `restaurantpos-customer-web-feature-boundaries`.
- Visual design: use `restaurantpos-customer-web-design-system`.
- Page composition: use `restaurantpos-customer-web-screen-recipes`.
- Forms and async states: use `restaurantpos-customer-web-form-state-patterns`.
- Copy and status labels: use `restaurantpos-customer-web-copy-ux`.
- Final checks: use `restaurantpos-customer-web-verification`.

## Guardrails

- Do not inspect `vendor/`, `node_modules/`, `storage/`, or `build/` broadly.
- Do not load full generated artifacts unless searching a specific route or schema.
- Do not read staff-web files unless building shared frontend patterns or explicitly comparing.
- Do not keep this skill loaded once implementation starts.
- Do not turn a context budget pass into a design review.
