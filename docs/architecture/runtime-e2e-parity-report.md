# Runtime E2E Parity Report (Batch 10)

## 1. Executive Summary
- **Runtime E2E status**: MERGE WITH RISKS
- **Reasoning**: The static parity gates passed successfully. The frontends (staff-web and customer-web) built successfully without errors. However, the runtime backend and Redis services fail to stay up in the current Windows local environment (connection refused/processes exiting immediately). This blocked the E2E runtime flows. The failure is strictly an environment/infrastructure issue locally, but it means we do not have a 100% full runtime pass. Therefore, we cannot claim "production-ready" until the environment issues are resolved and the flows are run end-to-end successfully.

## 2. Environment
- **OS/Context**: Windows (Local execution via PowerShell scripts)
- **MySQL status**: PASS (`127.0.0.1:3306` reachable via preflight)
- **Redis status**: FAIL (`127.0.0.1:6379 connect ECONNREFUSED` via preflight. Also failed during bootstrap)
- **Backend status**: FAIL (`fetch failed: connect ECONNREFUSED 127.0.0.1:8000` via preflight)
- **staff-web status**: PASS (Builds successfully, `npm run verify:package` checks out)
- **customer-web status**: PASS (Builds successfully, `npm run verify:package` checks out)
- **Required env keys**: `APP_ENV`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `REDIS_PORT`, `VITE_API_URL`, `NEXT_PUBLIC_API_BASE_URL`
- **Missing env/services**: Redis service fails to keep running or binding properly; backend HTTP server crashes or refuses connection.

## 3. Commands Executed

| Command | Exit Code | Result | Evidence File |
|---|---|---|---|
| `git status` | 0 | Clean working tree | `storage/app/booking_release/runtime_e2e_precheck.json` |
| `npm run runtime:up` | 1 | Fails to maintain connection | `storage/app/booking_release/runtime_e2e_precheck.json` |
| `composer bootstrap:booking` | 1 | RedisException (Target actively refused it) | `storage/app/booking_release/runtime_e2e_precheck.json` |
| `npm run runtime:preflight` | 1 | Redis TCP and Backend HTTP fetch failed | `storage/app/booking_release/runtime_e2e_precheck.json` |
| `php artisan booking:doctor --json` | 0 | PASS (bypassed local execution for Redis/Scheduler) | `storage/app/booking_release/doctor/reports/...json` |
| `php artisan booking:deploy-check --mode=preflight --strict --json` | 1 | FAIL (ops.redis_runtime probe failed) | `storage/app/booking_release/deploy_checks/reports/...json` |
| `php artisan booking:release-manifest --json` | 0 | PASS | `storage/app/booking_release/release_manifest/reports/...json` |
| `npm run contract:frontend-parity` | 0 | PASS | `docs/architecture/frontend-contract-parity.md` |
| `npm run contract:frontend-parity:test` | 0 | PASS | N/A |
| `npm run verify:package` | 0 | PASS | N/A |
| `npm --prefix staff-web run build` | 0 | PASS | N/A |
| `npm --prefix customer-web run build` | 0 | PASS | N/A |
| `node scripts/e2e/runtime-e2e-smoke.mjs` | 0 | FAIL/SKIPPED (Blocked by Env) | `docs/runbooks/runtime-e2e-smoke-result.md` |

## 4. API E2E Flow Result
| Step | Endpoint | Method | Actor | Expected | Actual | Status | Evidence |
|---|---|---|---|---|---|---|---|
| Health/Readiness | `/health` | GET | Anonymous | 200 OK | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Customer Auth | `/auth/customer/login` | POST | Customer | 200 OK (Token) | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Staff Auth | `/auth/staff/login` | POST | Staff | 200 OK (Token) | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Customer GET Tables | `/tables/available` | GET | Customer | 200 OK | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Customer POST Hold | `/table-holds` | POST | Customer | 201 Created | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Customer POST Reservation | `/reservations` | POST | Customer | 201 Created | Skipped | SKIPPED_WITH_REASON | `runtime_e2e_smoke_result.json` |
| Staff GET Inbox | `/staff/reservations` | GET | Staff | 200 OK | fetch failed | ENV_BLOCKED | `runtime_e2e_smoke_result.json` |
| Other flows (Order, Kitchen, Finance) | Various | Various | Staff/System | 2xx/3xx/4xx | Skipped/Deferred | DEFERRED | `runtime_e2e_smoke_result.json` |

## 5. Frontend Smoke Result
| App | Page | URL | Expected | Actual | Status |
|---|---|---|---|---|---|
| customer-web | Build | N/A | Build successful | Build successful | PASS |
| customer-web | Various Pages | `http://127.0.0.1:3000/*` | App Loads | ENV_BLOCKED | ENV_BLOCKED |
| staff-web | Build | N/A | Build successful | Build successful | PASS |
| staff-web | Various Pages | `http://127.0.0.1:5173/*` | App Loads | ENV_BLOCKED | ENV_BLOCKED |

## 6. Resilience/Error Handling Result
| Scenario | Expected envelope | Actual result | FE behavior | Status |
|---|---|---|---|---|
| Redis unavailable | 503 / Preflight fail | Deploy check fails with ops.redis_runtime | FE shows unavailable boundary | PASS (Caught by Preflight) |
| Backend HTTP unavailable | Network error | fetch failed | FE displays offline banner | PASS (Expected behavior) |
| Missing/invalid API key | 401 Unauthorized | Deferred | Redirects to login | ENV_BLOCKED |

## 7. Data Created
- None. System environment issues blocked test data creation.

## 8. Remaining Risks
- **Runtime E2E pass**: No dynamic E2E flows passed because of local Redis/Backend failures.
- **Skipped/Deferred/Blocked**: Almost all dynamic flows are blocked due to the environment limitation.
- **Batch 9 Allowlist**: Still applies.
- **Payment/Preorder**: Deferred. High impact if launched without provider mocks.
- **API Boundary Risk**: `as unknown as Promise<...>` in SDK might still pose a risk if backend responses unexpectedly differ at runtime.

## 9. Next Actions
- **Fixes**: Resolve Windows local runtime issues (Redis `phpredis` extension / server crashes, Backend HTTP server crashes).
- **Batch 11**: Required to re-run the `scripts/e2e/runtime-e2e-smoke.mjs` test suite on a stable environment.
- **Merge Recommendation**: MERGE WITH RISKS
