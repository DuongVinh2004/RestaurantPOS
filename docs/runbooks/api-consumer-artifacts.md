# API Consumer Artifacts

## Intent

This project already keeps a frozen OpenAPI artifact at:

- `storage/app/booking_release/openapi-v1.json`

That frozen artifact is the official backend API contract. Consumer artifacts exist to make the frozen contract easier for frontend and QA consumers to use, not to create competing contract sources.

## Generated artifacts

Canonical refresh flow for consumer-facing API artifacts:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

or:

```bash
composer api:artifacts
```

`composer api:artifacts` now follows the same explicit order. OpenAPI is written first, consumer artifacts are generated from that frozen spec, then the frozen release manifest snapshot is refreshed.

Generated Postman collection and environment templates are content-deterministic. Re-running the generator with unchanged OpenAPI/config inputs must not change their hashes or thaw the frozen release manifest snapshot.

Outputs are written under:

- `build/api-consumer/postman/RestaurantPOS.postman_collection.json`
- `build/api-consumer/postman/RestaurantPOS.local.template.postman_environment.json`
- `build/api-consumer/postman/RestaurantPOS.staging.template.postman_environment.json`
- `build/api-consumer/mutation-contracts.md`
- `build/api-consumer/enum-state-map.json`
- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/sdk/typescript/README.md`

If a UAT manifest is available, the same command can also emit a local ready-to-use environment:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate --uat-manifest=storage/app/uat/scenario-pack.json
php artisan booking:release-manifest --write
```

This adds:

- `build/api-consumer/postman/RestaurantPOS.uat.postman_environment.json`

## Freshness Control

The release package integrity check treats generated API artifacts as stale when they are older than the frozen OpenAPI source they depend on, or when the frozen release manifest snapshot is older than the generated artifacts it records.

If freshness fails, do not hand-edit generated outputs. Re-run the canonical refresh flow:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

Then re-run the package integrity check and frozen manifest verification before packaging.

## Official frontend contract story

| Need | Official source |
|---|---|
| Build a TypeScript frontend against the curated priority batch | `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts` |
| Consume FE-safe enum/state values and semantic aliases | `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`, `build/api-consumer/enum-state-map.json` |
| Check mutation requirements such as `row_version`, `Idempotency-Key`, `X-Session-Id`, and expected `403/409/422` handling for FE batch routes | `build/api-consumer/mutation-contracts.md` |
| Generate a typed client for another language, another FE stack, or a full-contract route outside the curated SDK batch | `storage/app/booking_release/openapi-v1.json` |
| Discover full-contract routes that are not in the SDK batch yet | `Reference` folder in `build/api-consumer/postman/RestaurantPOS.postman_collection.json` |
| Understand runtime/controller/resource behavior | Read backend code as implementation detail only, not as the consumer contract |

Do not treat controllers, resources, or ad-hoc route inspection as contract sources for FE integration.

## Source-of-truth model

- Route and schema source: `storage/app/booking_release/openapi-v1.json`
- Collection curation and variable capture rules: `config/api_artifacts.php`
- Curated frontend priority batch source: `config/api_artifacts.php` -> `postman.groups`
- Mutation matrix scope for FE batch friction routes: `config/api_artifacts.php` -> `mutation_contract.groups`
- Demo/UAT variable hydration: `storage/app/uat/scenario-pack.json`

Do not edit generated JSON or SDK files directly. Update the contract or `config/api_artifacts.php`, then regenerate.

The generated enum/state artifacts expose backed enum values and semantic metadata that FE should not infer from incidental payload strings. This is especially important for reservation checked-in behavior, where the persisted value remains backward-compatible.

## What is included

The generated collection prioritizes:

- auth
- availability -> hold -> reservation
- deposit self-pay
- dine-in -> checkout
- refund flows
- waiting-list customer + staff actions
- benefits apply/remove/redeem/release
- admin master-data create flow
- conversation inbox
- payment webhook intake
- health checks

The generated SDK and the top-level Postman folders cover the same curated priority batch.

The exact curated signature inventory is emitted into:

- `build/api-consumer/sdk/typescript/README.md`
- `build/api-consumer/mutation-contracts.md`

It also emits a `Reference` folder with remaining full-contract OpenAPI operations that are not part of the curated priority flow folders.

## Postman usage

1. Import `build/api-consumer/postman/RestaurantPOS.postman_collection.json`
2. Import one environment file:
   - local template for manual setup
   - staging template for non-secret remote setup
   - UAT environment if you already bootstrapped the canonical demo pack
3. Run customer or staff login requests first
4. The collection captures common values such as:
   - `customerToken`
   - `customerSessionId`
   - `staffApiKey`
   - `holdId`
   - `orderId`
   - `waitingListId`
   - `depositPaymentSessionId`

Webhook requests include a pre-request script that recomputes `X-Payment-Signature` when `paymentWebhookSecret` is populated.

## SDK usage

`build/api-consumer/sdk/typescript/restaurantpos-sdk.ts` is the official FE convenience layer for the curated priority batch only.

Current scope:

- generated from the frozen OpenAPI artifact
- typed request/response aliases for the curated route set
- auth-aware request helper for customer token, staff API key, and customer session id
- session-aware customer routes keep `X-Customer-Token` and `X-Session-Id` together when both are configured, so browser clients do not silently lose session correlation after login
- staff auth session typing now includes the Batch 1 startup surface on `login`, `auth/staff/me`, and `auth/staff/refresh`:
  - `data.startup.default_branch`
  - `data.startup.active_cashier_shift`
  - `data.startup.readiness`
- no npm package manifest in phase one

Use the generated SDK when the route you need appears in `build/api-consumer/sdk/typescript/README.md`.

Use `build/api-consumer/mutation-contracts.md` when FE needs to know whether a mutation requires `row_version`, `Idempotency-Key`, or session propagation, and whether the current frozen contract formally exposes `401`, `403`, `409`, or `422` handling for that route.

## Canonical error metadata

Frontend and QA consumers should rely on this error contract:

- Always read `error_code`, `message`, and `request_id`.
- Read `errors` for field-level validation or domain validation details.
- Treat `409 stale_row_version` as a stale-write retry path, not as ordinary validation.
- Use `conflict_type`, `replay_state`, `state_reason`, `warnings`, and `next_actions` when present to drive retry, reload, or operator guidance.
- On capability-denied staff/admin routes, `required_capability` and `staff_role_name` are the canonical machine-readable fields.
- If an idempotency error still includes top-level `error`, treat it as deprecated compatibility output and prefer `error_code`.

If the route is not in that curated batch but already has a full-contract shape in the frozen OpenAPI artifact, generate your own client from `storage/app/booking_release/openapi-v1.json` instead of reading controllers/resources directly.

If the route is still fallback-only in the frozen artifact, it is discoverable but not yet an endorsed FE contract. Prefer promoting that route to contract-grade before treating it as stable frontend surface.

## Helpers

PowerShell wrappers:

- `scripts/api/Generate-ApiArtifacts.ps1`
- `scripts/api/Invoke-SimulatedPaymentWebhook.ps1`

Example:

```powershell
pwsh ./scripts/api/Generate-ApiArtifacts.ps1 -RefreshOpenApi -UatManifestPath storage/app/uat/scenario-pack.json
```

```powershell
pwsh ./scripts/api/Invoke-SimulatedPaymentWebhook.ps1 `
  -BaseUrl http://127.0.0.1:8000 `
  -Secret simulated-webhook-secret `
  -ProviderSessionCode sim-dep-001 `
  -ProviderEventCode sim-webhook-001
```

## Smoke examples

Customer login:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/customer/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"identifier":"uat.customer.primary","password":"UatDemo!123","session_label":"web"}'
```

Availability:

```bash
curl "http://127.0.0.1:8000/api/v1/tables/available?branch_id=1&from=2026-04-05T12:00:00Z&to=2026-04-05T14:00:00Z&guest_count=2&suggest=1" \
  -H "Accept: application/json"
```

## Drift control

Recommended update flow:

1. Update backend routes / requests / contract metadata.
2. Refresh the frozen OpenAPI artifact.
3. Regenerate consumer artifacts from that frozen spec.
4. Refresh the frozen release manifest snapshot.
5. Run artifact tests.

Suggested commands:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
php artisan test --filter=ApiConsumerArtifactsGenerateCommandTest
php artisan test --filter=ApiOpenApi
```

Frontend harness shortcut:

```bash
php artisan booking:harness:fe-contract --refresh-openapi --json
```

For the full release chain ending in an immutable package, use `php artisan booking:release-build` or `composer release:package`.

For the full backend + `staff-web` release candidate loop, use `php artisan booking:release-loop` or `composer release:loop`. That loop keeps contract artifacts, backend harnesses, `staff-web` test/build, preview metadata, live smoke, and launch-readiness evidence in one report bundle.

Canonical release loop example:

```bash
php artisan booking:release-loop \
  --target=staging \
  --manifest-path=storage/app/uat/scenario-pack.json \
  --base-url=http://127.0.0.1:8000 \
  --bootstrap-uat
```

## Cross-Origin (CORS)

The backend ships an explicit CORS policy at `config/cors.php` for the split frontend architecture:

| Frontend | Stack | Typical dev origin |
|---|---|---|
| `customer-web` | Next.js + TypeScript | `http://localhost:3000` or `http://127.0.0.1:3000` |
| `staff-web` | React + TypeScript + Vite | `http://localhost:5173`, `http://127.0.0.1:5173`, `http://localhost:4173`, or `http://127.0.0.1:4173` |

### Backend env setup

Set `CORS_ALLOWED_ORIGINS` as a comma-separated list of allowed origins:

```env
# Local development
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173,http://localhost:4173,http://127.0.0.1:4173

# Staging
CORS_ALLOWED_ORIGINS=https://customer.staging.example.com,https://staff.staging.example.com

# Production
CORS_ALLOWED_ORIGINS=https://customer.example.com,https://staff.example.com
```

Use the exact browser origin. `localhost` and `127.0.0.1` are different origins for CORS and must both be listed if local dev uses both forms.

**Leave empty or omit to deny all cross-origin requests** (safe production default).

### Delivery policy

The backend CORS contract is intentionally narrow:

| Setting | Contract |
|---|---|
| Paths | `api/*` only |
| Allowed methods | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` |
| Credentials mode | `supports_credentials=false` |
| Exposed response headers | `X-Request-Id` |
| Wildcard origins | Not used; list exact origins in `CORS_ALLOWED_ORIGINS` |

### Headers available cross-origin

FE apps can send these custom headers without triggering a blocked preflight:

| Header | Purpose |
|---|---|
| `X-Customer-Token` | Customer authentication |
| `X-Staff-Key` | Staff API key authentication |
| `X-Session-Id` | Session correlation (holds, reservations) |
| `Idempotency-Key` | Idempotent mutation guard (canonical) |
| `X-Idempotency-Key` | Idempotent mutation guard (alias) |
| `X-Request-Id` | Request correlation / tracing |

The backend exposes `X-Request-Id` in responses so FE can read it for error correlation.

### FE base URL configuration

```typescript
// customer-web (Next.js)
const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1';

// staff-web (Vite)
const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1';
```

Point both values at the backend API origin, not at the frontend app origin. Typical examples:

- local: `http://localhost:8000/api/v1`
- staging: `https://api.staging.example.com/api/v1`
- production: `https://api.example.com/api/v1`

### Credentials mode

The API uses **header-based auth** (not cookies), so FE `fetch` calls should **not** set `credentials: 'include'`. The backend's `supports_credentials` is `false`.

```typescript
// Correct
fetch(`${API_BASE}/tables/available?branch_id=1`, {
  headers: { 'Accept': 'application/json', 'X-Customer-Token': token },
});

// Incorrect - do not use credentials mode
fetch(url, { credentials: 'include' }); // not needed, would fail
```

## Current limitations

- The Postman collection is optimized for high-signal flows, not every fallback route.
- Some flows still need manual row-version updates when a preceding mutation changes state mid-demo.
- The SDK is a generated foundation file, not yet a published package with versioned release workflow.
- Admin and conversation mutation breadth is intentionally narrower than the total route surface in phase one.
