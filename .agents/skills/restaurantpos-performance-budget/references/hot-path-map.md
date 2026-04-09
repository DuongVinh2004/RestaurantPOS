# Hot Path Map

## Docs

- `docs/performance-hot-paths.md`
- `docs/runbooks/booking-performance-verification.md`

## Measurement harness

- `tests/Support/ProfilesDatabaseQueries.php`
- `tests/Feature/Performance/HotPathPerformanceBudgetTest.php`
- `tests/Feature/Console/BookingPerformanceVerifyCommandTest.php`

## Current read-heavy code hotspots

- `app/Services/Staff/StaffTableBoardService.php`
- `app/Services/Staff/StaffReservationTimelineService.php`
- `app/Services/Staff/StaffReservationTimelineWorkbenchService.php`
- `app/Services/Staff/StaffOrderReadService.php`
- `app/Services/CustomerReservationOrderBillService.php`
- `app/Services/ReservationFinancialSyncService.php`
- `app/Services/RuntimeSettingService.php`
