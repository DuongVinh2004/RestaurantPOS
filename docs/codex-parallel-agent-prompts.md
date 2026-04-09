# Codex Parallel Workstream Prompts

## Muc dich

Tai lieu nay gom cac prompt copy-paste san de mo nhieu chat Codex AI Agent song song cho repo `C:\Users\Duong Vinh\RestaurantPOS-Laravel`.

Muc tieu:
- Chia nho thanh cac workstream co the lam song song.
- Giam xung dot file khi merge.
- Giu dung huong theo `AGENTS.md`: harden va hoan thien flow cot loi, khong overbuild.

## Cach su dung

1. Mo 6 chat song song cho cac task A-F.
2. Moi chat su dung dung prompt cua task tuong ung.
3. Merge theo thu tu khuyen nghi o cuoi tai lieu.
4. Sau khi A-F merge xong, mo them chat G de dong bo SQL-first contract.
5. Cuoi cung mo 1 chat Integrator Pass de ra soat toan bo.

## Nguyen tac dieu phoi

- Moi chat chi duoc sua trong vung so huu cua minh.
- Han che sua cac file shared:
  - `routes/api.php`
  - `config/booking.php`
  - `config/staff_capabilities.php`
  - `database/schema/mysql-schema.sql`
- Neu buoc phai sua file shared:
  - chi sua toi thieu
  - ghi ro trong bao cao la `shared file touched`
  - khong tranh thu mo rong scope

## Branch naming goi y

- `codex/auth-rbac-hardening`
- `codex/foh-core-hardening`
- `codex/checkout-finance-hardening`
- `codex/kitchen-foundation-maturation`
- `codex/inventory-foundation-hardening`
- `codex/ops-observability-hardening`
- `codex/schema-release-contract-sync`
- `codex/integration-pass`

## Prefix dung chung cho moi chat

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc tuan thu AGENTS.md cua repo:
- Doc code hien co truoc khi sua.
- Khong overbuild; uu tien harden va hoan thien flow cot loi.
- Keep controllers thin.
- Put business logic in services/domain logic.
- Add/update tests cho moi thay doi co y nghia.
- Protect production safety: transactions, idempotency, locking, authorization, audit.
- Work in small reviewable batches.
- Khong rewrite lon, khong cosmetic refactor, khong dong vao vung ngoai scope neu khong that su can.

Yeu cau thuc thi:
- Khong dung o phan tich; hay thuc su code va them test neu khong bi blocker.
- Uu tien giu nguyen API contract hien co cho batch nay.
- Neu bat buoc phai sua file shared ngoai scope (vi du routes/api.php, config/booking.php, database/schema/mysql-schema.sql), hay toi thieu hoa thay doi va neu ro ly do.
- Cuoi cung bao cao theo format:
  1. Intent
  2. Changed files
  3. Added/updated tests
  4. Remaining risks
```

## Task A - Auth / RBAC hardening

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Harden authorization and route-capability coverage without broad redesign.

Muc tieu:
- Siet chat authorization cho staff/admin/customer scope.
- Dong cac lo hong route-capability mapping neu co.
- Tang regression tests cho allow/deny paths o cac flow rui ro cao.

Scope so huu:
- config/staff_capabilities.php
- app/Http/Middleware/RequireStaffCapability.php
- app/Http/Middleware/StaffApiKeyMiddleware.php
- app/Http/Middleware/ResolveCustomerAuthMiddleware.php
- app/Support/StaffActorResolver.php
- tests/Feature/Auth/*
- tests/Feature/Staff/StaffCapabilityHttpGuardTest.php
- cac test route-surface/capability lien quan trong tests/Feature/Admin va tests/Feature/Staff

Viec can lam:
1. Review cac route mutation quan trong hien co va kiem tra capability coverage thuc te.
2. Voi cac route staff/admin rui ro cao, dam bao co guard ro rang va test regression tuong ung.
3. Bo sung test cho cac capability quan trong nhu reservation/order/payment/inventory/settings/reporting neu dang thieu deny-path hoac wrong-scope coverage.
4. Kiem tra khong co bleed giua customer scope va staff scope.
5. Giu nguyen API contract hien co; khong redesign auth stack trong batch nay.

Out of scope:
- Khong sua business logic reservation/checkout/kitchen/inventory ngoai phan authorization guard.
- Khong lam policy-system lon neu phai rewrite rong.

Acceptance criteria:
- Capability coverage chat hon o cac route quan trong.
- Co regression tests ro cho authorized va unauthorized access.
- Khong mo rong scope sang domain logic khac.
```

## Task B - Reservation / FOH core hardening

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Harden reservation + front-of-house core flows for production safety, keeping existing endpoints.

Muc tieu:
- Siet row_version, locking, branch scope, conflict handling, idempotency assumptions.
- Tang test coverage cho core dining-room operations.
- Giam rui ro lech hanh vi giua logic ung dung va SQL-first contract.

Scope so huu:
- app/Services/ReservationService.php
- app/Services/TableAvailabilityService.php
- app/Services/TableHoldService.php
- app/Services/TableTimeConflictService.php
- app/Services/Staff/StaffTableBoardService.php
- app/Services/Staff/StaffReservationBoardAssignmentService.php
- app/Services/Staff/StaffCheckInService.php
- app/Services/Staff/StaffMoveTableService.php
- tests/Feature/Reservation/*
- tests/Feature/Table/*
- tests/Feature/Staff/StaffTableBoard*.php
- tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php
- tests/Feature/Staff/StaffCheckInFlowTest.php
- tests/Feature/Staff/StaffMoveTableFlowTest.php
- tests/Feature/Staff/StaffFrontOfHouseOperationalClosureFlowTest.php

Viec can lam:
1. Review end-to-end chain: availability -> hold -> assign -> check-in -> move-table -> release guard.
2. Bo sung hoac siet cac guard con mong quanh overlap, stale row_version, branch drift, hold conflict, assignment idempotency.
3. Tang regression tests cho cac edge case production-sensitive.
4. Khong doi API contract; uu tien hardening va test.

Out of scope:
- Khong dong vao checkout/payment/webhook.
- Khong dong vao inventory/kitchen ngoai dependency hien co.

Acceptance criteria:
- Core FOH flows chat hon ve concurrency/state safety.
- Test matrix rong hon cho negative cases va stale/concurrent paths.
```

## Task C - Checkout / Payments / Finance hardening

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Harden settlement, refund, webhook, cashier, invoice, and reconciliation flows.

Muc tieu:
- Bao ve financial consistency tot hon.
- Mo rong coverage cho duplicate requests, idempotency replay, refund lineage, stale row_version, branch scope, and settlement race conditions.
- Giu nguyen API contract hien co.

Scope so huu:
- app/Services/Staff/StaffCheckoutService.php
- app/Services/Staff/BillLockService.php
- app/Services/Staff/PaymentCaptureService.php
- app/Services/Staff/RefundExecutionService.php
- app/Services/Staff/RefundPlannerService.php
- app/Services/Staff/Settlement*.php
- app/Services/Staff/StaffCashierShiftService.php
- app/Services/Staff/StaffInvoiceService.php
- app/Services/Staff/StaffFinancialReconciliationService.php
- app/Services/PaymentIntegration/*
- tests/Feature/Staff/StaffCheckout*.php
- tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php
- tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php
- tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php
- tests/Feature/Payments/*

Viec can lam:
1. Review toan bo settlement lifecycle: preview -> lock bill -> pay/finalize -> refund -> refund-cancel.
2. Review payment webhook ingestion va session lifecycle theo huong duplicate delivery / replay / stale ordering.
3. Bo sung test cho race conditions va invariants tai chinh con thieu.
4. Neu phat hien guard thieu, sua o service layer, khong day logic vao controller.

Out of scope:
- Khong redesign payment provider architecture.
- Khong dong vao reservation/table core tru khi la dependency tai chinh ro rang.

Acceptance criteria:
- Financial flows co them guard hoac test ro rang cho cac path rui ro cao.
- Khong lam rong scope ngoai finance/payments.
```

## Task D - Kitchen / KDS maturation

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Mature the existing kitchen/KDS foundation into a tighter operational slice without broad expansion.

Muc tieu:
- Lam kitchen flow hien co chat hon ve state invariants, route safety, feature-flag behavior, and realtime consistency.
- Tang muc hoan thien cua current KDS surface, khong mo rong thanh module lon moi.

Scope so huu:
- app/Services/Kitchen/*
- app/Http/Controllers/Api/Staff/*Kitchen* neu can
- app/Http/Controllers/Api/Admin/*Kitchen* neu can
- tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php
- tests/Feature/Admin/AdminKitchenRoutingFoundationHttpFlowTest.php
- cac resource/request/test lien quan kitchen route hien co

Viec can lam:
1. Review kitchen station routing, dispatch, fire, bump, recall, terminal ticket handling.
2. Siet consistency giua order item status va kitchen ticket status.
3. Kiem tra safety khi route thay doi, khi feature flag tat, khi item unrouted, khi ticket dang active.
4. Bo sung regression tests cho cac edge cases con thieu.
5. Khong them module kitchen hoan toan moi; chi nang current surface.

Out of scope:
- Khong dong inventory/purchasing.
- Khong doi checkout/reservation core ngoai dependency toi thieu.

Acceptance criteria:
- KDS hien co chat hon, it ambiguous state hon.
- Test coverage tot hon cho cac path active/terminal/flagged/unrouted.
```

## Task E - Inventory / Purchasing hardening

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Harden the current inventory and purchasing foundation on the existing admin surface.

Muc tieu:
- Lam current ingredient/recipe/movement/purchase-order/receiving flows an toan va ro invariants hon.
- Khong mo rong qua scope; uu tien production safety cho foundation hien co.

Scope so huu:
- app/Services/Inventory/*
- app/Http/Controllers/Api/Admin/*Inventory* neu can
- app/Http/Controllers/Api/Admin/*Purchasing* neu can
- model/resource/request lien quan inventory/purchasing
- tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php
- tests/Feature/Admin/AdminPurchasingFoundationHttpFlowTest.php
- tests/Feature/Admin/AdminInventoryKitchenPurchasing*.php

Viec can lam:
1. Review ingredient movement, recipe linkage, purchase order, partial receiving, final receiving.
2. Siet branch scope, unit consistency, over-receive protection, idempotency expectations, stock movement correctness.
3. Tang regression tests cho negative cases va operational invariants.
4. Khong tao surface API lon moi trong batch nay; uu tien hardening current foundation.

Out of scope:
- Khong dong kitchen dispatch.
- Khong dong staff order lifecycle ngoai interface inventory da co.

Acceptance criteria:
- Inventory/purchasing foundation chat hon ve safety va tests.
- Khong overbuild thanh full inventory suite.
```

## Task F - Ops / Observability / Outbox / Realtime hardening

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: Harden operational visibility and reliability for health, metrics, outbox, realtime, and retention.

Muc tieu:
- Tang do tin cay va kha nang quan sat cua cac flow van hanh.
- Cung co change-feed/outbox/health behavior ma khong doi business API chinh.

Scope so huu:
- app/Services/NotificationOutboxService.php
- app/Services/NotificationOutboxHealthService.php
- app/Services/OperationalInsightsService.php
- app/Services/OpsHeartbeatService.php
- app/Services/MetricsService.php
- app/Services/Staff/StaffOperationalRealtimeService.php
- app/Services/DataLifecycle/*
- tests/Feature/Infrastructure/*
- tests/Feature/Notifications/*
- tests/Feature/Console/* neu lien quan

Viec can lam:
1. Review health/metrics/output contracts cho cac check van hanh quan trong.
2. Review notification outbox cooldown/dedupe/retry/health assumptions.
3. Review realtime change feed behavior: versioning, stale cursor, event trimming, polling contract.
4. Review data retention safety cho derived/terminal artifacts.
5. Bo sung tests hoac hardening patch nho o service layer.

Out of scope:
- Khong sua financial business logic.
- Khong sua reservation core ngoai event/ops visibility.

Acceptance criteria:
- Health/metrics/outbox/realtime co coverage va guard tot hon.
- Khong lan sang domain logic khong thuoc ops.
```

## Task G - Schema / release contract sync

```text
[PREPEND PREFIX DUNG CHUNG O TREN]

Task: After merging the outputs of the parallel workstreams, reconcile the SQL-first schema/release/bootstrap contract.

Muc tieu:
- Dong bo code cuoi cung voi schema SQL-first, patches, bootstrap flow, docs, and contract checks.
- Dong rui ro drift giua application code va release artifact set.

Scope so huu:
- database/schema/mysql-schema.sql
- database/patches/*
- database/README_release_bootstrap.md
- tools/mysql/bootstrap_release.*
- app/Services/DatabaseContractInspector.php
- tests/Console/* neu lien quan
- tests/Feature/Infrastructure/*schema* neu lien quan
- release/bootstrap docs lien quan

Viec can lam:
1. Review cac thay doi da merge tu A-F va xac dinh cho nao can phan anh o SQL-first contract.
2. Dong bo mysql-schema, patches, bootstrap scripts, contract inspector, release docs.
3. Giu nguyen domain behavior; day la task contract/release sync, khong phai feature task.
4. Neu co deferred FK/import-safe notes can cap nhat, lam ro va them test/check tuong ung.

Out of scope:
- Khong them feature business moi.
- Khong refactor domain logic ngoai phan can de giu contract dung.

Acceptance criteria:
- SQL-first artifacts va code cuoi cung khop nhau.
- Bootstrap/release contract ro hon va it drift hon.
```

## Prompt cho Integrator Pass

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Nhiem vu:
- Review va hop nhat cac thay doi da duoc merge tu cac workstream song song:
  1. Auth / RBAC
  2. Reservation / FOH core
  3. Checkout / Payments / Finance
  4. Kitchen / KDS
  5. Inventory / Purchasing
  6. Ops / Observability / Outbox / Realtime
  7. Schema / release contract sync

Yeu cau:
- Khong lam feature moi.
- Chi resolve xung dot, chinh integration seams, sua regression ro rang, va chay/de xuat targeted verification.
- Kiem tra consistency giua:
  - route guards
  - service contracts
  - row_version / idempotency assumptions
  - feature flags
  - SQL-first schema/release contract
- Neu phat hien drift hoac regression, sua o pham vi toi thieu.

Output cuoi:
1. Intent
2. Merge/integration fixes made
3. Tests run / not run
4. Remaining risks
5. Follow-up backlog uu tien cao
```

## Thu tu mo chat

1. Chat A
2. Chat B
3. Chat C
4. Chat D
5. Chat E
6. Chat F
7. Chat G sau khi A-F merge xong
8. Integrator Pass sau khi G xong

## Thu tu merge khuyen nghi

1. Auth / RBAC
2. Reservation / FOH core
3. Checkout / Payments / Finance
4. Kitchen / KDS
5. Inventory / Purchasing
6. Ops / Observability / Outbox / Realtime
7. Schema / release contract sync
8. Integration pass

## Checklist danh gia output cua moi agent

- Co code that, khong chi dung o phan tich
- Co test moi hoac test cap nhat
- Co bao cao `Intent / Changed files / Added-updated tests / Remaining risks`
- Co noi ro neu da cham vao file shared
- Scope khong bi troi sang module khac

## Ghi chu cuoi

- Muc tieu cua bo prompt nay la toi uu hoa parallelism nhung van giu merge an toan.
- Neu mot task bi blocker vi can sua file shared lon, dung mo rong scope trong chat do; ghi ro blocker va day xu ly sang Integrator hoac task G neu phu hop.
