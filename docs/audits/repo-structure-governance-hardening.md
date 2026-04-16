# Repo Structure Governance Hardening Audit

## What Changed

- Grouped `.gitignore` by environment, dependencies, build outputs, reviewed
  generated artifacts, runtime cache, smoke output, and local scratch files.
- Marked transient PHPStan cache, phar debug bundles, staff-web smoke output,
  nested staff-web smoke output, and staff-web test results as ignored.
- Trimmed unused imports from `routes/api.php` and `routes/api/*.php` without
  changing route definitions, route names, middleware, or controller targets.
- Let Pint normalize the touched route include indentation after import cleanup;
  route surface verification stayed green.
- Added concise ownership guidance in `docs/architecture/module-ownership.md`.
- Added future test placement guidance in `tests/README.md`.
- Added README anchors in `tests/Unit/Modules` and `tests/Unit/Platform` so
  the intended future test roots are tracked by Git.
- Added an incremental decomposition plan for the largest services in
  `docs/architecture/giant-service-decomposition-plan.md`.
- Replaced the stale root `structure.md` with a minimal current structure index.
- Added an ops command registration note so new operator behavior lands in
  `app/Platform` services instead of accumulating inside
  `routes/console/ops_release.php`.

## Intentionally Deferred

- No business logic was moved from legacy Laravel paths into modules.
- No module namespace rewrite was attempted.
- No large service was split in this batch.
- Existing tests were not bulk-moved into the new taxonomy.
- Root and staff-web package tooling were not redesigned.
- Reviewed generated contract artifacts were kept tracked.

## Transitional Legacy Zones

These paths still contain real code and may remain until their domains are
migrated deliberately:

- `app/Services`
- `app/Models`
- `app/Http/Controllers/Api`
- `tests/Unit/Services`
- legacy feature-test folders that predate module ownership

New domain work should not default to those paths when a module already owns the
workflow.

## Large Services Needing Future Decomposition

- `app/Modules/CheckoutPayments/Application/Services/StaffCheckoutService.php`
- `app/Modules/Conversations/Application/Services/StaffConversationWorkflowService.php`
- `app/Modules/FloorOps/Application/Services/StaffTableBoardService.php`
- `app/Modules/Notifications/Application/Services/NotificationOutboxService.php`

Use `docs/architecture/giant-service-decomposition-plan.md` when one of these
services is touched by a future behavior change.

## Transient Artifacts Ignored

- `storage/phpstan/cache`
- `tmp/phar-debug`
- `staff-web/tmp-smoke`
- `staff-web/test-results`
- `staff-web/staff-web/tmp-smoke`

The following generated artifacts remain intentional and tracked unless a
contract review says otherwise:

- `build/api-consumer`
- `storage/app/booking_release`

## Remaining Risks

- Route imports are cleaner, but route files remain large shared seams.
- `routes/console/ops_release.php` still contains a large amount of command
  registration code.
- Legacy services and models still own some menu, inventory, purchasing, auth,
  session, and staff reservation timeline behavior.
- SQLite-backed automated tests do not prove MySQL bootstrap, Redis, scheduler,
  or release readiness.

## Verification

- `vendor/bin/pint --test routes/api.php routes/api/admin.php routes/api/auth.php routes/api/customer_self_service.php routes/api/ops_release.php routes/api/staff_pos.php routes/console/ops_release.php`
- `php -l` on `routes/api.php`, the touched route include files, and `routes/console/ops_release.php`
- unused-import audit for `routes/api/*.php`
- `php artisan test tests/Feature/Infrastructure/ApiRouteSurfaceIntegrityTest.php`
- `php artisan route:list --path=api/v1`
- `vendor/bin/phpstan analyse routes/api.php routes/api routes/console/ops_release.php --level=0 --memory-limit=512M`
- `git check-ignore -v` spot-checks for the untracked transient directories

`vendor/bin/phpstan analyse` without paths is not usable in this repo today
because no default PHPStan path config exists.

## Recommended Next Steps

1. Keep transient artifacts untracked and verify new local output is ignored.
2. For the next inventory or purchasing change, introduce the target module
   ownership before adding new behavior.
3. For the next checkout, conversation, table board, or notification change,
   extract one cluster from the documented decomposition plan with focused
   tests.
4. Continue trimming route files only when behavior changes already require
   touching the route surface.
5. Add module-owned tests for new module behavior and avoid growing
   `tests/Unit/Services` unless a legacy service remains canonical.
