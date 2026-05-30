# Runtime Notes

## Startup Commands
- `npm run runtime:up` (Starts MySQL, Redis, Backend, Scheduler)
- `npm run dev:all:reset` (Resets MySQL, restarts backend, boots customer/staff web servers cleanly and forces UAT seed)

## Bootstrap Result
- UAT scenario pack loaded. Database seeded with customer/staff/admin credentials and test tables.

## Health Checks
- `php artisan booking:doctor --json`: PASS (Completed in Batch 1)
- `php artisan notifications:outbox-health --json`: PASS (Completed in Batch 2)

## Known Limitations
- The QA audit is performed via automated Playwright tests constructed statically due to lack of manual visual interaction in this environment.
- Test coverage ensures primary routing, DOM presence, and API interaction are healthy, but layout/visual bugs (e.g. CSS overlaps) cannot be caught.
- Playwright tests interact with Ant Design components which are heavily reliant on portals, JS-based positioning, and internal wrappers. This causes flaky tests when trying to locate generic elements inside custom components (like `<input>` inside `InputNumber` or `.ant-select-item-option-content`). Prefer standard ARIA locators, keyboard navigation, or `placeholder` matching for best stability.
- Customer reservation API constraints (like min_lead_time_minutes) can block Playwright Setup flow if the exact time isn't calculated carefully. A better approach for Order Lifecycle testing is bypassing the full UI reservation flow and using an API setup to ensure deterministic preconditions.
- Cancel Order is currently NOT_IMPLEMENTED via a direct API endpoint.
- Row Version validations are strictly applied to all item and order mutations, providing solid concurrent edit protection (`422 Unprocessable Entity` on `stale_row_version`).

## Batch 11 — Checkout/Finance Notes (2026-05-30)

- Branch: `checkout-finance-complete-deep-audit`
- Walk-in session API (`POST /service-sessions/walk-in`) on branch 5 is the preferred SETUP_API_FALLBACK for checkout testing.
- `OrderSettlementWorkflow` uses `CashieringReplayRecorder` + Redis cache for idempotency; Redis must be up for duplicate payment protection to work.
- `POST /cashier/shifts/open` must be called before `POST /orders/{id}/pay` — backend checks for open cashier shift on branch.
- Refund endpoints operate on `reservation_id` not `order_id`. Walk-in creates a reservation internally; capture `reservation_id` from walk-in response.
- `POST /finance/invoices/{reservation_id}/issue` may return error if reservation is not in a finalized settlement state.
- `GET /finance/reconciliation/{reservation_id}` returns `net_paid_amount > 0` after successful cash payment — useful for smoke-testing payment linkage.
- Voucher/Loyalty: No seed data for walk-in reservations; tagged NEEDS_DATA. Bootstrap seed improvements needed for future audit.
- Cashier shift close API (`POST /cashier/shifts/{id}/close`) requires `actual_cash_amount` and `row_version`.
- Double-close attempt on a closed shift returns 4xx — guard confirmed.
- Finance permission guard: all 4 tested routes (settlement-preview, pay, refund, shift-close) return 401/403 when called without a valid `X-Staff-Key` header.

