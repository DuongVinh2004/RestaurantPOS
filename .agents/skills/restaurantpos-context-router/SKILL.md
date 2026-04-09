---
name: restaurantpos-context-router
description: Route RestaurantPOS tasks to the smallest correct context before doing a broad repo scan. Use when Codex needs to classify a request, choose the right project-local skill, decide which files to read first, minimize token use, or avoid loading unrelated domains for Laravel POS hardening work.
---

# RestaurantPOS Context Router

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/decision-map.md` before exploring a large or ambiguous task.

## Workflow

1. Classify the request into one primary domain before reading code.
2. Load one primary domain skill first.
3. Add at most two supporting process skills only if the task actually crosses those concerns.
4. Read the minimal first-pass files from `references/decision-map.md`.
5. Expand outward only when the first-pass files prove the task crosses more than one domain.

## Primary routing

- Split web-client contract, TypeScript SDK, mutation docs, error envelope, enum or state exposure, frontend DX: use `$restaurantpos-web-client-contracts`
- Split web auth, login refresh logout, auth headers, access sessions, session propagation, CORS auth delivery: use `$restaurantpos-web-auth-session-contract`
- Auth, capability mapping, actor resolution, access sessions, scope bleed: use `$restaurantpos-auth-rbac`
- Availability, hold, assignment, check-in, move-table, release: use `$restaurantpos-foh-reservations`
- Table order, item lifecycle, service-session mutation, order reads: use `$restaurantpos-order-lifecycle`
- Customer token, owner contract, self-service, waiting-list self-service, self-pay: use `$restaurantpos-customer-self-service`
- Checkout, refund, cashier shift, invoice, reconciliation, webhook, payment provider: use `$restaurantpos-checkout-finance`
- Kitchen routing, dispatch, ticket flow, KDS states: use `$restaurantpos-kitchen-kds`
- Inventory, recipe, stock movement, purchasing, receiving: use `$restaurantpos-inventory-purchasing`
- Route surface, requests, resources, OpenAPI, generated artifacts, API gate tests: use `$restaurantpos-api-contract-gates`
- SQL-first schema, patches, bootstrap, release artifacts, outbox, health, deploy readiness: use `$restaurantpos-ops-release-contract`
- Privacy requests, data export, anonymization, retention: use `$restaurantpos-data-lifecycle`
- Notification outbox, channel drivers, preferences, reminder and dead-letter behavior: use `$restaurantpos-notification-platform`
- Staff conversation inbox, assignment, links, internal notes, branch rollout: use `$restaurantpos-conversation-inbox`
- Branch-local business hours, closure windows, booking policy overrides, branch timezone rules: use `$restaurantpos-branch-scheduling`
- Branch settings, default branch behavior, reporting snapshot rebuilds, staff reporting read models: use `$restaurantpos-multi-branch-reporting`
- Multi-batch planning or parallelization: use `$restaurantpos-workstream-orchestrator`

## Supporting process skills

- Use `$restaurantpos-web-client-contracts` when the task is driven by `customer-web`, `staff-web`, generated SDK usage, mutation contract docs, FE error shapes, enum exposure, or browser-facing DX
- Use `$restaurantpos-web-auth-session-contract` when the task involves `X-Customer-Token`, `X-Staff-Key`, `X-Session-Id`, login lifecycle, access sessions, or split-web CORS auth behavior
- Use `$restaurantpos-shared-file-discipline` when the task may touch `routes/api.php`, `config/booking.php`, `config/staff_capabilities.php`, or `database/schema/mysql-schema.sql`
- Use `$restaurantpos-sql-first-schema-sync` when code behavior depends on schema, patch, or dump changes
- Use `$restaurantpos-targeted-verification` when you need the smallest safe test and gate set
- Use `$restaurantpos-prompt-router` when the user gave only a natural-language task and you want a scripted first-pass route
- Use `$restaurantpos-git-aware-verify` when Git metadata exists and you want diff-based path gathering before verification
- Use `$restaurantpos-skill-pack-quality` when you are editing the skill pack itself
- Use `$restaurantpos-runbook-sync` when operator commands, rollout behavior, or API consumer behavior changes
- Use `$restaurantpos-runtime-smoke` when SQLite-backed tests are not enough to prove runtime safety
- Use `$restaurantpos-feature-flag-rollout` when the request adds or changes a feature flag
- Use `$restaurantpos-audit-observability` when the change affects audit logs, outbox, metrics, alerts, or realtime feeds
- Use `$restaurantpos-performance-budget` when the task can affect query count, latency, or hot-path read shape

## Token discipline

- Do not start with full-tree scans through `app/`, `tests/`, `docs/`, and `database/` all at once
- Do not read `build/`, `storage/`, `vendor/`, or `node_modules/` unless the task is explicitly about generated artifacts, runtime evidence, dependencies, or release output
- Prefer 3 to 8 file reads plus one targeted search over whole-repo dumping
- If the task is clearly single-domain, stay inside that skill's hotspot files and tests first
