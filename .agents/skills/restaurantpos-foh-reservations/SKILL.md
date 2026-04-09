---
name: restaurantpos-foh-reservations
description: Harden front-of-house reservation, availability, hold, assignment, check-in, move-table, and release flows in RestaurantPOS. Use when Codex changes reservation services, table state orchestration, branch scoping, conflict detection, row_version handling, locking, or FOH regression tests.
---

# RestaurantPOS FOH & Reservations

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Trace the full chain: availability -> hold -> assignment -> check-in -> move-table -> release.
2. Validate branch scope, stale `row_version` handling, lock timing, overlap detection, idempotency, and release guards before changing code.
3. Keep orchestration inside services. Controllers and resources should stay thin.
4. Add regression tests for stale write, concurrent assignment, branch drift, and conflict paths.
5. If a change affects reservation or table schema assumptions, review SQL-first artifacts before finalizing.

## Guardrails

- Protect reservation state and table state from partial updates.
- Prefer targeted fixes in reservation and table services over broad refactors.
- Minimize changes to shared files such as `routes/api.php`, `config/booking.php`, and `database/schema/mysql-schema.sql`.
- Treat hold expiry, reservation locks, and no-show windows as runtime contracts, not UI hints.

## Verify

- `php artisan test tests/Feature/Reservation tests/Feature/Table`
- `php artisan test tests/Feature/Staff/StaffTableBoardHttpFlowTest.php tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php tests/Feature/Staff/StaffCheckInFlowTest.php tests/Feature/Staff/StaffMoveTableFlowTest.php tests/Feature/Staff/StaffTableReleaseServiceTest.php`
- Add unit or feature coverage for any new stale or concurrent path.
