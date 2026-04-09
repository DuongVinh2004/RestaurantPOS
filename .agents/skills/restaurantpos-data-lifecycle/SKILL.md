---
name: restaurantpos-data-lifecycle
description: Harden RestaurantPOS privacy requests, customer data export, anonymization, and retention enforcement. Use when Codex changes customer or admin privacy endpoints, export payload shape, anonymization redaction rules, retention pruning, or docs and tests that protect what must be kept for finance and audit versus what must be purged or redacted.
---

# RestaurantPOS Data Lifecycle

Read `AGENTS.md`, `.codex/AGENTS.md`, `docs/data-lifecycle.md`, and `references/paths.md` before patching.

## Workflow

1. Classify the change as export, request creation, admin review, commit anonymization, or retention enforcement.
2. Identify which artifacts must be preserved for finance, accounting, dispute, or audit before changing any redaction logic.
3. Keep controller changes thin and place retention, export, and anonymization rules in `app/Services/DataLifecycle/*`.
4. If the change affects route or response shape, combine with `restaurantpos-api-contract-gates`.
5. If the change touches retention tables or release-sensitive SQL artifacts, combine with `restaurantpos-sql-first-schema-sync` and `restaurantpos-ops-release-contract`.

## Guardrails

- Do not hard-delete payments, invoices, webhook receipts, or audit history.
- Preserve `user_id` lineage where history and reporting still depend on it.
- Keep `dry_run` side-effect free.
- Redact or purge source rows, not only read models or API serializers.
- When changing retention scope, document exactly which tables are newly pruned and why they are operationally safe.

## Verify

- `php artisan test tests/Feature/DataLifecycle`
- `php artisan test tests/Feature/DataLifecycle/DataLifecycleRetentionConsoleTest.php tests/Feature/DataLifecycle/DataLifecycleRouteSurfaceTest.php`
- Add `restaurantpos-runbook-sync` if the operator or customer-facing lifecycle contract moved
