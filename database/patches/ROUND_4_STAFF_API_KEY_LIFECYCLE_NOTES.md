# Round 4 — staff API key lifecycle hardening

Apply this patch after round 3.

## What this round adds

- `staff_api_keys` table for database-backed hashed staff API credentials
- SHA-256 lookup path in `StaffActorResolver`
- revoke / expiry / last-used support
- release-manifest / database-contract awareness for the new table and unique hash index
- transitional env fallback controls in `config/staff_auth.php`

## Recommended rollout

1. Apply the SQL patch:

```sql
SOURCE database/patches/2026_03_15_000025_staff_api_key_lifecycle_round.sql;
```

2. Insert hashed keys for real staff users before disabling env fallback completely.

Example:

```sql
INSERT INTO staff_api_keys (`user_id`, `label`, `key_hash`)
VALUES (2, 'primary-staff-key', SHA2('replace-with-raw-secret', 256));
```

3. After verifying DB-backed auth works, disable env fallback in production:

- `STAFF_AUTH_ALLOW_ENV_FALLBACK=false`
- clear any legacy `STAFF_API_KEYS_JSON` / `STAFF_API_KEY` secrets from production envs

## Transitional behavior

The resolver now prefers the database store. Env fallback can still be used when:

- explicitly enabled by config, or
- the app environment is in `STAFF_AUTH_ENV_FALLBACK_ALLOWED_ENVIRONMENTS`, or
- the database key store is unavailable/empty and `STAFF_AUTH_ALLOW_ENV_FALLBACK_WHEN_DATABASE_STORE_UNAVAILABLE=true`

That last switch is intentionally transitional to avoid hard lockout during rollout.
