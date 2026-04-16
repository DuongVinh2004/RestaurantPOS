# Module Ownership Governance

This repo is in a transitional state. Legacy Laravel paths can remain while they
own real unmigrated code, but they are not default targets for new domain work.

## Placement Rules

- New business code goes under `app/Modules/<Domain>`.
- New release, API contract, metrics, health, verification, backup, and operator
  code goes under `app/Platform`.
- `app/Services`, `app/Models`, `app/Http/Controllers/Api`, and `app/Support`
  are transitional or shared compatibility zones. Do not add new domain logic
  there when a module already owns the workflow.
- Keep controllers thin. Validation belongs in request classes, response shaping
  belongs in resources or small presenters, and decisions belong in application
  services, actions, policies, guards, or state objects.
- Prefer module namespaces over legacy aliases when adding or changing module
  code.

## Test Placement

- New module unit tests should live under `tests/Unit/Modules/<Domain>/...`.
- New module feature tests should live under `tests/Feature/<Domain or Surface>/`
  until a feature-module taxonomy is introduced.
- New platform tests should live under `tests/Unit/Platform/...` or
  `tests/Feature/Infrastructure/...`, depending on whether they exercise pure
  services or runtime-facing commands/routes.
- Existing tests do not need bulk moves. Move tests only when the touched code is
  already being edited and the move improves ownership clarity.

## Artifact Boundaries

- Source code, SQL-first bootstrap artifacts, reviewed API consumer artifacts,
  and intentional release snapshots may be tracked.
- Runtime caches, local smoke output, screenshots, PHPStan cache, dev-server
  logs, debug bundles, and nested temporary folders are transient and ignored.
- `build/api-consumer` and `storage/app/booking_release` are treated as reviewed
  generated contract artifacts. Do not delete or untrack them without a contract
  artifact review.

## Large Service Rule

Do not rewrite oversized services wholesale. When changing one, first identify
the responsibility cluster being touched, add or update a focused test, then
extract only the local action, query, policy, state transition, or helper needed
for that change. Leave unrelated clusters in place.

## Shared Seams

Files such as `routes/api.php`, `routes/api/*.php`, `config/booking.php`,
`config/staff_capabilities.php`, and `database/schema/mysql-schema.sql` affect
multiple domains. Keep diffs small, preserve route and schema contracts, and run
targeted verification before merging.
