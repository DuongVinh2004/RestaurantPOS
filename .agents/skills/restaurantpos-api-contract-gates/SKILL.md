---
name: restaurantpos-api-contract-gates
description: Protect the RestaurantPOS API surface, contract artifacts, route inventories, OpenAPI output, and operational gate tests. Use when Codex changes routes, requests, resources, artifact generators, contract metadata, release gates, or any code that can drift API docs, snapshots, or runtime contract checks.
---

# RestaurantPOS API Contract Gates

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace the affected route, request, resource, service, and contract test before changing the API surface.
2. When route, payload, or resource shape changes, update contract metadata, generated artifacts, and gate tests in the same batch.
3. Prefer additive changes. If a breaking change is required, call it out explicitly and update docs or runbooks.
4. Treat generated artifacts and route inventories as reviewed outputs, not scratch files.
5. Keep release and runtime gates green after contract changes.

## Guardrails

- Do not hand-edit generated API outputs unless the generator explicitly requires it.
- Minimize route alias churn; preserve compatibility when possible.
- Keep controllers thin and let request or resource objects own validation and shaping concerns.
- Contract drift across routes, OpenAPI artifacts, and tests counts as a bug even if the feature test still passes.

## Verify

- `composer api:artifacts`
- `php artisan test tests/Feature/Infrastructure tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php`
- `php artisan test tests/Feature/Http tests/Unit/Config/ApiArtifactsConfigContractTest.php tests/Unit/Services/ApiContract/FormRequestSchemaFactoryTest.php`
