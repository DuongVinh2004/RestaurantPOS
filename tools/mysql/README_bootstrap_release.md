# Bootstrap release database

Canonical release contract:

1. `database/schema/mysql-schema.sql`
2. every `database/patches/*.sql` in lexical order
3. `tools/mysql/verify_release_contract.sql`

Preferred cross-platform wrapper:

```bash
php tools/mysql/bootstrap_release.php --env-file=.env
```

The full booking bootstrap should be invoked through Composer:

```bash
composer bootstrap:booking
```

When CI/CD pre-creates the target database for a restricted app user, keep the Composer wrapper and pass `--skip-create-db` through to the database bootstrap:

```bash
composer bootstrap:booking -- --skip-create-db
```

Environment variables accepted by the scripts:

- `DB_HOST` / `MYSQL_HOST`
- `DB_PORT` / `MYSQL_PORT`
- `DB_USERNAME` / `MYSQL_USER`
- `DB_PASSWORD` / `MYSQL_PASSWORD`
- `DB_DATABASE` / `MYSQL_DATABASE`

Use one namespace consistently in CI/CD. Prefer `DB_*`.

Linux/macOS:

```bash
./tools/mysql/bootstrap_release.sh
```

Windows:

```bat
tools\mysql\bootstrap_release.cmd
```

The shell and `.cmd` wrappers consume process environment variables only.
The PHP wrapper also understands `--env-file=.env` so local bootstrap can follow the repo's checked-in environment file without exporting every `DB_*` value first.

## Built-in verification

All bootstrap wrappers now import the canonical schema dump, apply every SQL patch, then run `tools/mysql/verify_release_contract.sql`.
Bootstrap should be treated as failed if the verification script reports any missing table, column, trigger, critical foreign key, or finance data invariant from the SQL-first release contract, including the April 5 notification, audit, branch policy, privacy, feature-flag foundations, branch ownership foreign keys for reservations, table holds, and cashier shifts, cashier shift finance user foreign keys, cashier shift row-version triggers, the staff branch assignment foundation, and completed paid on-spot reservations missing `final_bill_amount`, `bill_currency`, or `billed_at`.

The same verification requires `reservation_order_items.recipe_snapshot` to be a non-null JSON array after patches run. Patch `2026_07_19_000071_order_item_recipe_snapshot.sql` backfills existing rows from the deployment-time recipe, then enforces the immutable snapshot contract used by serve-time consumption and kitchen wastage.

The default branch is provisioned by the release/site bootstrap path. Runtime read paths are expected to surface missing bootstrap state instead of creating branch rows implicitly.

## Backup automation

Create timestamped logical backups with manifest + checksums:

```bash
./tools/mysql/backup_release.sh --json
```

Windows:

```bat
tools\mysql\backup_release.cmd --json
```

See `tools/mysql/README_backup_release.md` for retention, compression, and output details.
