# Codex Batch 00 Baseline

## Intent

Create a safe baseline before production-readiness fixes. This note records the dirty worktree state observed before this batch added any file, the commands run, and the paths later batches must preserve.

## Current HEAD

- `git rev-parse --short HEAD`: `8b0dee5c`

## Initial dirty worktree

The initial `git status --short --untracked-files=all` output had no untracked files. All dirty paths below existed before Batch 00, except this baseline note.

### Source, Config, Script, And Test Changes

- `app/Http/Middleware/RequireRedisCacheMiddleware.php`
- `app/Modules/InventoryProcurement/Application/UseCases/Inventory/InventoryStockMovementService.php`
- `app/Modules/InventoryProcurement/Application/UseCases/Inventory/OrderItemInventoryConsumptionService.php`
- `app/Modules/KitchenDispatch/Application/Actions/DispatchKitchenOrderAction.php`
- `app/Modules/KitchenDispatch/Application/Workflows/KitchenRoutingService.php`
- `app/Modules/KitchenDispatch/Http/Controllers/Staff/KitchenDispatchController.php`
- `app/Modules/KitchenDispatch/Http/Requests/Staff/DispatchKitchenTicketRequest.php`
- `app/Platform/ApiContract/Services/ApiContractMetadataRegistry.php`
- `app/Platform/Health/Services/BookingDoctorService.php`
- `app/Support/ApiErrorCategory.php`
- `phpunit.xml`
- `routes/api/ops_release.php`
- `scripts/ci/booking-full-gate.sh`
- `tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php`
- `tests/Feature/Auth/StaffProductAuthHttpFlowTest.php`
- `tests/Feature/Console/BookingDoctorCommandTest.php`
- `tests/Feature/Http/ApiDependencyOutageEnvelopeTest.php`
- `tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php`
- `tests/Feature/Payments/PaymentProviderWebhookRouteSurfaceTest.php`
- `tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php`
- `tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php`
- `tests/Feature/Staff/StaffOrderSettlementWorkflowCharacterizationTest.php`
- `tests/Feature/Staff/StaffServiceSessionHttpFlowTest.php`
- `tests/Unit/Config/StaffCapabilityRouteInventoryContractTest.php`
- `tests/Unit/Infrastructure/DatabaseReleaseContractArtifactSyncTest.php`
- `tests/Unit/Services/Inventory/InventoryStockMovementServiceTest.php`

### Generated Release And API Artifacts

- `build/api-consumer/mutation-contracts.md`
- `build/api-consumer/postman/RestaurantPOS.postman_collection.json`
- `build/api-consumer/postman/RestaurantPOS.uat.postman_environment.json`
- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `storage/app/booking_release/openapi-v1.json`
- `storage/app/booking_release/release_manifest_snapshot.json`

### Cache/Test Artifacts

- `storage/phpstan/resultCache.php` was `MM`: staged and also modified in the worktree.

### Unknown User Documentation Changes

- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-api-contract.md`
- `docs/runbooks/booking-deploy-runbook.md`

## Files Later Batches Must Not Overwrite Blindly

- Treat every initial dirty path listed above as user/audit-owned unless a later batch explicitly claims it.
- Do not regenerate or overwrite `build/api-consumer/*` or `storage/app/booking_release/*` unless the batch owns API/release artifacts and records the drift.
- Do not normalize, delete, or restage `storage/phpstan/resultCache.php`; it already had both staged and unstaged changes at baseline.
- Treat `routes/api/ops_release.php` as a shared route contract file and preserve existing route behavior unless a later batch explicitly changes it.
- Preserve the unknown documentation changes under `docs/runbooks/` unless a later batch explicitly edits those runbooks.

## Commands Run

- `git rev-parse --short HEAD`
  - Exit code: `0`
  - Output: `8b0dee5c`
- `git status --short`
  - Exit code: `0`
  - Result: dirty; categories listed above.
- `php artisan route:list --path=api`
  - Exit code: `0`
  - Exact summary line: `Showing [236] routes`
- `git status --short --untracked-files=all`
  - Exit code: `0`
  - Result: dirty; no untracked files before this note.
- `git diff --cached --name-status`
  - Exit code: `0`
  - Result: staged paths matched the initial dirty categories above.
- `git diff --name-status`
  - Exit code: `0`
  - Output: `M storage/phpstan/resultCache.php`
- `git ls-files --others --exclude-standard`
  - Exit code: `0`
  - Output: empty.
- `python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py`
  - Exit code: `0`
  - Result: included staged and unstaged changes; recommended targeted verification plus route, API contract, ops release, runtime, and domain gates.

## Verification Results

- `vendor/bin/pint --test`
  - Exit code: `0`
  - Exact output: `{"result":"pass"}`
- `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
  - Exit code: `0`
  - Exact result: `[OK] No errors`
  - Exact note: `Note: Using configuration file C:\Users\Duong Vinh\RestaurantPOS-Laravel\phpstan.neon.dist.`
- `php artisan booking:route-gate --json`
  - Exit code: `0`
  - Exact key result: `"ok": true`
  - Exact summary: `"route_count": 240`, `"expected_route_count": 236`, `"error_count": 0`, `"warning_count": 0`
  - Exact generated time: `"generated_at_utc": "2026-04-26T11:24:25+00:00"`
- `php artisan booking:release-manifest --json`
  - Exit code: `0`
  - Exact key result: `"ok": true`, `"status": "ok"`, `"issues": []`
  - Exact patch summary: `"count": 55`, `"required_count": 48`, `"missing": []`
  - Exact generated time: `"generated_at_utc": "2026-04-26T11:24:30+00:00"`
  - Exact report paths:
    - `storage/app/booking_release/release_manifest/reports/booking-release-manifest-snapshot-20260426t112430z.json`
    - `storage/app/booking_release/release_manifest/reports/booking-release-manifest-snapshot-20260426t112430z.md`
    - `storage/app/booking_release/release_manifest/reports/latest-snapshot.json`
    - `storage/app/booking_release/release_manifest/reports/latest-snapshot.md`
- `npm run verify:package`
  - Exit code: `0`
  - Exact final line: `decision=pass checked=52 missing=0 blocking_missing=0 advisory_missing=0 stale=0 blocking_stale=0 advisory_stale=0`

No command failed because of missing MySQL or Redis in this batch.
