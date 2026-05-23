# Batch 14 — Baseline Verification Report

This document records the baseline preflight verification results for **Batch 14** before proceeding with further payment integration and financial flow hardens.

## Execution Details
- **Timestamp**: `2026-05-24T05:58:00Z`
- **Branch**: `harden-frontend-contract-parity`
- **Environment**: Local Sandbox (SQLite + MySQL + Redis)

## Step-by-Step Command Results

| Command | Exit Code | Result | Evidence File / Detail |
|---|---|---|---|
| `git status` | `0` | `PASS` | On branch `harden-frontend-contract-parity`. Clean workspace. |
| `npm run runtime:preflight` | `0` | `PASS` | All TCP, HTTP, doctor, and heartbeats are alive. |
| `npm run e2e:staff-ops-smoke` | `0` | `PASS` | Seeding core reservation → check-in → dine-in POS order → kitchen bump → cash payment succeeded. |
| `npm --prefix staff-web run e2e:smoke` | `0` | `PASS` | Browser UAT Playwright smoke passed (1 passed, 17.4s). |
| `npm run contract:frontend-parity` | `0` | `PASS` | OpenAPI Parity Gate passed. 247 operationIds verified. |
| `npm run contract:frontend-parity:test` | `0` | `PASS` | Parity Scanner negative checks passed. |
| `npm run verify:package` | `0` | `PASS` | Package integrity gate passed (52 verified, 0 missing, 0 stale). |
| `npm --prefix staff-web run build` | `0` | `PASS` | Vite compile complete, built 4692 modules successfully in 10s. |
| `npm --prefix customer-web run build` | `0` | `PASS` | Next.js build complete, TypeScript and pages generated successfully. |

## Baseline Verdict
**PASS** — Staging/local environment is fully operational and healthy. We are ready to begin Phase 1 (Payment Provider Discovery).
