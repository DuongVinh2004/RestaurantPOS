# Round 9 — Database contract inspector hotfix

This hotfix restores the missing `DatabaseContractInspector::hasStaffApiKeyHashUniqueIndex()` helper.

Without this method, `booking:ops-snapshot` reports a degraded `database_contract` section even when the live schema is otherwise healthy.

## Important rollout note
If `database_contract` still shows the following checks as false after this hotfix:
- `menu_price_overlap_trigger_insert`
- `menu_price_overlap_trigger_update`
- `reservation_active_voucher_column`
- `reservation_active_voucher_unique`
- `payment_provider_transaction_unique`

then the live database likely has **not** had the round 2 / round 3 / round 4 SQL artifacts applied yet.

Apply these SQL files in order:
- `database/patches/2026_03_15_000023_menu_price_and_active_voucher_integrity.sql`
- `database/patches/2026_03_15_000024_payment_reconciliation_and_table_audit_round.sql`
- `database/patches/2026_03_15_000025_staff_api_key_lifecycle_round.sql`
