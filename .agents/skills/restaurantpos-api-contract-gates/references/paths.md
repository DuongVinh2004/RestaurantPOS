# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-api-contract.md`

## Code hotspots

- `routes/api.php`
- `config/api_artifacts.php`
- `app/Services/ApiArtifacts/*`
- `app/Services/ApiContract/*`
- `app/Http/Requests/*`
- `app/Http/Resources/*`
- `app/Console/Commands/*` that generate artifacts or gates

## Test surface

- `tests/Feature/Infrastructure/ApiOpenApiArtifactSnapshotTest.php`
- `tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php`
- `tests/Feature/Infrastructure/ApiRouteSurfaceIntegrityTest.php`
- `tests/Feature/Infrastructure/ApiRuntimeSmokeGateTest.php`
- `tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php`
- `tests/Feature/Http/ApiListingQueryStandardTest.php`
- `tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php`
- `tests/Unit/Config/ApiArtifactsConfigContractTest.php`
- `tests/Unit/Services/ApiContract/FormRequestSchemaFactoryTest.php`

## Questions to answer before patching

- Which contract test or snapshot proves this surface today?
- Is the change additive, compatible, or explicitly breaking?
- Which artifact generator or route inventory must be updated with the code?
- Which runbook or consumer-facing doc should move with the change?
