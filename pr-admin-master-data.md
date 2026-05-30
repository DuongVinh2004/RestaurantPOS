## Summary

Completes the Admin Master Data foundation across staff-web UI, typed API wrappers, Playwright E2E coverage, and QA documentation.

### Completed

- Branch create/update flows
- Zone list/rename flows
- Table create/update/delete foundation
- Kitchen station create/update flows
- Category route sync for KDS routing
- Tax profile read/update/restore flow
- Staff capability / permission audit
- Import/export verification where available
- Admin Master Data contract map and QA report
- Playwright E2E: `admin-master-data-deep-audit.spec.ts`

### Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- Backend targeted tests for Branch/Table/Kitchen/Tax/Capability areas
- `cd staff-web && npx tsc --noEmit`
- `cd staff-web && npx playwright test --project=chromium e2e/admin-master-data-deep-audit.spec.ts`

### Remaining Risks

- Table templates are read-only if backend only supports list.
- Import/export is limited to existing backend-supported endpoints.
- Broader UAT is still required.
- This PR does not claim production readiness.

## Recommendation

READY TO MERGE WITH RISKS after CI passes.
