# Decision Map

Use this file to choose the smallest correct context before reading code.

## Always read first

- `AGENTS.md`
- `.codex/AGENTS.md`

## Domain map

| Request shape | Primary skill | Minimal first-pass files |
| --- | --- | --- |
| customer-web or staff-web contract, sdk, error shape, enum or state exposure, frontend DX | `restaurantpos-web-client-contracts` | `docs/runbooks/api-consumer-artifacts.md`, `docs/runbooks/booking-api-contract.md`, `config/api_artifacts.php`, `app/Support/ApiErrorResponse.php`, `config/cors.php` |
| customer or staff web login, refresh or logout, auth headers, session propagation, CORS auth | `restaurantpos-web-auth-session-contract` | `config/customer_auth.php`, `config/staff_auth.php`, `config/cors.php`, `app/Http/Middleware/CustomerOrStaffMiddleware.php`, `app/Http/Controllers/Api/Auth/*` |
| staff-web operator UI, React route, Ant Design table/form/modal, POS/KDS/cashier/admin screen | `restaurantpos-staff-web-react` | `staff-web/package.json`, `staff-web/src/app/router/*`, owning `staff-web/src/domains/*` or `staff-web/src/workspaces/*` feature, adjacent `*.test.tsx` |
| customer-web page, form, async state, booking/menu/reservation/payment UI | `restaurantpos-customer-web-ui-flow` | `customer-web/package.json`, owning `customer-web/src/features/*` route or component, `customer-web/src/lib/*` API adapter, adjacent tests |
| visible UI consistency, table/form/modal/status badge, responsive or accessibility polish | `restaurantpos-ui-design-system-guardian` | affected app `package.json`, owning component, existing sibling pattern, adjacent UI test |
| staff auth, customer auth, RBAC, capability, deny-path | `restaurantpos-auth-rbac` | `config/staff_auth.php`, `config/customer_auth.php`, `config/staff_capabilities.php`, `app/Http/Middleware/*`, `routes/api.php` |
| reservation board, hold, check-in, move-table, release | `restaurantpos-foh-reservations` | `app/Services/ReservationService.php`, `app/Services/TableAvailabilityService.php`, `app/Services/TableHoldService.php`, `app/Services/Staff/StaffTableBoardService.php`, matching feature tests |
| table order, item status, active order, order reads | `restaurantpos-order-lifecycle` | `app/Services/Staff/StaffTableOrderService.php`, `app/Services/Staff/StaffOrderItemLifecycleService.php`, `app/Services/Staff/StaffOrderReadService.php`, matching staff tests |
| customer token, self-service, owner access, self-pay | `restaurantpos-customer-self-service` | `config/customer_auth.php`, `app/Services/CustomerAccessSessionService.php`, `app/Services/CustomerReservationSessionAccessService.php`, matching customer or reservation tests |
| checkout, refund, cashier, invoice, reconciliation, webhooks | `restaurantpos-checkout-finance` | `app/Services/Staff/StaffCheckoutService.php`, `app/Services/PaymentIntegration/*`, `app/Services/ReservationFinancialSyncService.php`, matching payments and checkout tests |
| kitchen, ticket dispatch, routing, KDS flags | `restaurantpos-kitchen-kds` | `app/Services/Kitchen/*`, `app/Http/Controllers/Api/Staff/StaffKitchenController.php`, `app/Http/Controllers/Api/Admin/AdminKitchenRoutingController.php`, matching kitchen tests |
| inventory, purchasing, receiving, stock movement | `restaurantpos-inventory-purchasing` | `app/Services/Inventory/*`, `app/Services/Admin/AdminInventoryService.php`, `app/Services/Admin/AdminPurchasingService.php`, matching admin inventory tests |
| route changes, requests, resources, OpenAPI, snapshots | `restaurantpos-api-contract-gates` | `routes/api.php`, `app/Http/Requests/*`, `app/Http/Resources/*`, `app/Services/ApiContract/*`, `app/Services/ApiArtifacts/*` |
| schema, patches, bootstrap, doctor, outbox, release gates | `restaurantpos-ops-release-contract` | `database/schema/mysql-schema.sql`, `database/patches/*`, `tools/mysql/*`, `app/Services/DatabaseContractInspector.php`, matching console and infrastructure tests |
| privacy requests, data export, anonymization, retention | `restaurantpos-data-lifecycle` | `docs/data-lifecycle.md`, `app/Services/DataLifecycle/*`, `app/Http/Controllers/Api/Admin/AdminCustomerDataLifecycleController.php`, matching data lifecycle tests |
| notification outbox, preferences, drivers, reminders, dead-letter | `restaurantpos-notification-platform` | `docs/runbooks/notification-platform-v2.md`, `app/Services/NotificationOutboxService.php`, `app/Services/Notifications/*`, matching notification and ops tests |
| conversation inbox, assignment, links, internal notes | `restaurantpos-conversation-inbox` | `docs/runbooks/staff-conversation-inbox.md`, `app/Http/Controllers/Api/Staff/StaffConversationInboxController.php`, `app/Services/Staff/StaffConversation*`, matching staff inbox tests |
| branch-local business hours, closure windows, timezone policy | `restaurantpos-branch-scheduling` | `docs/runbooks/branch-scheduling-policy-resolution.md`, `app/Services/Branch/BranchSchedulingPolicyService.php`, `app/Services/Branch/ReservationBranchScopeService.php`, branch scheduling tests |
| branch settings, default branch behavior, reporting snapshots | `restaurantpos-multi-branch-reporting` | `app/Http/Controllers/Api/Admin/AdminBranchController.php`, `app/Http/Controllers/Api/Admin/AdminReportingController.php`, `app/Services/Reporting/ReportingSnapshotService.php`, reporting and multi-branch tests |

## Add supporting skills when needed

- Need FE contract, SDK, error envelope, enum or state exposure, or browser DX coverage: `restaurantpos-web-client-contracts`
- Need split web auth headers, access sessions, or browser auth delivery coverage: `restaurantpos-web-auth-session-contract`
- Visible frontend UI changed: `restaurantpos-ui-design-system-guardian`
- Shared seam touched: `restaurantpos-shared-file-discipline`
- DB contract moved: `restaurantpos-sql-first-schema-sync`
- Need smallest safe tests: `restaurantpos-targeted-verification`
- Need prompt-first domain selection from natural language: `restaurantpos-prompt-router`
- Need diff-aware path collection: `restaurantpos-git-aware-verify`
- Need to validate the skill pack itself: `restaurantpos-skill-pack-quality`
- Runtime proof needed: `restaurantpos-runtime-smoke`
- Docs or runbooks moved: `restaurantpos-runbook-sync`
- Feature flag behavior moved: `restaurantpos-feature-flag-rollout`
- Audit or outbox behavior moved: `restaurantpos-audit-observability`
- Query shape or latency risk moved: `restaurantpos-performance-budget`

## Stop conditions

- If the first-pass files clearly isolate the fix, do not broaden the scan
- If a task reads as multi-domain, switch to `restaurantpos-workstream-orchestrator` before patching
- If a task affects both schema and runtime ops, combine `restaurantpos-sql-first-schema-sync` and `restaurantpos-ops-release-contract`

## Context budget

Use `context-budget.md` before reading beyond the first-pass set. Prefer exact symbols and path-limited searches over broad module dumps.
