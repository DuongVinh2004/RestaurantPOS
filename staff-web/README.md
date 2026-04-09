# RestaurantPOS Staff Web

`staff-web` la frontend staff production-lean bind truc tiep vao generated SDK va canonical backend contracts cua repo Laravel nay.

## Scope hien tai

- Staff auth/session bootstrap qua `POST /api/v1/auth/staff/login`, `GET /api/v1/auth/staff/me`, `POST /api/v1/auth/staff/refresh`
- Staff auth session envelope nay la startup source chinh cho:
  - `startup.default_branch`
  - `startup.active_cashier_shift`
  - `startup.readiness`
- Capability-aware route tree cho:
  - `access`
  - `board`
  - `orders`
  - `settlement`
  - `refunds`
  - `cashier`
  - `conversations`
- Core staff flows:
  - table board + waiting notify/seat + check-in
  - create order + add items + order detail
  - bill snapshot + settlement preview + finalize
  - refund preview + refund + refund-cancel
  - cashier current/open/show/close
  - conversation inbox list/detail + take-over + internal note + guarded outbound reply

## Runtime caveats canh source-of-truth

- FE route/nav gating dung granted `capabilities` cong backend `startup.readiness`; `known_capabilities` chi la metadata tham khao.
- Runtime staff/core stale `row_version` hien tai thuong surfacing qua `422 validation_error` voi `errors.row_version` hoac `details.errors.row_version`, khong nen mac dinh la `409`.
- Error envelopes quan trong cho operator handling gom `error_code`, `required_capability`, `request_id`, `errors`, va `details.errors`.
- Order/refund/cashier da bind backend that, nhung lookup UX van production-lean:
  - uu tien board suggestions hoac current shift sources
  - manual IDs van duoc giu lam fallback cho historical/non-board cases
- Settlement nay uu tien canonical reservation lookup + reservation-order lookup truoc khi xuong manual `order_id`
- Board/waiting change cursors duoc background-poll theo visibility-aware cadence, chi refetch full slices khi backend bao co thay doi
- Conversations list co them `status`, `assignment_state`, `q` filters qua generated SDK query params

## Contracts va docs

- Architecture: `./STAFF_WEB_ARCHITECTURE.md`
- Backend contract inventory: `./STAFF_WEB_BACKEND_CONTRACTS.md`
- Setup/runbook: `./STAFF_WEB_SETUP.md`
- Roadmap/deferred work: `./STAFF_WEB_ROADMAP.md`
- Generated SDK source: `../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- Mutation matrix: `../build/api-consumer/mutation-contracts.md`

## Run

```bash
npm install
npm run lint
npm run test
npm run build
npm run smoke:live
```

`npm run smoke:live` now prefers the canonical UAT manifest at `../storage/app/uat/scenario-pack.json` when present, stays read-only by default, and only enables write paths behind explicit `STAFF_WEB_SMOKE_ALLOW_*` mutation gates. Smoke auth steps also assert the Batch 1 startup contract on login/me/refresh before moving deeper into board/order/cashier flows. Mutation mode also reuses canonical board/check-in metadata so the dine-in order-create path can promote a `Confirmed` reservation into the in-service state before creating the order.

For preview/staging evidence, set:

```bash
STAFF_WEB_SMOKE_TARGET=staging
STAFF_WEB_SMOKE_EVIDENCE_DIR=../storage/app/booking_release/staff_web_smoke
STAFF_WEB_SMOKE_PREVIEW_URL=https://preview.example.com
STAFF_WEB_SMOKE_PREVIEW_LABEL=vercel-preview
```

That makes the smoke harness write JSON/Markdown evidence files in addition to the console summary.
It only records preview metadata, though. A real preview candidate still needs an actual deployed URL plus runtime-log or release-tag evidence from the chosen platform.

Canonical backend + `staff-web` release candidate loop from the repo root:

```bash
composer release:loop -- --target=staging --manifest-path=storage/app/uat/scenario-pack.json --base-url=http://127.0.0.1:8000 --bootstrap-uat
```

## Environment

```bash
VITE_API_URL=http://localhost:8000/api/v1
```

Client se normalize base URL ve host goc ma generated SDK can.
