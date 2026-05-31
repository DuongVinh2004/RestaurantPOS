# Order Lifecycle Contract Map

## Order Endpoints
- **Create Order**: `POST /api/staff/tables/{table_id}/orders` (Capability: `order.manage`)
- **Close Order**: `POST /api/staff/orders/{order_id}/close` (Capability: `order.manage`)
- **Read Order**: `GET /api/staff/orders/{order_id}` (Capability: `order.manage`)
- **Active Table Order**: `GET /api/staff/tables/{table_id}/active-order` (Capability: `order.manage`)
- **Active Reservation Order**: `GET /api/staff/reservations/{reservation_id}/active-order` (Capability: `order.manage`)

## Item Mutation Endpoints
- **Add Items**: `POST /api/staff/orders/{order_id}/items` (Capability: `order.manage`)
- **Update Item (Quantity/Note)**: `PATCH /api/staff/orders/{order_id}/items/{order_item_id}` (Capability: `order.manage`)
- **Update Item Status**: `POST /api/staff/orders/{order_id}/items/{order_item_id}/status` (Capability: `order.manage`)

## Kitchen Dispatch Endpoints
- **Dispatch Order to Kitchen**: `POST /api/staff/orders/{order_id}/kitchen/dispatch` (Capability: `order.manage`)
- **Kitchen Ticket Actions**:
  - `POST /api/staff/kitchen/tickets/{ticket_id}/fire` (Capability: `kitchen.manage`)
  - `POST /api/staff/kitchen/tickets/{ticket_id}/bump` (Capability: `kitchen.manage`)
  - `POST /api/staff/kitchen/tickets/{ticket_id}/recall` (Capability: `kitchen.manage`)

## Void / Cancel Endpoints
- **Cancel Order**: `NOT_IMPLEMENTED`. There is no dedicated endpoint or state transition via generic endpoint exposed to explicitly cancel an active order prior to payment in this audit.
- **Void Item**: Typically handled by setting item status to `Cancelled` via `POST .../status` or `PATCH .../items/{id}`.

## State Enums
- **ReservationOrderStatus**: `Active`, `Cancelled`, `Completed`
- **ReservationOrderItemStatus**: `Ordered`, `InProgress`, `Served`, `Cancelled`
- **KitchenTicketStatus** (Inferred): `Queued`, `Fired`, `Ready`, `Completed`, `Cancelled`

## Expected Transitions
- **Order**: Active -> Completed
- **Item**: Ordered -> InProgress -> Served. (Cancelled can occur from Ordered, maybe InProgress).
- **Kitchen Ticket**: Syncs with item. (Ordered -> Queued, InProgress -> Fired, Served -> Completed).

## Contract Gaps & Notes
- **Cancel Order**: `NOT_IMPLEMENTED`.
- **Concurrent Edit**: The API successfully implements `row_version` for both `ReservationOrder` and `ReservationOrderItem`, ensuring robust stale state conflict detection (`422 Unprocessable Entity` with `stale_row_version`).
