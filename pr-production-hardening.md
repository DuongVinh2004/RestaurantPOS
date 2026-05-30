## Summary

Adds production hardening foundations for RestaurantPOS across infrastructure, CI/CD readiness gates, SQL-first DB patch dry-run, observability, backup/restore, security, production smoke testing, and staging rehearsal documentation.

This PR does not deploy production and does not claim production readiness.

### Completed

- Added production-oriented Docker Compose and Nginx hardening templates.
- Added infrastructure provisioning runbook.
- Added secret scanning to production readiness workflow.
- Added SQL-first DB patch dry-run script.
- Reviewed generic HMAC webhook signature handling and documented remaining provider-specific blockers.
- Added optional observability/outbox health scripts.
- Added backup/restore scripts with safety guards.
- Added K6 baseline load test.
- Hardened CORS/session configuration in an environment-aware way.
- Added production smoke test configuration.
- Added staging cutover rehearsal runbook.

### Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:deploy-check --mode=preflight --strict --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:launch-readiness --target=staging --json`
- Backend targeted tests for Reservation/Preorder/Waitlist/QrBill/Voucher/Loyalty/Benefits/Checkout/Privacy/CustomerDataExport/Anonymization where available
- `cd staff-web && npx tsc --noEmit && npm run build`
- `cd customer-web && npx tsc --noEmit && npm run build`
- `bash -n` for production ops scripts
- Optional: shellcheck/k6 inspect if available

### Important Notes

- Actual production cutover is still blocked until real infrastructure, production secrets, domain/TLS, production DB/Redis, backup target, payment credentials, provider webhook verification, monitoring/alerting, staging dry-run evidence, and operator approval are available.
- Local `booking:deploy-check` may report expected environment-specific failures such as scheduler heartbeat or missing production API keys. These remain mandatory blockers in staging/production.
- Real payment provider callbacks remain out of scope until provider credentials and provider-specific signature verification are configured.
- UAT/demo seeds must not be run in production.

### Remaining Risks

- Provider-specific payment webhook verification still requires real sandbox/production credentials.
- Backup/restore must be rehearsed against staging infrastructure before production use.
- Monitoring/alerting requires real destination configuration.
- Production smoke tests require real deployment target and operator approval.

## Recommendation

READY TO OPEN PR FOR PRODUCTION HARDENING REVIEW.

NOT READY FOR ACTUAL PRODUCTION CUTOVER.
