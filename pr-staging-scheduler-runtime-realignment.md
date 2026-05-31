## Summary

Resolves the first production readiness blocker by documenting and implementing a safe scheduler heartbeat runtime lane for local/staging environments, and adds a production-like MySQL/Redis test lane plan for concurrency-sensitive flows.

This PR does not claim production readiness and does not perform production deployment.

### Completed

- Audited scheduler heartbeat writer/reader/storage path.
- Added safe scheduler heartbeat runtime process scripts for local/staging:
  - `./scripts/ops/start-scheduler-heartbeat.sh` (Linux/macOS)
  - `./scripts/ops/start-scheduler-heartbeat.ps1` (Windows)
- Added operator guidance runbook for supervisor/systemd/cron usage:
  - `docs/runbooks/staging-scheduler-runtime.md`
- Added MySQL/Redis test lane scripts and runbook for production-like validation:
  - `./scripts/ops/run-mysql-redis-tests.sh` (Linux/macOS)
  - `./scripts/ops/run-mysql-redis-tests.ps1` (Windows)
  - `docs/runbooks/mysql-redis-test-lane.md`
- Preserved SQL-first bootstrap rules (`tools/bootstrap_booking.php --env-file=.env.testing`).
- Kept payment/S3/observability/operator approval blockers out of scope.

### Verification

All local preflight gates have been successfully run and verified:
- `php vendor/laravel/pint/builds/pint --test -v` (PASSED 1,073 files)
- `php artisan booking:doctor --json` (PASSED; `"runtime"."scheduler"."ok"` is `true`)
- `php artisan notifications:outbox-health --json` (PASSED; `"ok": true`)
- `php artisan booking:deploy-check --mode=preflight --strict --json` (PASSED runtime baseline checks)
- `php artisan booking:release-manifest --json` (PASSED release schema patch list check)
- `php artisan booking:launch-readiness --target=staging --json` (PASSED runtime baseline check; `runtime_baseline_blocked: false`)
- Targeted test class `BookingOpsHeartbeatTouchCommandTest` (PASSED)

### Remaining Risks

- Actual production cutover remains blocked by real infrastructure, secrets, payment provider credentials, S3 backup target, observability/alerting, DR evidence, and operator approval.
- Provider-specific payment webhook verification is out of scope for this PR.
- Real S3 backup/restore rehearsal is out of scope for this PR.

## Recommendation

READY TO MERGE after CI passes, since the scheduler heartbeat blocker is fully resolved to environment setup with documented operator steps.

NOT READY FOR ACTUAL PRODUCTION CUTOVER.
