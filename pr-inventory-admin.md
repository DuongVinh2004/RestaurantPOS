## Summary

Completes the Inventory/Admin foundation across backend, staff-web UI, generated API artifacts, and QA audit coverage.

### Completed
- Ingredients CRUD
- Suppliers CRUD
- Purchase Orders foundation
- Purchase Receipts
- Stock Movement / Stock On Hand verification
- Recipe Management
- Row Version / Optimistic Concurrency
- Permission/API-level checks
- Inventory Playwright E2E rewritten as real assertions
- QA docs updated under `docs/qa/ui-business-flow-audit/`
- API artifacts regenerated and release manifest refreshed

### Stabilization
- Removed tracked Playwright screenshots/log/trace artifacts
- Added Playwright output folders to `.gitignore`
- Fixed Pint issues in standalone test scripts
- Fixed Playwright SPA navigation/auth state loss
- Hardened Ant Design/Vite selector handling
- Final Inventory Playwright suite passes 12/12

## Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan test --filter=AdminInventory`
- `php artisan test --filter=Purchasing`
- Staff-web unit/Vitest checks
- `cd staff-web && npx playwright test --project="chromium" e2e/inventory-admin-deep-audit.spec.ts`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- API contract/artifacts regenerated:
  - `php artisan booking:api-contract --write`
  - `php artisan booking:api-artifacts:generate`
  - `php artisan booking:release-manifest --write`

## Remaining Risks

- Import/export remains not implemented.
- Inventory foundation is ready for merge, but this PR does not claim full production readiness.
- Further UAT is still needed for real operator usage and edge cases.

## Recommendation

READY TO MERGE WITH RISKS after CI passes.
