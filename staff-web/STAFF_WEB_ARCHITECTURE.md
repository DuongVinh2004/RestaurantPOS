# Staff Web Architecture

## Stack

- Vite
- React 18
- TypeScript
- React Router
- TanStack Query
- Zustand
- Ant Design
- Generated OpenAPI SDK at `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`

## Source Boundaries

- `src/app`
  - Runtime entry component, providers, router, auth startup, shell layout, and stores.
  - App code may compose workspaces and shared primitives, but business domains should not import app runtime modules.
- `src/shared`
  - Generated SDK facade, API client helpers, auth primitives, generic UI, generic hooks, mutation feedback, status tones, config, and formatting utilities.
  - Shared code must stay domain-neutral. Do not place reservation, table, kitchen, finance, or reporting policy here.
- `src/workspaces`
  - Mounted route modules, page entrypoints, workspace navigation, and workspace-specific page widgets.
  - Every mounted staff page should enter through a file under `src/workspaces/*/pages`.
- `src/domains`
  - Reusable business helpers, URL mappers, adapters, and view-model logic for reservations, tables, orders, kitchen, finance, reporting, audit, conversations, and related domains.
  - Domain code may depend on `shared`, but not on `app` runtime stores, providers, or router guards.

Top-level `src/main.tsx`, `src/styles`, and `src/test` remain special Vite, styling, and test support areas.

## Canonical Runtime

- `src/main.tsx`
  - Vite entrypoint.
- `src/app/App.tsx`
  - Mounts the current staff-web application shell only.
- `src/app/router/index.tsx`
  - Owns the mounted route tree.
  - Boots the authenticated session.
  - Redirects authenticated staff through `/access` or the canonical workspace routes.
- `src/app/layout/StaffAppShell.tsx`
  - Shared shell for all mounted staff routes.
  - Holds branch selection, session refresh/logout, current route context, and scoped notices.

## Route Tree

- `/login`
- `/access`
  - Default authenticated access hub.
- `/ops`
  - `/ops/dashboard`
  - `/ops/tables`
  - `/ops/reservations`
  - `/ops/waiting-list`
  - `/ops/orders`
  - `/ops/checkout`
  - `/ops/refunds`
  - `/ops/cashier-shift`
  - `/ops/finance-review`
  - `/ops/conversations`
- `/kitchen`
  - Landing route for branch/station lock, assigned station selection, workload summary, and live sync status.
  - `/kitchen/board`
    - Ticket queue route with status lanes, ticket timeline, and fire/bump/recall actions.
- `/admin`
  - Landing route for back-office domain selection and workspace summaries.
  - `/admin/settings`
    - Branch registry, table config ownership, kitchen routing config, and settings-side control surfaces.
  - `/admin/inventory`
    - Ingredient, supplier, and purchase-order oversight for the admin supply lane.
  - `/admin/reporting`
  - `/admin/audit-trail`

The workspace routes above are the only mounted staff-web runtime paths. Flat aliases such as `/tables` and `/reporting` are no longer part of the app shell.

## State Ownership

- `src/app/store/auth-store.ts`
  - Session bootstrap, login, refresh, logout, expire notice, and recommended route selection.
- `src/workspaces/workspaces.ts`
  - Resolves canonical `ops`, `kitchen`, and `admin` workspaces from the staff session payload.
  - Keeps an `ops` fallback so legacy capability catalogs do not break bootstrap.
- `src/app/store/workspace-store.ts`
  - Holds active, available, and primary workspace state for the current operator session.
- `src/app/store/flow-store.ts`
  - Holds branch context plus selected table, reservation, order, and station ids.
- `src/app/router/useJourneyContext.ts`
  - Applies route query params into the flow store.
- `src/app/router/journey.ts`
  - Builds and reads route-owned operational context.

## Kitchen Workspace Rules

- `/kitchen` is the station-first landing surface. It should help an operator lock branch and station context before loading ticket queues.
- `/kitchen/board` owns the active ticket queue and should keep operators inside kitchen-specific lanes instead of linking out to mixed staff surfaces by default.
- Kitchen guard decisions and ticket view-model logic belong under `src/domains/kitchen`; page components should compose those rules with workspace UI.
- Missing kitchen access, missing branch context, missing assigned station, and invalid station ids must render explicit operational states before ticket actions are enabled.

## Admin Workspace Rules

- `/admin` is the back-office landing surface. It should explain domain ownership first and route staff into admin pages instead of dropping them into ops-style task lists.
- `/admin/settings` owns branch, table, and kitchen-routing configuration context. Admin configuration metadata belongs under `src/domains/admin`, not in shell runtime code.
- `/admin/inventory` owns ingredient, supplier, and purchase-order admin reads. Query builders and summaries belong under `src/domains/inventory`.
- `/admin/reporting` and `/admin/audit-trail` stay in the admin workspace even when they read branch-scoped or mixed-scope data.
- Admin navigation must stay distinct from ops navigation. Do not reintroduce mixed menu groups that blend floor execution with back-office control surfaces.

## URL-Driven Operational State

- Journey state remains in query params for table, reservation, order, kitchen, checkout, refund, and audit handoff:
  - `source`
  - `table_id`
  - `table_ids`
  - `reservation_id`
  - `reservation_row_version`
  - `order_id`
  - `order_row_version`
  - `station_id`
- Conversation inbox state is deep-linkable on `/ops/conversations`:
  - `status`
  - `assignment`
  - `channel`
  - `q`
  - `page`
  - `conversation`
  - `tab`

Reload, back/forward, and copied internal links should preserve the same workflow context.

## Frontend Data Strategy

- The backend stays the source of truth.
- No fake seed data or optimistic client-side mutation cache.
- Page-level queries stay route-scoped and capability-gated until they are extracted into domain query modules.
- Persisted flow state is subordinate to route state.
- Shell branch context is explicit, and route-scoped warnings must surface known backend scope gaps.

## Verification Surface

- Type safety: `npx tsc --noEmit`
- Route/query/state helpers: targeted Vitest files under `src/app`, `src/shared`, `src/workspaces`, and `src/domains`
- Mounted staff-web behavior: focused tests on route pages under `src/workspaces/*/pages`
