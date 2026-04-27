# Go-Live Candidate Gate

- Decision: `no_go`
- Target: `staging`
- Checked at UTC: `2026-04-27T01:31:12.509Z`
- Artifact root: `C:\Users\Duong Vinh\RestaurantPOS-Laravel\build\booking-go-live`

## Summary

- Checks: `21`
- Pass: `15`
- Warnings: `1`
- Fail: `5`
- No-go: `5`

## Results

| Group | Check | Status | Classification | No-go |
| --- | --- | --- | --- | --- |
| repository | Git status clean or documented dirty artifacts | `pass_with_warning` | `documented_dirty_artifacts` |  |
| environment | APP_ENV/APP_DEBUG/auth environment checks | `fail` | `unsafe_environment_config` | go-live environment config unsafe |
| manual_evidence | P0/P1 fixed or mitigated evidence | `pass` | `passed` |  |
| sql_first | SQL-first bootstrap and verifier | `pass` | `passed` |  |
| runbooks | Backup/restore/rollback runbooks exist | `pass` | `passed` |  |
| runtime | MySQL/Redis/scheduler/outbox runtime gate | `fail` | `runtime.redis` | booking:doctor fail |
| runtime | Notification outbox health | `fail` | `runtime.db` | outbox health fail |
| release_contract | Locked route inventory gate | `pass` | `passed` |  |
| release_contract | Release manifest | `pass` | `passed` |  |
| runtime | Deploy preflight guardrail | `fail` | `runtime.db` | deploy-check fail |
| release_contract | Package integrity verifier | `pass` | `passed` |  |
| targeted_ladders | Security/RBAC/branch isolation ladder | `pass` | `passed` |  |
| targeted_ladders | Order lifecycle ladder | `pass` | `passed` |  |
| targeted_ladders | Kitchen/KDS ladder | `pass` | `passed` |  |
| targeted_ladders | Money/cashier/refund ladder | `pass` | `passed` |  |
| targeted_ladders | Inventory/purchasing ladder | `pass` | `passed` |  |
| release_contract | Release contract ladder | `pass` | `passed` |  |
| staff_web | Staff-web production build | `pass` | `passed` |  |
| staff_web | Staff-web day-1 live smoke | `fail` | `runtime.failure` | staff-web day-1 smoke not run |
| manual_evidence | Backup/restore drill evidence | `pass` | `passed` |  |
| manual_evidence | Rollback plan evidence | `pass` | `passed` |  |

## No-Go Conditions

- `environment_config`: go-live environment config unsafe - APP_ENV must be production-like for go-live evidence; got local. APP_DEBUG must be false for go-live evidence. CUSTOMER_AUTH_JWT_SECRET must be a non-placeholder secret of at least 32 characters.
- `booking_doctor`: booking:doctor fail - MySQL/Redis/scheduler/outbox runtime gate failed.
- `outbox_health`: outbox health fail - Notification outbox health failed.
- `deploy_check_preflight`: deploy-check fail - Deploy preflight guardrail failed.
- `staff_web_smoke`: staff-web day-1 smoke not run - Staff-web day-1 live smoke failed.
