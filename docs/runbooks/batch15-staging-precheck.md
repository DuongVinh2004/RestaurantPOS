# Staging Precheck Diagnostics — Batch 15

- **Git Commit Hash**: `0db900fa8429cbd668704f83f02a431570d7e632`
- **Branch**: `harden-frontend-contract-parity`
- **PHP Version**: `8.4.0`
- **Loaded Modules**: `bcmath`, `pdo_mysql`, `pdo_sqlite`, `redis`

## Staging Target Diagnostic Outcomes

| Check Command | Exit Code | Result | Finding / Diagnosis |
|---|---|---|---|
| `php artisan booking:doctor --json` | 1 | EXPECTED WARNING | Completed with minor pending outbox messages (`pending=7`). |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | 1 | ENVIRONMENT-BLOCKED | Reporting analytics drift and stuck KDS backlog detected. This is a documented sandbox environment limitation. Staging must run active scheduler/cron to pass deploy-check without manual bypass. |
| `php artisan booking:release-manifest --json` | 0 | PASS | All SQL-first database schema and patch mappings match configurations exactly. |
| `npm run verify:package` | 0 | PASS | Standard circular dependency audits, manifests, and enums verify cleanly. |

## Documentation of Sandbox Limitations
- **Cron Ticker Dependency**: Reporting analytics drift and KDS queue backlog warnings arise because the background task scheduler is not running continuously on a local sandbox.
- **Go-Live Policy Guidance**: This precheck is not a production go-live approval. Staging deploy-check must pass with an active scheduler/cron and real target services before live deployment is approved.
