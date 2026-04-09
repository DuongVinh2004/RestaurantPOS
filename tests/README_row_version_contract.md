# Row version source-of-truth

Persisted `row_version` behavior for the booking/checkout domain is release-governed by the MySQL schema in `database/schema/mysql-schema.sql`, not by ad-hoc service code.

## Canonical rule

For every table that exposes or relies on `row_version` in request/resource/service contracts, release-grade schema must provide:

- a `row_version` column
- a `BEFORE INSERT` trigger that initializes it to `1`
- a `BEFORE UPDATE` trigger that advances it to `OLD.row_version + 1`

Current inventory locked by test:

- `loyalty_tiers`
- `payments`
- `reservation_order_items`
- `reservation_orders`
- `reservations`
- `restaurant_tables`
- `table_holds`
- `user_points`
- `user_vouchers`
- `users`
- `vouchers`
- `waiting_list`

## Role of `HasRowVersion`

`app/Models/Concerns/HasRowVersion.php` remains in place as an application-side compatibility helper for Eloquent model saves and test harnesses, but it is **not** the release artifact source-of-truth for persisted row-version advancement.

Implications:

- do not add manual `DB::raw('row_version + 1')` increments in services
- when adding a new row-version-backed table, update the schema triggers first
- update the inventory test `tests/Unit/Support/RowVersionSourceOfTruthInventoryTest.php`
- if a mutation path uses bulk SQL updates, persisted row_version safety depends on the schema trigger existing for that table
