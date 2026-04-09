# Booking backup runbook

## Goal

Create a repeatable MySQL logical backup before risky changes, release cutovers, or incident response.

## When to run

Run a backup at least for:

- production releases before schema changes
- high-risk hotfixes touching payments / reservations / staff auth
- incident response before manual data repair
- rehearsal drills for recovery readiness

## Command

Linux/macOS:

```bash
./tools/mysql/backup_release.sh --json
```

Windows:

```bat
tools\mysql\backup_release.cmd --json
```

## Required environment

- `DB_HOST`
- `DB_PORT`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`

Optional:

- `BOOKING_BACKUP_ROOT`
- `BOOKING_BACKUP_RETENTION_DAYS`
- `BOOKING_BACKUP_COMPRESS`

## Expected output

Each backup run produces a dedicated directory under `storage/app/booking_backups/` with:

- sanitized schema dump
- full logical dump
- `manifest.json`
- `checksums.sha256`

The backup root also gets `latest-manifest.json` for quick inspection.

## Verification checklist

1. Confirm the command exits with `ok=true` when using `--json`.
2. Confirm `manifest.json` exists in the created backup directory.
3. Confirm `checksums.sha256` exists and includes every backup artifact.
4. Confirm the manifest points to the intended database name.
5. Confirm the schema artifact is marked `portable=true`.
6. Attach the manifest JSON to the release / incident evidence bundle.

## Retention guidance

- Default retention is `14` days.
- Increase retention for production to match policy.
- Do not disable pruning unless another lifecycle policy is already in place.

## Example

```bash
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_USERNAME=root \
DB_PASSWORD=secret \
DB_DATABASE=restaurant_pos \
BOOKING_BACKUP_RETENTION_DAYS=30 \
./tools/mysql/backup_release.sh --json
```

## Cautions

- Do not treat `database/schema/mysql-schema.sql` or `db_all.sql` in source control as live-environment backups.
- Keep backup evidence with the release sign-off package.
- Backup creation does not replace restore rehearsal. Restore verification should be executed separately.
- For canonical DR evidence, follow `docs/runbooks/booking-disaster-recovery-drill.md`.
