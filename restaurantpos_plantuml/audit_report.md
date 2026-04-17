# Diagram correction audit (v9)

Applied corrections:
1. Synced bundle metadata to the real inventory, including overview diagrams and newly added cashier-shift diagrams.
2. Added dedicated cashier-shift behavioral coverage with `UC-23`, `AD-32`, and `SD-24`.
3. Rewrote stale sequence diagrams to actual controller and service names used by the repo in auth, payments, waiting list, floor ops, kitchen, notifications, and ops.
4. Fixed `CD-12` and `ERD-12` to the SQL-first notification schema (`outbox_id`, `attempt_id`, `provider_key`, `status`, `next_retry_at`, quiet-hour columns).
5. Removed technical pseudo-use-cases from the weakest use case diagrams and moved those concerns into runtime notes and constraints.
6. Split broad activity diagrams into clearer business branches for waiting list staff flow, board operations, settlement, inventory, ops readiness, notifications, and cashier shift.

Remaining intentional limitations:
- class diagrams remain hybrid domain + database diagrams, not recovered OO class models from PHP source
- sequence diagrams stay code-bound at route, controller, and service level; they do not claim one-to-one recovery of every internal method call
- ERD diagrams remain domain-sliced rather than a single full physical ERD
