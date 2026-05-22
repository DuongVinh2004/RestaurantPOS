# System Overview

RestaurantPOS is structured as a resilient, SQL-first monolithic backend built on Laravel, serving multiple frontend web clients. It is designed to handle the concurrency and strict operational requirements of a live restaurant environment.

## Architecture Components

*   **Customer Web (`customer-web/`)**: A Next.js (App Router) mobile-first client. It communicates with the backend via the `X-Customer-Token` for session identity.
*   **Staff Web (`staff-web/`)**: A React (Vite) tablet-first SPA. It serves front-of-house (waitstaff, hosts), back-of-house (kitchen), and management. It communicates via staff sessions or explicit API keys (`X-Staff-Key`).
*   **Laravel API**: The central backend application. Exposes strictly defined routes under `/api/v1/` segmented by actor (`customer_self_service`, `staff_pos`, `admin`, `ops_release`).
*   **MySQL 8 (Primary Datastore)**: The absolute source of truth. The schema is provisioned strictly via `database/schema/mysql-schema.sql` and `database/patches/`. We do not rely on standard Laravel ORM migrations to ensure a tight contract with production DBAs.
*   **Redis 7 (Ephemeral & Locks)**: Used for fast access, session storage, and most critically, **short-lived distributed locks** to prevent concurrent mutations (e.g., double billing, concurrent table assignment).
*   **Scheduler (`schedule:work`)**: Vital for background health. It prunes expired reservations, releases expired table holds, aggregates metrics, and pulses a heartbeat.
*   **Notification Outbox**: A robust database-backed outbox pattern. Notifications (emails, SMS) are written to an outbox table within the same transaction as the business mutation, then picked up asynchronously to ensure zero data loss if external providers fail.

## Request Lifecycle

1.  **Entry Point**: A request hits the `public/index.php` and enters the Laravel HTTP kernel.
2.  **Global Middleware**: `reqid` (generates correlation ID) and `audit.request` (captures audit logs) run first.
3.  **Authentication Middleware**: Resolves the actor based on tokens or sessions. `CustomerOrStaffMiddleware` ensures proper routing.
4.  **Routing & Capability Gates**: The request hits the segmented route files (`routes/api/staff_pos.php`, etc.). Staff routes are protected by capability checks (e.g., `RequireStaffCapabilityMiddleware`).
5.  **Thin Controller**: The controller validates the incoming payload (using Form Requests) and immediately delegates to an Application Service or Domain logic class within `app/Modules/`.
6.  **Business Logic (Module)**: The module service handles idempotency checks, distributed locks (via Redis), begins a database transaction, mutates data, writes to the Outbox, and commits.
7.  **Response Envelope**: The response is formatted into a standardized API envelope and returned to the client.

## API Envelope Conventions

While specific shapes vary by module, the backend generally adheres to a consistent JSON envelope for success and error states to simplify client-side parsing.

**Success Envelope:**
```json
{
  "data": { ... },
  "meta": { ... } // Pagination, row_version, or cache details
}
```

**Error Envelope (handled via `ApiErrorResponse`):**
```json
{
  "error": {
    "code": "VALIDATION_FAILED", // Machine-readable string code
    "message": "Human readable description",
    "details": { ... } // e.g., specific field validation errors
  },
  "reqid": "uuid-for-log-correlation"
}
```

## Where Domain Logic Should Live

**Do not put business logic in Controllers.** Controllers exist only to marshal HTTP input to domain inputs and return HTTP responses.

All business rules, state transitions, locking, and multi-table orchestrations belong in `app/Modules/`. Cross-cutting concerns like metrics, outbox, health checks, and global observability belong in `app/Platform/`.
