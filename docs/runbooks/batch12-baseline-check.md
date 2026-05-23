# Batch 12 — Baseline Verification Check

This document tracks the pre-operational baseline check for Batch 12 to ensure the local runtime and static contract environments are fully functional before beginning core staff operations work.

## Checklist

| Command | Exit Code | Result | Evidence File |
|---|---|---|---|
| `git status` | 0 | Clean working directory (1 commit ahead) | Console output |
| `npm run runtime:preflight` | 0 | `decision=pass` (MySQL, Redis, Outbox and Doctor are all green) | Console output |
| `php artisan booking:doctor --json` | 0 | Probes and database structure check passed | `storage/app/booking_release/doctor/reports/` |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | 0 | Strict deploy checking passed cleanly | `storage/app/booking_release/deploy_checks/` |
| `node scripts/e2e/runtime-e2e-smoke.mjs` | 0 | Healthy E2E smoke check | `storage/app/booking_release/runtime_e2e_smoke_result.json` |
| `npm run contract:frontend-parity` | 0 | Gate passed, parity complete | `storage/app/booking_release/frontend_contract_parity.json` |
| `npm run contract:frontend-parity:test` | 0 | Validator unit tests pass | Console output |
| `npm run verify:package` | 0 | Manifest integrity and file freshness checks passed | `storage/app/booking_release/release_manifest_snapshot.json` |
| `npm --prefix staff-web run build` | 0 | Transforming and bundling succeeds | Vite build output |
| `npm --prefix customer-web run build` | 0 | Compilation succeeds with Turbopack | Next.js build output |

## Status
✅ **PASS**
The local dev runtime matches all verification requirements. The operational baseline is active, robust, and clean. We are ready to begin the Batch 12 staff operations runtime verification work!
