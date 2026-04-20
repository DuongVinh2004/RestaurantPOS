# Booking API contract

## Artifact

- Frozen OpenAPI artifact: `storage/app/booking_release/openapi-v1.json`
- Release manifest definition: `config/booking_release.php`
- Canonical route inventory input: `tests/fixtures/route_inventory_gate.json`

## Generate and refresh

1. Generate or refresh the OpenAPI artifact:

```bash
php artisan booking:api-contract --write
```

2. Regenerate consumer artifacts from that frozen spec:

```bash
php artisan booking:api-artifacts:generate
```

3. Refresh the frozen release manifest snapshot after the artifact changes:

```bash
php artisan booking:release-manifest --write
```

4. Verify drift locally:

```bash
php artisan test --filter=ApiOpenApi
php artisan test --filter=RouteSurfaceIntegrity
php artisan booking:release-manifest --verify-frozen
```

For the full canonical release chain through immutable packaging:

```bash
php artisan booking:release-build
```

## Route and capability reconcile

Use the explicit reconcile command when `routes/api.php`, `config/staff_capabilities.php`, and `tests/fixtures/route_inventory_gate.json` drift during API or RBAC work:

```bash
php artisan booking:route-contract:reconcile --json
```

Default mode is diff-only. It does not rewrite the locked contract or runtime config paths.

When a reviewed route change is intentional, operators or developers can explicitly refresh the locked artifacts:

```bash
php artisan booking:route-contract:reconcile --write-route-inventory --write-staff-capabilities
```

Mapping rules:

- `route_capabilities` is generated from live runtime routes carrying `staff.capability:*` middleware.
- `known_capabilities` is merged with runtime-discovered route capabilities so strict known-capability enforcement stays reviewable.
- `route_aliases` remains explicit config; locked `alias_groups` in `route_inventory_gate.json` are generated from that map plus runtime controller actions.
- `capability_aliases` is preserved manual config for actor resolution and is not inferred from route middleware.

Do not run the write mode in deploy or request paths. The command is a review-time reconcile tool for frozen contract maintenance only.

If the locked route inventory changed and OpenAPI consumers depend on it, refresh the frozen artifacts after the reconcile:

```bash
php artisan booking:api-contract --write
php artisan booking:release-manifest --verify-frozen --json
```

## Contract model

### Success envelope

- Default shape is `{ "data": ... }`.
- Collection responses use `{ "data": [...], "meta": { ... } }` when pagination or action metadata exists.
- Known envelope exceptions are runtime-backed and documented explicitly:
  - `GET /api/v1/health`
  - `GET /api/v1/health/redis`
  - `GET /api/v1/staff/tables/board`

### Error envelope

Default API error payload:

```json
{
  "error_code": "validation_error",
  "category_code": "validation_error",
  "message": "Validation error.",
  "request_id": "req-123",
  "errors": {
    "field": ["..."]
  }
}
```

Canonical optional top-level fields:

- `category_code`: canonical frontend branch key for important failure families such as `authentication_required`, `forbidden_capability`, `owner_scope_denied`, `policy_denied`, `resource_conflict`, `stale_write`, `domain_invariant_violation`, and `idempotency_conflict`
- `errors`: validation-style field map when request or domain validation details exist
- `conflict_type`: machine-readable conflict family such as `stale_write`, `state_conflict`, or `idempotency_payload_mismatch`
- `replay_state`: idempotency replay state such as `in_progress` or `payload_mismatch`
- `state_reason`: stable deny/conflict reason token such as `row_version_mismatch` or `missing_required_capability`
- `required_capability` and `staff_role_name`: capability-denied context for staff/admin guard failures
- `warnings`: non-fatal contract notes such as deprecated alias warnings
- `next_actions`: machine-readable recovery hints such as `reload_resource` or `retry_with_latest_row_version`
- `deprecated_aliases`: legacy top-level aliases still emitted for compatibility

Supported standardized error schemas in the spec:

- `validation_error`
- `unauthorized`
- `forbidden`
- `not_found`
- `conflict`
- `stale_row_version`
- idempotency-specific conflicts and replay protection

Canonical conflict split:

- `422 validation_error`: malformed or incomplete input, or domain validation that is not a write-conflict
- `409 stale_row_version`: optimistic-concurrency failure for `row_version`-guarded mutations
- `409 conflict`: other state or uniqueness conflicts, including idempotency payload mismatch and database write conflicts

Legacy compatibility note:

- `category_code` is now the canonical frontend branch key. Prefer it when deciding retry, reload, sign-in, or denied-surface flows.
- Some idempotency errors still emit top-level `error` for backwards compatibility.
- `error_code` remains for backwards compatibility with older consumers and still describes the transport-level error family.
- `error` is deprecated and may be removed after consumers migrate.

### Auth schemes

- `CustomerAccessToken`
  - header `X-Customer-Token`
- `CustomerSessionId`
  - header `X-Session-Id`
  - current runtime also accepts `session_id` on selected routes
- `StaffApiKey`
  - header `X-Staff-Key`

## Full contract-grade flows

These routes currently have explicit tags, request schemas, response schemas, auth metadata, examples, and drift coverage:

- Customer auth and staff auth
- Reservation create and reservation show
- Reservation self-service list, cancel, reschedule
- Reservation deposit preview, acknowledge, intent, revoke
- Reservation deposit payment sessions
- Reservation bill, bill preview, active order
- Reservation bill payment sessions
- Customer waiting-list owner flows
- Payment provider webhooks
- Staff table board
- Staff cashier shift flows
- Staff bill snapshot, settlement preview/finalize, refund preview/refund/refund-cancel
- Admin branch master data

Deprecated compatibility aliases remain in the spec and are marked `deprecated: true` when declared in the route inventory alias groups.

## Use with clients

### Postman

- Import `storage/app/booking_release/openapi-v1.json` directly.
- Use environment variables for the base URL and auth headers.
- Deprecated aliases remain visible, so collections can be cleaned up incrementally.
- The generated consumer collection exposes the curated priority batch as top-level folders and the remaining full-contract operations in the `Reference` folder.
- For the curated generated collection, environments, and SDK foundation, use the explicit sequence `booking:api-contract --write` -> `booking:api-artifacts:generate` -> `booking:release-manifest --write`, or `composer api:artifacts`.
- Consumer artifact workflow is documented in `docs/runbooks/api-consumer-artifacts.md`.

### Frontend and mobile

- Use the frozen artifact as the only schema source for typed clients.
- Use `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts` as the official TypeScript convenience layer only for the curated priority batch declared in `config/api_artifacts.php` and enumerated in `build/api-consumer/sdk/typescript/README.md`.
- Use `build/api-consumer/sdk/typescript/restaurantpos-enums.ts` and `build/api-consumer/enum-state-map.json` for FE-safe enum/state values and semantic aliases instead of inferring states from incidental payload strings.
- The generated TypeScript SDK expects the backend origin as its base URL and appends `/api/v1/...` paths itself. Boolean query parameters are serialized as `1` and `0` to match the frozen HTTP contract.
- If a full-contract route is not in that curated SDK batch, generate from the frozen artifact instead of reading controllers/resources directly.
- If a route only appears as fallback in the frozen artifact, treat it as discoverable but not yet an endorsed FE contract.
- Controllers and resources are backend implementation detail, not the official consumer contract.
- The spec exposes `x-auth-mode`, `x-runtime-middleware`, and `x-contract-grade` for runtime-aware tooling.
- Priority flows already include concrete examples suitable for mock handlers and integration fixtures.

### SDK generation

- The artifact is OpenAPI `3.1.0`.
- Generate SDKs from the frozen artifact, not from ad-hoc route inspection.
- The repo-generated TypeScript SDK is intentionally narrower than the full OpenAPI surface; it tracks the curated FE priority batch rather than every full-contract route.
- Regenerate SDKs only after refreshing the OpenAPI artifact, the consumer artifacts, and then the release manifest snapshot in that order.

## Current limitations

As of 2026-04-19, the generated spec reports `123` full-contract operations and `113` fallback operations.

Routes still below contract-grade are intentionally left as fallback until their response shape is formalized or the source is tightened. The main remaining groups are:

- Admin benefits, inventory, kitchen, menu, restaurant, and finance setting endpoints
- Metrics and assorted operational reporting endpoints
- Staff kitchen, realtime, inbox, timeline, voucher, loyalty, and broader master-data CRUD surfaces
- Legacy `/api/user`

Fallback routes still appear in the artifact, but they use generic envelopes and inferred request schemas rather than curated domain schemas.

Customer-facing auth, menu, availability, table holds, reservations, canonical `/preorder` flows, deposit, bill, waiting-list, benefits, privacy, and data-export surfaces are now part of the frozen full-contract customer lane. Selected deprecated aliases can still remain fallback-discoverable; for example, the legacy `DELETE /api/v1/reservations/{id}/pre-order` alias is not the contract source for customer-web. Frontend rollout policy can still be narrower than contract coverage; for example, customer-web keeps preorder env-gated and Wave 2 surfaces env-gated by default even though the canonical underlying contract is explicit.

## Notable source fixes made for contract alignment

- `FormRequest` introspection no longer resolves through the container, so spec generation reads validation rules without triggering real request validation.
- Priority customer self-service reservation errors now use the standardized API error envelope with `request_id`.
- `ReservationController` not-found responses now use the shared error helper.
- `IdempotencyMiddleware` conflict and in-progress responses now emit the normalized error envelope while keeping the legacy `error` alias for compatibility.
- Global API exception rendering now emits normalized English fallback messages instead of mojibake payload text.

## Canonical alias notes

These are the only locked route alias groups remaining in `tests/fixtures/route_inventory_gate.json` for rollout safety.

- Bill snapshot
  - Canonical: `POST /api/v1/staff/orders/{order_id}/bill-snapshot`
  - Legacy alias: `POST /api/v1/staff/orders/{order_id}/close`

- Finalize settlement
  - Canonical: `POST /api/v1/staff/orders/{order_id}/settlement/finalize`
  - Legacy alias: `POST /api/v1/staff/orders/{order_id}/checkout`

- Voucher release
  - Canonical mutation remains the remove route
  - Compatibility alias: `POST /api/v1/staff/reservations/{reservation_id}/voucher/release`

- Loyalty redemption release
  - Canonical mutation remains the redeem-release route
  - Compatibility alias: `POST /api/v1/staff/reservations/{reservation_id}/loyalty/release`

- Staff table board
  - Canonical: `GET /api/v1/staff/tables/board`
  - Legacy alias: `GET /api/v1/staff/table-board`

Compatibility input aliases still accepted by the current implementation:

- Canonical idempotency header: `Idempotency-Key`
- Compatibility header alias: `X-Idempotency-Key`
- Compatibility body field alias: `idempotency_key`
