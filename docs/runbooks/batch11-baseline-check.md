# Batch 11 Baseline Verification Report

This runbook documents the status of all critical baseline checks before modifications were started for Batch 11.

- **Check Run Date:** 2026-05-23
- **Status:** PASS (All baseline preflight, static, and runtime checks pass cleanly)

## Summary of Checks

| Check Name | Command | Status | Notes |
|---|---|---|---|
| Git Status | `git status` | PASS | Branch `harden-frontend-contract-parity` is up to date |
| Runtime Preflight | `npm run runtime:preflight` | PASS | Redis, MySQL, and Laravel HTTP are responsive. Deploy checks are passing. |
| Booking Doctor | `php artisan booking:doctor --json` | PASS | DB, Redis, scheduler, outbox are healthy. |
| Deploy Check | `php artisan booking:deploy-check --mode=preflight --strict --json` | PASS | Strict deploy checks have zero errors or warnings. |
| E2E Smoke Test | `node scripts/e2e/runtime-e2e-smoke.mjs` | PASS | Smoke script completed with exit code 0. |
| Frontend Parity | `npm run contract:frontend-parity` | PASS | OpenAPI routes align perfectly with frontend operations. |
| Parity Tests | `npm run contract:frontend-parity:test` | PASS | Scanner validation works properly. |
| Package Verification | `npm run verify:package` | PASS | Dependency and release manifests are healthy. |

## Actions Taken
The initial preflight test failed due to a stale scheduler heartbeat. This was fixed by running:
```bash
php artisan booking:ops-heartbeat:touch
```
Subsequent runs of `npm run runtime:preflight` passed cleanly.
