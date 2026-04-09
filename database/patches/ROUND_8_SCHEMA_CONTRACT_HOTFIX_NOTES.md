# Round 8 hotfix — schema artifact + contract inspection portability

This hotfix fixes two concrete issues observed during `booking:deploy-check` / `booking:ops-snapshot`:

1. `database/schema/mysql-schema.sql` was missing these required fragments even though `db_all.sql` already had them:
   - `chk_reservations__money_nonneg`
   - `chk_reservations__reserved_requires_checked_in_at`
2. `DatabaseContractInspector` could fail on some MySQL / driver combinations while reading `information_schema`, producing errors such as:
   - `Undefined property: stdClass::$trigger_name`

## Files changed
- `app/Services/DatabaseContractInspector.php`
- `database/schema/mysql-schema.sql`
- `tests/Unit/Services/ReleaseArtifactContractTest.php`

## No SQL migration is required
This round only fixes:
- release artifact contract text in the schema dump
- runtime metadata inspection portability
- regression coverage for required schema fragments

## Why `ops.staff_api_keys` can still fail after this hotfix
That check is **not** caused by the schema/inspector bug. It fails when:
- `STAFF_AUTH_DATABASE_STORE_ENABLED=true`
- `staff_api_keys` has no active rows
- env fallback is not enabled for the current runtime contract

If you want release checks to pass with the DB-backed staff auth contract, create at least one active key.

Example SQL (replace values before running):

```sql
INSERT INTO staff_api_keys (`user_id`, `label`, `key_hash`, `created_at`, `updated_at`)
VALUES (2, 'primary-staff-key', SHA2('replace-with-raw-secret', 256), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));
```

Then verify:

```bash
php artisan booking:ops-snapshot --json
php artisan booking:deploy-check --mode=preflight --json --strict
```

## Redis runtime failure
The `booking:doctor` Redis failure shown during the report is environmental:
- Redis is not listening on `127.0.0.1:6379`
- or the configured Redis host/port is wrong

That part is not fixed by this hotfix because the application is correctly reporting an unavailable Redis runtime dependency.
