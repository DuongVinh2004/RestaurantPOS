# Branch Scheduling Policy Resolution

## Intent

This phase adds branch-local operating calendar and booking policy primitives without introducing a full recurring calendar engine.

The foundation lives on `branches` through three nullable JSON attributes:

- `business_hours`
- `closure_windows`
- `booking_policy`

These fields store branch overrides. When a field is `null`, runtime falls back to `config('booking.branch_policy_defaults.*')`.

## Resolution Order

For any booking-time decision, policy resolves in this order:

1. Resolve effective branch from reservation tables, hold, waiting-list entry, or explicit `branch_id`.
2. Resolve branch timezone from `branches.timezone`.
3. Resolve branch policy payload from branch JSON columns.
4. If a branch JSON column is `null`, fall back to `booking.branch_policy_defaults.*`.
5. For nested booking-policy values that are not explicitly overridden, fall back to current global runtime config:
   - `booking.customer_reservation_cancellation_cutoff_minutes`
   - `booking.customer_reservation_reschedule_cutoff_minutes`
   - `booking.waiting_list_notify_hold_minutes`
   - `booking.waiting_list_service_minutes`
   - `booking.service_buffer_minutes`

This means branch-local values override global config. Null branch policy keeps current global behavior.

## Branch Rules In Phase 1

### Business hours

- Stored as day-of-week rows with one or more open periods.
- Supports same-day periods and overnight periods by using an end time earlier than start time.
- `24:00` is accepted as an end time.

### Special closures

- Stored as explicit local datetime windows:
  - `start_local`
  - `end_local`
  - `type` in `closure|holiday|blackout`
  - optional `reason`
- Evaluated in branch-local timezone.

### Booking policy

- `reservation.min_lead_time_minutes`
- `reservation.max_advance_time_minutes`
- `reservation.same_day_cutoff_time`
- `reservation.cancellation_cutoff_minutes`
- `reservation.reschedule_cutoff_minutes`
- `waiting_list.enabled`
- `waiting_list.notify_hold_minutes`
- `waiting_list.default_service_minutes`
- `availability.service_buffer_minutes`

## Integrated Flows

### Availability

- `GET /api/v1/tables/available`
- Resolves branch timezone and branch policy before calculating available tables.
- Applies:
  - business hours
  - closure / holiday / blackout windows
  - same-day cutoff
  - max advance window
  - branch-local service buffer
- Availability keeps realtime windows usable and does not reject them on zero-minute lead-time.

### Table holds

- `POST /api/v1/table-holds`
- Branch must match selected tables.
- Hold window must satisfy branch-local operating calendar and booking-window rules.

### Reservation create

- Shared reservation create flow now validates branch-local booking window after effective branch is resolved from tables and/or hold.
- Hold-based reservation create still checks branch consistency between hold and tables.

### Reservation reschedule

- Staff reschedule and customer self-service reschedule now validate:
  - branch scope
  - branch-local business hours
  - closure windows
  - lead / max-advance / same-day cutoff
- Customer self-service reschedule also keeps hold-conflict and table-conflict protection aligned with branch scope.

### Waiting list

- Customer and staff waiting-list create now require branch eligibility.
- Eligibility means:
  - waiting list enabled for that branch
  - branch is open at request time
  - request time is not inside a closure / blackout window
- Staff notify uses branch-local `notify_hold_minutes`.
- Staff seat uses branch-local `default_service_minutes`.

### Customer self-service visibility

- Reservation resource and self-service guards now use branch-local:
  - cancellation cutoff
  - reschedule cutoff

## Admin Surface

Admin branch create/update now accepts:

- `business_hours`
- `closure_windows`
- `booking_policy`

Branch resource returns those values, or resolved defaults when branch columns are null.

## Phase 1 Non-Goals

The following are intentionally not implemented yet:

- RRULE-style recurring holiday calendar management
- exception inheritance between branch groups / brands / regions
- separate staff-only override policy for lead-time or same-day cutoff
- capacity rules based on branch service mode, event calendar, or demand tiers
- per-channel policy splits such as online vs walk-in vs OTA
- automatic shortening of waiting-list notify holds to the next close boundary
- full audit diffing of nested policy JSON semantics

## Operational Notes

- Branch policy columns are override-only. Do not backfill global defaults into every branch row unless you explicitly want to freeze that branch away from future global fallback changes.
- All comparisons are performed in UTC after converting from branch-local policy definitions.
- Boundary tests were added for timezone conversion, same-day cutoff, max advance, closures, realtime availability, hold creation, reservation create/reschedule, and waiting-list eligibility.
