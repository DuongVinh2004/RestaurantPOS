## Summary

Adds Order Lifecycle deep audit coverage for Staff Web and backend order state behavior.

### Covered

- Checked-in table/order setup
- Order creation/opening
- Add item
- Update item
- Void/remove item before dispatch
- Dispatch to Kitchen/KDS
- KDS state sync
- Duplicate add/dispatch idempotency guard
- Cancel order documented as not implemented
- Concurrent edit/stale state documented as skipped / needs test harness

### Important QA Note

The create-order setup uses an official backend API fallback when Staff Web branch context does not match the reservation/table branch. This is documented as `PASS_WITH_API_FALLBACK`, not a pure UI create-order pass.

Follow-up risk:
- Staff UI branch context switching should be polished so order creation can be driven fully through UI when the reservation/table belongs to a non-default branch.

### Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan test --filter=Order`
- `php artisan test --filter=Kitchen`
- `php artisan test --filter=Reservation`
- `cd staff-web && npx tsc --noEmit`
- `cd staff-web && npx playwright test --project=chromium e2e/order-lifecycle-deep-audit.spec.ts`

### Remaining Risks

- Cancel Order endpoint is not implemented.
- Concurrent edit / stale-state UI harness is not fully implemented.
- Create Order is covered with API fallback due to Staff Web branch context mismatch.
- This PR does not claim production readiness.

## Recommendation

READY TO MERGE WITH RISKS after CI passes.
