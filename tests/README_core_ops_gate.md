# Core operations canonical gate

This repository now treats the core operations gate as the high-signal regression net for the existing booking / floor flows.
The source-of-truth suite definition lives in `tests/fixtures/core_ops_gate_suite.json`.

## What it covers

The gate is intentionally scoped to flows that later cumulative patches are likely to break:

- table availability filtering and suggestion behavior
- table hold create / show / refresh / cancel contracts
- reservation create / show / status mutations
- staff floor mutations: check-in, reschedule, move-table, release table
- waiting-list notify / seat / cancel lifecycle

## Canonical command

Run the gate and persist a machine-readable snapshot:

```bash
php artisan booking:core-ops-gate --write
```

JSON output for CI / release evidence:

```bash
php artisan booking:core-ops-gate --write --json
```

The persisted snapshot is written to:

- `storage/app/booking_release/core_ops_gate_snapshot.json`

## Required suite members

At minimum the canonical gate includes:

- `tests/Feature/Table/TableAvailabilityFeatureTest.php`
- `tests/Feature/Table/TableHoldHttpFlowTest.php`
- `tests/Feature/Reservation/ReservationHttpFlowTest.php`
- `tests/Feature/Reservation/ReservationStatusHttpFlowTest.php`
- `tests/Feature/Staff/StaffCheckInFlowTest.php`
- `tests/Feature/Staff/StaffReservationRescheduleFlowTest.php`
- `tests/Feature/Staff/StaffMoveTableFlowTest.php`
- `tests/Feature/Staff/StaffTableReleaseHttpFlowTest.php`
- `tests/Feature/Staff/StaffWaitingListLifecycleTest.php`

## Release usage

Use this gate before landing further booking / floor-operation patches so the existing core contracts stay locked.
