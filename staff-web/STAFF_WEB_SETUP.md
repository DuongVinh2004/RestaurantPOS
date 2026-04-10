# Staff Web Setup

## Prerequisites

- Backend Laravel app running and seeded with staff users / staff API keys
- Generated artifacts already up to date:
  - `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
  - `../build/api-consumer/mutation-contracts.md`
- Node.js + npm available

## Environment

Create `.env` from `.env.example`:

```bash
VITE_API_URL=http://localhost:8000/api/v1
```

`VITE_API_URL` should point at the backend API prefix. The client normalizes this to the host root expected by the SDK generator.
The default Vite dev and preview hosts are `localhost` so they line up with the backend default API origin and local CORS contract. If you intentionally open the UI from `http://127.0.0.1:5173`, the backend `CORS_ALLOWED_ORIGINS` must also include that exact origin.

## Install + Run

```bash
npm install
npm run lint
npm run test
npm run build
npm run dev
```

## Login Expectations

- Staff login uses:
  - `identifier`
  - `password`
  - `device_name`
- Backend returns an opaque token and the app stores it locally
- Every authenticated request sends the token through `X-Staff-Key`

## Operational Notes

- `401` clears the current local session and forces relogin
- Non-auth restore/refresh failures should keep the stored session/token and surface a retry notice instead of forcing logout
- `403` means capability or branch scope mismatch; frontend gating is not a substitute for backend auth
  - some payloads also include `required_capability`
- `409` usually means idempotency replay/conflict or another state conflict
- `422` can also mean stale `row_version`
  - inspect `errors.row_version` and `details.errors.row_version`
- `request_id` should be surfaced in operator-facing errors when present
- `known_capabilities` are metadata only; FE gating must use granted `capabilities`
- `GET /api/v1/staff/cashier/shifts/current` may legitimately return `404`

## Verification Checklist

- `npm run lint`
- `npm run test`
- `npm run build`
- Manual smoke against a running backend:
  - login
  - board refresh + waiting notify/seat
  - order create + add items
  - settlement preview + finalize
  - refund preview + refund
  - cashier open/current/show/close
  - conversation take-over + internal note + guarded outbound reply

## Live Backend Smoke Harness

Run the lightweight smoke script when a local or staging backend is up:

```bash
npm run smoke:live
```

Smoke defaults to a read-only pass. It always verifies:

- backend health
- staff login + session refresh
- board load + board changes
- waiting list load + changes
- reservation lookup + reservation-order lookup when data/capability exists
- order load
- settlement preview
- refund preview
- cashier current/show
- conversations list/detail

Write paths stay gated:

- `STAFF_WEB_SMOKE_ALLOW_MUTATIONS=1`
  - enables order create, order add-item, and cashier open
- `STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE=1`
  - enables settlement finalize
- `STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION=1`
  - enables refund execute
- `STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE=1`
  - enables cashier close

### Runtime prerequisites

Before claiming a live smoke result, make sure the backend really satisfies the repo runtime contract:

- backend HTTP server reachable at `http://127.0.0.1:8000` or your staging base URL
- MySQL bootstrapped via `composer bootstrap:booking`
- Redis reachable when `REQUIRE_REDIS_FOR_BOOKING_API=true`
- scheduler heartbeat fresh enough for runtime health if your environment expects it

Recommended local prep:

```bash
php artisan booking:doctor --json
php artisan booking:uat-pack:bootstrap --base-url=http://127.0.0.1:8000 --json
php artisan serve --host=127.0.0.1 --port=8000
```

The UAT bootstrap creates `../storage/app/uat/scenario-pack.json`, and the smoke script now reuses that manifest by default for:

- `auth.staff.username`
- `auth.staff.password`
- `branch.branch_id`
- `reservations.dine_in_checkin.reservation_id`
- `scenarios.dine_in_checkout.table_id`
- `scenarios.conversation_inbox.conversation_id`

If the manifest is missing or stale, the script exits with an actionable startup blocker instead of failing later in the flow.

### Exact env vars

Primary env:

```bash
STAFF_WEB_SMOKE_API_URL=http://localhost:8000/api/v1
STAFF_WEB_SMOKE_MANIFEST_PATH=../storage/app/uat/scenario-pack.json
STAFF_WEB_SMOKE_IDENTIFIER=uat.staff
STAFF_WEB_SMOKE_PASSWORD=UatDemo!123
```

Optional env:

```bash
STAFF_WEB_SMOKE_DEVICE_NAME=staff-web-live-smoke
STAFF_WEB_SMOKE_RESERVATION_QUERY=RES-
STAFF_WEB_SMOKE_RESERVATION_ID=77
STAFF_WEB_SMOKE_ORDER_TABLE_ID=11
STAFF_WEB_SMOKE_REFUND_RESERVATION_ID=91
STAFF_WEB_SMOKE_ORDER_ID=9001
STAFF_WEB_SMOKE_CONVERSATION_QUERY=RES-77
STAFF_WEB_SMOKE_CONVERSATION_ID=conv-1
STAFF_WEB_SMOKE_ALLOW_MUTATIONS=1
STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE=1
STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION=1
STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE=1
STAFF_WEB_SMOKE_PAYMENT_METHOD=Cash
STAFF_WEB_SMOKE_PAYMENT_PROVIDER=Cash
STAFF_WEB_SMOKE_CASHIER_OPENING_FLOAT=100000
STAFF_WEB_SMOKE_CASHIER_CURRENCY=VND
STAFF_WEB_SMOKE_CASHIER_BRANCH_ID=1
STAFF_WEB_SMOKE_CASHIER_TERMINAL_CODE=staff-web-live-smoke
STAFF_WEB_SMOKE_TARGET=staging
STAFF_WEB_SMOKE_PREVIEW_URL=https://preview.example.com
STAFF_WEB_SMOKE_PREVIEW_LABEL=vercel-preview
STAFF_WEB_SMOKE_EVIDENCE_DIR=../storage/app/booking_release/staff_web_smoke
```

`STAFF_WEB_SMOKE_IDENTIFIER` and `STAFF_WEB_SMOKE_PASSWORD` are only required when you are not using the canonical UAT manifest, or when you need to override the manifest credentials with a different staff actor.
These preview variables only annotate the smoke artifact. They do not replace a real deployed preview URL or runtime-log evidence for the same candidate build.

### Usage examples

Read-only against local backend with canonical UAT manifest:

```bash
npm run smoke:live
```

Read-only against staging with explicit credentials:

```bash
STAFF_WEB_SMOKE_API_URL=https://staging.example.com/api/v1 \
STAFF_WEB_SMOKE_IDENTIFIER=staging.staff \
STAFF_WEB_SMOKE_PASSWORD=*** \
npm run smoke:live
```

Mutation-gated local pass:

```bash
STAFF_WEB_SMOKE_ALLOW_MUTATIONS=1 \
STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE=1 \
STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION=1 \
STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE=1 \
npm run smoke:live
```

Harness behavior:

- prefers `STAFF_WEB_SMOKE_*` env when provided, otherwise falls back to the canonical UAT manifest when present
- fails early on startup blockers such as missing credentials/manifest parse errors
- fails clearly on network/runtime issues, including the public `/api/v1/health` base checks for `db`, `redis`, `scheduler`, and `disk`, and points back to `php artisan booking:doctor --json`
- auto-derives reservation, order, board, waiting, menu, and conversation sources from backend responses or the UAT manifest where possible
- when the canonical dine-in reservation is still `Confirmed`, auto-runs `POST /api/v1/staff/reservations/{id}/check-in` from board action metadata or the manifest table fallback before `order create`
- reports `PASS`, `SKIP`, or `FAIL` per step
- when `STAFF_WEB_SMOKE_EVIDENCE_DIR` is set, writes JSON/Markdown evidence plus `latest-<target>` pointers for preview/staging release bundles
- only runs write paths such as create/add-item/finalize/refund/open/close when the corresponding mutation gates are enabled

## Canonical release loop

From the repo root, use the backend-owned release loop when you need one bundle that covers contract artifacts, backend harnesses, `staff-web` test/build, preview metadata, live smoke, and launch-readiness:

```bash
composer release:loop -- --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --bootstrap-uat
```
