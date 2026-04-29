# Staff Web Backend Contracts

## Source Of Truth

- Consumer truth:
  - `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
  - `../build/api-consumer/mutation-contracts.md`
- Backend route truth:
  - `../routes/api/auth.php`
  - `../routes/api/staff_pos.php`

## Route Surface Governance

- Staff-web routes must be backed by the frozen OpenAPI artifact in `../storage/app/booking_release/openapi-v1.json`.
- Full-contract routes must avoid `GenericDataEnvelope`; `tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php` guards the current staff-web full route set.
- Raw `apiRequest` additions in `src/shared/api/staff-api.ts` must update the explicit route allowlist in `src/shared/api/staff-api.generated.test.ts`.
- Routes still allowed as fallback are limited to the documented transition set: admin inventory writes, legacy reservation status, finance review, order-item edits, floor assignment helpers, table release/active-order helpers, and staff waiting-list create/advance/cancel.
- Realtime change feeds for table board, kitchen, and waiting list must use generated SDK delegates and carry `branch_id` when a branch is active.

## Auth + Session

### Canonical routes

- `POST /api/v1/auth/staff/login`
- `GET /api/v1/auth/staff/me`
- `POST /api/v1/auth/staff/refresh`
- `POST /api/v1/auth/staff/logout`

### Contract notes

- Auth header: `X-Staff-Key`
- Refresh-cookie rollout is opt-in:
  - staff-web sends `session_transport=refresh_cookie` on login only when `VITE_STAFF_REFRESH_COOKIE_ENABLED=true`
  - backend must have `STAFF_AUTH_BROWSER_SESSION_COOKIE_ENABLED=true`
  - backend sets HttpOnly `Secure` SameSite refresh cookie `staff_web_refresh`
  - backend sets readable CSRF cookie `staff_web_csrf`; staff-web echoes it as `X-Staff-CSRF` on cookie-backed refresh/logout
  - `POST /api/v1/auth/staff/refresh` with refresh cookie returns a new memory-only `access_token`; it must not expose the refresh cookie secret
  - `POST /api/v1/auth/staff/logout` clears both cookies and revokes the refresh/session key
  - CORS credentials are only valid with exact `CORS_ALLOWED_ORIGINS`; no wildcard credential mode
- Session envelope now includes:
  - `capabilities`
  - `known_capabilities`
  - `capability_source`
  - `startup.primary_workspace`
  - `startup.available_workspaces`
  - `startup.default_branch_id`
  - `startup.allowed_branch_ids`
  - `startup.assigned_station_ids`
  - `startup.default_branch`
  - `startup.active_cashier_shift`
  - `startup.readiness`
- Runtime `capability_source` for the current staff auth pipeline is `role_capabilities`
- FE route guards/nav chi duoc gate bang granted `capabilities`
- `known_capabilities` chi la metadata ve capability catalog, khong phai grant runtime
- Staff-web must prefer the startup workspace contract for persona landing and switcher state. Capability-derived workspace mapping is only a compatibility fallback when older payloads do not include `startup.available_workspaces`.
- `startup.default_branch_id` and `startup.allowed_branch_ids` are the lightweight branch bootstrap contract; the nested `startup.default_branch` and `startup.branch_access` objects remain for labels, readiness, and branch selector metadata.
- `startup.assigned_station_ids` is the lightweight kitchen bootstrap contract. Until a dedicated per-staff kitchen identity model exists, backend may return all active station ids visible to kitchen-capable staff or an empty list when station context is unavailable.
- Post-login redirect, access gate, cashier-shift gating, shell notices, va startup-driven warnings phai doc tu `startup.readiness`; khong duoc tu suy doan lai bang hard-coded raw strings rai rac
- FE source of truth hien tai:
  - `src/shared/auth/capabilities.ts` cho granted capability checks
  - `src/app/auth/startup.ts` cho startup/readiness predicates
- Error envelope quan trong cho staff-web:
  - top-level `error_code`
  - top-level `request_id`
  - `required_capability` tren mot so `403`
  - validation details co the nam o `errors` hoac `details.errors`
- Main errors:
  - `401` invalid/expired opaque token
    - FE phai clear local session/token khi backend xac nhan het han/khong hop le
  - `403` capability or actor-scope mismatch
    - FE khong duoc auto-expire session cho `403`; phai giu token va surfacing gate/error cho operator
  - `422` bad credentials or malformed payload
  - `419` missing/mismatched CSRF header on cookie-backed refresh/logout

## Board + Waiting

### Canonical routes

- `GET /api/v1/staff/tables/board`
- `GET /api/v1/staff/tables/board/changes`
- `GET /api/v1/staff/waiting-list`
- `GET /api/v1/staff/waiting-list/changes`
- `POST /api/v1/staff/reservations/{id}/check-in`
- `POST /api/v1/staff/reservations/{id}/move-table`
- `POST /api/v1/staff/tables/{table_id}/release`
- `POST /api/v1/staff/waiting-list/{id}/notify`
- `POST /api/v1/staff/waiting-list/{id}/seat`

### Capability gates

- `table.board.view`
- `waiting_list.manage`

### Concurrency notes

- `check-in`, `notify`, `seat` all require `row_version`
- `move-table` requires `from_table_id`, `to_table_id`, and current reservation `row_version`
- `release table` is a write-side board action and still requires `Idempotency-Key`
- Mutations require `Idempotency-Key`
- Main mutation errors:
  - `401` auth failure
  - `403` capability or branch boundary
  - `409` idempotency/state conflict
  - `422` payload validation, including runtime stale `row_version`
- FE phai doc stale row_version tu `errors.row_version` hoac `details.errors.row_version`, khong duoc mac dinh `409`

### Journey continuity notes

- Active FOH handoff phai giu `source`, `table_id`, `table_ids`, `reservation_id`, `reservation_row_version`, va `order_id`/`order_row_version` neu da co order.
- `board -> reservation`, `reservation -> check-in`, `waiting -> seat`, va `move-table -> order workspace` deu phai merge URL journey params voi flow-store thay vi tu suy lai context.
- `conversation -> waiting-list` phai mo `/ops/waiting-list?focus=<waiting_id>` de khoa dung dong hang cho da lien ket thay vi nhay ve dong dau tien trong danh sach.
- FE duoc phep dung realtime change feeds (`/board/changes`, `/waiting-list/changes`) de trigger refetch full slice; feed do khong phai source of truth thay the payload board/waiting canonical.

### Deprecated alias to avoid

- `GET /api/v1/staff/table-board`

## Orders

### Canonical routes

- `GET /api/v1/staff/tables/{table_id}/active-order`
- `GET /api/v1/staff/reservations/{reservation_id}/active-order`
- `POST /api/v1/staff/tables/{table_id}/orders`
- `POST /api/v1/staff/orders/{order_id}/items`
- `GET /api/v1/staff/orders/{order_id}`
- `POST /api/v1/staff/orders/{order_id}/kitchen/dispatch`
- `GET /api/v1/staff/kitchen/stations/{station_id}/tickets`
- `POST /api/v1/staff/orders/{order_id}/bill-snapshot`

### Capability gate

- `order.manage`
- `GET /api/v1/staff/kitchen/*` routes now require `kitchen.manage`; backend still accepts legacy `order.manage` during role backfill so staff-web can roll forward safely.

### Concurrency notes

- Create/add-items/bill-snapshot require `row_version`
- Mutations require `Idempotency-Key`
- FE uses board reservation metadata or loaded order detail as row_version source
- Neu `order_id` tren URL bi stale/404, FE phai recover lai qua `active-order-by-table` / `active-order-by-reservation` truoc khi cho phep mutation tiep.
- Handoff `order -> kitchen` phai mang theo `station_id` tu dispatch response, khong duoc tu default station dau tien.
- Runtime stale `row_version` hien tai surfacing chu yeu qua `422 validation_error`

## Settlement

### Canonical routes

- `GET /api/v1/staff/reservations`
- `GET /api/v1/staff/reservations/{reservation_id}/orders`
- `GET /api/v1/staff/orders/{order_id}/settlement-preview`
- `POST /api/v1/staff/orders/{order_id}/pay`
- `POST /api/v1/staff/orders/{order_id}/settlement/finalize`

### Capability gate

- `settlement.manage`

### Concurrency notes

- Pay/finalize require `row_version`
- Pay/finalize require `Idempotency-Key`
- Pay supports partial capture; full capture returns the same settlement envelope as finalize and requires an immutable bill snapshot on completion.
- Preview is read-only and does not require idempotency
- `409` duoc de danh cho idempotency/state conflict; stale `row_version` can surface qua `422`
- staff-web nen uu tien reservation lookup + reservation-order lookup de lay current/historical order truoc khi yeu cau manual `order_id`

## Refunds

### Canonical routes

- `GET /api/v1/staff/reservations/{reservation_id}/refund-preview`
- `POST /api/v1/staff/reservations/{reservation_id}/refund`
- `POST /api/v1/staff/reservations/{reservation_id}/refund-cancel`

### Capability gate

- `payment.refund`

### Concurrency notes

- Preview returns reservation summary with current `row_version`
- Refund and refund-cancel require that `row_version`
- Both mutations require `Idempotency-Key`
- FE nen uu tien preview envelope lam row_version source; stale row_version runtime hien tai surfacing qua `422`

## Cashier

### Canonical routes

- `GET /api/v1/staff/cashier/shifts/current`
- `POST /api/v1/staff/cashier/shifts/open`
- `GET /api/v1/staff/cashier/shifts/{shift_id}`
- `POST /api/v1/staff/cashier/shifts/{shift_id}/close`

### Capability gate

- `cashier.shift.manage`
- Backend still accepts legacy `settlement.manage` during role backfill.

### Concurrency notes

- Open requires `Idempotency-Key`, no `row_version`
- Close requires both `row_version` and `Idempotency-Key`
- `GET current` may return `404` when staff has no open shift
- staff-web uu tien hydrate open/lookup context tu `GET current`; manual shift ID van la fallback

## Reporting

### Canonical routes

- `GET /api/v1/staff/reporting/daily-sales`
- `GET /api/v1/staff/reporting/daily-operations`
- `GET /api/v1/staff/reporting/daily-inventory`

### Capability gate

- `reporting.view`
- Backend still accepts legacy `settlement.manage` during role backfill.

## Branch Context

### Canonical routes

- `GET /api/v1/staff/branches`

### Contract notes

- Route now returns only operationally accessible branches for the authenticated actor.
- Current operational scope is the default branch plus any branch where that actor has an open cashier shift.
- `GET /api/v1/staff/reservations/{reservation_id}` and staff kitchen branch filters fail closed to `404` when the requested branch is outside that operational scope.

## Finance Review

### Canonical routes used by staff-web

- `GET /api/v1/staff/finance/reconciliation`
- `GET /api/v1/staff/finance/reconciliation/{reservation_id}`
- `GET /api/v1/staff/finance/invoices/{reservation_id}`
- `POST /api/v1/staff/finance/invoices/{reservation_id}/issue`
- `GET /api/v1/staff/finance/accounting-export`

### Contract notes

- Reconciliation list/detail va finance invoice envelopes hien tai deu tra `reservation.row_version`
- staff-web phai mang `reservation_row_version` khi mo lai `/ops/reservations` tu finance review
- Finance review la lane hau-kiem tra sau thanh toan; no khong thay the settlement/refund guards tren mutation path
- Reconciliation list/detail, finance invoice show, va accounting export hien tai mac dinh fail closed theo operational branch scope cua actor xac thuc
- Explicit `branch_id` ngoai operational scope tra `404 not_found` thay vi list rong

## Audit Trail

### Canonical routes used by staff-web

- `GET /api/v1/staff/audit-trail`

### Contract notes

- Audit trail list hien tai mac dinh chi tra ve cac event thuoc operational branch scope cua actor xac thuc
- Explicit `branch_id` ngoai operational scope tra `404 not_found`
- `request.branch_id` van la field on dinh staff-web co the dung de giu visible branch context trong panel triage

## Conversations

### Canonical routes used by staff-web

- `GET /api/v1/staff/conversations`
- `GET /api/v1/staff/conversations/{conversation_id}`
- `POST /api/v1/staff/conversations/{conversation_id}/take-over`
- `POST /api/v1/staff/conversations/{conversation_id}/internal-notes`
- `POST /api/v1/staff/conversations/{conversation_id}/outbound-replies`

### Capability gate

- `conversation.manage`

### Notes

- List route query params da duoc staff-web dung toi thieu cho `status`, `assignment_state`, va `q`
- Detail payload exposes conversation capabilities used by FE to lock outbound reply action
- `conversation.manage` la capability route-level; outbound reply van phai ton trong `data.capabilities.outbound_reply` cua detail payload
- linked waiting-list handoff hien tai duoc mo bang `/waiting-list?focus=<waiting_id>` o FE de khoa dung queue item
- Mutations use `Idempotency-Key`
- `403` co the tra `required_capability`; FE surfaces truong nay cho operator

## Deferred / Caveats

- Khong co endpoint bootstrap rieng ngoai auth session envelope
  - `GET /api/v1/auth/staff/me` va `POST /api/v1/auth/staff/refresh` la startup/readiness bootstrap contract hien tai
- Board/waiting changes endpoints nay duoc background-poll tren FE, nhung backend van la source of truth va FE chi refetch full slices khi cursors bao co thay doi
- Order/refund/cashier lookup van production-lean:
  - board suggestions/current shift sources duoc uu tien
  - manual IDs van duoc giu cho non-board/historical cases
- FE intentionally avoids deprecated aliases:
  - `/staff/table-board`
  - `/staff/orders/{order_id}/close`
  - `/staff/orders/{order_id}/checkout`
