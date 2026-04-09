# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-api-contract.md`

## Code hotspots

- `config/api_artifacts.php`
- `config/cors.php`
- `app/Support/ApiErrorResponse.php`
- `app/Enums/*`
- `app/Services/ApiContract/*`
- `app/Services/ApiArtifacts/*`
- `app/Http/Requests/*`
- `app/Http/Resources/*`

## Artifact and doc surface

- `storage/app/booking_release/openapi-v1.json`
- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/README.md`
- `build/api-consumer/mutation-contracts.md`
- `build/api-consumer/postman/RestaurantPOS.postman_collection.json`

## Test surface

- `tests/Feature/Infrastructure/ApiOpenApiArtifactSnapshotTest.php`
- `tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php`
- `tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php`
- `tests/Feature/Http/ApiListingQueryStandardTest.php`
- `tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php`
- `tests/Feature/CorsContractTest.php`
- `tests/Unit/Config/ApiArtifactsConfigContractTest.php`

## Questions to answer before patching

- Which web consumer depends on this shape right now?
- Does the change belong in frozen OpenAPI, generated mutation contracts, or both?
- Will `error_code`, `request_id`, or validation payload keys change?
- Does a new enum or state value need explicit schema or example coverage?
- Does CORS or header exposure need to change for browser clients?
