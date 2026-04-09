# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md` (Task B)
- `docs/runbooks/uat-demo-scenario-pack.md`

## Code hotspots

- `app/Services/ReservationService.php`
- `app/Services/Reservation/ReservationCreateService.php`
- `app/Services/Reservation/ReservationConflictValidator.php`
- `app/Services/Reservation/ReservationTableAssignmentService.php`
- `app/Services/Reservation/ReservationStatusTransitionService.php`
- `app/Services/TableAvailabilityService.php`
- `app/Services/TableHoldService.php`
- `app/Services/TableTimeConflictService.php`
- `app/Services/RestaurantTableStateService.php`
- `app/Services/Staff/StaffTableBoardService.php`
- `app/Services/Staff/StaffReservationBoardAssignmentService.php`
- `app/Services/Staff/StaffCheckInService.php`
- `app/Services/Staff/StaffMoveTableService.php`
- `app/Services/Staff/StaffTableReleaseService.php`

## Test surface

- `tests/Feature/Reservation/*`
- `tests/Feature/Table/*`
- `tests/Feature/Staff/StaffTableBoard*.php`
- `tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php`
- `tests/Feature/Staff/StaffCheckInFlowTest.php`
- `tests/Feature/Staff/StaffMoveTableFlowTest.php`
- `tests/Feature/Staff/StaffTableRelease*.php`
- `tests/Feature/Staff/StaffFrontOfHouseOperationalClosureFlowTest.php`
- `tests/Feature/Services/RestaurantTableStateServiceRowVersionTest.php`
- `tests/Feature/Services/TableHoldServiceRowVersionTest.php`

## Questions to answer before patching

- Which lock or guard prevents double assignment?
- What happens on stale `row_version` or branch drift?
- Is the hold or assignment path idempotent already?
- Which feature or unit test proves the negative path?
