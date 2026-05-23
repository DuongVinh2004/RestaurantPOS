# Runtime Resilience Checks

| Scenario | Expected Envelope | Actual Result | FE Behavior | Status |
|---|---|---|---|---|
| Redis unavailable or Redis gate fail | 503 Service Unavailable / Preflight deployment blocked | Deploy check fails with ops.redis_runtime. Backend APIs return fetch failed/ECONNREFUSED or 500/503 depending on boot configuration. | Frontend shows backend unavailable error boundary | PASS (Caught by Preflight correctly) |
| Backend HTTP unavailable | Network error / ECONNREFUSED | fetch failed: connect ECONNREFUSED | Frontend catches network error and displays offline banner | PASS (Expected failure when process dies) |
| Missing/invalid staff API key | 401 Unauthorized | Deferred (Backend down) | Redirects to login / unauthenticated view | ENV_BLOCKED |
| Expired/invalid customer token/session | 401 Unauthorized | Deferred (Backend down) | Redirects to login | ENV_BLOCKED |
| Idempotency replay/conflict | 409 Conflict / 422 Unprocessable Entity | Deferred (Backend down) | Shows toast with error message | ENV_BLOCKED |
