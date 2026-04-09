# Round 2 integrity patch

This patch builds on round 1 and adds DB-backed integrity guards for the two highest-risk silent-corruption gaps from the audit:

1. Prevent overlapping `menu_item_prices` windows for the same `item_id` via DB triggers.
2. Prevent the same `user_voucher` from being attached to more than one active reservation at a time via a generated column + unique key on `reservations`.

Also included in this round:

- updated `database/schema/mysql-schema.sql`
- updated `db_all.sql`
- updated release manifest contract fragments
- updated database contract inspector checks
- friendlier validation mapping for the new DB conflict messages

## Apply order

1. Replace files from round 1 with this round 2 patch.
2. Apply `database/patches/2026_03_15_000023_menu_price_and_active_voucher_integrity.sql` to the target MySQL database.
3. Re-export `db_all.sql` from the hardened database when you are ready to freeze a new release artifact.

## Important preflight behavior

The SQL patch intentionally **fails fast** if existing data already violates either invariant:

- overlapping rows already exist in `menu_item_prices`
- the same `applied_user_voucher_id` is already attached to multiple active reservations

That is deliberate, to avoid silently installing guards on top of already-corrupted data.
