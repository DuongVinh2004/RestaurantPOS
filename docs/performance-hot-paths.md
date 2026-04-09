# Performance Hot Paths

## Scope

This batch audited the highest-value read-heavy paths first, with query-count evidence captured in feature tests instead of ad hoc guessing.

For live load/burst/soak/fault evidence, use the canonical runbook in [booking-performance-verification.md](C:\Users\Duong Vinh\RestaurantPOS-Laravel\docs\runbooks\booking-performance-verification.md). This document remains the local query-budget and hotspot-analysis companion, not the staging verification gate.
For limited-production evidence, archive the matching `booking:performance-verify` report and reference it as `performance_verification_report` in the launch-readiness manual evidence JSON.

Audited hot paths:

- `GET /api/v1/staff/tables/board`
- `GET /api/v1/staff/reservations/timeline`
- `GET /api/v1/reservations/{reservation_id}/active-order`
- `GET /api/v1/reservations/{reservation_id}/bill-preview`

Reviewed but intentionally not optimized in this batch:

- table availability / hold mutation internals
- waiting-list queue reads
- checkout settlement read models
- reporting rebuild flows

Those paths are still worth a second pass, but the query-shape regressions on board/timeline/bill preview were materially larger and lower risk to fix first.

## Measurement Harness

The baseline and regression guard use:

- [tests/Support/ProfilesDatabaseQueries.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\tests\Support\ProfilesDatabaseQueries.php)
- [tests/Feature/Performance/HotPathPerformanceBudgetTest.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\tests\Feature\Performance\HotPathPerformanceBudgetTest.php)

The harness records:

- total SQL query count
- summed SQL execution time reported by Laravel
- wall-clock request duration
- top repeated query patterns for hotspot diagnosis

The most reliable guard here is query count. Wall-clock timings on the SQLite-backed test harness are still useful, but noisier.

## Before / After

Measured on the same harness and seed scenario.

| Endpoint | Before queries | After queries | Before SQL ms | After SQL ms | Before wall ms | After wall ms | Notes |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| `staff/tables/board` | 462 | 18 | 20.16 | 1.71 | 283.14 | 178.51 | Biggest win. Removed reservation x table branch/conflict loops from query path. |
| `staff/reservations/timeline` | 191 | 17 | 6.71 | 1.10 | 69.87 | 43.21 | Candidate preview now reuses batched table search context. |
| `reservations/{id}/bill-preview` | 37 | 14 | 1.46 | 0.97 | 28.71 | 29.07 | Query count dropped sharply; wall time is roughly flat/noisy on SQLite. |

`active-order` did not have a separate baseline captured before the refactor because it previously shared the same heavy read path as bill preview. It now has its own guard with a query budget of `<= 10`.

## Applied Optimizations

### Staff board / timeline

- Batched candidate-table conflict loading in [StaffTableBoardService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffTableBoardService.php) instead of per-reservation/per-table conflict queries.
- Reused batched candidate preview in [StaffReservationTimelineService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffReservationTimelineService.php).
- Switched read-only branch compatibility checks to in-memory validation in:
  - [ReservationBranchScopeService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Branch\ReservationBranchScopeService.php)
  - [StaffCheckInReadinessService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffCheckInReadinessService.php)
  - [StaffOrderReadService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffOrderReadService.php)
- Timeline workbench now reuses precomputed check-in window data instead of re-reading settings in [StaffReservationTimelineWorkbenchService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Staff\StaffReservationTimelineWorkbenchService.php).
- [RuntimeSettingService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\RuntimeSettingService.php) now memoizes resolved values in-process, so repeated reads of the same setting key do not keep hitting SQL when cache storage is unavailable or null-valued.

### Customer bill / active order reads

- Split the light `active-order` read path from the heavier bill-preview path in [CustomerReservationOrderBillService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\Customer\CustomerReservationOrderBillService.php).
- Reused one hydrated reservation model for payments, tables, voucher, and active-order reads instead of reloading the same reservation graph multiple times.
- Reused one computed bill snapshot across active-order settlement totals and bill preview.
- Replaced order snapshot eager-loading in [ReservationFinancialSyncService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\ReservationFinancialSyncService.php) with a lean joined item query.
- Added a lightweight loyalty preview path in [LoyaltyPointsService.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\app\Services\LoyaltyPointsService.php) so bill preview no longer loads full loyalty transaction history or customer tier summary just to render redeemability flags.

## Guard Rails

Current regression budgets in [HotPathPerformanceBudgetTest.php](C:\Users\Duong Vinh\RestaurantPOS-Laravel\tests\Feature\Performance\HotPathPerformanceBudgetTest.php):

- board candidate preview: `<= 22` queries
- timeline candidate preview: `<= 19` queries
- customer active-order read: `<= 10` queries
- customer bill preview: `<= 16` queries

These are intentionally tight enough to catch N+1 regressions, while still leaving a small amount of room for harmless support queries.

## Deferred Work

Not done in this batch on purpose:

- response caching for board/availability/timeline
  - Risk: invalidation must track reservations, holds, move-table, release, checkout, and scheduler mutations. Wrong invalidation here is worse than a slower read.
- write-path lock changes for checkout / settlement
  - Risk: correctness and concurrency matter more than shaving a small number of reads.
- reporting snapshot rebuild parallelization or materialization
  - Risk: broader architectural change, needs separate profiling on realistic data volume.
- collapsing legacy runtime setting aliases
  - Risk: changes operational config behavior. The SQL cost is now low enough that compatibility is more important.

## Next Recommendations

Second-pass candidates, in order:

1. Profile waiting-list queue and staff inbox with the same query-pattern harness.
2. Profile checkout settlement read paths separately from write locks.
3. Profile reporting rebuild with production-like data volume and add index evidence before changing schema.
4. Consider targeted composite indexes for overlap-heavy reads only after capturing actual slow query plans on the real database engine.
