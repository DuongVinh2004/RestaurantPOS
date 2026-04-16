# Test Placement Guide

The suite still contains legacy folders. Do not move the whole suite just to
match the module tree.

Use these defaults for new tests:

- Module unit tests: `tests/Unit/Modules/<Domain>/...`
- Platform unit tests: `tests/Unit/Platform/...`
- API, command, and runtime-facing feature tests: keep the existing
  `tests/Feature/<Surface>/...` convention until that surface is migrated.
- Shared contract tests stay in `tests/Feature/Infrastructure` or
  `tests/Unit/Config` when they protect routes, artifacts, config, or release
  gates.

When editing legacy code under `app/Services`, `app/Models`, or
`app/Http/Controllers/Api`, place new tests according to the domain that should
own the behavior next. Do not add new `tests/Unit/Services` coverage unless the
legacy service is still the canonical owner.
