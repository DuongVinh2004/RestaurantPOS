# Codex Implementation Batches — FE to 100% Backend Coverage

Rules for all batches:

- Do not edit Laravel backend unless a contract bug is proven and explicitly requested later.
- Do not edit generated SDK files by hand.
- Do not hard-code API paths when an SDK/client/adapter pattern already exists.
- Every mutation must use `Idempotency-Key` where backend route requires it.
- Every stale-write mutation must send latest `row_version` and handle 409/422 conflict recovery.
- Feature-gated domains must remain closed by default in production-like posture.
- Add tests in the same batch as implementation.

## Batch A — Customer Wave-1 proof closure

**Goal**: Convert customer booking core from strong static coverage to release-proof coverage.

**Scope**:
- `customer-web/src/features/table-booking/*`
- `customer-web/src/features/reservations/*`
- `customer-web/e2e/customer-live.spec.ts`

**Tasks**:
1. Add/review tests for table availability empty/error/loading states.
2. Add tests for hold create/refresh/cancel with session id, idempotency, optional row_version.
3. Add tests for reservation create/list/detail using held table context.
4. Add conflict/expired hold recovery tests.
5. Add live smoke notes to the report after running.

**DoD**:
- `npm run verify:wave-1` passes.
- `npm run test:e2e:live` passes against fresh UAT pack.
- No dev mocks are enabled in live proof.

## Batch B — Customer preorder gated implementation

**Goal**: Finish preorder while keeping `NEXT_PUBLIC_FEATURE_PREORDER` default-off.

**Scope**:
- `customer-web/src/features/preorder/*`
- `customer-web/src/features/menu/api.ts`
- `customer-web/src/features/reservations/reservation-detail-page.tsx`
- `customer-web/src/lib/config/support-matrix.ts` only if status copy/evidence changes; do not widen default exposure.

**Routes**:
- `POST /api/v1/menu/preorder/preview`
- `GET /api/v1/reservations/{id}/preorder`
- `POST /api/v1/reservations/{id}/preorder/preview`
- `PUT /api/v1/reservations/{id}/preorder`
- `DELETE /api/v1/reservations/{id}/preorder`

**Tasks**:
1. Add adapter methods if missing using generated SDK.
2. Implement cart/preview/replace/clear flow with reservation `row_version` and conditional `pre_order_row_version`.
3. Add disabled state when feature flag off.
4. Add tests for gated/off, preview, replace, clear, stale row_version.

**DoD**:
- Preorder is usable when flag is on and invisible/disabled when flag is off.
- Mutation contract is satisfied.

## Batch C — Customer deposit/bill self-pay posture

**Goal**: Separate read visibility from payment-session promotion.

**Scope**:
- `customer-web/src/features/deposit/*`
- `customer-web/src/features/billing/*`
- `customer-web/src/lib/config/support-matrix.ts`

**Tasks**:
1. Confirm every payment session action sends `row_version`, `session_id`, `Idempotency-Key`.
2. Add provider posture/disabled reason UI.
3. Add status refresh handling for payment sessions.
4. Add tests for disabled production-like bill self-pay.
5. Add optional live exercise docs/env usage.

**DoD**:
- Deposit/bill reads are visible when backend returns data.
- Money-moving actions are disabled unless runtime/provider posture allows them.

## Batch D — Staff POS day-1 money path

**Goal**: Complete and prove staff core flow.

**Scope**:
- `staff-web/src/workspaces/ops/pages/tables/*`
- `staff-web/src/workspaces/ops/pages/reservations/*`
- `staff-web/src/workspaces/ops/pages/orders/*`
- `staff-web/src/workspaces/ops/pages/checkout/*`
- `staff-web/src/workspaces/ops/pages/refunds/*`
- `staff-web/src/workspaces/ops/pages/cashier-shift/*`
- `staff-web/src/workspaces/ops/pages/finance-review/*`
- `staff-web/src/shared/api/staff-api.ts`
- `staff-web/src/shared/mutations/mutation-ux.ts`

**Tasks**:
1. Verify all ops mutations use API helper with idempotency.
2. Add latest row_version refresh after board/reservation/order mutations.
3. Complete order item update/status UI and conflict handling.
4. Ensure checkout finalize is blocked without active shift.
5. Add refund and refund-cancel flow tests.
6. Add finance invoice issue/reconciliation refresh tests.

**DoD**:
- Staff live smoke covers board -> reservation/walk-in -> order -> add item -> bill -> pay/finalize -> refund -> finance.

## Batch E — Waiting-list coordination

**Goal**: Staff manual day-1 path + customer gated wave-2 path.

**Scope**:
- `staff-web/src/workspaces/ops/pages/waiting-list/*`
- `customer-web/src/features/waiting-list/*`
- `staff-web/src/shared/api/staff-api.ts`

**Tasks**:
1. Staff manual notify/seat/cancel with row_version.
2. Staff create/advance stays clear: advance is not day-1 automation unless explicitly enabled.
3. Customer owner accept/confirm-arrival/decline/cancel behind flag.
4. Change feed polling/status banner.
5. Tests for capability denied, no board access fallback, stale row_version.

**DoD**:
- Staff manual route works; customer route remains gated.
- No fake notification/realtime claims.

## Batch F — KDS gated promotion

**Goal**: Finish kitchen board without breaking day-1 flag posture.

**Scope**:
- `staff-web/src/workspaces/kitchen/*`
- `staff-web/src/domains/kitchen/*`
- `staff-web/src/shared/api/staff-api.ts`

**Tasks**:
1. Station selection and branch guard.
2. Read-only board when mutation flag/capability absent.
3. Dispatch/fire/bump/recall with row_version/idempotency when enabled.
4. Tests for missing station, capability denied, gated mutation, conflict recovery.

**DoD**:
- KDS mutations can only run under explicit rollout conditions.

## Batch G — Admin settings + menu management

**Goal**: Cover core admin configuration and menu master data.

**Scope**:
- `staff-web/src/workspaces/admin/pages/settings/*`
- `staff-web/src/workspaces/admin/pages/catalog/*`
- `staff-web/src/workspaces/admin/pages/components/AdminMasterDataImportPanel.tsx`
- `staff-web/src/shared/api/staff-api.ts`

**Tasks**:
1. Branch CRUD/update row_version.
2. Table CRUD/delete guard/import/export/templates/zones.
3. Kitchen station/category route config.
4. Tax profile and reporting snapshot rebuild.
5. Menu category/item/price CRUD/import/export.
6. Tests for dry-run/commit imports.

**DoD**:
- Admin settings/menu routes are no longer `PARTIAL/MISSING` in matrix.

## Batch H — Inventory/procurement uplift

**Goal**: Implement inventory/procurement UI behind correct posture.

**Scope**:
- `staff-web/src/workspaces/admin/pages/inventory/*`
- `staff-web/src/domains/inventory/*`
- `staff-web/src/shared/api/staff-api.ts`

**Tasks**:
1. Ingredients list/create/update and movements.
2. Menu item recipe show/upsert.
3. Suppliers list/create/update.
4. Purchase orders list/create/update and receipts.
5. Gate advanced workflows according to `inventory.uplift` posture.
6. Tests for all mutations and row_version conflicts.

**DoD**:
- Inventory/procurement backend routes have UI + adapter + tests or documented gate.

## Batch I — Promotions, loyalty, privacy, conversations, reporting

**Goal**: Complete non-core contract-visible surfaces.

**Scope**:
- Customer benefits/privacy/data export features.
- Staff/admin voucher and loyalty flows.
- Staff conversation inbox.
- Audit/reporting pages.
- Admin privacy review.

**Tasks**:
1. Customer account benefits gated wallet and reservation mutations.
2. Admin vouchers/loyalty tiers/settings CRUD/import/export.
3. Staff loyalty adjustment/redeem and voucher apply/remove.
4. Conversation detail actions, signed files, internal notes, outbound replies.
5. Privacy request/data export and admin review lifecycle.
6. Reporting filters/exports and audit trail filters.

**DoD**:
- Each route in CSV is DONE, GATED with proof, or explicitly NOT_APPLICABLE.

## Batch J — Release evidence and report refresh

**Goal**: Convert implementation into release evidence.

**Tasks**:
1. Run backend SQL-first bootstrap and artifact generation.
2. Sync customer contracts.
3. Run customer release and live lanes.
4. Run staff integrity/build/vitest/live smoke.
5. Update `docs/audit/fe-be-coverage-summary.md` and CSV statuses with evidence.

**Commands**:

```bash
composer bootstrap:booking
composer api:artifacts
cd customer-web && npm run sync:contracts && npm run verify:release
cd ../staff-web && npm run integrity:check && npm run build
```

```powershell
npm run dev:all
npm run dev:smoke
cd customer-web && npm run test:e2e:live
cd ../staff-web && npm run live:smoke
```

**DoD**:
- Reports are updated after real test output.
- No static-only claim remains marked DONE without test/live evidence where required.
