# RestaurantPOS TypeScript SDK Foundation

Generated from `storage/app/booking_release/openapi-v1.json` by:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

This is the official TypeScript convenience client for the curated priority frontend batch. The frozen OpenAPI artifact remains the only official schema source for the whole API surface.

## Contract consumption policy

| Need | Official source |
|---|---|
| TypeScript frontend work on the curated priority batch below | `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts` |
| FE enum/state values and semantic aliases such as checked-in reservation state | `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`, `build/api-consumer/enum-state-map.json` |
| Mutation requirements such as `row_version`, `Idempotency-Key`, `X-Session-Id`, and expected `403/409/422` handling | `build/api-consumer/mutation-contracts.md` |
| Typed client generation for another stack or for a full-contract route outside the curated batch | `storage/app/booking_release/openapi-v1.json` |
| Discovery of full-contract routes that are not in the SDK batch | `Reference` folder in `build/api-consumer/postman/RestaurantPOS.postman_collection.json` |
| Runtime or controller behavior investigation | Read backend code as implementation detail only, not as the consumer contract |

Do not treat controllers, resources, or ad-hoc route inspection as contract sources.

The SDK only guarantees method coverage for the curated priority batch listed below. Other full-contract routes stay in the frozen OpenAPI artifact and the generated `Reference` folder until they are curated into a later batch.

## Curated priority batch

### Auth

- POST api/v1/auth/customer/login
- GET api/v1/auth/customer/me
- POST api/v1/auth/customer/refresh
- POST api/v1/auth/customer/logout
- POST api/v1/auth/staff/login
- GET api/v1/auth/staff/me
- POST api/v1/auth/staff/refresh
- POST api/v1/auth/staff/logout

### Availability + Reservation

- GET api/v1/tables/available
- POST api/v1/table-holds
- GET api/v1/table-holds/{hold_id}
- PATCH api/v1/table-holds/{hold_id}/refresh
- DELETE api/v1/table-holds/{hold_id}
- GET api/v1/menu/categories
- GET api/v1/menu/items
- GET api/v1/menu/items/{id}
- POST api/v1/menu/preorder/preview
- POST api/v1/reservations
- GET api/v1/reservations
- GET api/v1/reservations/{id}
- POST api/v1/reservations/{id}/cancel
- POST api/v1/reservations/{id}/reschedule
- GET api/v1/reservations/{id}/preorder
- POST api/v1/reservations/{id}/preorder/preview
- PUT api/v1/reservations/{id}/preorder
- DELETE api/v1/reservations/{id}/preorder

### Deposit Self-Pay

- GET api/v1/reservations/{id}/deposit-preview
- POST api/v1/reservations/{id}/deposit/acknowledge
- POST api/v1/reservations/{id}/deposit/intent
- POST api/v1/reservations/{id}/deposit/intent/revoke
- POST api/v1/reservations/{reservation_id}/deposit/payment-sessions
- GET api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}
- POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh
- POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm

### Dine-In + Checkout

- GET api/v1/staff/menu/items
- GET api/v1/staff/tables/board
- GET api/v1/staff/tables/board/changes
- POST api/v1/staff/service-sessions/walk-in
- POST api/v1/staff/reservations/{id}/check-in
- POST api/v1/staff/tables/{table_id}/orders
- POST api/v1/staff/orders/{order_id}/items
- PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}
- POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status
- GET api/v1/reservations/{reservation_id}/active-order
- POST api/v1/staff/orders/{order_id}/bill-snapshot
- GET api/v1/reservations/{reservation_id}/bill-preview
- GET api/v1/reservations/{reservation_id}/bill
- POST api/v1/reservations/{reservation_id}/bill/payment-sessions
- GET api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}
- POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh
- POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm
- GET api/v1/staff/orders/{order_id}
- GET api/v1/staff/cashier/shifts/current
- POST api/v1/staff/cashier/shifts/open
- GET api/v1/staff/cashier/shifts/{shift_id}
- POST api/v1/staff/cashier/shifts/{shift_id}/close
- GET api/v1/staff/orders/{order_id}/settlement-preview
- POST api/v1/staff/orders/{order_id}/settlement/finalize

### Kitchen / KDS

- GET api/v1/staff/kitchen/changes
- GET api/v1/staff/kitchen/stations
- GET api/v1/staff/kitchen/stations/{station_id}/tickets
- POST api/v1/staff/orders/{order_id}/kitchen/dispatch
- POST api/v1/staff/kitchen/tickets/{ticket_id}/fire
- POST api/v1/staff/kitchen/tickets/{ticket_id}/bump
- POST api/v1/staff/kitchen/tickets/{ticket_id}/recall

### Staff Lookup

- GET api/v1/staff/reservations
- GET api/v1/staff/reservations/{reservation_id}
- GET api/v1/staff/reservations/{reservation_id}/orders
- GET api/v1/staff/cashier/shifts

### Operations Read Models

- GET api/v1/staff/audit-trail
- GET api/v1/staff/reporting/daily-sales
- GET api/v1/staff/reporting/daily-operations
- GET api/v1/staff/reporting/daily-inventory
- GET api/v1/admin/inventory/ingredients
- GET api/v1/admin/inventory/suppliers
- GET api/v1/admin/inventory/purchase-orders
- GET api/v1/admin/settings/branches

### Refunds

- GET api/v1/staff/reservations/{reservation_id}/refund-preview
- POST api/v1/staff/reservations/{reservation_id}/refund
- POST api/v1/staff/reservations/{reservation_id}/refund-cancel

### Waiting List

- GET api/v1/waiting-list
- POST api/v1/waiting-list
- GET api/v1/waiting-list/{id}
- GET api/v1/staff/waiting-list
- GET api/v1/staff/waiting-list/changes
- POST api/v1/staff/waiting-list/{id}/notify
- POST api/v1/waiting-list/{id}/accept
- POST api/v1/waiting-list/{id}/confirm-arrival
- POST api/v1/waiting-list/{id}/decline
- POST api/v1/waiting-list/{id}/cancel
- POST api/v1/staff/waiting-list/{id}/seat

### Benefits

- GET api/v1/me/loyalty
- GET api/v1/me/vouchers
- GET api/v1/reservations/{id}/benefits-preview
- POST api/v1/reservations/{id}/voucher/apply
- POST api/v1/reservations/{id}/voucher/remove
- POST api/v1/reservations/{id}/loyalty/redeem
- POST api/v1/reservations/{id}/loyalty/redeem/release

### Customer Privacy

- GET api/v1/me/data-export
- GET api/v1/me/privacy-requests
- POST api/v1/me/privacy-requests

### Admin Master Data

- GET api/v1/admin/restaurant/table-templates
- POST api/v1/admin/restaurant/tables
- POST api/v1/admin/menu/categories
- POST api/v1/admin/menu/items
- POST api/v1/admin/menu/items/{item_id}/prices

### Conversation Inbox

- GET api/v1/staff/conversations
- GET api/v1/staff/conversations/{conversation_id}
- POST api/v1/staff/conversations/{conversation_id}/take-over
- POST api/v1/staff/conversations/{conversation_id}/unassign
- POST api/v1/staff/conversations/{conversation_id}/internal-notes
- POST api/v1/staff/conversations/{conversation_id}/outbound-replies

### Payment Webhooks

- POST api/v1/payments/providers/{provider_code}/webhooks

### Health

- GET api/v1/health
- GET api/v1/health/detailed
- GET api/v1/health/redis

Usage sketch:

```ts
import { RestaurantPosClient } from './restaurantpos-sdk';

const client = new RestaurantPosClient({
  baseUrl: 'http://127.0.0.1:8000',
  customerToken: () => localStorage.getItem('customerToken') ?? undefined,
  customerSessionId: () => sessionStorage.getItem('customerSessionId') ?? undefined,
  staffApiKey: () => localStorage.getItem('staffApiKey') ?? undefined,
  staffCsrfToken: () => readCookie('staff_web_csrf') ?? undefined,
});

const login = await client.postV1AuthCustomerLogin({
  identifier: 'uat.customer.primary',
  password: 'UatDemo!123',
  session_label: 'web',
});
```

Limitations:

- On curated customer routes whose mutation contract requires session propagation, the generated client keeps `X-Customer-Token` and `X-Session-Id` together when both are configured.
- Staff refresh-cookie login/refresh/logout can opt into `credentials: 'include'`; refresh/logout also send `staffCsrfToken` as `X-Staff-CSRF` when provided.
- The SDK is intentionally scoped to the curated priority batch, not every full-contract or fallback endpoint.
- Enum/state exports are generated separately in `restaurantpos-enums.ts` and `enum-state-map.json` so FE can consume stable state values without inferring them from incidental payload strings.
- Response typing follows the frozen OpenAPI artifact. Routes still below contract-grade remain outside the official SDK batch and can stay coarse in the spec.
- No package manifest is emitted in this phase; consumers can vendor the generated file or wrap it in their own workspace.

For the full immutable release path, use `php artisan booking:release-build`.