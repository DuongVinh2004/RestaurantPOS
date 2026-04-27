# API Mutation Contract

Last reviewed: 2026-04-27.

This runbook is the human go/no-go checklist for staff and admin mutation safety. It is not a replacement for generated artifacts. When API routes, middleware, FormRequests, capabilities, or consumer artifacts change, refresh and review these sources together:

- Runtime route inventory: `php artisan route:list --path=api`
- Route gate: `php artisan booking:route-gate --json`
- Staff capability map: `config/staff_capabilities.php`
- Idempotency middleware: `app/Http/Middleware/IdempotencyMiddleware.php`
- Row-version FormRequests under `app/Modules/*/Http/Requests`
- Generated mutation matrix: `build/api-consumer/mutation-contracts.md`
- Consumer artifact workflow: `docs/runbooks/api-consumer-artifacts.md`

Do not hand-edit files under `build/api-consumer`. Regenerate them with `composer api:artifacts` when the API contract itself changes.

## Route Cleanup Status

The 2026-04-27 route inventory check found the staff order read routes on the guarded read path:

| Route | Routed action | Guarded path |
| --- | --- | --- |
| `GET api/v1/staff/orders/{order_id}` | `OrderReadController@show` | `StaffOrderReadService::findOrder` |
| `GET api/v1/staff/tables/{table_id}/active-order` | `OrderReadController@showActiveByTable` | `StaffOrderReadService::findActiveOrderByTable` |
| `GET api/v1/staff/reservations/{reservation_id}/active-order` | `OrderReadController@showActiveByReservation` | `StaffOrderReadService::findActiveOrderByReservation` |
| `GET api/v1/staff/reservations/{reservation_id}/orders` | `ReservationOrderController@indexByReservation` | `StaffOrderReadService::listOrdersByReservation` |

No route should point at `ReservationOrderController@show`. That legacy direct model read was removed because it was unrouted and bypassed the newer staff actor and branch-scoped read service.

## Common Failure Contract

Use these response rules when reading the table:

- Missing `Idempotency-Key` on a required scope returns `422 idempotency_key_required` with `category_code=validation_error` and `state_reason=missing_idempotency_key`.
- Same key and same payload replays the cached successful response with `Idempotency-Replayed: true`.
- Same key with a different payload returns `409 idempotency_conflict`, `category_code=idempotency_conflict`, `conflict_type=idempotency_payload_mismatch`, and `replay_state=payload_mismatch`.
- Same key while the first request is still pending returns `409 idempotency_in_progress`, `category_code=idempotency_conflict`, and `replay_state=in_progress`.
- Missing required `row_version` is request validation and returns `422 validation_error`.
- Stale `row_version` returns `409 stale_row_version`, `category_code=stale_write`, `conflict_type=stale_write`, and `state_reason=row_version_mismatch`.
- Missing or insufficient staff capability returns `403 forbidden` with `category_code=forbidden_capability` and `required_capability`.

Idempotency identity is actor-scoped first: authenticated user, then resolved staff actor, then customer session, then IP. The route fingerprint uses the canonical route path, so declared aliases such as `close`, `checkout`, voucher `release`, and loyalty `release` replay against their canonical route.

## Mutation Matrix

| Method/path | Required capability | Requires `Idempotency-Key` | Idempotency scope | Requires `row_version` | Expected stale response | Expected replay response | Expected conflict response |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `PATCH api/v1/reservations/{id}/status` | `reservation.manage` | Yes | `staff.reservation-status` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; branch-scope or status-policy denials are `422 validation_error` or `403 forbidden` according to the guard that fails first |
| `POST api/v1/staff/reservations/{id}/check-in`<br>`POST api/v1/staff/reservations/{id}/timeline/actions/check-in` | `reservation.manage` | Yes | `staff.reservation-checkin` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict` or `409 idempotency_in_progress`; business-rule denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{id}/reschedule` | `reservation.manage` | Yes | `staff.reservation-reschedule` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; schedule policy denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{id}/move-table` | `reservation.manage` | Yes | `staff.reservation-move-table` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; table/branch policy denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{id}/assign-table`<br>`POST api/v1/staff/reservations/{id}/timeline/actions/assign-suggested` | `reservation.manage` | Yes | `staff.reservation-assign-table` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; unavailable table denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{id}/assign-best-fit`<br>`POST api/v1/staff/reservations/{id}/timeline/actions/assign-best-fit` | `reservation.manage` | Yes | `staff.reservation-assign-best-fit` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; no-fit denials are `422 validation_error` |
| `POST api/v1/staff/tables/{table_id}/release` | `table.release` | Yes | `staff.table-release` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; occupied-state denials are `422 validation_error` |
| `POST api/v1/staff/service-sessions/walk-in` | `reservation.manage` | Yes | `staff.service-sessions.walk-in` | No | n/a | `201` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; branch/table denials are `422 validation_error` |
| `POST api/v1/staff/tables/{table_id}/orders` | `order.manage` | Yes | `staff.table-orders` | Yes, body `row_version` | `409 stale_row_version` | `201` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; branch/reservation denials are `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/items` | `order.manage` | Yes | `staff.order-items` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; terminal order denials are `422 validation_error` |
| `PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}` | `order.manage` | Yes | `staff.order-item.update` | Yes, body `order_row_version` and item `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; item/order state denials are `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status` | `order.manage` | Yes | `staff.order-item.status` | Yes, body `order_row_version` and item `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; invalid transition denials are `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/kitchen/dispatch` | `order.manage` | Yes | `staff.kitchen.dispatch` | Yes, order body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; unroutable items can be reported in success metadata |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/fire` | `kitchen.manage` | Yes | `staff.kitchen.fire` | Yes, ticket body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; invalid transition denials are `422 validation_error` |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/bump` | `kitchen.manage` | Yes | `staff.kitchen.bump` | Yes, ticket body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; invalid transition denials are `422 validation_error` |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/recall` | `kitchen.manage` | Yes | `staff.kitchen.recall` | Yes, ticket body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; invalid transition denials are `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/bill-snapshot`<br>`POST api/v1/staff/orders/{order_id}/close` | `order.manage` | Yes | `staff.order-close` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; terminal order denials are `422 validation_error` |
| `GET api/v1/staff/orders/{order_id}/settlement-preview` | `settlement.manage` | No | n/a | No | n/a | n/a | Missing order or branch scope is `404 not_found`; validation stays `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/settlement/finalize`<br>`POST api/v1/staff/orders/{order_id}/checkout` | `settlement.manage` | Yes | `staff.checkout` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; cashier/payment denials are `422 validation_error` |
| `POST api/v1/staff/orders/{order_id}/pay` | `settlement.manage` | Yes | `staff.order-pay` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; cashier/payment denials are `422 validation_error` |
| `GET api/v1/staff/reservations/{reservation_id}/refund-preview` | `payment.refund` | No | n/a | No | n/a | n/a | Refund policy denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{reservation_id}/refund` | `payment.refund` | Yes | `staff.reservation-refund` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; refund amount/source denials are `422 validation_error` |
| `POST api/v1/staff/reservations/{reservation_id}/refund-cancel` | `payment.refund` | Yes | `staff.reservation-refund-cancel` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; cancel/refund policy denials are `422 validation_error` |
| `POST api/v1/staff/cashier/shifts/open` | `cashier.shift.manage` | Yes | `staff.cashier-shift.open` | No | n/a | `201` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; already-open shift denials are `422 validation_error` |
| `POST api/v1/staff/cashier/shifts/{shift_id}/close` | `cashier.shift.manage` | Yes | `staff.cashier-shift.close` | Yes, body `row_version` | `409 stale_row_version` | `200` with `Idempotency-Replayed: true` | `409 idempotency_conflict`; shift/cash-count denials are `422 validation_error` |
| `POST api/v1/admin/settings/branches/import` | `settings.manage` | Commit mode only | `admin.branches.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |
| `POST api/v1/admin/restaurant/tables/import` | `settings.manage` | Commit mode only | `admin.tables.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |
| `POST api/v1/admin/menu/categories/import` | `menu.manage` | Commit mode only | `admin.menu-categories.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |
| `POST api/v1/admin/menu/items/import` | `menu.manage` | Commit mode only | `admin.menu-items.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |
| `POST api/v1/admin/menu/prices/import` | `menu.manage` | Commit mode only | `admin.prices.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |
| `POST api/v1/admin/benefits/vouchers/import`<br>`POST api/v1/admin/benefits/loyalty-tiers/import` | `voucher.master_data.manage` | Commit mode only | `admin.master-data.import`, required only when `mode=commit` | No | n/a | Commit success with `Idempotency-Replayed: true`; dry run is not cached | `409 idempotency_conflict`; import validation returns `422 validation_error` |

## Follow-Up Watchlist

- Expand `config/api_artifacts.php` `mutation_contract.groups` when the generated `build/api-consumer/mutation-contracts.md` should include the newer floor-operation, order-item, refund, and admin import lanes. This runbook documents them now because they are live production mutation routes.
- Keep legacy public aliases explicit. Do not remove `close`, `checkout`, voucher `release`, loyalty `release`, or `table-board` without route inventory, OpenAPI, SDK, staff-web, and smoke-test updates.
- If a mutation route is added without both a staff capability and an idempotency decision, block release until the table above is updated and the route gate or contract tests cover it.
