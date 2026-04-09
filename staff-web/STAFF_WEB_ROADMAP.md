# Staff Web Roadmap

## Delivered In This Batch

- Session restore/refresh semantics that only expire on `401`
- Granted-capability protected navigation (`known_capabilities` kept as metadata only)
- Table board + waiting notify/seat + check-in
- Order create + add items + order detail
- Bill snapshot + settlement preview + finalize
- Refund preview + refund + refund-cancel
- Cashier current/open/show/close
- Settlement canonical reservation lookup + reservation-order lookup before manual `order_id`
- Board/waiting background polling via change cursors with visibility-aware cadence
- Conversation inbox filters for `status`, `assignment_state`, and `q`
- Conversation inbox detail + take-over + note + guarded reply
- Live-backend smoke harness for auth, board, orders, settlement, refund, cashier, and conversations
- Shared error normalization for `error_code`, `required_capability`, `request_id`, `errors`, and `details.errors`
- Row_version conflict detection aligned to runtime `422` payload semantics
- Frontend test coverage for helper, auth/session, conversations, and operator mutation slices

## Deferred With Evidence

### Feature readiness bootstrap

- Evidence:
  - backend auth session now exposes capabilities
  - no separate staff readiness/feature bootstrap endpoint exists in current consumer artifacts
- Impact:
  - FE can gate by capability and route/action metadata
  - FE cannot yet preload branch feature readiness in one dedicated bootstrap call

### Rich reservation/order search

- Evidence:
  - current staff-web core now uses canonical reservation lookup for orders, refunds, and settlement
  - no dedicated cross-domain search page was added in this batch to avoid widening contract surface
- Impact:
  - core flows work
  - lookup UX is still page-scoped rather than one shared operational search surface
  - some historical cases can still fall back to manual IDs when capability or backend data availability blocks lookup

## Recommended Next Batch

1. Add richer reservation/order search only if backend exposes a tighter focused contract than current page-scoped lookup.
2. Add stronger live smoke fixtures for safe mutation targets in staging so settlement/refund/cashier writes can run with less manual gating.
3. Add conversation assignment-centric views only if the inbox slice becomes operationally heavy.
