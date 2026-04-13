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

## Canonical Runtime

- `src/App.tsx`
  - mounts the current staff-web application shell only
- `src/app/router/index.tsx`
  - owns the mounted route tree
  - boots the authenticated session
  - redirects authenticated staff to `/access`
- `src/app/layout/StaffAppShell.tsx`
  - shared shell for all mounted staff routes
  - holds branch selection, session refresh/logout, current route context, and scoped notices

Legacy files such as `src/app/router.tsx`, `src/components/shell/StaffShell.tsx`, and older page surfaces remain in the repo but are not part of the mounted runtime.

## Route Tree

- `/login`
- `/access`
  - default authenticated landing page
  - shows startup readiness, trusted branch context, and recommended next action
- `/tables`
  - gate: `table.board.view`
- `/reservations`
  - gate: `reservation.manage`
- `/waiting-list`
  - gate: `waiting_list.manage`
- `/orders`
  - gate: `order.manage`
- `/kitchen`
  - gate: `kitchen.manage`
- `/checkout`
  - gate: `settlement.manage`
  - hosts settlement plus reservation-linked refund actions; refund controls additionally require `payment.refund`
  - no dedicated mounted `/refunds` route in the active shell
- `/cashier-shift`
  - gate: `cashier.shift.manage`
- `/finance-review`
  - gate: `settlement.manage`
- `/conversations`
  - gate: `conversation.manage`
- `/audit-trail`
  - gate: `audit.view`
- `/reporting`
  - gate: `reporting.view`

## State Ownership

- `src/app/store/auth-store.ts`
  - session bootstrap, login, refresh, logout, expire notice
  - `/access` is the default authenticated route
  - `recommendedPathForSession()` picks the safest next operational screen
- `src/app/store/flow-store.ts`
  - branch context plus selected table/reservation/order/station ids
  - clears core operational context on branch change and session-owner change
- `src/hooks/useJourneyContext.ts`
  - applies route query params into the flow store
- `src/core/utils/journey.ts`
  - builds and reads route-owned operational context
  - now also builds a safe “resume current workflow” target for shell/access actions

## URL-Driven Operational State

- Journey state remains in query params for table/reservation/order/kitchen/checkout handoff:
  - `source`
  - `table_id`
  - `reservation_id`
  - `reservation_row_version`
  - `order_id`
  - `order_row_version`
  - `station_id`
- Conversation inbox state is deep-linkable on `/conversations`:
  - `status`
  - `assignment`
  - `channel`
  - `q`
  - `page`
  - `conversation`
  - `tab`

This means reload, back/forward, and copied internal links preserve the same inbox triage state and selected detail panel.

## Frontend Data Strategy

- The backend stays the source of truth
- No fake seed data or optimistic client-side mutation cache
- Page-level queries stay route-scoped and capability-gated
- Persisted flow state is subordinate to route state
- Shell branch context is explicit, and route-scoped warnings must surface known backend scope gaps instead of implying false confidence

## Verification Surface

- Type safety: `npx tsc --noEmit`
- Route/query/state helpers: targeted Vitest files under `src/core/utils`, `src/app/store`, and per-feature tests
- Mounted staff-web behavior: focused tests on the new route surfaces under `src/features/*`
