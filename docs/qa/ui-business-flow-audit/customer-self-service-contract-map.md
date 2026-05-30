# Customer Self-Service Contract Map

## 1. Customer-Facing Endpoints
Based on `routes/api/customer_self_service.php`:

### Public Endpoints
- `GET restaurant/profile`
- `GET menu/categories`
- `GET menu/items`, `GET menu/items/{id}`
- `POST menu/preorder/preview`
- `GET qr/bill-preview/{token}`
- `GET tables/available`
- `POST table-holds`, `GET table-holds/{hold_id}`, `DELETE table-holds/{hold_id}`, `PATCH table-holds/{hold_id}/refresh`

### Auth/Session Required Endpoints (CustomerOrStaffMiddleware / ResolveCustomerAuthMiddleware)
- **Account & Privacy**: `GET me/loyalty`, `GET me/vouchers`, `GET me/data-export`, `GET me/privacy-requests`, `POST me/privacy-requests`
- **Reservations**: 
  - `POST reservations` (Create)
  - `GET reservations`, `GET reservations/{id}`
  - `POST reservations/{id}/cancel`, `POST reservations/{id}/reschedule`
- **Preorder**: 
  - `GET reservations/{id}/preorder`
  - `POST reservations/{id}/preorder/preview`, `PUT reservations/{id}/preorder`, `POST reservations/{id}/preorder/submit`, `DELETE reservations/{id}/preorder`
- **Deposits**:
  - `GET reservations/{id}/deposit-preview`
  - `POST reservations/{id}/deposit/acknowledge`, `POST reservations/{id}/deposit/intent`, `POST reservations/{id}/deposit/intent/revoke`
  - `POST reservations/{reservation_id}/deposit/payment-sessions`
- **Benefits/Vouchers**:
  - `GET reservations/{id}/benefits-preview`
  - `POST reservations/{id}/voucher/apply`, `POST reservations/{id}/voucher/remove`
  - `POST reservations/{id}/loyalty/redeem`, `POST reservations/{id}/loyalty/redeem/release`
- **Bill/Checkout**:
  - `GET reservations/{reservation_id}/bill`, `GET reservations/{reservation_id}/active-order`, `GET reservations/{reservation_id}/bill-preview`
  - `POST reservations/{reservation_id}/bill/payment-sessions`
- **Waiting List**:
  - `GET waiting-list`, `POST waiting-list`, `GET waiting-list/{id}`
  - `POST waiting-list/{id}/accept`, `POST waiting-list/{id}/confirm-arrival`, `POST waiting-list/{id}/decline`, `POST waiting-list/{id}/cancel`

## 2. Request/Response Envelope
- Standard Laravel JSON response structure: `{ "data": ... }` for successful read requests.
- Pagination returns `data` alongside `links` and `meta`.
- Error responses standard structure `{ "message": "...", "errors": {...} }`.
- Domain errors typically `400 Bad Request` or `422 Unprocessable Entity` for validation.
- API validation uses FormRequests.

## 3. Auth/Session Headers/Cookies Used
- `X-Customer-Token` / Bearer Token for authenticated customer sessions.
- `X-Session-Id` if applicable for unauthenticated or sticky sessions (typically passed to customer endpoints if supported, though `ResolveCustomerAuthMiddleware` handles it).

## 4. Booking / Hold / Reservation State Transitions
- **Hold**: Create hold -> `valid` until timeout -> `expired` or converted to reservation.
- **Reservation**: Create -> `pending` (if deposit required) -> `confirmed` (deposit paid/acknowledged) -> `checked_in` -> `completed`, or `cancelled`.

## 5. Preorder Endpoints
- Draft preorder states are attached to reservation via `CustomerReservationPreorderController`.
- Transitions: Preview -> Replace (Draft) -> Submit (Locked).

## 6. Waiting List Endpoints
- Customer joins via `POST waiting-list`.
- Status tracking via `GET waiting-list/{id}`.
- Can accept, confirm arrival, decline, or cancel.

## 7. QR Bill Preview Endpoints
- `GET qr/bill-preview/{token}`: Uses cryptographically secure or signed token to allow public preview of the bill for a short duration. Guarded by rate limiting.

## 8. Deposit/Payment Customer-Facing Endpoints
- Use `payment-sessions` endpoints. Simulates a local UAT session.
- Real provider callbacks are out of scope.

## 9. Privacy/Customer Data Endpoints
- Supported: `GET me/data-export`, `GET me/privacy-requests`, `POST me/privacy-requests`.

## 10. Voucher/Loyalty Visibility Endpoints
- `GET me/vouchers`, `GET me/loyalty`
- Attached to reservation via `reservations/{id}/voucher/apply`.

## 11. UI Pages/Components Currently Implemented
- `(public)/page.tsx` (Homepage)
- `(public)/booking` (Booking entry / form)
- `(public)/menu` (Menu catalog)
- `(public)/qr` (QR bill preview)
- `(protected)/account` (Profile / Privacy)
- `(protected)/reservations` (Reservation list/detail/new/preorder)
- `(protected)/waiting-list` (Waiting list entry)

## 12. Missing UI/API Flows
- Voucher/benefits UAT environment needs real seeded vouchers to verify full end-to-end checkout-level loyalty mutations.
- Other self-service flows (preorder, cancel, reschedule, QR bill preview) are fully covered and integrated.

## 13. Risks and Test Data Requirements
- Auth leak risk (Customer Or Staff Middleware must correctly segregate).
- Overbooking or deposit bypass if UI validations are bypassed.
- Requires test branches, tables, items, and vouchers seeded.
