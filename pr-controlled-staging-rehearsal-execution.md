## Summary

Adds a sanitized controlled staging rehearsal report and any necessary rehearsal workflow fixes discovered during staging evidence execution.

This PR does not deploy production and does not claim production readiness.

### Completed

- Executed or attempted controlled staging rehearsal using the evidence pack workflow.
- Collected/redacted evidence for scheduler, DR, notification, alerting, payment callback, load/performance, frontend smoke, and operator approval where available.
- Ran launch-readiness with and without manual evidence.
- Produced a sanitized staging rehearsal report.
- Preserved SQL-first release rules.
- Avoided committing secrets, raw logs, dumps, or runtime artifacts.

### Verification

- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:deploy-check --mode=preflight --strict --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:launch-readiness --target=staging --json`
- `php artisan booking:launch-readiness --target=staging --manual-evidence=<evidence-pack> --json`
- DR/notification/alerting/payment/load/frontend smoke commands as available
- Secret/PII scan on generated evidence
- `php vendor/laravel/pint/builds/pint --test -v` if code changed

### Remaining Risks

- Any missing real staging credential remains a blocker.
- Any missing operator approval remains a blocker.
- This PR does not perform production deployment.
- Production cutover requires separate explicit approval and rollback plan confirmation.

## Recommendation

READY TO MERGE after CI passes if report is sanitized and evidence classification is honest.

PRODUCTION CUTOVER remains BLOCKED unless the report explicitly shows all required staging evidence and operator approvals are complete.
