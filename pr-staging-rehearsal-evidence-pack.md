## Summary

Adds a staging rehearsal and operator evidence pack workflow for RestaurantPOS release readiness.

This PR standardizes how scheduler, DR restore, notification delivery, alerting, payment callback, load test, and operator approval evidence should be collected, redacted, validated, and supplied to launch-readiness gates.

This PR does not deploy production and does not claim production readiness.

### Completed

- Added staging evidence pack runbook.
- Added operator rehearsal checklist.
- Added evidence JSON templates.
- Added or documented evidence pack builder scripts.
- Added or documented staging rehearsal runner scripts.
- Added redaction rules for secrets and PII.
- Preserved SQL-first release conventions.
- Preserved local/test safety.
- Kept operator approval manual and explicit.

### Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:deploy-check --mode=preflight --strict --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:launch-readiness --target=staging --json`
- `php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/manual_evidence.json --json`
- `bash -n scripts/ops/build-staging-evidence-pack.sh`
- `bash -n scripts/ops/run-staging-rehearsal.sh`
- Relevant evidence/launch-readiness tests if code changed
- Frontend builds if smoke flow touched

### Remaining Risks

- Real staging credentials must be supplied by operators.
- Real S3 restore evidence must be collected against staging infrastructure.
- Real notification delivery evidence must be collected with safe recipients.
- Real payment callback evidence must be collected from provider sandbox.
- Real alerting evidence must be collected from Sentry/Slack/Telegram channels.
- Operator approval remains manual and required.
- This PR does not perform production deployment.

## Recommendation

READY TO MERGE after CI passes and evidence workflow checks are green.

NOT READY FOR ACTUAL PRODUCTION CUTOVER.
