# Staff-Web MVP Smoke

This smoke path is for local and staging only. Do not point it at production data, and do not enable mutation gates unless the backend has been reset with disposable UAT fixtures.

## Scope

Day-1 MVP path:

1. Login.
2. Access/readiness screen.
3. Cashier shift current/open.
4. Table board.
5. Reservation check-in or waiting/walk-in.
6. Table/service-session assignment.
7. Active order create/read.
8. Add item and, when row versions are present, update quantity or status.
9. Bill snapshot and settlement preview.
10. Finalize payment.
11. Refund or refund-cancel when the fixture is safe.
12. Cashier shift close or finance review.

Kitchen, inventory, admin, reporting, and customer paths can stay mounted, but this runbook treats them as dependencies only when they are required to complete the MVP path.

## Environment

Required for local mutation smoke:

```powershell
$env:STAFF_WEB_SMOKE_API_URL='http://127.0.0.1:8000/api/v1'
$env:STAFF_WEB_SMOKE_TARGET='local'
$env:STAFF_WEB_SMOKE_MANIFEST_PATH='../storage/app/uat/scenario-pack.json'
```

Required when not using the UAT manifest credentials:

```powershell
$env:STAFF_WEB_SMOKE_IDENTIFIER='uat.staff'
$env:STAFF_WEB_SMOKE_PASSWORD='UatDemo!123'
$env:STAFF_WEB_SMOKE_DEVICE_NAME='staff-web-live-smoke'
```

Optional fixture selectors:

```powershell
$env:STAFF_WEB_SMOKE_RESERVATION_ID='77'
$env:STAFF_WEB_SMOKE_ORDER_TABLE_ID='11'
$env:STAFF_WEB_SMOKE_MENU_ITEM_IDS='201,202'
$env:STAFF_WEB_SMOKE_REFUND_RESERVATION_ID='91'
$env:STAFF_WEB_SMOKE_CASHIER_BRANCH_ID='1'
$env:STAFF_WEB_SMOKE_CASHIER_TERMINAL_CODE='staff-web-live-smoke'
```

Mutation gates are off by default. Enable only on local/staging UAT data:

```powershell
$env:STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE='1'
$env:STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM='1'
$env:STAFF_WEB_SMOKE_ALLOW_KITCHEN_DISPATCH='1'
$env:STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE='1'
$env:STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION='1'
$env:STAFF_WEB_SMOKE_ALLOW_CASHIER_OPEN='1'
$env:STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE='1'
```

Never set these gates for a production URL. The smoke evidence target must be `local`, `staging`, or a disposable preview label.

## Fixtures

Before a local mutation smoke, reset the backend and rebuild the UAT pack:

```powershell
composer bootstrap:booking
php artisan booking:uat-pack:bootstrap --base-url=http://127.0.0.1:8000 --json
```

Required fixture properties:

- Staff actor has day-1 capabilities for the path being tested: `table.board.view`, `reservation.manage`, `waiting_list.manage`, `order.manage`, `settlement.manage`, `payment.refund`, and `cashier.shift.manage`.
- Session payload includes `startup.available_workspaces`, `startup.primary_workspace`, `startup.readiness`, `startup.allowed_branch_ids`, and `known_capabilities`.
- The dine-in reservation is active enough to check in and create an order.
- The menu item ids are available for the current service time.
- Refund fixture has captured payment lineage and a positive refund preview.
- MySQL is bootstrapped. Redis must be reachable when `REQUIRE_REDIS_FOR_BOOKING_API=true`.

If MySQL or Redis is unavailable, classify the result as runtime/environment failure, not a staff-web fix.

## Static Checks

Run before manual or live smoke:

```powershell
cd staff-web
npm run integrity:check
npm run build
```

The build includes the generated SDK freshness check and verifies that critical staff-web calls compile against the frozen OpenAPI contract.

## Live API Smoke

Read-only API smoke:

```powershell
cd staff-web
npm run smoke:live
```

Mutation-gated local/staging API smoke:

```powershell
cd staff-web
$env:STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE='1'
$env:STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM='1'
$env:STAFF_WEB_SMOKE_ALLOW_KITCHEN_DISPATCH='1'
$env:STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE='1'
$env:STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION='1'
$env:STAFF_WEB_SMOKE_ALLOW_CASHIER_OPEN='1'
$env:STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE='1'
npm run smoke:live
```

Expected API checkpoints:

| Step | Route | Expected |
| --- | --- | --- |
| Health | `GET /api/v1/health` | `200`, DB/Redis/scheduler checks explain runtime blockers |
| Login | `POST /api/v1/auth/staff/login` | `200`, session contains granted `capabilities` and startup metadata |
| Session | `GET /api/v1/auth/staff/me`, `POST /api/v1/auth/staff/refresh` | `200`, same capability/startup contract |
| Board | `GET /api/v1/staff/tables/board` | `200`, rows include action metadata and row versions |
| Board changes | `GET /api/v1/staff/tables/board/changes` | `200`, realtime cursor payload |
| Waiting | `GET /api/v1/staff/waiting-list` | `200`, entries include row versions |
| Reservations | `GET /api/v1/staff/reservations` | `200`, active fixture is selected |
| Check-in | `POST /api/v1/staff/reservations/{id}/check-in` | `200` or `201`, sends `row_version` and `Idempotency-Key` |
| Order create | `POST /api/v1/staff/tables/{table_id}/orders` | `200` or `201`, sends reservation/table context, `row_version`, and `Idempotency-Key` |
| Order read | `GET /api/v1/staff/orders/{order_id}` | `200`, order and item row versions are visible |
| Add item | `POST /api/v1/staff/orders/{order_id}/items` | `200` or `201`, sends order `row_version` and `Idempotency-Key` |
| Kitchen dispatch | `POST /api/v1/staff/orders/{order_id}/kitchen/dispatch` | `200` or `201`, sends order `row_version` and `Idempotency-Key` |
| Settlement preview | `GET /api/v1/staff/orders/{order_id}/settlement-preview` | `200`, includes payable amount and row version |
| Settlement finalize | `POST /api/v1/staff/orders/{order_id}/settlement/finalize` | `200` or `201`, sends order `row_version` and `Idempotency-Key` |
| Refund preview | `GET /api/v1/staff/reservations/{id}/refund-preview` | `200`, includes refund amount, currency, and reservation row version |
| Refund execute | `POST /api/v1/staff/reservations/{id}/refund` | `200` or `201`, sends preview reservation `row_version` and `Idempotency-Key` |
| Cashier current/open/show/close | `/api/v1/staff/cashier/shifts*` | `200`, `201`, or `404` for no current shift; close sends shift `row_version` and `Idempotency-Key` |

Expected failure handling:

- `403` or policy denial means the session lacks a granted capability or branch scope; do not use `known_capabilities` as a grant.
- Stale `row_version` appears as `409` or `422` with row-version details; UI must show a refresh-needed state and must not blind retry.
- Idempotency mismatch appears as conflict; use a new UI action after refresh, not the old key with a different payload.
- `404` on canonical UAT ids usually means stale fixtures; rerun `booking:uat-pack:bootstrap`.

## Manual UI Smoke

Use a local Vite preview or staging deployment connected to the same non-production backend.

1. Open `/login`.
   - Expected: staff login succeeds, shell stores the staff session, and access/readiness becomes visible.
   - Verify: workspace tiles come from `startup.available_workspaces`. If a workspace is listed only in `known_capabilities`, it must not become clickable.

2. Open `/access`.
   - Expected: readiness shows branch/cashier/operator state from startup metadata.
   - Verify: missing capabilities hide or deny day-1 actions instead of rendering active mutation buttons.

3. Open `/ops/cashier`.
   - Expected: current shift loads or the page shows no open shift.
   - If opening is allowed: open a shift and verify the response row version is shown.
   - If closing is allowed: close only a disposable UAT shift; close must use the displayed shift row version.

4. Open `/ops/tables`.
   - Expected: board rows load for the selected branch.
   - Verify: check-in, assign, seat, move, release, and create-order entry points are hidden when the session lacks capability.
   - Verify: actions with row-version metadata use that version; stale response shows refresh-needed state.

5. Check in a reservation or create/seat a waiting entry.
   - Expected: mutation sends `row_version` and `Idempotency-Key`, then board/reservation views refresh.
   - Double-click check: repeated click while pending must not create duplicate service state.

6. Open `/ops/orders` with the table/reservation journey context.
   - Expected: active order loads or create-order form is shown.
   - Create order: requires table id, reservation id, reservation row version, and idempotency.
   - Add item: requires order row version and idempotency.
   - Quantity/status update: enabled only when both order and item row versions are visible.

7. Optional kitchen handoff.
   - Expected: dispatch button is disabled until order row version is available.
   - Expected: KDS fire/bump/recall buttons are disabled until ticket row version is available.

8. Open `/ops/checkout`.
   - Expected: settlement preview loads; payment finalize is blocked until the cashier shift requirement is satisfied.
   - Finalize: sends order row version and idempotency. Stale row-version conflicts show the mutation status conflict notice with a refresh action.

9. Open `/ops/refunds` or use the refund panel on checkout.
   - Expected: refund mutation stays disabled until preview matches current mode, amount, scope, and currency.
   - Execute only against safe UAT refund fixtures; use the row version from refund preview.

10. Open `/ops/finance-review`.
    - Expected: finance rows reflect settlement/refund activity for the same branch and reservation.

## Triage

- Build or integrity failure: inspect generated SDK freshness and `staff-web/src/shared/api/staff-api.generated.test.ts` before changing route usage.
- Capability failure: compare `capabilities`, `known_capabilities`, `startup.available_workspaces`, and route metadata. Only `capabilities` grant action access.
- Branch mismatch: confirm shell branch id is in `startup.allowed_branch_ids` or `startup.branch_access.accessible_branch_ids`.
- Stale write: refresh the page or affected query, then repeat with the latest row version.
- Duplicate mutation: check `Idempotency-Key` and pending button state before changing backend services.
- Runtime failure: run `php artisan booking:doctor --json`, `npm run preflight:local`, and inspect `storage/logs/laravel.log`.
