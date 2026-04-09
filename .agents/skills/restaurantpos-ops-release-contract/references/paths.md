# Paths

## Read first

- `README.md`
- `AGENTS.md`
- `.codex/AGENTS.md`
- `database/README_release_bootstrap.md`
- `tools/mysql/README_bootstrap_release.md`
- `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`
- `docs/runbooks/booking-launch-readiness.md`

## Code hotspots

- `database/schema/mysql-schema.sql`
- `database/patches/*`
- `db_all.sql`
- `tools/mysql/bootstrap_release.*`
- `tools/mysql/restore_release.*`
- `app/Services/DatabaseContractInspector.php`
- `app/Services/BookingDoctorService.php`
- `app/Services/BookingDeploySafetyService.php`
- `app/Services/NotificationOutboxService.php`
- `app/Services/NotificationOutboxHealthService.php`
- `app/Services/OperationalInsightsService.php`
- `app/Services/OpsHeartbeatService.php`
- `app/Services/Reporting/ReportingSnapshotService.php`
- `app/Services/DisasterRecovery/*`
- `scripts/ci/*`

## Test surface

- `tests/Feature/Console/*`
- `tests/Feature/Infrastructure/*`
- `tests/Feature/Notifications/*`
- `tests/Feature/DataLifecycle/*`
- `tests/Unit/Infrastructure/*`
- `tests/Unit/Services/BookingDeploySafetyServiceTest.php`
- `tests/Unit/Services/BookingEnvironmentValidatorTest.php`
- `tests/Unit/Services/DatabaseContractInspectorTest.php`
- `tests/Unit/Services/ReleaseArtifact*`

## Questions to answer before patching

- Which SQL-first artifact is the source of truth for this behavior?
- Does the change alter bootstrap, runtime health, or release packaging expectations?
- Which operational command or gate should fail if this logic breaks?
- Do docs or runbooks need to move with the code change?
