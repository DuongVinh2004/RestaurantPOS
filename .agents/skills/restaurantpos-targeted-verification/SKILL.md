---
name: restaurantpos-targeted-verification
description: Choose the smallest high-signal verification set for RestaurantPOS changes. Use when Codex has a path list or changed file set and needs to recommend focused tests, artisan checks, and runtime gates without defaulting to the full test suite or missing SQL-first and runtime-sensitive validation.
---

# RestaurantPOS Targeted Verification

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/verification-rules.md` before selecting commands.

## Workflow

1. Gather the changed files or the likely file set for the batch.
2. Run `python .agents/skills/restaurantpos-targeted-verification/scripts/recommend_verify.py <path...>` from the repo root.
3. If Git metadata exists and you do not want to assemble paths manually, use `restaurantpos-git-aware-verify` first.
4. Start with the smallest command set that proves the change.
5. Escalate only when the file set touches schema, shared files, runtime-sensitive services, API contract surfaces, feature flags, or performance hot paths.
6. In the final report, state what you ran and what you intentionally did not run.

## Guardrails

- Do not jump to `php artisan test` or a full gate by default
- Do not treat SQLite-backed tests as proof of MySQL, Redis, scheduler, or release safety
- If `routes/api.php`, `config/booking.php`, `config/staff_capabilities.php`, or `database/schema/mysql-schema.sql` changed, treat that as an escalation trigger
- If the script recommends multiple domains, run the narrowest tests from each touched domain before considering a full suite

## Script usage

```bash
python .agents/skills/restaurantpos-targeted-verification/scripts/recommend_verify.py app/Services/Staff/StaffCheckoutService.php tests/Feature/Payments/PaymentProviderWebhookFlowTest.php
```

The script prints:

- recommended skills
- recommended test and gate commands
- notes about shared-file, schema, or runtime escalation

Use `--json` if you want machine-readable output.
