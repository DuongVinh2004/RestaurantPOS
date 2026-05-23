# Runtime E2E Verification

## How to start runtime
1. Start MySQL and Redis services locally.
2. Ensure `.env` is configured properly.
3. Run `npm run runtime:up` to start the backend, scheduler, and verify local connections.
4. Run `composer bootstrap:booking` to verify the release contract and apply SQL structure if needed.
5. Run `npm run dev:all` for the full stack UI lane.

## Required env
- `APP_ENV=local`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT`
- `VITE_API_URL` (Staff-web)
- `NEXT_PUBLIC_API_BASE_URL` (Customer-web)
- `CORS_ALLOWED_ORIGINS`

## Known local prerequisites
- Node 20 LTS
- PHP 8.2
- MySQL 8 compatible
- Redis 7 compatible

## Smoke test strategy
- Backend: Run `scripts/e2e/runtime-e2e-smoke.mjs`
- API Level Tests: Test Customer endpoints (health, hold, reservation, preorder) and Staff endpoints (inbox, check-in, order creation, checkout)
- Frontend: Load web clients, check auth, dashboard, booking pages.

## Manual fallback steps
- If MySQL local helper fails, install MySQL server locally.
- If Redis local helper fails, run Redis locally or via Docker.
- If preflight fails, fix `DB_*` or `REDIS_*` env variables.
