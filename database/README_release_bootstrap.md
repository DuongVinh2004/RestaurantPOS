# Booking release bootstrap

This repo is SQL-first for bootstrap. Do not treat `php artisan migrate` as the primary provisioning path.

Canonical bootstrap artifacts:

1. `database/schema/mysql-schema.sql`
2. `database/patches/*.sql` in lexical order
3. `tools/mysql/verify_release_contract.sql`

## Preferred commands

Quick bootstrap after `.env` is configured:

```bash
composer bootstrap:booking
```

CI/staging jobs where the target database is already created by the platform can keep the canonical wrapper and skip only database creation:

```bash
composer bootstrap:booking -- --skip-create-db
```

Database-only bootstrap:

```bash
composer bootstrap:release-db
```

Manual equivalent:

1. Import the canonical schema + SQL patches + built-in contract verification:
   - `php tools/mysql/bootstrap_release.php --env-file=.env`
   - If you bypass the wrapper, run `tools/mysql/verify_release_contract.sql` after the schema dump and every patch.
2. Seed canonical reference roles:
   - `php artisan db:seed --class=ReferenceDataSeeder --force`
3. Clear stale Laravel caches:
   - `php artisan config:clear`
   - `php artisan cache:clear`
   - `php artisan route:clear`
   - `php artisan view:clear`
4. Bootstrap the first operational site:
   - `php artisan booking:bootstrap-site --json`
   - This is the release path that may create or mark the default branch. Runtime read paths must not auto-create branch rows.
5. Rebuild reporting snapshots:
   - `php artisan booking:reporting-snapshots:rebuild --days=7 --json`
6. Normalize and inspect release artifacts:
   - `php artisan booking:artifacts-normalize --json`
   - `php artisan booking:release-manifest --json`
7. Run runtime verification:
   - `php artisan booking:deploy-check --mode=preflight`
   - `php artisan booking:doctor --json`
   - `php artisan test`

## Required SQL patch inventory

At minimum, the release must include and apply the patch set listed in `config/booking_release.php` under `required_sql_patches`.

The current SQL-first release contract also assumes the April 5 foundations are present in both schema dump and applied patches:

- notification platform v2 (`notification_outbox.recipient_user_id`, `notification_outbox.dedupe_key`, `notification_delivery_attempts`, `notification_preferences`)
- unified audit trail (`audit_logs.actor_type`, `audit_logs.summary_json`, `audit_logs.request_id`, `audit_log_subjects`)
- branch scheduling policy (`branches.business_hours`, `branches.closure_windows`, `branches.booking_policy`)
- branch ownership foreign keys for core runtime tables (`reservations.branch_id`, `table_holds.branch_id`, `cashier_shifts.branch_id`)
- cashier shift finance user foreign keys (`cashier_shifts.cashier_user_id`, `cashier_shifts.opened_by`, `cashier_shifts.closed_by`)
- cashier shift optimistic-write triggers (`trg_cashier_shifts__bi_row_version`, `trg_cashier_shifts__bu_row_version`) from `database/patches/2026_04_27_000059_cashier_shift_row_version.sql`
- per-staff branch assignment foundation (`staff_branch_assignments`)
- data lifecycle / privacy (`customer_privacy_requests`, `users.privacy_anonymized_at`)
- feature flags (`feature_flags`)

## Portability rules

- Stored dump artifacts must not contain environment-specific `DEFINER=` clauses.
- Stored dump artifacts should expose the final guard columns directly in the table definitions for:
  - `agent_assignments.active_conversation_id`
  - `bank_accounts.default_user_id`
  - `reservations.active_applied_user_voucher_id`
- The full dump is optional for bootstrap, but when present it should match the same release contract as the schema dump.
- Canonical release artifacts must include the database-backed `staff_api_keys` contract when staff auth is configured to prefer DB-backed hashed keys.
- When an existing installation uses non-canonical role identifiers, override `STAFF_ALLOWED_ROLE_IDS` / `CUSTOMER_AUTH_ALLOWED_ROLE_IDS` explicitly instead of relying on defaults.
- Customer self-pay is day-1 disabled when `PAYMENT_CUSTOMER_SELF_PAY_ENABLED=false`; in that mode reservation deposit/bill settlement stays on the staff-settlement path until a live provider is configured.

## Verification outcome

A release is considered ready only when all of the following are true:

- `php tools/mysql/bootstrap_release.php --env-file=.env` completes with contract verification
- `booking:release-manifest --json` returns `ok=true`
- `booking:deploy-check --mode=preflight` returns `ok=true`
- `php artisan test` passes
- the full-system package lane includes both `staff-web/` and `customer-web/` as required roots
- customer-web contract, lint, typecheck, Vitest, production build, and Playwright smoke gates pass after browser dependencies are installed
