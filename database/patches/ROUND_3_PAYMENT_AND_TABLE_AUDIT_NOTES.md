# Round 3 payment reconciliation + table audit patch

This patch builds on round 2 and hardens two production-facing contracts:

1. Enforces unique `transaction_code` per `payment_provider` in `payments`.
2. Records structured `audit_logs` rows for restaurant table state transitions triggered by staff/service flows.

## Included in this round

- updated `database/schema/mysql-schema.sql`
- updated `db_all.sql`
- updated release-manifest contract fragments
- updated database contract inspector checks
- friendlier validation mapping for duplicate payment references
- structured table state audit helper + targeted service wiring

## Apply order

1. Apply round 1, then round 2.
2. Replace files with this round 3 patch.
3. Apply `database/patches/2026_03_15_000024_payment_reconciliation_and_table_audit_round.sql` to MySQL.
4. Re-export `db_all.sql` after the database is in the desired release state.

## Important preflight behavior

The SQL patch intentionally fails fast if existing rows already contain duplicate `(payment_provider, transaction_code)` pairs for non-empty transaction codes.
