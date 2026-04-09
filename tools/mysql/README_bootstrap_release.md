# Bootstrap release database

Canonical release contract:

1. `database/schema/mysql-schema.sql`
2. every `database/patches/*.sql` in lexical order
3. `tools/mysql/verify_release_contract.sql`

Preferred cross-platform wrapper:

```bash
php tools/mysql/bootstrap_release.php --env-file=.env
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
Bootstrap should be treated as failed if the verification script reports any missing table or column from the SQL-first release contract, including the April 5 notification, audit, branch policy, privacy, and feature-flag foundations.

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
