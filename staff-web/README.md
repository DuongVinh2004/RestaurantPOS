# RestaurantPOS Staff Web

This frontend is now intentionally narrowed around one usable operational chain:

`login -> table board -> reservation handling -> walk-in / assign table -> active order -> checkout / settlement / refund`

Kitchen/KDS, conversation inbox, and inventory uplift can remain mounted or contract-visible, but they are not part of the day-1 launch promise.

The app uses:

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- Zustand
- Ant Design

## Visual contract

The active staff-web visual contract imported from the design bundle now lives in:

- `staff-web/DESIGN.md`
- `staff-web/UI_SCOPE.md`
- `staff-web/REFERENCE_LINKS.md`

These files define the shared UI direction for the active app shell and shared primitives. They complement the repo root `AGENTS.md`; they do not replace it.

## Current scope

Day-1 ON:

- `Login`
- `Table Board`
- `Reservations`
- `Waiting List` for manual operator notify, seat, and cancel only
- `Active Order`
- `Checkout + Refund`
- `Cashier Shift`
- `Finance Review`

Mounted or contract-visible, but not day-1 launch-promised:

- `Kitchen` (`/kitchen` landing, `/kitchen/board` ticket queue)
- `Admin Home`
- `Admin Settings`
- `Admin Inventory`
- `Conversation Inbox`
- `Audit Trail`
- `Reporting`
- `Access` readiness screen

Everything else from the older staff-web remains outside the main route tree on purpose.

Deferred outside the current shell:

- a separate settlement-only console
- admin write flows beyond the mounted settings and inventory routes

## Backend contract usage

This build binds directly to the staff backend and uses real APIs for the core flow:

- Auth: `/api/v1/auth/staff/login`, `/me`, `/refresh`, `/logout`
- Board: `/api/v1/staff/tables/board`, `/tables/board/changes`
- Reservations: `/api/v1/staff/reservations`, `/reservations/{id}`, `/assign-table`, `/assign-best-fit`, `/check-in`
- Walk-in: `/api/v1/staff/service-sessions/walk-in`
- Orders: `/api/v1/staff/tables/{table_id}/orders`, `/orders/{order_id}`, `/orders/{order_id}/items`, `/orders/{order_id}/items/{order_item_id}`, `/orders/{order_id}/items/{order_item_id}/status`, `/tables/{table_id}/active-order`, `/reservations/{reservation_id}/active-order`
- Staff menu: `/api/v1/staff/menu/items`
- Kitchen: `/api/v1/staff/kitchen/stations`, `/stations/{station_id}/tickets`, `/orders/{order_id}/kitchen/dispatch`, `/kitchen/tickets/{ticket_id}/fire|bump|recall`
- Checkout: `/api/v1/staff/orders/{order_id}/bill-snapshot`, `/settlement-preview`, `/settlement/finalize`, `/api/v1/staff/reservations/{reservation_id}/refund-preview`, `/refund`, `/refund-cancel`
- Cashier shift: `/api/v1/staff/cashier/shifts`, `/current`, `/open`, `/{shift_id}/close`
- Finance review: `/api/v1/staff/finance/reconciliation`, `/reconciliation/{reservation_id}`, `/finance/invoices/{reservation_id}`, `/finance/invoices/{reservation_id}/issue`
- Waiting list: `/api/v1/staff/waiting-list`, `/changes`, `/{id}/notify`, `/{id}/advance`, `/{id}/seat`, `/{id}/cancel`
- Branch context: `/api/v1/staff/branches`
- Conversation inbox: `/api/v1/staff/conversations`, `/{conversation_id}`, `/take-over`, `/unassign`, `/internal-notes`, `/outbound-replies`
- Audit trail: `/api/v1/staff/audit-trail`
- Reporting: `/api/v1/staff/reporting/daily-sales`, `/daily-operations`, `/daily-inventory`
- Admin settings: `/api/v1/admin/settings/branches`, `/admin/settings/finance/tax-profile`, `/admin/restaurant/tables`, `/admin/restaurant/zones`, `/admin/kitchen/stations`
- Admin inventory: `/api/v1/admin/inventory/ingredients`, `/admin/inventory/suppliers`, `/admin/inventory/purchase-orders`

## Honest gaps

The FE does **not** fake completeness where backend contracts are still thin.

- Order line item update/status routes are live through the generated SDK and require order `row_version`, item `row_version`, and `Idempotency-Key`.
- Add-item, settlement, and reservation-linked refund still run live.
- Kitchen routing and ticket surfaces can still be mounted; dispatch and ticket actions require granted capability, branch/station context, `row_version`, and `Idempotency-Key`.
- Waiting list create/advance/cancel routes are wired live through the local `staff-api` adapter because the generated TypeScript SDK does not currently expose those endpoints.
- Customer waiting-list remains off on day 1; staff waiting-list is the manual operator path only.
- Waiting-list notify prefers board-driven table selection when `table.board.view` is granted; otherwise the UI falls back to explicit `table_id` entry instead of pretending the board data exists.
- Checkout finalize is still intentionally blocked whenever startup readiness says an active cashier shift is required and the session has not refreshed into that state yet.
- Refund is mounted at `/ops/refunds` and reuses the checkout domain flow instead of branching into a separate mixed shell.
- Finance reconciliation and invoice responses now surface reservation `row_version` so the review workspace can reopen `/ops/reservations` without dropping stale-write protection.
- Conversation detail now opens linked waiting-list records through `/ops/waiting-list?focus=<waiting_id>` so staff lands on the intended queue item instead of the first row in the list.

## Local run

Preferred repo-root daily lane:

```bash
npm run dev:all
npm run dev:smoke
```

`npm run dev:all` starts the backend, `customer-web`, and `staff-web` together on:

- `http://127.0.0.1:8000`
- `http://127.0.0.1:3000`
- `http://127.0.0.1:5173`

It also refreshes the shared UAT manifest used by the local login and smoke lanes. `npm run dev:smoke` is the short repo-level proof that backend, customer-web, staff-web, and the demo credentials are all wired correctly.

If you only need the operator UI and already have the backend lane running, you can still work inside `staff-web` directly:

```bash
npm install
npm run build
```

For local development:

```bash
npm run dev
```

For a standalone session that matches the repo-root lane exactly, run `npm run dev -- --host 127.0.0.1 --port 5173`.

## Environment

Create `.env` from `.env.example` if needed:

```bash
VITE_API_URL=http://localhost:8000/api/v1
VITE_APP_TITLE=RestaurantPOS Staff Web
```

`VITE_API_URL` should include `/api/v1`.

Keep the frontend origin aligned with the backend CORS allow-list. `http://localhost:5173` and `http://127.0.0.1:5173` are different origins. The repo-root `npm run dev:all` lane is validated on `127.0.0.1`, so either mirror that host family in standalone runs or ensure both origins are explicitly allowed by the backend.

## Verification used for this batch

```bash
npm run integrity:check
npm run build
npx vitest run src/shared/auth/capabilities.compat.test.ts src/workspaces/workspaces.test.ts src/app/router/workspace-route-guards.test.tsx src/workspaces/kitchen/pages/board/KitchenBoardPage.test.tsx src/workspaces/ops/pages/checkout/CheckoutPage.test.tsx
```

MVP local/staging smoke steps are documented in `../docs/runbooks/staff-web-mvp-smoke.md`.

## Next recommended module after this batch

Once the day-1 chain is stable, the next highest-value follow-up is:

1. cashier shift -> checkout handoff polish and reconciliation detail
2. order line-item edit/status browser smoke against real row-version fixtures
3. kitchen/KDS promotion after rollout evidence exists
4. conversation inbox
5. audit and reporting
