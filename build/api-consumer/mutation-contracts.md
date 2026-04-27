# RestaurantPOS Mutation Contract Matrix

Generated from `storage/app/booking_release/openapi-v1.json` by:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

Use this file to answer:

- which mutation requires `row_version`
- which mutation requires `Idempotency-Key`
- which mutation still depends on customer session context / `X-Session-Id`
- when FE should expect `401`, `403`, `409`, or `422`

Deprecated alias routes are intentionally omitted. Use canonical routes only.

## Legend

- `SDK`: route is in the generated TypeScript SDK and the frozen spec marks it as full-contract.
- `SDK (fallback)`: the SDK exposes a method, but the frozen spec still marks the route as fallback. Treat request constraints as useful, but do not assume a fully-endorsed FE response/error contract yet.
- `OpenAPI`: route is full-contract in the frozen spec but not curated into the generated SDK batch.
- `OpenAPI (fallback)`: route is only discoverable through the frozen spec today. Promote it to full-contract before treating it as a stable FE surface.
- When the session column mentions `session_id`, keep sending `X-Session-Id` for session-owned access and also send the documented request field while the current validator still checks it.

## Customer availability + table holds

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/table-holds` | `SDK` | `customer_session` | No | `Required` | X-Session-Id accepted; body.session_id required | No | No | idempotency conflict/replay | validation / missing Idempotency-Key / missing session_id |
| `PATCH api/v1/table-holds/{hold_id}/refresh` | `SDK` | `customer_or_staff` | body.row_version optional | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | authorization boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch |
| `DELETE api/v1/table-holds/{hold_id}` | `SDK` | `customer_or_staff` | query.row_version optional | `Required` | X-Session-Id accepted; query.session_id optional | missing customer/staff auth or session | authorization boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch |

## Customer reservation + preorder + deposit + bill payment

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/reservations` | `SDK` | `customer_or_staff` | No | `Required` | X-Session-Id accepted; body.session_id required with hold_id | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / missing session_id |
| `POST api/v1/reservations/{id}/cancel` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/reschedule` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `PUT api/v1/reservations/{id}/preorder` | `SDK` | `customer_or_session` | body.row_version required; body.pre_order_row_version conditional | `Required` | X-Session-Id accepted | missing customer auth or session | ownership/session boundary | No | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `DELETE api/v1/reservations/{id}/preorder` | `SDK` | `customer_or_session` | query.row_version required; query.pre_order_row_version conditional | `Required` | X-Session-Id accepted | missing customer auth or session | ownership/session boundary | No | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/deposit/acknowledge` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/deposit/intent` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/deposit/intent/revoke` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/deposit/payment-sessions` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/bill/payment-sessions` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm` | `SDK` | `customer_or_staff` | body.row_version required | `Required` | X-Session-Id accepted; body.session_id optional | missing customer/staff auth or session | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Customer waiting list

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/waiting-list` | `SDK` | `customer_access_token` | No | `Required` | No | missing/invalid X-Customer-Token | No | idempotency conflict/replay | validation / missing Idempotency-Key |
| `POST api/v1/waiting-list/{id}/accept` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/waiting-list/{id}/confirm-arrival` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/waiting-list/{id}/decline` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/waiting-list/{id}/cancel` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | No | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Customer benefits

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/reservations/{id}/voucher/apply` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/voucher/remove` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/loyalty/redeem` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/reservations/{id}/loyalty/redeem/release` | `SDK` | `customer_access_token` | body.row_version required | `Required` | No | missing/invalid X-Customer-Token | ownership/session boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Customer privacy

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/me/privacy-requests` | `SDK` | `customer_access_token` | No | `Required` | No | missing/invalid X-Customer-Token | authorization boundary | No | validation / missing Idempotency-Key |

## Staff waiting list

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/staff/waiting-list/{id}/notify` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/waiting-list/{id}/seat` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Staff order + checkout + cashier core

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/staff/reservations/{id}/check-in` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/tables/{table_id}/orders` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/orders/{order_id}/items` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/orders/{order_id}/bill-snapshot` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/orders/{order_id}/settlement/finalize` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/cashier/shifts/open` | `SDK` | `staff_api_key` | No | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key |
| `POST api/v1/staff/cashier/shifts/{shift_id}/close` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Staff kitchen core

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `POST api/v1/staff/orders/{order_id}/kitchen/dispatch` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/fire` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/bump` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |
| `POST api/v1/staff/kitchen/tickets/{ticket_id}/recall` | `SDK` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | capability/branch boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |

## Admin branch update

| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |
|---|---|---|---|---|---|---|---|---|---|
| `PATCH api/v1/admin/settings/branches/{id}` | `OpenAPI` | `staff_api_key` | body.row_version required | `Required` | No | missing/invalid X-Staff-Key | staff capability boundary | idempotency conflict/replay | validation / missing Idempotency-Key / stale row_version mismatch / missing row_version |