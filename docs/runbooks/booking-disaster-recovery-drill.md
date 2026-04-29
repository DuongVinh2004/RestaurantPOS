# Booking disaster recovery drill

## Goal

Provide one canonical DR drill command that proves more than "restore script ran":

- backup artifact is usable
- restore completes into an isolated target
- release contract still holds after restore
- critical domain invariants still hold after restore
- operator gets JSON/Markdown evidence plus measured RTO/RPO fields

Canonical command:

```bash
php artisan booking:dr-drill --mode=metadata-verify --json
```

## Modes

### `metadata-verify`

Use for fast artifact hygiene only.

- Fully automated
- CI-safe
- Verifies manifest load, artifact checksum/size integrity, checksum inventory, backup age vs configured RPO
- Does **not** prove recoverability

### `dry-restore`

Use before a heavier rehearsal when you want to validate target scope and import plan.

- Fully automated
- CI-safe only if a scratch MySQL target is available
- Reuses `tools/mysql/restore_release.php --dry-run`
- Verifies isolated target guardrails without importing data
- Does **not** prove recoverability

### `full-isolated-restore`

Use for periodic serious DR evidence on staging/manual drill windows.

- Automated workflow, but staging/manual-only by policy
- Requires an isolated scratch database
- Reuses `tools/mysql/restore_release.php`
- Runs post-restore schema/data probes plus release contract/core ops verification
- This is the only mode that proves end-to-end recoverability for the selected artifact

## Preconditions

- Never point the drill at the live application database.
- Use a scratch target database whose name clearly includes `restore`, `rehearsal`, `scratch`, `drill`, `sandbox`, or `tmp`.
- `mysql` CLI must be available because `tools/mysql/restore_release.php` depends on it.
- `DB_*` should point at the source environment when `--capture-backup` is used.
- `RESTORE_DB_*` or `--target-*` options should point at the isolated target for `dry-restore` and `full-isolated-restore`.

Recommended scratch target example:

```bash
php artisan booking:dr-drill \
  --mode=full-isolated-restore \
  --manifest=storage/app/booking_backups/latest-manifest.json \
  --target-db=restaurant_pos_restore_drill \
  --drop-target-first \
  --json
```

End-to-end fresh-backup drill:

```bash
php artisan booking:dr-drill \
  --mode=full-isolated-restore \
  --capture-backup \
  --target-db=restaurant_pos_restore_drill \
  --drop-target-first \
  --json
```

## What gets verified

Fully automated:

- backup manifest load and artifact hash/size integrity
- `checksums.sha256` completeness and hash match
- backup freshness vs configured RPO target
- isolated target naming/safety boundary
- `tools/mysql/restore_release.php`
- `tools/mysql/verify_release_contract.sql`
- `booking:doctor` against restored target
- `booking:deploy-check --mode=postflight` against restored target
- restored schema inventory probe
- restored critical table sample/count probe
- `booking:release-manifest --verify-frozen`
- `booking:core-ops-gate`

Staging/manual-only:

- full isolated restore scheduling on a scratch MySQL target
- scratch database cleanup after evidence capture

## Data checks after restore

The post-restore probe verifies:

- required tables exist: `users`, `reservations`, `restaurant_tables`, `waiting_list`, `payments`, `notification_outbox`
- schema inventory is queryable: table/view/trigger/procedure/function/event counts
- critical sample tables are countable and readable without query errors
- anchor table emptiness is surfaced as a warning, not hidden

The postflight deploy-check step also contributes domain invariant verification for:

- reservation/payment integrity
- refund lineage
- lifecycle timestamps
- voucher/session linkage
- artifact/runtime guardrails

## Evidence artifacts

Artifacts are written to:

- `storage/app/booking_release/disaster_recovery_drills/reports/*.json`
- `storage/app/booking_release/disaster_recovery_drills/reports/*.md`
- latest pointers per mode under the same directory

The JSON artifact is the machine-readable source of truth.

It includes:

- mode and evidence level
- integrated checks with pass/fail/warn
- blocking failures
- major warnings
- raw source payloads
- measured timings
- RTO/RPO fields
- `launch_evidence` fields that can be copied into `booking:launch-readiness --manual-evidence`

The `launch_evidence` section is intentionally safe to archive with release evidence. It includes the restored dump identifier, restore target, verification command/result, timestamp, optional operator/reviewer markers, and flags indicating that secrets are not included.

Example manual-evidence mapping:

```json
{
  "checks": {
    "disaster_recovery_restore_evidence": {
      "status": "pass",
      "performed_by": "ops.release",
      "performed_at_utc": "2026-04-28T09:00:00Z",
      "restored_dump_identifier": "backup-20260428T083000Z",
      "restore_target": "restaurant_pos_restore_drill",
      "verification_command": "php artisan booking:dr-drill --mode=full-isolated-restore --target-db=restaurant_pos_restore_drill --json",
      "verification_result": "pass",
      "operator": "ops.release",
      "reviewer": "release.lead"
    }
  }
}
```

## Reading the result

`exit_code=0`

- Mode objective passed with no warnings

`exit_code=2`

- No blocking failure, but at least one major warning exists
- Common cases: stale backup age, anchor table unexpectedly empty, RTO slower than target

`exit_code=1`

- Recoverability evidence is not acceptable for the selected mode
- Common cases: manifest hash mismatch, unsafe target DB, restore step failure, release contract failure, restored-target invariant failure

## Failure interpretation

Manifest/checksum failure:

- Artifact set is not trustworthy
- Stop and generate a fresh backup set

Target isolation failure:

- The drill was pointed at a risky target scope
- Stop and choose a clearly isolated scratch database

Restore/import failure:

- Restore path is broken for the selected artifact/target
- Fix import blockers before claiming DR readiness

Release contract or deploy-check failure:

- Restore completed, but restored state is not contract-safe
- Treat as failed recoverability evidence

Core ops gate failure:

- Build-level core flow regression exists even if restore worked
- Do not certify recovered release state

## RTO / RPO

Current defaults are placeholders until real staging drill evidence is collected:

- `BOOKING_DR_RTO_TARGET_MINUTES=60`
- `BOOKING_DR_RPO_TARGET_MINUTES=1440`

Actual measurement behavior:

- RPO uses backup manifest age at drill time
- RTO uses restore duration + post-restore verification duration
- Only `full-isolated-restore` produces actual RTO evidence

If no real staging drill has been run yet, these are target placeholders, not production-proven timings.

## Cleanup

After artifact capture and sign-off, drop or archive the scratch database explicitly.

Example:

```sql
DROP DATABASE restaurant_pos_restore_drill;
```

Do not automate destructive cleanup against computed production-like names.
