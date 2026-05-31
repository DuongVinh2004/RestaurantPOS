## Summary

Completes Checkout/Finance deep audit coverage across settlement, payment, refund, cashier shift, reconciliation, and permission guard flows.

### Completed

- Settlement preview / final bill snapshot
- Cash payment safe path
- Duplicate payment / idempotency guard
- Payment state verification
- Refund preview and execution
- Over-refund guard
- Cashier shift close and double-close guard
- Reconciliation / invoice read flow
- Anonymous/unauthenticated permission guard
- Checkout/Finance contract map and QA report
- Playwright E2E: `checkout-finance-deep-audit.spec.ts`

### Notes

- Voucher and Loyalty flows are marked `NEEDS_DATA` because no stable seeded/mock data is currently available.
- Real MoMo/VNPay callback behavior is intentionally out of scope.
- This PR does not claim production readiness.

## Verification

- `php vendor/laravel/pint/builds/pint --test -v`
- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- Backend targeted tests for Checkout/Payment/Refund/Cashier/Voucher/Loyalty where available
- `cd staff-web && npx tsc --noEmit`
- `cd staff-web && npx playwright test --project=chromium e2e/checkout-finance-deep-audit.spec.ts`

## Remaining Risks

- Voucher/Loyalty requires seeded test data for full E2E validation.
- Real provider callback flows remain out of scope.
- Broader UAT is still required.

## Recommendation

READY TO MERGE WITH RISKS after CI passes.
