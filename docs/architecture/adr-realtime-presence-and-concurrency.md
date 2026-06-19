# ADR: Realtime Presence and Concurrency

## Context
In a busy restaurant, multiple staff members may view or interact with the same table, reservation, or order concurrently. We must prevent silent data overwrites (e.g., two staff members adding items to the same bill) without introducing rigid, brittle pessimistic locks that could stall operations if a staff device disconnects.

## Decision
1. **Advisory Presence**: We will implement realtime presence indicators (e.g., "Staff A is viewing this table", "Staff B is checking out") via WebSockets.
2. **Not Authorization**: Presence is strictly an advisory signal to aid collaboration. It is NOT an authorization mechanism or a source of truth for locks.
3. **Optimistic Concurrency Control (OCC)**: All critical business aggregates (Orders, Reservations, Bills) will use `row_version` (or equivalent version tokens).
4. **Server is Authoritative**: When a client mutates an aggregate, it must pass the `row_version`. The server validates it. If it doesn't match, the server rejects the request with a conflict envelope.
5. **Conflict Resolution**: Upon receiving a conflict, the client does not silently merge. It prompts the user with a conflict resolution UI to reload the latest state or retry manually.

## Consequences
- Requires robust error handling for 409 Conflict responses across the staff web app.
- Ensures data integrity without locking tables when staff devices go offline or crash.
