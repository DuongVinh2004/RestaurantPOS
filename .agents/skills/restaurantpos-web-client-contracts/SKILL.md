---
name: restaurantpos-web-client-contracts
description: Protect the RestaurantPOS backend contract as consumed by customer-web (Next.js + TypeScript) and staff-web (React + TypeScript + Vite), including frozen OpenAPI, generated TypeScript SDK, mutation contract docs, standardized error envelopes, enum/state exposure, CORS, and frontend-facing DX. Use when Codex changes request or response shape, API artifacts, machine-readable errors, enum or state fields, pagination or meta behavior, or frontend integration guidance for web consumers.
---

# RestaurantPOS Web Client Contracts

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Identify the invariant owner first. Keep the relevant domain skill loaded when the task is really auth, self-service, checkout, reservation, or admin behavior.
2. Treat `storage/app/booking_release/openapi-v1.json` and generated consumer artifacts as the only official frontend contract sources.
3. When a request or response shape changes, update contract metadata, generated artifacts, and FE-facing docs in the same batch.
4. Keep the default success envelope stable: `{ data: ... }` or `{ data: [...], meta: ... }` for collections.
5. Keep machine-readable error behavior stable: `error_code`, `message`, `request_id`, and `errors` when validation details exist.
6. When enum or state fields change, expose them through explicit schema, examples, or generated SDK types instead of expecting FE to infer values from incidental strings.
7. Keep `row_version`, `Idempotency-Key`, and `X-Session-Id` requirements visible in mutation contract outputs when they matter to browser clients.
8. Refresh artifacts and run contract tests before closing the batch.

## Guardrails

- Do not hand-edit generated files under `build/api-consumer/` or `storage/app/booking_release/`.
- Do not make FE rely on localized text, controller internals, or PHP enum class names as the contract.
- Do not let route-level fixes drift away from `config/api_artifacts.php`, mutation contract docs, or frozen OpenAPI metadata.
- Preserve the split-web CORS contract and `X-Request-Id` response exposure when touching client-facing headers.

## Verify

- `composer api:artifacts`
- `php artisan test tests/Feature/Infrastructure tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php`
- `php artisan test tests/Feature/Http/ApiListingQueryStandardTest.php tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php tests/Feature/CorsContractTest.php tests/Unit/Config/ApiArtifactsConfigContractTest.php`
