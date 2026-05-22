# Module Ownership

RestaurantPOS uses a modular monolith approach. Code is grouped by business domain in `app/Modules/` rather than by technical concern.

Below is an overview of key modules, their responsibilities, and their boundaries.

## Core POS Modules

### `Reservations` & `Waitlist`
*   **Owned Workflows**: Customer booking, host stand assignment, capacity checking, waitlist queueing.
*   **Likely Entities**: `reservations`, `waitlist_entries`, `reservation_status_logs`.
*   **API Surface**: `api/customer_self_service.php` (booking), `api/staff_pos.php` (host stand).
*   **Test Surface**: `tests/Feature/Customer/` (self-service), `tests/Feature/Staff/` (FOH host).
*   **Boundary Risks**: High coupling with `BranchScheduling` (for open hours) and `FloorOperations` (for capacity).
*   **Cross-Module Dependencies**: Polling branch schedules and interacting with floor operations.

### `FloorOperations`
*   **Owned Workflows**: Table state management (available, seated, dirty), service session tracking.
*   **Likely Entities**: `tables`, `service_sessions`, `table_holds`.
*   **API Surface**: `api/staff_pos.php`.
*   **Test Surface**: `tests/Feature/Staff/StaffTable*`.
*   **Cross-Module Dependencies**: Interacts heavily with `Ordering` to link a physical table to an active bill.
*   **Boundary Risks**: Synchronizing table statuses with ongoing service sessions and POS orders.

### `Ordering`
*   **Owned Workflows**: Order creation, item entry, item modification, voiding, sub-totaling.
*   **Likely Entities**: `orders`, `order_items`, `order_status_logs`.
*   **API Surface**: `api/staff_pos.php`.
*   **Test Surface**: `tests/Feature/Staff/StaffOrder*`.
*   **Cross-Module Dependencies**: Must hand off items to `KitchenDispatch`. Must hand off finalized orders to `CheckoutPayments`.
*   **Boundary Risks**: Concurrency is extremely high. Multiple staff members may attempt to add items to the same table order simultaneously. Idempotency and locking are critical here.

### `KitchenDispatch` (KDS)
*   **Owned Workflows**: Routing order items to specific kitchen stations (grill, salad, bar), ticket bumping, fire/hold timing.
*   **Likely Entities**: `kitchen_tickets`, `kitchen_ticket_items`.
*   **API Surface**: `api/staff_pos.php`.
*   **Test Surface**: `tests/Feature/Staff/StaffKitchenDispatch*`.
*   **Cross-Module Dependencies**: Reads from `Ordering`. Triggers status updates back to `Ordering` when tickets are bumped.
*   **Boundary Risks**: Ticket state transitions failing to propagate back to the POS order view.

### `CheckoutPayments` & `Cashiering`
*   **Owned Workflows**: Bill presentation, split bills, applying discounts, capturing payment, processing refunds, cashier shift start/end, till reconciliation.
*   **Likely Entities**: `payments`, `refunds`, `cashier_shifts`, `cash_drawer_logs`.
*   **API Surface**: `api/staff_pos.php`, `api/customer_self_service.php` (if self-checkout).
*   **Test Surface**: `tests/Feature/Staff/StaffCheckout*`, `tests/Feature/Staff/StaffCashier*`.
*   **Cross-Module Dependencies**: Reads totals from `Ordering`.
*   **Boundary Risks**: Highest financial risk. Strict transactional boundaries required. Idempotency keys are mandatory for all external payment webhooks to prevent duplicate charges.

### `InventoryProcurement`
*   **Owned Workflows**: Stock tracking, recipe consumption (BOM deduction), purchase orders, supplier receiving.
*   **Likely Entities**: `inventory_items`, `stock_movements`, `purchase_orders`.
*   **API Surface**: `api/admin.php`.
*   **Test Surface**: `tests/Feature/Admin/AdminInventory*`.
*   **Cross-Module Dependencies**: Consumes signals from `Ordering` or `CheckoutPayments` to deduct stock based on sold items.
*   **Boundary Risks**: Over-deduction from concurrent sales; unit conversions.

## Supporting Modules

### `IdentityAccess`
*   **Owned Workflows**: Customer authentication, staff login, role-based access control (RBAC), API key validation, capability resolution.
*   **Likely Entities**: `users`, `roles`, `staff_capabilities`, `api_keys`.
*   **API Surface**: `api/auth.php`.
*   **Test Surface**: `tests/Feature/Security/*`.
*   **Boundary Risks**: Incorrectly issuing staff privileges to customer sessions.

### `Reporting`
*   **Owned Workflows**: Generating read-models, EOD (End of Day) sales snapshots, labor reports.
*   **Likely Entities**: `sales_snapshots`, `daily_summaries`.
*   **API Surface**: `api/admin.php`.
*   **Test Surface**: `tests/Feature/Reporting/*` (to verify).
*   **Cross-Module Dependencies**: Reads from almost all other modules.
*   **Boundary Risks**: Locking hot transactional tables during business hours; stale read models.

---

## Module Ownership Governance

This repo is in a transitional state. Legacy Laravel paths can remain while they own real unmigrated code, but they are not default targets for new domain work.

### Placement Rules
- New business code goes under `app/Modules/<Domain>`.
- New release, API contract, metrics, health, verification, backup, and operator code goes under `app/Platform`.
- `app/Services`, `app/Models`, `app/Http/Controllers/Api`, and `app/Support` are transitional or shared compatibility zones. Do not add new domain logic there when a module already owns the workflow.
- Keep controllers thin. Validation belongs in request classes, response shaping belongs in resources or small presenters, and decisions belong in application services, actions, policies, guards, or state objects.

### Test Placement
- New module unit tests should live under `tests/Unit/Modules/<Domain>/...`.
- New module feature tests should live under `tests/Feature/<Domain or Surface>/` until a feature-module taxonomy is introduced.
- Existing tests do not need bulk moves. Move tests only when the touched code is already being edited and the move improves ownership clarity.

### Shared Seams
Files such as `routes/api.php`, `config/booking.php`, `config/staff_capabilities.php`, and `database/schema/mysql-schema.sql` affect multiple domains. Keep diffs small, preserve route and schema contracts, and run targeted verification before merging.
