# RestaurantPOS Customer Web

This frontend is being hardened around one usable customer chain:

`sign in -> browse menu -> check availability -> hold a table -> create reservation -> review reservation`

Deposit and bill actions stay in scope only when the live backend, provider support, and seeded UAT data actually expose them.

The app uses:

- Next.js App Router
- TypeScript
- TanStack Query
- React Hook Form
- shadcn/ui primitives

## Contract truth

Treat these as the only frontend contract sources:

- `storage/app/booking_release/openapi-v1.json`
- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/mutation-contracts.md`
- `src/lib/config/support-matrix.ts`

Do not infer customer-web contract behavior from Laravel controllers unless you are resolving a real ambiguity after checking the frozen artifacts above.

## Release scope

Wave 1 live:

- auth and session restore
- menu browse
- table availability and holds
- reservation create, list, and detail
- deposit preview, with payment-session proof only when provider support is real
- bill preview and active-order visibility only when backend or seeded UAT data is real

Wave 2 live-conditional and still env-gated by default:

- waiting list
- account benefits (`loyalty` + `vouchers`)
- privacy requests
- data export

Deferred from the current go-live dependency chain:

- preorder surfaces are visible in the frozen contract, but they are gated by `NEXT_PUBLIC_FEATURE_PREORDER` and are not part of the Wave 1 launch promise yet
- dev mocks are local-only resilience tooling, never a production behavior

The current implementation detail and rollout intent for each surface lives in `src/lib/config/support-matrix.ts`.

That file is the single rollout source of truth for customer-web:

- `live-ready`: safe to treat as part of the current live rollout
- `live-conditional`: surface is live, but final proof still depends on real provider or seeded runtime prerequisites
- `ci-safe-only`: contract-visible or deterministic proof only, not a live launch pass
- `local-uat-only`: local or controlled UAT diagnostics only, never production-ready evidence
- `rollout-gated`: disabled until its dedicated rollout flag is intentionally enabled
- `blocked`: fail closed until support is explicitly proven

## Local setup

Preferred local customer-web dev and live-proof lane from the repo root:

```powershell
npm run dev:all
npm run dev:smoke
```

`npm run dev:all` ensures repo-local MySQL and Redis, runs `composer bootstrap:booking`, refreshes `storage/app/uat/scenario-pack.json`, and starts:

- Laravel on `http://127.0.0.1:8000`
- customer-web on `http://127.0.0.1:3000`
- staff-web on `http://127.0.0.1:5173`

`npm run dev:smoke` is the short deterministic runtime check. It verifies the three ports above plus customer and staff demo logins from the current UAT pack.

When you also need backend runtime-readiness evidence such as scheduler heartbeat, outbox drain, or `php artisan booking:doctor --json`, use the repo-level runtime lane as well:

```powershell
npm run runtime:up
npm run runtime:preflight
```

That runtime lane proves backend readiness. `npm run dev:all` plus `npm run dev:smoke` proves the frontend/live-E2E lane only.

If you bootstrap or reseed the backend outside `npm run dev:all`, refresh the UAT pack again before any live proof:

```powershell
powershell -ExecutionPolicy Bypass -File ..\scripts\uat\Bootstrap-UatPack.ps1 -BaseUrl http://127.0.0.1:8000
```

Manual fallback when you do not want the repo-level helpers:

1. From the repo root, bootstrap the backend with the canonical SQL-first flow:

```bash
composer bootstrap:booking
```

2. Refresh the frozen API artifacts that customer-web depends on:

```bash
composer api:artifacts
```

3. Make sure the backend is reachable at `http://127.0.0.1:8000`. One local option is:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

4. In `customer-web`, create `.env.local` from `.env.example`.

5. Install dependencies, sync the curated frontend contract files, and start the app:

```bash
npm install
npx playwright install chromium
npm run sync:contracts
npm run dev
```

6. Open `http://127.0.0.1:3000`.

Keep the same host family on both sides when possible. `localhost` and `127.0.0.1` are different browser origins and must match backend CORS allow-lists. The current live proof lane is validated on `http://127.0.0.1:3000`; moving customer-web to another origin requires Laravel CORS alignment first.

## Environment

`NEXT_PUBLIC_API_BASE_URL` is the backend origin only. Do not append `/api/v1` or a trailing slash. The generated SDK appends `/api/v1/...` route paths itself.

Public env contract:

| Variable | Default | Purpose |
|---|---|---|
| `NEXT_PUBLIC_API_BASE_URL` | `http://127.0.0.1:8000` | Backend origin for generated SDK requests and health checks |
| `NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS` | `true` in non-production | Show the dev-only backend status banner |
| `NEXT_PUBLIC_ENABLE_DEV_MOCKS` | `false` | Use mock fetch adapters outside production when the backend is unavailable |
| `NEXT_PUBLIC_FEATURE_PREORDER` | `false` | Deferred preorder read surface; keep off unless running a focused contract or QA pass |
| `NEXT_PUBLIC_FEATURE_MENU_CATEGORIES` | `true` | Keep category browse visible in Wave 1 |
| `NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL` | `true` | Keep menu detail drill-down visible in Wave 1 |
| `NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY` | `true` | Keep live table availability enabled in Wave 1 |
| `NEXT_PUBLIC_FEATURE_TABLE_HOLDS` | `true` | Keep live hold creation and refresh enabled in Wave 1 |
| `NEXT_PUBLIC_FEATURE_WAITING_LIST` | `false` | Wave 2 rollout gate for waiting-list flows |
| `NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS` | `false` | Wave 2 rollout gate for loyalty and voucher surfaces |
| `NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS` | `false` | Wave 2 rollout gate for privacy requests |
| `NEXT_PUBLIC_FEATURE_DATA_EXPORT` | `false` | Wave 2 rollout gate for data export |

Temporary compatibility aliases are still accepted during the transition:

- `NEXT_PUBLIC_ENABLE_WAITING_LIST`
- `NEXT_PUBLIC_FEATURE_VOUCHERS`
- `NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS`
- `NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS`

New work should use the preferred `NEXT_PUBLIC_FEATURE_*` names above.

Compatibility aliases never widen support beyond `src/lib/config/support-matrix.ts`, and unknown surfaces fail closed to disabled. The backend status banner only reports browser-observable diagnostics: mock mode, the configured API health URL, response status or request id, local-base-url mismatch, and compatibility alias cleanup. It does not prove UAT pack freshness, seeded payment data, provider support, notifications, or benefits runtime readiness; the live runtime preflight owns those checks.

## Contract sync

`npm run sync:contracts` only copies the generated TypeScript SDK artifacts into `src/lib/contracts/generated`.

It does not regenerate the backend contract. The correct refresh order is:

```bash
composer api:artifacts
npm run sync:contracts
```

If the sync script reports missing contract inputs, fix the backend artifact chain first instead of hand-editing generated frontend files.

After syncing contracts, verify the typed customer-web adapter boundary before relying on screen tests:

```bash
npm run verify:contracts
npm run typecheck
npm run test -- api
```

`npm run verify:contracts` checks that the frozen OpenAPI, generated SDK, mutation matrix, and support matrix are present; that `src/lib/contracts/generated` matches `build/api-consumer`; and that proven Wave 2 surfaces remain env-gated while preorder stays CI-safe only. It warns when generated artifacts have local Git changes so reviewers can confirm they came from `composer api:artifacts` and `npm run sync:contracts`, not hand edits. For release provenance on a clean branch, rerun `composer api:artifacts`, `npm run sync:contracts`, and then `node scripts/check-contract-governance.mjs --strict-generated` so dirty generated files fail closed.

Use these files during feature work:

- route and schema truth: `storage/app/booking_release/openapi-v1.json`
- generated SDK signatures: `build/api-consumer/sdk/typescript/README.md`
- mutation requirements such as `row_version`, `Idempotency-Key`, and `X-Session-Id`: `build/api-consumer/mutation-contracts.md`
- release intent for customer-web: `src/lib/config/support-matrix.ts`

## Verification

Fast local proof set:

```bash
npm run verify:contracts
npm run lint
npm run typecheck
npm run test
```

Use the smallest relevant subset while iterating. Before release or handoff, run all three after a fresh `composer api:artifacts` plus `npm run sync:contracts`.

High-signal customer journey proof set:

```bash
npm run verify:wave-1
```

This runs lint, typecheck, and the focused Wave 1 journey suite for auth/session, booking, reservation detail, and backend-health behavior.

Release-facing proof set:

```bash
npm run verify:release
```

This adds the full Vitest suite, a production build, and the Playwright smoke. The Playwright smoke uses browser-level route interception to keep the checks deterministic; it validates shell and journey wiring, not live backend contract health.

`npm run verify:release` is CI-safe and mock-safe. A passing result proves the release wiring, not a real backend launch proof.

Live runtime Wave 1 proof set:

```powershell
# Preferred: run this from the repository root first.
npm run dev:all
npm run dev:smoke
```

```powershell
# If you are not using npm run dev:all, the live lane requires:
# - Laravel already running on http://127.0.0.1:8000
# - customer-web already running on http://127.0.0.1:3000
# - a freshly refreshed storage/app/uat/scenario-pack.json
# Run from customer-web in a second shell.
$env:NEXT_PUBLIC_API_BASE_URL="http://127.0.0.1:8000"
$env:CUSTOMER_WEB_LIVE_APP_HOST="127.0.0.1"
$env:CUSTOMER_WEB_LIVE_APP_PORT="3000"
$env:CUSTOMER_WEB_LIVE_IDENTIFIER="uat.customer.primary"
$env:CUSTOMER_WEB_LIVE_PASSWORD="UatDemo!123"
$env:CUSTOMER_WEB_LIVE_START_TIME="2026-04-20T18:30"
npm run test:e2e:live
```

`npm run test:e2e:live` is a strict live-only lane. It does not start customer-web for you, it fails if the canonical UAT manifest or live credentials are missing, and it rejects `NEXT_PUBLIC_ENABLE_DEV_MOCKS=true`. The browser runs against the real Laravel API for login, session restore, menu browse, availability search, table hold, reservation create/list/detail/cancel/reschedule, deposit preview and payment sessions, bill or active-order reads, and bill payment sessions when the live exercise flag is enabled.

A healthy live lane still is not enough to claim every payment path is proven. Deposit payment sessions need a real or explicitly simulated runtime provider path, and positive bill self-pay still needs refreshed dine-in checkout fixtures from the canonical UAT pack.

Use the live release gate before launch confidence:

```powershell
npm run verify:release:live
```

`npm run verify:release:live` is not CI-safe by design. If you need the CI-safe lane, use `npm run verify:release`.

QA checklist before live proof:

- Run `npm run dev:all` and `npm run dev:smoke`, or otherwise start Laravel and customer-web on the same host family allowed by CORS.
- Confirm `NEXT_PUBLIC_ENABLE_DEV_MOCKS=false`; mock responses are local or controlled-UAT diagnostics only.
- Confirm the canonical UAT manifest is fresh, parseable, and generated for the same API base URL used by the live gate.
- Set optional payment-session exercise flags only when the provider or simulated UAT stub and seeded data are intentionally present.
- Keep Wave 2 rollout flags off by default; if QA enables waiting-list, benefits, privacy, or data-export diagnostics, treat them as gated proof, not default launch exposure.

## Wave 1 freeze summary

Go or no-go should be read through these rollout buckets:

- Live-ready: auth and session restore, menu browse, availability search, hold create/refresh/cancel, reservation create/list/detail/cancel/reschedule, deposit preview plus payment-session create/read/refresh/confirm, positive active-order visibility, bill preview/detail, and bill payment-session create/read/refresh/confirm.
- Live-conditional: waiting list, account benefits, privacy requests, and data export have live proof only behind their explicit rollout flags.
- CI-safe only: the deterministic mock-backed Playwright smoke in `npm run verify:release` proves shell and journey wiring, but it does not prove a live Laravel runtime.
- Local or UAT only: dev mocks and simulated payment proof are useful diagnostics, but they are never production-ready evidence.
- CI-safe deferred: preorder remains contract-visible but disabled by default behind `NEXT_PUBLIC_FEATURE_PREORDER`; it is not launch proof until replace/clear live proof is intentionally added.

Live test fixture env vars:

| Variable | Required | Purpose |
|---|---:|---|
| `NEXT_PUBLIC_API_BASE_URL` or `CUSTOMER_WEB_LIVE_API_BASE_URL` | Yes | Laravel origin used by the browser and health check |
| `CUSTOMER_WEB_LIVE_APP_HOST` | No | Host for the already-running customer-web app; defaults to `127.0.0.1` |
| `CUSTOMER_WEB_LIVE_APP_PORT` | No | Port for the already-running customer-web app; defaults to `3000` to match the local Laravel CORS allow-list |
| `CUSTOMER_WEB_LIVE_IDENTIFIER` | Yes | Customer email, phone, or id for live login |
| `CUSTOMER_WEB_LIVE_PASSWORD` | Yes | Customer password for live login |
| `CUSTOMER_WEB_LIVE_START_TIME` | No | Local `YYYY-MM-DDTHH:mm` availability slot; defaults to a future rounded slot |
| `CUSTOMER_WEB_LIVE_GUEST_NAME` | No | Reservation guest name |
| `CUSTOMER_WEB_LIVE_GUEST_PHONE` | No | Reservation guest phone |
| `CUSTOMER_WEB_LIVE_GUEST_EMAIL` | No | Reservation guest email |
| `CUSTOMER_WEB_LIVE_GUEST_COUNT` | No | Availability and reservation party size |
| `CUSTOMER_WEB_LIVE_DURATION_MINUTES` | No | Availability and reservation duration |
| `CUSTOMER_WEB_LIVE_EXERCISE_DEPOSIT_PAYMENT_SESSION` | No | Set true only when the provider/stub supports live deposit payment sessions |
| `CUSTOMER_WEB_LIVE_EXERCISE_BILL_PAYMENT_SESSION` | No | Set true only when the provider/stub supports live bill payment sessions |
| `CUSTOMER_WEB_LIVE_EXERCISE_WAITING_LIST` | No | Set true only for Wave 2 waiting-list diagnostics after the UAT pack exposes seeded invite and seating prerequisites |
| `CUSTOMER_WEB_LIVE_EXERCISE_ACCOUNT_BENEFITS` | No | Set true only for Wave 2 benefits diagnostics with the account-benefits public flag enabled |
| `CUSTOMER_WEB_LIVE_EXERCISE_PRIVACY_TOOLS` | No | Set true only for Wave 2 privacy request diagnostics with the privacy public flag enabled |
| `CUSTOMER_WEB_LIVE_EXERCISE_DATA_EXPORT` | No | Set true only for Wave 2 data export diagnostics with the data-export public flag enabled |
| `CUSTOMER_WEB_LIVE_MAX_MANIFEST_AGE_MINUTES` | No | Override the live preflight freshness window for `storage/app/uat/scenario-pack.json`; defaults to 360 minutes |

Backend prerequisites for a non-skipped live pass:

- canonical SQL-first bootstrap has completed
- Laravel is reachable at the configured API origin
- CORS allows the customer-web origin used by Playwright
- `storage/app/uat/scenario-pack.json` is fresh and was generated for the same API base URL used by the live gate
- the customer fixture can authenticate through `POST /api/v1/auth/customer/login`
- the requested slot has at least one available table that can be held
- reservation creation is allowed for the held slot and session
- deposit and bill preview routes are enabled for the created reservation
- payment-session provider/stub support is configured before enabling the optional payment-session env flags
- positive bill self-pay proof has a real seeded dine-in path: staff check-in, active order creation, bill snapshot, and bill payment-session route support
- waiting-list Wave 2 diagnostics have seeded customer accounts, staff API key, and at least two table candidates; customer-web still does not prove notification delivery, realtime updates, or final seating when branch scheduling blocks immediate staff seating
- account benefits diagnostics have a benefits reservation, voucher, and loyalty fixture; voucher apply/remove and loyalty redeem/release remain env-gated even after live proof
- privacy and data-export diagnostics use the real customer token; privacy request processing after submission remains an operator lifecycle outside browser proof

Known live proof boundaries as of April 19, 2026:

- deposit self-pay and bill self-pay are live-ready against the backend contract, but production PSP configuration remains separate from the local UAT `simulated` provider path
- the local `simulated` provider proves browser-to-backend payment-session flow only; it does not prove production PSP account setup, webhook secret rollout, or real-money settlement rails
- waiting-list owner actions are live-proven behind flag; notification delivery, realtime updates, and branch-scheduled final seating remain runtime prerequisites
- account benefits and privacy/data-export are live-proven behind flags, not default-on launch exposure
