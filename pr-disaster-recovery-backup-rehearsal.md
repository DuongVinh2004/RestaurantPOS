## Summary

Hardens the Disaster Recovery and backup/restore workflow by adding safe, rehearsable backup and restore procedures, S3-ready guardrails, checksum validation, staging restore evidence structure, and operator runbook documentation.

This PR does not restore production data, does not include real credentials, and does not claim production readiness.

### Completed

- Audited existing backup/restore/DR scripts and launch-readiness evidence expectations.
- Hardened backup script behavior with fail-fast checks, non-zero dump validation, timestamped artifacts, checksum generation, and S3-ready configuration.
- Hardened restore script behavior with explicit confirmation, checksum verification, staging/sandbox default targets, and production restore guardrails.
- Fixed relative path manifest matching and Windows absolute path regex issues in `tools/mysql/backup_release.php`.
- Eliminated Windows "Array to string conversion" warnings in `tools/mysql/restore_release.php`.
- Fixed schema columns mismatch bug in `config/booking_disaster_recovery.php` for `restaurant_tables` and `waiting_list` sample table probes.
- Added or updated staging DR rehearsal runner `scripts/ops/run-dr-rehearsal.sh`.
- Added evidence JSON structure for DR restore drills at `storage/app/booking_release/launch_readiness/manual_evidence.json`.
- Added Disaster Recovery / Backup Rehearsal runbook at `docs/runbooks/disaster-recovery-backup-rehearsal.md`.
- Preserved SQL-first deployment conventions.
- Avoided committing secrets, backup dumps, or runtime artifacts.

### Verification

- `php vendor/laravel/pint/builds/pint --test -v` (PASSED)
- `php artisan booking:doctor --json` (PASSED)
- `php artisan notifications:outbox-health --json` (PASSED)
- `php artisan booking:deploy-check --mode=preflight --strict --json` (PASSED)
- `php artisan booking:release-manifest --json` (PASSED)
- `php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/launch_readiness/manual_evidence.json --json` (PASSED, 0 failures, status: PASS)
- `bash -n scripts/ops/backup-to-s3.sh` (PASSED)
- `bash -n scripts/ops/restore-from-s3.sh` (PASSED)
- `bash -n scripts/ops/db-patch-dry-run.sh` (PASSED)
- `bash -n scripts/ops/run-dr-rehearsal.sh` (PASSED)
- Relevant DR/backup/restore/release tests where available (16 tests, 126 assertions PASSED)

### Remaining Risks

- Real AWS/S3 credentials and isolated staging bucket are still required for live DR rehearsal.
- Actual restore drill evidence must be captured against a staging sandbox database.
- Payment provider credentials and live callback evidence remain out of scope.
- Observability/alerting and operator approval remain out of scope.
- This PR does not perform production deployment.

## Recommendation

READY TO MERGE after CI passes and script safety checks are green.

NOT READY FOR ACTUAL PRODUCTION CUTOVER.
