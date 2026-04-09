# Booking MySQL backup automation

Use `tools/mysql/backup_release.php` (or the shell / cmd wrappers) to create timestamped logical backups with:

- schema backup sanitized for portability
- full logical dump
- optional gzip compression
- SHA-256 checksums
- machine-readable `manifest.json`
- retention pruning for old backup directories

## Environment variables

- `DB_HOST` / `MYSQL_HOST`
- `DB_PORT` / `MYSQL_PORT`
- `DB_USERNAME` / `MYSQL_USER`
- `DB_PASSWORD` / `MYSQL_PASSWORD`
- `DB_DATABASE` / `MYSQL_DATABASE`
- `BOOKING_BACKUP_ROOT` (default: `storage/app/booking_backups`)
- `BOOKING_BACKUP_RETENTION_DAYS` (default: `14`)
- `BOOKING_BACKUP_COMPRESS` (`true` / `false`, default: `true`)

## Linux/macOS

```bash
./tools/mysql/backup_release.sh --json
```

## Windows

```bat
tools\mysql\backup_release.cmd --json
```

## Useful flags

- `--json`
- `--retention-days=30`
- `--output-dir=storage/app/booking_backups`
- `--compress=false`
- `--skip-schema`
- `--skip-full`
- `--no-prune`

## Output structure

Each run creates a directory like:

```text
storage/app/booking_backups/20260321T042530Z-restaurantpos/
```

Typical contents:

- `schema.sql.gz`
- `full.sql.gz`
- `manifest.json`
- `checksums.sha256`

The backup root also receives a convenience copy:

- `latest-manifest.json`

## Notes

- The schema backup is passed through the same portability sanitization used for release SQL artifacts.
- The full backup is intentionally preserved as a full logical dump for restore workflows.
- Retention pruning deletes directories older than the configured retention window, excluding the backup produced by the current run.
