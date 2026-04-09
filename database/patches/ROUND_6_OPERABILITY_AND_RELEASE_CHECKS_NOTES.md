# Round 6 — Operability + release checks + ops visibility

This round is intentionally code-only: no schema migration is required.

## What this round hardens

- expands `booking:ops-snapshot` visibility with:
  - `staff_api_keys` health
  - `table_state_audit` coverage
- expands `booking:deploy-check` with release-gating ops checks for:
  - missing active DB-backed staff keys
  - recent table-state audit rows missing actor/context
- adds thresholds under `config/booking.php` for ops hygiene
- adds unit coverage for the new operational health evaluators

## Rollout notes

1. Apply rounds 1 -> 5 first.
2. Apply this round 6 patch.
3. Clear config/cache and run:

```bash
php artisan optimize:clear
php artisan booking:doctor --json --strict
php artisan booking:deploy-check --mode=preflight --json --strict
php artisan booking:ops-snapshot --json
```

## Expected new checks

- `ops.staff_api_keys` should fail if database-backed auth is enabled but no active keys exist.
- `ops.table_state_audit` should warn/fail when recent table transitions miss actor/context coverage.

## No SQL patch required

This round does **not** add a new SQL patch file. It relies on tables introduced in earlier rounds (`staff_api_keys`, `audit_logs`).
