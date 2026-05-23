# Staff Operations Flow Inventory

This document maps all 19 critical staff operations and backend capabilities against endpoints, business preconditions, and testing coverage.

## Core Operations & Route Mapping

| Flow Step | Backend Route | Controller/Service | Required Preconditions | Capabilities / Headers | SDK Method | Current Status | Risk | Action Needed |
|---|---|---|---|---|---|---|---|---|
| **1. Staff reservation inbox** | `GET /staff/reservations` | `ReservationInboxController` | Staff Auth | `reservation.manage` | `listStaffReservations` | Covered | Low | Checked in baseline. |
| **2. Staff reservation detail/timeline** | `GET /staff/reservations/{id}` | `ReservationInboxController` | Reservation exists | `reservation.manage` | `getStaffReservation` | Covered | Low | Checked in baseline. |
| **3. Reservation check-in** | `POST /staff/reservations/{id}/check-in` | `ReservationCheckInController` | Status is Confirmed, tables assigned | `reservation.manage` | `checkInStaffReservation` | Missing | High | Verify in staff smoke runner. |
| **4. Open/create service session** | Implicit in check-in, or `POST /staff/service-sessions/walk-in` | `ServiceSessionController` | Table is unoccupied | `reservation.manage` | `createStaffServiceSessionWalkIn` | Missing | High | Verify check-in session results. |
| **5. Staff table board state** | `GET /staff/tables/board` | `TableBoardController` | Staff Auth | `table.board.view` | `listStaffTablesBoard` | Covered | Low | Checked in baseline. |
| **6. Create order** | `POST /staff/tables/{id}/orders` | `ReservationOrderController` | Active service session on table | `order.manage` | `createStaffTableOrder` | Missing | High | Verify in staff smoke runner. |
| **7. Add/update/remove order item** | `POST /staff/orders/{id}/items` | `ReservationOrderController` | Active order exists | `order.manage` | `addStaffOrderItems` | Missing | High | Verify in staff smoke runner. |
| **8. Submit/send order** | `POST /staff/orders/{id}/items` | `ReservationOrderController` | Draft items exist | `order.manage` | `addStaffOrderItems` | Missing | High | Verify in staff smoke runner. |
| **9. Dispatch order to kitchen** | `POST /orders/{id}/kitchen/dispatch` | `KitchenDispatchController` | Undispatched items in order | `order.manage` | `dispatchStaffOrderToKitchen` | Missing | High | Verify in staff smoke runner. |
| **10. Kitchen station tickets** | `GET /kitchen/stations/{id}/tickets` | `KitchenDispatchController` | Dispatched items exist | `kitchen.manage` | `listStaffKitchenStationTickets` | Missing | High | Verify in staff smoke runner. |
| **11. Fire ticket** | `POST /kitchen/tickets/{id}/fire` | `KitchenDispatchController` | Ticket status is queued | `kitchen.manage` | `fireStaffKitchenTicket` | Missing | Med | Verify in staff smoke runner. |
| **12. Bump ticket** | `POST /kitchen/tickets/{id}/bump` | `KitchenDispatchController` | Ticket status is fired | `kitchen.manage` | `bumpStaffKitchenTicket` | Missing | Med | Verify in staff smoke runner. |
| **13. Recall ticket** | `POST /kitchen/tickets/{id}/recall` | `KitchenDispatchController` | Ticket status is ready/bumped | `kitchen.manage` | `recallStaffKitchenTicket` | Missing | Med | Verify in staff smoke runner. |
| **14. Checkout preview** | `GET /orders/{id}/settlement-preview` | `CheckoutController` | Order has printable items | `settlement.manage` | `getStaffOrderSettlementPreview` | Missing | High | Verify in staff smoke runner. |
| **15. Checkout finalize** | `POST /orders/{id}/settlement/finalize` | `CheckoutController` | Outstanding bill exists | `settlement.manage` | `finalizeStaffOrderSettlement` | Missing | High | Verify in staff smoke runner. |
| **16. Record payment** | `POST /orders/{id}/pay` | `CheckoutController` | Open cashier session | `settlement.manage` | `payStaffOrder` | Missing | High | Verify in staff smoke runner. |
| **17. Refund if supported** | `POST /reservations/{id}/refund` | `ReservationRefundController` | Paid deposit exists | `payment.refund` | `refundStaffReservation` | Deferred | Med | Skip to prevent seeding disruption. |
| **18. Cashier shift open/close** | `GET /staff/cashier/shifts/current` | `CashierShiftController` | Staff Auth | `cashier.shift.manage` | `getStaffCurrentCashierShift` | Covered | Low | Checked in baseline. |
| **19. Daily sales/operations verification** | `GET /staff/reporting/daily-sales` | `SalesReportController` | Rebuilt snapshot complete | `reporting.view` | `listStaffDailySalesReports` | Covered | Low | Checked in baseline. |

## Risk Curation
Downstream checkout finalization and kitchen dispatch mutations hold the highest operational runtime risk. Verification of these routes under genuine sqlite/mysql contexts guarantees production resilience.
