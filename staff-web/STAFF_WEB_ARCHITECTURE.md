# Staff Web Architecture

## Stack

- Vite
- React 18
- TypeScript
- Tailwind
- `react-router-dom`
- Generated OpenAPI SDK at `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`

## Route Tree

- `/login`
- `/access`
  - gate: authenticated staff session with zero granted capabilities
- `/board`
  - gate: `table.board.view` or `waiting_list.manage`
- `/orders`
  - gate: `order.manage`
- `/settlement`
  - gate: `settlement.manage`
- `/refunds`
  - gate: `payment.refund`
- `/cashier`
  - gate: `settlement.manage`
- `/conversations`
  - gate: `conversation.manage`

## App Shell

- `src/app/session.tsx`
  - restores staff session from stored opaque token
  - clears local session only on `401` restore/refresh failures
  - keeps the stored token on non-auth restore/refresh failures and surfaces a retry notice
- `src/app/router.tsx`
  - protected routes
  - capability route guards
- `src/components/shell/StaffShell.tsx`
  - shared nav
  - refresh/logout
  - backend host + session summary

## API Layer

- `src/api/client.ts`
  - wraps canonical SDK methods only
  - centralizes `Idempotency-Key` generation
  - exposes typed helpers for board, waiting, orders, settlement, refunds, cashier, conversations
- `src/lib/api-errors.ts`
  - normalizes backend error semantics
- `src/lib/conflicts.ts`
  - row_version conflict detection + operator-facing retry message
- `src/lib/capabilities.ts`
  - session capability checks

## Data Strategy

- No fake seed data and no axios fallback
- Page-level state with direct typed wrappers
- Explicit reload after successful mutations to keep backend as source of truth
- Visibility-aware background polling on board/waiting via changes endpoints; full board/waiting reload only when cursors report change
- No optimistic local mutation cache
- Operator lookup sources stay contract-lean:
  - orders/refunds prefer board suggestions before manual IDs
  - settlement prefers canonical reservation lookup plus reservation-order lookup before manual `order_id`
  - cashier prefers `GET /current` before manual shift lookup
  - conversations filter through generated SDK query params instead of client-only list slicing

## Concurrency + Idempotency

- All mutation wrappers in `src/api/client.ts` attach `Idempotency-Key`
- Row-versioned flows pull `row_version` from board, order detail, refund preview, or cashier shift payloads
- Runtime stale `row_version` is detected from actual payload semantics, including `422 validation_error` with `errors.row_version` or `details.errors.row_version`
- `409` is treated as conflict/idempotency-state handling, not assumed to be row_version drift

## Permission Strategy

- Session bootstrap uses granted capability list returned by backend auth session envelope
- Navigation and route guards derive directly from `session.capabilities`
- `session.known_capabilities` is metadata only and never treated as granted access
- Feature modules still respect backend `403`; client gating is advisory, backend remains authority

## Folder Structure

- `src/app`
  - router, sections, session
- `src/api`
  - client wrapper, sdk re-export
- `src/components`
  - login, shell, shared UI
- `src/features`
  - `board`
  - `orders`
  - `settlement`
  - `refunds`
  - `cashier`
  - `conversations`
- `src/lib`
  - formatting, errors, capabilities, conflicts, idempotency
- `src/test`
  - setup, fixtures, render helpers
