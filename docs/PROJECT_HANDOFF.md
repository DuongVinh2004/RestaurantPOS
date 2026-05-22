# Project Handoff

Welcome to the RestaurantPOS repository. This document serves as a professional handoff layer for reviewers, interviewers, or future maintainers to quickly understand the project's purpose, structure, and operational standards.

## Project Overview

RestaurantPOS is a lean, full-stack, production-grade backend for restaurant operations. It manages the entire lifecycle of a dine-in and walk-in restaurant experience, from table reservations to kitchen dispatch, ordering, and final checkout. It is designed to be rigorous, emphasizing state machines, idempotency, role-based access control, and a strong database contract.

## What Problem RestaurantPOS Solves

Modern restaurants require a highly concurrent, reliable point-of-sale system that bridges front-of-house (waitstaff, host), back-of-house (kitchen), and the customer. RestaurantPOS aims to centralize:
- **Reservations and Waitlist**: Managing table holds and branch capacity.
- **Service Sessions**: Tracking dine-in flows and table states.
- **Ordering**: Fast order item entry and status tracking.
- **Kitchen Dispatch**: Routing items to the correct kitchen stations (KDS).
- **Cashiering and Checkout**: Handling split bills, refunds, and cashier shifts.
- **Inventory**: Deducting stock as items are consumed.
- **Reporting**: Daily sales and operational metrics.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2)
- **Database**: MySQL 8 (Primary datastore), Redis 7 (Caching, queues, short-lived locks)
- **Frontend (Staff)**: React 18, Vite, TypeScript, Tailwind CSS, Ant Design (`staff-web/`)
- **Frontend (Customer)**: Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS, shadcn/ui (`customer-web/`)
- **Testing & QA**: PHPUnit, PHPStan, Pint, Playwright (E2E)

## SQL-First Database Strategy

Unlike many Laravel projects, this repository **does not use `php artisan migrate`** as the default local or release bootstrap path. It employs a **SQL-first** strategy.
- The single source of truth for the schema is `database/schema/mysql-schema.sql`.
- Incremental changes are applied via strict SQL patch files in `database/patches/`.
- A compiled snapshot exists in `db_all.sql`.
- This approach prevents ORM migration drift, explicitly tracks database changes over time, and ensures the schema applied locally is the exact schema running in production.

## Main Backend Modules

Business logic is isolated into specific domains under `app/Modules/`, keeping controllers thin:
- **Reservations / Waitlist**: Handles bookings and table holds.
- **FloorOperations**: Manages table status and service sessions.
- **Ordering**: Controls the lifecycle of an order and its items.
- **KitchenDispatch**: Routes items to kitchen stations and tracks completion.
- **CheckoutPayments & Cashiering**: Manages the flow of money, split bills, and shift reconciliation.
- **InventoryProcurement**: Tracks ingredients, recipes, and stock movements.
- **Reporting**: Generates snapshots for branch performance.
- **IdentityAccess**: Handles authentication and staff/customer RBAC capabilities.

## Staff Web and Customer Web Roles

- **`staff-web/`**: A tablet-first POS interface for hosts, servers, kitchen staff, and cashiers. Requires strong API key or staff session authentication.
- **`customer-web/`**: A mobile-first Next.js web application for customers to browse the menu, book tables, join the waitlist, and potentially view their current order. Relies on `X-Customer-Token` for authorization.

## API Contract and Generated Artifacts

API interactions between the frontend clients and the Laravel backend are heavily protected by generated contracts.
- We do not hand-write API shapes from route files on the frontend.
- Backend API responses and mutations generate artifacts located in `build/api-consumer/`.
- The Next.js and React clients consume the `restaurantpos-sdk.ts`, enums, and mutation contracts to guarantee type safety and endpoint alignment.

## Local Setup Summary

1. Clone the repository.
2. Install backend dependencies (`composer install`) and frontend dependencies (`npm ci` in root, `staff-web`, `customer-web`).
3. Set up `.env` with actual MySQL and Redis connections (use Docker or local binaries).
4. **Crucial**: Run `composer bootstrap:booking` to provision the database using the SQL-first approach, seed reference data, and prepare the local artifacts.
5. Run `npm run runtime:up` to boot the local environment (Laravel server + schedule worker).
6. Run `npm run dev:all` (or equivalent) to launch both frontends.

## Runtime and Preflight Verification

We do not trust SQLite tests alone for runtime health. You must verify the actual running state.
- Use `npm run runtime:preflight` or `php artisan booking:doctor` to assert that MySQL, Redis, the outbox, and the scheduler are alive.
- We employ strict deployment checks: `php artisan booking:deploy-check --mode=preflight`.

## Release Evidence Concept

A release candidate is not valid unless it produces evidence of correctness.
- The `storage/app/booking_release/` directory holds frozen release manifests and API snapshot JSONs.
- `php artisan booking:release-manifest --verify-frozen` ensures the code matches the claimed release state.
- CI/CD governance requires passing the "release manifest check" to prevent regressions.

## Known Limitations

Please review [Known Limitations](./product/known-limitations.md) for a candid assessment of what the project does not yet claim to do (e.g., real hardware integration, live payment provider integrations).

## How to Review the Repo Professionally

1. **Start with the Core:** Review `routes/api.php` and `app/Modules/Ordering/` to understand the data flow.
2. **Examine the SQL Contract:** Look at `database/schema/mysql-schema.sql` and `database/patches/` to see the underlying architecture.
3. **Check the Tests:** We write targeted tests. Review `tests/Feature/Staff/` to see how business logic and access controls are validated.
4. **Follow the Workflow:** Try the setup script. See how errors are surfaced and how state transitions are guarded against illegal operations.
