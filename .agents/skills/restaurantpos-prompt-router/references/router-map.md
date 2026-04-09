# Router Map

## Pick the invariant owner

- Customer-web or staff-web contract, generated SDK, mutation docs, FE error envelope, enum or state exposure: `restaurantpos-web-client-contracts`
- Split web auth headers, access sessions, login refresh logout, CORS auth delivery: `restaurantpos-web-auth-session-contract`
- Identity, actor boundary, deny path, capability: `restaurantpos-auth-rbac`
- Reservation window, table board, holds, release, check-in: `restaurantpos-foh-reservations`
- Table order mutation, item lifecycle, service session: `restaurantpos-order-lifecycle`
- Customer token access, self-service, self-pay: `restaurantpos-customer-self-service`
- Checkout, refund, cashier shift, finance reconciliation: `restaurantpos-checkout-finance`
- Kitchen dispatch, routing, ticket state: `restaurantpos-kitchen-kds`
- Inventory movement, recipe, purchasing, receiving: `restaurantpos-inventory-purchasing`
- Route surface, form requests, resources, OpenAPI, artifacts: `restaurantpos-api-contract-gates`
- SQL-first bootstrap, patches, release gates, runtime safety: `restaurantpos-ops-release-contract`
- Privacy request, data export, anonymization, retention: `restaurantpos-data-lifecycle`
- Notification outbox, channel drivers, preferences, dead-letter, reminders: `restaurantpos-notification-platform`
- Staff conversation inbox, assignment, links, internal notes: `restaurantpos-conversation-inbox`
- Branch-local business hours, closure windows, booking policy overrides, timezone rules: `restaurantpos-branch-scheduling`
- Branch settings, default branch behavior, reporting snapshot rebuilds, staff reporting read models: `restaurantpos-multi-branch-reporting`

## Common supporting skills

- Need browser-facing contract or DX coverage for `customer-web` or `staff-web`: `restaurantpos-web-client-contracts`
- Need split web auth or session propagation coverage: `restaurantpos-web-auth-session-contract`
- Shared seam touched: `restaurantpos-shared-file-discipline`
- Need smallest test set: `restaurantpos-targeted-verification`
- Need Git diff based path collection: `restaurantpos-git-aware-verify`
- Need operator or runbook sync: `restaurantpos-runbook-sync`
- Need feature flag rollout safety: `restaurantpos-feature-flag-rollout`
- Need audit, metrics, or outbox visibility: `restaurantpos-audit-observability`
- Need query-count or latency guardrails: `restaurantpos-performance-budget`

## Escalate to orchestration

- The prompt scores strongly in two domains with different shared files.
- The change touches both schema contract and operator behavior.
- The request spans branch policy plus downstream reservation or waiting-list flows.
- The request spans branch settings plus reporting read models or snapshot rebuild logic.
