---
name: restaurantpos-runtime-smoke
description: Verify RestaurantPOS runtime readiness beyond SQLite-backed automated tests. Use when Codex changes code that depends on MySQL bootstrap artifacts, Redis, scheduler heartbeat, external-like payment behavior, notifications, release commands, or other runtime checks that need artisan health commands or live API smoke instead of only phpunit.
---

# RestaurantPOS Runtime Smoke

Read `README.md`, `.codex/AGENTS.md`, and `references/smoke-matrix.md` before claiming runtime safety.

## Workflow

1. Decide whether the change is runtime-sensitive or fully covered by automated tests.
2. If runtime-sensitive, make sure the local environment matches the repo contract:
   - MySQL-compatible DB bootstrapped with `composer bootstrap:booking`
   - Redis reachable when required
   - `php artisan schedule:work` running long enough for heartbeat freshness
3. Run the smallest runtime checks that prove the touched slice.
4. Separate runtime proof from automated-test proof in the final report.

## Guardrails

- A green `php artisan test` result does not prove runtime health
- Do not claim runtime smoke was performed if Redis, scheduler, or MySQL prerequisites were unavailable
- When runtime checks fail, report which prerequisite or command blocked confidence
