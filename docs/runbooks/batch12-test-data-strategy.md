# Batch 12 — Test Data and Fixture Strategy

To support reliable, repeatable staff operations smoke runs in local development without polluting live production data, we leverage a structured UAT test data strategy.

## 1. Stable Pre-Seeded Reference Data
Our tests utilize the standard SQLite/MySQL database seeded by running `composer bootstrap:booking` or `php artisan booking:uat-pack:bootstrap --json`. The following entities are assumed stable:
- **Staff Credentials**: 
  - Staff key/actor: `bootstrap-admin` / `password` (has global capabilities like `reservation.manage`, `order.manage`, `kitchen.manage`, `settlement.manage`).
- **Customer Credentials**:
  - Customer owner: `ms.customer-03` / `password`.
- **Branch Defaults**:
  - ID: `14` (UATDEMO).
- **Seeded Catalog Items**:
  - Available menu item to order: ID extracted dynamically via `GET /menu/items`.
- **Table holds**:
  - Available tables extracted dynamically via `GET /tables/available`.

## 2. Dynamic Transactional Fixtures
To ensure E2E workflows can run consecutively:
- **Reservations**: Formulated dynamically via customer auth -> table hold -> reservation submission. Each test creates a unique reservation ID and tracks its corresponding `row_version` across updates.
- **Service Sessions & Orders**: Discovered dynamically by checking in the newly created reservation. The reservation checkout payments apply specifically to this dynamically provisioned order ID.
- **Kitchen Tickets & Cashier Shifts**: 
  - Station tickets are matched by searching for the order's active ticket payload dynamically.
  - Cashier shifts are checked using the `shifts/current` API without executing destructive shift closures (which would break downstream checks).

## 3. Post-Run Cleanup / Resiliency
- Unique identifier tags like `e2e_batch12_<timestamp>` are passed during mutations.
- The test runs within local SQLite transaction contexts where possible, or is safe to repeat on MySQL as it relies on newly created reservations and table allocations each run.
- Stale holds expire naturally via scheduled heartbeat tasks.
