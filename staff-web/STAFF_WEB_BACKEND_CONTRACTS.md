# Staff Web Backend Contracts

## Source Of Truth

- Consumer truth:
  - `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
  - `../build/api-consumer/mutation-contracts.md`
- Backend route truth:
  - `../routes/api/auth.php`
  - `../routes/api/staff_pos.php`

## Auth + Session

### Canonical routes

- `POST /api/v1/auth/staff/login`
- `GET /api/v1/auth/staff/me`
- `POST /api/v1/auth/staff/refresh`
- `POST /api/v1/auth/staff/logout`

### Contract notes

- Auth header: `X-Staff-Key`
- Session envelope now includes:
  - `capabilities`
  - `known_capabilities`
  - `capability_source`
- FE route guards/nav chi duoc gate bang granted `capabilities`
- `known_capabilities` chi la metadata ve capability catalog, khong phai grant runtime
- Error envelope quan trong cho staff-web:
  - top-level `error_code`
  - top-level `request_id`
  - `required_capability` tren mot so `403`
  - validation details co the nam o `errors` hoac `details.errors`
- Main errors:
  - `401` invalid/expired opaque token
  - `422` bad credentials or malformed payload

## Board + Waiting

### Canonical routes

- `GET /api/v1/staff/tables/board`
- `GET /api/v1/staff/tables/board/changes`
- `GET /api/v1/staff/waiting-list`
- `GET /api/v1/staff/waiting-list/changes`
- `POST /api/v1/staff/reservations/{id}/check-in`
- `POST /api/v1/staff/waiting-list/{id}/notify`
- `POST /api/v1/staff/waiting-list/{id}/seat`

### Capability gates

- `table.board.view`
- `waiting_list.manage`

### Concurrency notes

- `check-in`, `notify`, `seat` all require `row_version`
- Mutations require `Idempotency-Key`
- Main mutation errors:
  - `401` auth failure
  - `403` capability or branch boundary
  - `409` idempotency/state conflict
  - `422` payload validation, including runtime stale `row_version`
- FE phai doc stale row_version tu `errors.row_version` hoac `details.errors.row_version`, khong duoc mac dinh `409`

### Deprecated alias to avoid

- `GET /api/v1/staff/table-board`

## Orders

### Canonical routes

- `POST /api/v1/staff/tables/{table_id}/orders`
- `POST /api/v1/staff/orders/{order_id}/items`
- `GET /api/v1/staff/orders/{order_id}`
- `POST /api/v1/staff/orders/{order_id}/bill-snapshot`

### Capability gate

- `order.manage`

### Concurrency notes

- Create/add-items/bill-snapshot require `row_version`
- Mutations require `Idempotency-Key`
- FE uses board reservation metadata or loaded order detail as row_version source
- Runtime stale `row_version` hien tai surfacing chu yeu qua `422 validation_error`

## Settlement

### Canonical routes

- `GET /api/v1/staff/reservations`
- `GET /api/v1/staff/reservations/{reservation_id}/orders`
- `GET /api/v1/staff/orders/{order_id}/settlement-preview`
- `POST /api/v1/staff/orders/{order_id}/settlement/finalize`

### Capability gate

- `settlement.manage`

### Concurrency notes

- Finalize requires `row_version`
- Finalize requires `Idempotency-Key`
- Preview is read-only and does not require idempotency
- `409` duoc de danh cho idempotency/state conflict; stale `row_version` can surface qua `422`
- staff-web nen uu tien reservation lookup + reservation-order lookup de lay current/historical order truoc khi yeu cau manual `order_id`

## Refunds

### Canonical routes

- `GET /api/v1/staff/reservations/{reservation_id}/refund-preview`
- `POST /api/v1/staff/reservations/{reservation_id}/refund`
- `POST /api/v1/staff/reservations/{reservation_id}/refund-cancel`

### Capability gate

- `payment.refund`

### Concurrency notes

- Preview returns reservation summary with current `row_version`
- Refund and refund-cancel require that `row_version`
- Both mutations require `Idempotency-Key`
- FE nen uu tien preview envelope lam row_version source; stale row_version runtime hien tai surfacing qua `422`

## Cashier

### Canonical routes

- `GET /api/v1/staff/cashier/shifts/current`
- `POST /api/v1/staff/cashier/shifts/open`
- `GET /api/v1/staff/cashier/shifts/{shift_id}`
- `POST /api/v1/staff/cashier/shifts/{shift_id}/close`

### Capability gate

- `settlement.manage`

### Concurrency notes

- Open requires `Idempotency-Key`, no `row_version`
- Close requires both `row_version` and `Idempotency-Key`
- `GET current` may return `404` when staff has no open shift
- staff-web uu tien hydrate open/lookup context tu `GET current`; manual shift ID van la fallback

## Conversations

### Canonical routes used by staff-web

- `GET /api/v1/staff/conversations`
- `GET /api/v1/staff/conversations/{conversation_id}`
- `POST /api/v1/staff/conversations/{conversation_id}/take-over`
- `POST /api/v1/staff/conversations/{conversation_id}/internal-notes`
- `POST /api/v1/staff/conversations/{conversation_id}/outbound-replies`

### Capability gate

- `conversation.manage`

### Notes

- List route query params da duoc staff-web dung toi thieu cho `status`, `assignment_state`, va `q`
- Detail payload exposes conversation capabilities used by FE to lock outbound reply action
- `conversation.manage` la capability route-level; outbound reply van phai ton trong `data.capabilities.outbound_reply` cua detail payload
- Mutations use `Idempotency-Key`
- `403` co the tra `required_capability`; FE surfaces truong nay cho operator

## Deferred / Caveats

- No separate backend feature-readiness bootstrap endpoint exists yet
  - FE relies on auth capabilities plus route/action availability in returned payloads
- Board/waiting changes endpoints nay duoc background-poll tren FE, nhung backend van la source of truth va FE chi refetch full slices khi cursors bao co thay doi
- Order/refund/cashier lookup van production-lean:
  - board suggestions/current shift sources duoc uu tien
  - manual IDs van duoc giu cho non-board/historical cases
- FE intentionally avoids deprecated aliases:
  - `/staff/table-board`
  - `/staff/orders/{order_id}/close`
  - `/staff/orders/{order_id}/checkout`
