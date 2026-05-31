## Summary

Adds staging/production-ready notification channel configuration guidance, outbox health alerting safeguards, observability setup documentation, and safe smoke rehearsal paths for RestaurantPOS.

This PR does not include real SMTP/SMS/Zalo/Sentry/Slack/Telegram credentials and does not claim production readiness.

### Completed

- Audited current notification outbox, mailer, and observability setup.
- Added or documented environment-safe notification channel configuration.
- Added or documented staging notification delivery smoke rehearsal flow.
- Added or tightened outbox health alerting behavior.
- Added or documented Sentry/error tracking setup for staging and production.
- Added safe alerting payload policy with secret/PII redaction.
- Added notification and observability runbooks.
- Preserved local/test stub behavior while making staging/production requirements explicit.

### Verification

- `php vendor/laravel/pint/builds/pint --test -v` (Passed)
- `php artisan test --filter=NotificationOpsCommandsTest` (Passed)
- `php artisan test --filter=BookingEnvironmentValidatorTest` (Passed)
- `php artisan test --filter=Doctor` (Passed)
- `php artisan test --filter=DeployCheck` (Passed)
- `php artisan test --filter=LaunchReadiness` (Passed)
- `php artisan booking:doctor --json` (Passed/Skipped expected local gaps)
- `php artisan notifications:outbox-health --json` (Passed)
- `php artisan booking:deploy-check --mode=preflight --strict --json` (Passed)
- `php artisan booking:release-manifest --json` (Passed)
- `php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/staging-20260531.json --json` (Successfully verified: Manual evidence resolved and cleared 'Real notification delivery rehearsal')
- Frontend builds: Not affected (No staff-web or customer-web TypeScript/React changes made).
- Secret scan: Executed `git grep` scanning across all sensitive keys; zero real secrets, tokens, or URL variables were leaked or committed.

### Remaining Risks

- Real SMTP/SMS/Zalo credentials must still be provided by operators.
- Real Sentry DSN and alert webhooks must be configured in staging/production secrets.
- Delivery rehearsal evidence must be captured in staging before production cutover.
- Payment callback evidence, S3/DR evidence, and operator approval remain separate release blockers if not already cleared.
- This PR does not perform production deployment.

## Recommendation

READY TO MERGE after CI passes and notification/observability checks are classified honestly.

NOT READY FOR ACTUAL PRODUCTION CUTOVER.
