---
name: restaurantpos-audit-observability
description: Protect audit trails and operational visibility in RestaurantPOS. Use when Codex changes audit event capture, actor resolution, outbox behavior, metrics, alerts, realtime feeds, notification health, or any mutation path that must remain observable without leaking sensitive data.
---

# RestaurantPOS Audit & Observability

Read `docs/audit-trail.md`, `docs/runbooks/notification-platform-v2.md`, and `references/observability-map.md` before editing this area.

## Workflow

1. Identify whether the change affects audit logging, notification outbox, metrics, alerts, or realtime feeds.
2. Trace actor resolution, request correlation, event recording, and health surfaces before patching.
3. Preserve observability without logging secrets, tokens, signatures, or low-value payload noise.
4. Add or update tests for both the functional behavior and the operational evidence path.
5. If operator behavior changes, also use `$restaurantpos-runbook-sync`.

## Guardrails

- Audit and outbox changes must remain production-safe even when requests replay
- Prefer canonical action names and concise summaries over dumping raw payloads
- Treat missing alert or health coverage as a regression for operations-sensitive changes
