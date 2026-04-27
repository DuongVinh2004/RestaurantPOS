# Backend Critical Flow Test Ladders

Full `php artisan test` timed out at 604 seconds during audit, so release confidence for critical flows is tracked by domain ladders. Keep the full suite available, but use these scripts when a broad run would hide which production-critical surface failed.

The backend tests use the `phpunit.xml` SQLite in-memory default unless a command explicitly boots the runtime stack. MySQL, Redis, scheduler, or backend HTTP failures in runtime gates should be triaged as environment/runtime blockers first, not as test rewrites.

## Summary

| Ladder | Command | Covers | Expected runtime observed locally | Runtime dependencies |
| --- | --- | --- | --- | --- |
| Security | `composer test:security` | Auth, RBAC, staff capabilities, branch isolation, ops authorization | About 7-8 minutes | SQLite; no Redis/MySQL required for the PHPUnit lane |
| Orders | `composer test:orders` | Order create/read/mutate, item lifecycle, bill lock, row-version, idempotency | About 3-4 minutes | SQLite; Redis is faked where idempotency tests need it |
| Kitchen/KDS | `composer test:kitchen` | Kitchen routing, KDS dispatch/actions, row-version, branch scope, idempotency | About 10-11 minutes | SQLite; Redis/realtime caches are faked in tests |
| Money/Cashier | `composer test:money` | Checkout/pay/finalize, refund, cashier shifts, branch and actor guards, money policies | About 8-9 minutes | SQLite; Redis is faked where idempotency tests need it |
| Inventory | `composer test:inventory` | Ingredients, stock movements, unit/branch guards, served-item consumption, movement replay safety | About 3-4 minutes | SQLite; no live Redis/MySQL required |
| Release contract | `composer test:release-contract` | Config, route inventory, release artifact services, route gate, manifest, deploy/doctor command contracts | Usually under 1 minute | SQLite for tests; live artisan gates below need runtime config |
| Critical aggregate | `composer test:critical` | Runs all backend ladders above in sequence | Long-running; use CI matrix for visibility | Same as component ladders |
| Staff web | `cd staff-web && npm run integrity:check`; `cd staff-web && npm run build` | Package integrity, generated artifact freshness, TypeScript/Vite build | Build-time dependent; usually a few minutes | Node/npm only |

## Backend Commands

Run individual ladders when a domain is touched:

```bash
composer test:security
composer test:orders
composer test:kitchen
composer test:money
composer test:inventory
composer test:release-contract
```

Run all critical backend ladders when you need one local command and can tolerate the runtime:

```bash
composer test:critical
```

Keep the full suite command available for broad confidence:

```bash
composer test
php artisan test
```

## Staff Web

Run these from the repository root when staff-web code, generated API artifacts, package integrity, or release packaging changes:

```bash
cd staff-web && npm run integrity:check
cd staff-web && npm run build
```

## Runtime And Release Gates

These are not replacements for the PHPUnit ladders. They prove runtime/release surfaces and may require MySQL, Redis, scheduler heartbeat, release artifacts, and local environment values.

```bash
php artisan booking:route-gate --json
php artisan booking:release-manifest --json
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

`php artisan booking:release-manifest --json` can fail when release artifacts are stale. Use `composer api:artifacts` only when the contract intentionally changed.

The OpenAPI artifact snapshot test is intentionally not inside `composer test:release-contract` because stale snapshots can produce very large diffs. Artifact freshness is checked by `php artisan booking:release-manifest --json` and CI release evidence. When API route or schema generation changes, run the snapshot explicitly with a higher memory ceiling:

```bash
php -d memory_limit=1G artisan test tests/Feature/Infrastructure/ApiOpenApiArtifactSnapshotTest.php
```

## Failure Triage

- Validation, stale `row_version`, branch denial, idempotency replay, or actor boundary assertions usually mean a backend contract regression. Inspect the failing test name first; these ladders intentionally encode the invariant in the method name.
- `could not connect`, Redis refused, missing scheduler heartbeat, or MySQL bootstrap errors are runtime/environment blockers. Fix local services or CI service health before changing tests.
- A `booking:release-manifest` freshness failure means generated artifacts or manifest snapshots are stale. Regenerate only when the source contract intentionally changed.
- A kitchen ladder run around 10 minutes is expected in this repo. CI runs it as its own matrix lane so the old full-suite timeout does not hide KDS failures.
- A Pint failure is formatting only; run Pint fix separately and review the diff.
- A PHPStan timeout without diagnostics is a runtime-duration issue. Use a scoped PHPStan pass on the changed paths to separate introduced typing issues from global analysis runtime.

## CI Matrix

`.github/workflows/booking-ci.yml` runs each backend ladder as its own matrix lane:

- `critical-security`
- `critical-orders`
- `critical-kitchen-kds`
- `critical-money-cashier`
- `critical-inventory`
- `critical-release-contract`

The workflow also keeps `full-gate` available and runs `critical-staff-web` as a separate Node job with `npm run integrity:check` and `npm run build`.

## Coverage Notes

- Order lifecycle covers checked-in table/session context, branch isolation, required and stale `row_version`, bill locks, terminal state guards, quantity/status transition policy, and order idempotency replay versus payload mismatch.
- Kitchen/KDS covers dispatch and ticket action `row_version`, branch isolation, duplicate dispatch reuse, ticket action transition policy, same-branch kitchen success, idempotency replay versus payload mismatch, and ticket row-version conflicts.
- Money/cashier covers finalize/pay `row_version`, open cashier-shift requirements, overpay denial, refund balance and currency invariants, refund cancel amount invariants, one open shift per cashier/branch, close row-version guard, branch mismatch denial, and idempotency replay versus payload mismatch.
- Inventory covers served item stock consumption exactly once, replay-safe movement references, negative stock denial, branch/unit mismatch denial, cancelled item non-consumption, and adjustment actor attribution.

## TODO

- Inventory adjustment audit currently relies on `ingredient_stock_movements.created_by` actor attribution. Decide whether a separate audit-log event is part of the release contract before making that a hard test gate.
