<?php

declare(strict_types=1);

namespace App\Platform\Verification;

use RuntimeException;
use Symfony\Component\Process\Process;

class VerificationSelectorService
{
    /**
     * @var list<string>
     */
    private const SHARED_FILES = [
        'routes/api.php',
        'config/booking.php',
        'config/staff_capabilities.php',
        'database/schema/mysql-schema.sql',
    ];

    /**
     * @var list<array{
     *   key: string,
     *   label: string,
     *   skills: list<string>,
     *   fragments: list<string>,
     *   commands: list<array{tier: string, command: string, reason: string}>
     * }>
     */
    private const DOMAIN_DEFINITIONS = [
        [
            'key' => 'verification_tooling',
            'label' => 'Verification tooling',
            'skills' => [
                'restaurantpos-targeted-verification',
                'restaurantpos-git-aware-verify',
                'restaurantpos-runbook-sync',
            ],
            'fragments' => [
                'app/platform/verification/',
                'routes/console/verification.php',
                '.agents/skills/restaurantpos-targeted-verification/',
                '.agents/skills/restaurantpos-git-aware-verify/',
                'scripts/ci/booking-verify-select.sh',
                'tests/feature/console/bookingverifyselectcommandtest.php',
                'tests/unit/services/verification/',
            ],
            'commands' => [
                [
                    'tier' => 'selector-tests',
                    'command' => 'php artisan test tests/Feature/Console/BookingVerifySelectCommandTest.php tests/Unit/Services/Verification/VerificationSelectorServiceTest.php',
                    'reason' => 'Cover the selector contract and the console entrypoint together.',
                ],
                [
                    'tier' => 'selector-self-check',
                    'command' => 'php artisan booking:verify-select --path=app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php --json',
                    'reason' => 'Probe a finance-sensitive path mapping through the repo-native selector.',
                ],
                [
                    'tier' => 'selector-self-check',
                    'command' => 'php artisan booking:verify-select --path=routes/api.php --json',
                    'reason' => 'Verify route-surface escalation stays visible in the JSON output.',
                ],
                [
                    'tier' => 'selector-self-check',
                    'command' => 'python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php --json',
                    'reason' => 'Keep the skill wrapper aligned with the repo-native selector contract.',
                ],
            ],
        ],
        [
            'key' => 'web_harness_contracts',
            'label' => 'Web harness / FE contracts',
            'skills' => [
                'restaurantpos-web-auth-session-contract',
                'restaurantpos-web-client-contracts',
                'restaurantpos-runbook-sync',
            ],
            'fragments' => [
                'app/platform/harness/',
                'app/platform/apicontract/apiartifacts/apienumstateartifactservice.php',
                'app/platform/apicontract/apiartifacts/apiconsumerartifactservice.php',
                'routes/console/harness.php',
                'config/api_artifacts.php',
                'docs/runbooks/api-consumer-artifacts.md',
                'docs/runbooks/booking-api-contract.md',
                'docs/runbooks/booking-launch-readiness.md',
                'tests/feature/console/bookingharness',
            ],
            'commands' => [
                [
                    'tier' => 'changed-tests',
                    'command' => 'php artisan test tests/Feature/Console/BookingHarnessWebAuthCommandTest.php tests/Feature/Console/BookingHarnessFeContractCommandTest.php tests/Feature/Console/BookingHarnessGoldenFlowsCommandTest.php tests/Feature/Console/BookingHarnessEnumStateCommandTest.php tests/Feature/Console/BookingHarnessReleaseReadinessCommandTest.php',
                    'reason' => 'Run the dedicated harness command coverage first when FE/runtime harness wiring changes.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php tests/Unit/Config/ApiArtifactsConfigContractTest.php',
                    'reason' => 'Protect FE artifact generation and config contract drift together.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php',
                    'reason' => 'Keep split-web auth/session and browser header contracts stable.',
                ],
                [
                    'tier' => 'self-check',
                    'command' => 'php artisan booking:harness:fe-contract --json',
                    'reason' => 'Smoke the FE contract harness output after changing harness or artifact services.',
                ],
                [
                    'tier' => 'self-check',
                    'command' => 'php artisan booking:harness:web-auth --json',
                    'reason' => 'Smoke the web auth/session harness summary after auth contract changes.',
                ],
            ],
        ],
        [
            'key' => 'auth_rbac',
            'label' => 'Auth / Identity / RBAC',
            'skills' => ['restaurantpos-auth-rbac'],
            'fragments' => [
                'config/staff_auth.php',
                'config/customer_auth.php',
                'config/staff_capabilities.php',
                'app/http/middleware/',
                'app/modules/identityaccess/',
                'tests/feature/auth/',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Auth tests/Feature/Staff/StaffCapabilityHttpGuardTest.php',
                    'reason' => 'Protect auth and staff capability guards when identity boundaries move.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Unit/Http/Middleware tests/Unit/Config/StaffAuthConfigContractTest.php tests/Unit/Config/StaffCapabilitiesConfigContractTest.php tests/Unit/Config/CustomerAuthConfigContractTest.php',
                    'reason' => 'Keep middleware and auth config contracts stable.',
                ],
            ],
        ],
        [
            'key' => 'foh_reservations',
            'label' => 'FOH reservations',
            'skills' => ['restaurantpos-foh-reservations'],
            'fragments' => [
                'app/modules/reservations/',
                'app/modules/branchscheduling/',
                'app/modules/flooroperations/',
                'tests/feature/reservation/',
                'tests/feature/table/',
                'tests/feature/staff/stafftableboard',
                'tests/feature/staff/staffreservationboard',
                'tests/feature/staff/staffcheckinflowtest.php',
                'tests/feature/staff/staffmovetableflowtest.php',
                'tests/feature/staff/stafftablerelease',
                'tests/feature/staff/staffservicesession',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Reservation tests/Feature/Table',
                    'reason' => 'Cover reservation and table-state regressions for FOH flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffTableBoardHttpFlowTest.php tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php tests/Feature/Staff/StaffCheckInFlowTest.php tests/Feature/Staff/StaffMoveTableFlowTest.php tests/Feature/Staff/StaffTableReleaseServiceTest.php',
                    'reason' => 'Verify the staff-facing board and table orchestration paths.',
                ],
            ],
        ],
        [
            'key' => 'order_lifecycle',
            'label' => 'Order lifecycle',
            'skills' => ['restaurantpos-order-lifecycle'],
            'fragments' => [
                'app/modules/ordering/',
                'tests/feature/staff/stafforder',
                'tests/feature/staff/stafftableorder',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php tests/Feature/Staff/StaffOrderReadFlowTest.php',
                    'reason' => 'Cover staff order lifecycle and active-order reads.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffTableOrderBranchScopeTest.php tests/Feature/Staff/StaffTableOrderConcurrencyGuardServiceTest.php tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php',
                    'reason' => 'Guard branch scope, concurrency, and idempotent replay paths.',
                ],
            ],
        ],
        [
            'key' => 'customer_self_service',
            'label' => 'Customer self-service',
            'skills' => ['restaurantpos-customer-self-service'],
            'fragments' => [
                'app/modules/identityaccess/infrastructure/persistence/customeraccesssessionstore.php',
                'app/modules/identityaccess/application/workflows/reservationsessionaccessworkflow.php',
                'app/modules/reservations/application/services/customerreservation',
                'app/modules/reservations/http/controllers/customer/',
                'app/modules/reservations/http/controllers/customerreservation',
                'app/modules/waitlist/',
                'app/modules/billing/http/controllers/customer/',
                'app/modules/payments/http/controllers/customer/',
                'tests/feature/customer/',
                'tests/feature/waitinglist/',
                'tests/feature/reservation/customerreservation',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Customer tests/Feature/WaitingList',
                    'reason' => 'Cover customer-visible reservation and waiting-list flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Reservation/CustomerReservation* tests/Feature/Auth/Customer*',
                    'reason' => 'Protect token/session scoped reservation access paths.',
                ],
            ],
        ],
        [
            'key' => 'checkout_finance',
            'label' => 'Checkout / finance',
            'skills' => ['restaurantpos-checkout-finance'],
            'fragments' => [
                'app/modules/billing/',
                'app/modules/payments/',
                'app/modules/cashiering/',
                'tests/feature/payments/',
                'tests/feature/staff/staffcheckout',
                'tests/feature/staff/staffcashiershift',
                'tests/feature/staff/stafffinance',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffCheckout*.php tests/Feature/Payments',
                    'reason' => 'Cover settlement, checkout, and provider-facing payment paths.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php',
                    'reason' => 'Protect cashier shift and finance reporting flows.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'php artisan booking:round5-gate --json',
                    'reason' => 'Escalate payment-sensitive changes through the finance gate.',
                ],
            ],
        ],
        [
            'key' => 'kitchen_kds',
            'label' => 'Kitchen / KDS',
            'skills' => ['restaurantpos-kitchen-kds'],
            'fragments' => [
                'app/modules/kitchendispatch/application/workflows/',
                'app/modules/kitchendispatch/domain/models/kitchenorderitemticket.php',
                'app/modules/kitchendispatch/domain/models/kitchenstation.php',
                'app/modules/kitchendispatch/domain/models/kitchenstationcategoryroute.php',
                'app/modules/kitchendispatch/http/controllers/staff/kitchendispatchcontroller.php',
                'app/modules/kitchendispatch/http/controllers/admin/kitchenstationcontroller.php',
                'app/modules/kitchendispatch/http/controllers/admin/kitchencategoryroutecontroller.php',
                'tests/feature/staff/staffkitchendispatchfoundationflowtest.php',
                'tests/feature/admin/adminkitchenroutingfoundationhttpflowtest.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php tests/Feature/Admin/AdminKitchenRoutingFoundationHttpFlowTest.php',
                    'reason' => 'Protect kitchen dispatch and routing behavior.',
                ],
            ],
        ],
        [
            'key' => 'inventory_purchasing',
            'label' => 'Inventory / purchasing',
            'skills' => ['restaurantpos-inventory-purchasing'],
            'fragments' => [
                'app/modules/inventoryprocurement',
                'app/modules/inventoryprocurement/',
                'tests/feature/admin/admininventory',
                'tests/feature/admin/adminpurchasing',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php tests/Feature/Admin/AdminPurchasingFoundationHttpFlowTest.php',
                    'reason' => 'Cover the admin inventory and purchasing HTTP surface.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminInventoryKitchenPurchasing*.php',
                    'reason' => 'Protect kitchen purchasing and inventory integration seams.',
                ],
            ],
        ],
        [
            'key' => 'data_lifecycle',
            'label' => 'Data lifecycle',
            'skills' => ['restaurantpos-data-lifecycle'],
            'fragments' => [
                'app/modules/privacycompliance/',
                'app/modules/privacycompliance/http/controllers/admin/privacycontroller.php',
                'tests/feature/datalifecycle/',
                'docs/data-lifecycle.md',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/DataLifecycle',
                    'reason' => 'Cover customer data lifecycle flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/DataLifecycle/DataLifecycleRetentionConsoleTest.php tests/Feature/DataLifecycle/DataLifecycleRouteSurfaceTest.php',
                    'reason' => 'Protect retention and route-surface contracts for privacy features.',
                ],
            ],
        ],
        [
            'key' => 'notification_platform',
            'label' => 'Notification platform',
            'skills' => ['restaurantpos-notification-platform', 'restaurantpos-audit-observability'],
            'fragments' => [
                'app/modules/notifications/',
                'app/platform/metrics/services/notificationoutboxhealthservice.php',
                'tests/feature/notifications/',
                'tests/feature/services/notificationoutboxserviceretrytest.php',
                'tests/feature/console/notificationopscommandstest.php',
                'docs/runbooks/notification-platform-v2.md',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Notifications tests/Feature/Services/NotificationOutboxServiceRetryTest.php tests/Feature/Console/NotificationOpsCommandsTest.php',
                    'reason' => 'Cover notification delivery, retries, and operator console flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Unit/Services/NotificationOutboxHealthServiceTest.php tests/Unit/Services/NotificationOutboxServiceIdempotencyKeyTest.php',
                    'reason' => 'Protect outbox health and idempotency invariants.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'php artisan notifications:outbox-health --json',
                    'reason' => 'Escalate notification platform changes through the runtime health probe.',
                ],
            ],
        ],
        [
            'key' => 'conversation_inbox',
            'label' => 'Conversation inbox',
            'skills' => ['restaurantpos-conversation-inbox'],
            'fragments' => [
                'app/modules/conversations/http/controllers/staff/conversationinboxcontroller.php',
                'app/modules/conversations/application/services/staffconversationinboxservice.php',
                'app/modules/conversations/application/services/staffconversationworkflowservice.php',
                'tests/feature/staff/staffconversationinboxflowtest.php',
                'docs/runbooks/staff-conversation-inbox.md',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Staff/StaffConversationInboxFlowTest.php',
                    'reason' => 'Cover inbox read and assignment workflow behavior.',
                ],
            ],
        ],
        [
            'key' => 'branch_scheduling',
            'label' => 'Branch scheduling',
            'skills' => ['restaurantpos-branch-scheduling'],
            'fragments' => [
                'app/modules/branchscheduling/',
                'docs/runbooks/branch-scheduling-policy-resolution.md',
                'tests/unit/services/branch/branchschedulingpolicyservicetest.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Unit/Services/Branch/BranchSchedulingPolicyServiceTest.php',
                    'reason' => 'Protect branch-local scheduling resolution rules.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php',
                    'reason' => 'Cover branch-local admin defaults and policy exposure.',
                ],
            ],
        ],
        [
            'key' => 'multi_branch_reporting',
            'label' => 'Multi-branch reporting',
            'skills' => ['restaurantpos-multi-branch-reporting'],
            'fragments' => [
                'app/modules/reporting/',
                'app/modules/flooroperations/application/queries/staffbranchcontextservice.php',
                'app/modules/branchscheduling/application/services/branchmanagementservice.php',
                'app/modules/branchscheduling/http/controllers/admin/branchcontroller.php',
                'app/modules/reporting/http/controllers/admin/reportingsnapshotcontroller.php',
                'app/modules/reporting/http/controllers/staff/salesreportcontroller.php',
                'app/modules/reporting/http/controllers/staff/operationsreportcontroller.php',
                'app/modules/reporting/http/controllers/staff/inventoryreportcontroller.php',
                'tests/feature/admin/adminmultibranch',
                'tests/feature/admin/adminreporting',
                'tests/feature/staff/staffreporting',
                'tests/unit/config/bookingreportingandmultibranchconfigcontracttest.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php',
                    'reason' => 'Cover branch context and defaults for admin flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminReportingReadModelsFoundationHttpFlowTest.php tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php',
                    'reason' => 'Protect reporting read-model behavior across admin and staff surfaces.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Admin/AdminReportingAndMultiBranchRouteSurfaceTest.php tests/Unit/Config/BookingReportingAndMultiBranchConfigContractTest.php tests/Unit/Services/Branch/BranchContextServiceTest.php',
                    'reason' => 'Guard route surface and branch context contracts for reporting.',
                ],
            ],
        ],
        [
            'key' => 'api_contract',
            'label' => 'API contract surface',
            'skills' => ['restaurantpos-api-contract-gates'],
            'fragments' => [
                'routes/api.php',
                'app/http/requests/',
                'app/http/resources/',
                'app/platform/apicontract/',
                'tests/feature/infrastructure/',
                'tests/feature/http/',
            ],
            'commands' => [
                [
                    'tier' => 'contract-gates',
                    'command' => 'composer api:artifacts',
                    'reason' => 'Refresh API consumer artifacts when the public contract moves.',
                ],
                [
                    'tier' => 'contract-gates',
                    'command' => 'php artisan booking:route-gate --json',
                    'reason' => 'Run the route inventory gate when API surface seams change.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Infrastructure tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php',
                    'reason' => 'Cover runtime artifact generation and infrastructure-facing API checks.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Http tests/Unit/Config/ApiArtifactsConfigContractTest.php tests/Unit/Services/ApiContract/FormRequestSchemaFactoryTest.php',
                    'reason' => 'Protect HTTP contract metadata and schema factory behavior.',
                ],
            ],
        ],
        [
            'key' => 'ops_release_contract',
            'label' => 'Ops / release contract',
            'skills' => ['restaurantpos-ops-release-contract', 'restaurantpos-runtime-smoke'],
            'fragments' => [
                'config/booking_release.php',
                'database/schema/',
                'database/patches/',
                'db_all.sql',
                'storage/app/booking_release/',
                'tools/mysql/',
                'app/platform/apicontract/',
                'app/platform/backup/',
                'app/platform/health/',
                'app/platform/metrics/',
                'app/platform/performance/',
                'app/platform/release/',
                'app/platform/uat/',
                'scripts/release/',
                'scripts/ci/',
                'tests/feature/console/',
                'tests/feature/infrastructure/',
                'tests/unit/services/release',
                'composer.json',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Unit/Services/ReleaseArtifactManifestServiceTest.php tests/Unit/Services/ReleasePackageServiceTest.php',
                    'reason' => 'Keep release manifest and packaging invariants aligned before broader console gates.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Console tests/Feature/Infrastructure',
                    'reason' => 'Cover console-facing release, doctor, and deploy checks.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'node scripts/release/check-package-integrity.mjs --json',
                    'reason' => 'Verify release package shape and frozen artifact freshness together.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'php artisan booking:doctor --json',
                    'reason' => 'Escalate runtime-sensitive changes through the doctor gate.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'php artisan booking:deploy-check --mode=preflight',
                    'reason' => 'Run deploy preflight for release-contract changes.',
                ],
                [
                    'tier' => 'domain-gates',
                    'command' => 'php artisan booking:release-manifest --json',
                    'reason' => 'Protect the release artifact contract when CI/bootstrap surfaces move.',
                ],
            ],
        ],
        [
            'key' => 'feature_flag_rollout',
            'label' => 'Feature flags',
            'skills' => ['restaurantpos-feature-flag-rollout'],
            'fragments' => [
                'config/feature_flags.php',
                'app/platform/featureflags/',
                'tests/feature/console/featureflagconsolecommandtest.php',
                'tests/unit/config/featureflagconfigcontracttest.php',
                'tests/unit/services/featureflagservicetest.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Console/FeatureFlagConsoleCommandTest.php tests/Unit/Config/FeatureFlagConfigContractTest.php tests/Unit/Services/FeatureFlagServiceTest.php',
                    'reason' => 'Protect rollout controls and feature-flag contract behavior.',
                ],
            ],
        ],
        [
            'key' => 'audit_observability',
            'label' => 'Audit / observability',
            'skills' => ['restaurantpos-audit-observability'],
            'fragments' => [
                'docs/audit-trail.md',
                'docs/runbooks/notification-platform-v2.md',
                'app/modules/privacycompliance/application/queries/audit/audittrailqueryhandler.php',
                'app/platform/metrics/',
                'app/platform/realtime/services/operationalrealtimeservice.php',
                'app/support/audittrail/',
                'tests/feature/notifications/',
                'tests/feature/staff/staffaudittrailhttpflowtest.php',
                'tests/feature/staff/staffoperationalrealtimeflowtest.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Notifications tests/Feature/Staff/StaffAuditTrailHttpFlowTest.php tests/Feature/Staff/StaffOperationalRealtimeFlowTest.php',
                    'reason' => 'Cover audit-trail and realtime observability flows.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Unit/Services/NotificationOutboxHealthServiceTest.php tests/Unit/Services/OperationalAlertServiceTest.php tests/Unit/Services/OperationalInsightsServiceTest.php',
                    'reason' => 'Protect observability service contracts and alert logic.',
                ],
            ],
        ],
        [
            'key' => 'performance_budget',
            'label' => 'Performance budget',
            'skills' => ['restaurantpos-performance-budget'],
            'fragments' => [
                'docs/performance-hot-paths.md',
                'app/platform/performance/',
                'tests/feature/performance/',
                'tests/support/profilesdatabasequeries.php',
                'app/modules/flooroperations/application/queries/stafftableboardservice.php',
                'app/modules/flooroperations/application/queries/timeline/staffreservationtimelineservice.php',
                'app/modules/billing/application/usecases/previews/customerreservationorderbillservice.php',
            ],
            'commands' => [
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Performance/HotPathPerformanceBudgetTest.php',
                    'reason' => 'Protect the locked hot-path query budgets.',
                ],
                [
                    'tier' => 'targeted-tests',
                    'command' => 'php artisan test tests/Feature/Console/BookingPerformanceVerifyCommandTest.php',
                    'reason' => 'Cover the performance verification console surface.',
                ],
            ],
        ],
    ];

    /**
     * @var list<array{
     *   key: string,
     *   label: string,
     *   reason: string,
     *   skills: list<string>,
     *   fragments: list<string>
     * }>
     */
    private const ESCALATION_DEFINITIONS = [
        [
            'key' => 'shared_file',
            'label' => 'Shared integration seam',
            'reason' => 'Keep the diff minimal and verify downstream domains explicitly.',
            'skills' => ['restaurantpos-shared-file-discipline'],
            'fragments' => self::SHARED_FILES,
        ],
        [
            'key' => 'schema_contract',
            'label' => 'SQL-first schema contract',
            'reason' => 'Schema/bootstrap artifacts changed, so SQL-first release artifacts must stay aligned.',
            'skills' => ['restaurantpos-sql-first-schema-sync'],
            'fragments' => [
                'database/schema/',
                'database/patches/',
                'db_all.sql',
                'tools/mysql/',
            ],
        ],
        [
            'key' => 'route_surface',
            'label' => 'Route or API contract surface',
            'reason' => 'Public route metadata or request/response contracts moved.',
            'skills' => ['restaurantpos-api-contract-gates'],
            'fragments' => [
                'routes/api.php',
                'app/http/requests/',
                'app/http/resources/',
            ],
        ],
        [
            'key' => 'auth_boundary',
            'label' => 'Auth boundary',
            'reason' => 'Authentication or capability boundaries moved and should stay tightly verified.',
            'skills' => ['restaurantpos-auth-rbac'],
            'fragments' => [
                'config/staff_capabilities.php',
                'config/staff_auth.php',
                'config/customer_auth.php',
                'app/http/middleware/',
                'app/modules/identityaccess/',
            ],
        ],
        [
            'key' => 'payment_finance',
            'label' => 'Payment or finance flow',
            'reason' => 'Settlement, refund, or payment integration logic changed and needs finance gates.',
            'skills' => ['restaurantpos-checkout-finance'],
            'fragments' => [
                'app/modules/billing/',
                'app/modules/payments/',
                'app/modules/cashiering/',
                'tests/feature/payments/',
            ],
        ],
        [
            'key' => 'runtime_sensitive',
            'label' => 'Runtime-sensitive flow',
            'reason' => 'Runtime health or release-sensitive paths changed; SQLite tests alone are not full proof.',
            'skills' => ['restaurantpos-runtime-smoke'],
            'fragments' => [
                'config/booking_release.php',
                'app/platform/health/',
                'app/platform/metrics/',
                'app/platform/release/',
                'storage/app/booking_release/',
                'scripts/release/',
                'scripts/ci/',
                'routes/console/',
                'composer.json',
            ],
        ],
        [
            'key' => 'feature_flags',
            'label' => 'Feature flag rollout',
            'reason' => 'Flag defaults or flag resolution changed and need rollout-specific coverage.',
            'skills' => ['restaurantpos-feature-flag-rollout'],
            'fragments' => [
                'config/feature_flags.php',
                'app/platform/featureflags/',
            ],
        ],
    ];

    /**
     * @param  list<string>  $explicitPaths
     * @return array{
     *   paths: list<string>,
     *   source: array{type: string, git_available: bool, base_ref: ?string, included_uncommitted: bool},
     *   notes: list<string>
     * }
     */
    public function collectPaths(array $explicitPaths = [], ?string $base = null, ?string $stdin = null): array
    {
        $gitAvailable = $this->isGitRepository();
        $baseRef = $this->sanitizeBaseRef($base);
        $paths = $this->uniquePaths($explicitPaths);

        if ($paths !== []) {
            return [
                'paths' => $paths,
                'source' => [
                    'type' => 'explicit',
                    'git_available' => $gitAvailable,
                    'base_ref' => $baseRef,
                    'included_uncommitted' => false,
                ],
                'notes' => ['used explicit path input'],
            ];
        }

        if ($gitAvailable) {
            [$gitPaths, $gitNotes] = $this->collectGitPaths($baseRef);

            if ($gitPaths === []) {
                throw new RuntimeException('No changed files were found from Git diff state.');
            }

            return [
                'paths' => $gitPaths,
                'source' => [
                    'type' => 'git',
                    'git_available' => true,
                    'base_ref' => $baseRef,
                    'included_uncommitted' => true,
                ],
                'notes' => $gitNotes,
            ];
        }

        $stdinPaths = $this->uniquePaths($this->parseStdinPaths($stdin));
        if ($stdinPaths !== []) {
            return [
                'paths' => $stdinPaths,
                'source' => [
                    'type' => 'stdin',
                    'git_available' => false,
                    'base_ref' => $baseRef,
                    'included_uncommitted' => false,
                ],
                'notes' => ['used stdin path input because Git metadata is unavailable'],
            ];
        }

        throw new RuntimeException('Git metadata is unavailable and no explicit paths were provided.');
    }

    /**
     * @param  list<string>  $explicitPaths
     * @return array{
     *   paths: list<string>,
     *   source: array{type: string, git_available: bool, base_ref: ?string, included_uncommitted: bool},
     *   domains: list<array{key: string, label: string, matched_paths: list<string>}>,
     *   skills: list<string>,
     *   commands: list<array{tier: string, command: string, reason: string}>,
     *   notes: list<string>,
     *   escalations: list<array{key: string, label: string, reason: string}>,
     *   meta: array{generated_at_utc: string}
     * }
     */
    public function buildReport(array $explicitPaths = [], ?string $base = null, ?string $stdin = null): array
    {
        $selection = $this->collectPaths($explicitPaths, $base, $stdin);
        $paths = $selection['paths'];
        $normalizedPaths = array_map([$this, 'normalizePath'], $paths);
        $skills = ['restaurantpos-targeted-verification'];
        $commands = [];
        $notes = $selection['notes'];
        $domains = [];
        $escalations = [];
        $matchedDomainKeys = [];
        $matchedEscalationKeys = [];

        if ($selection['source']['type'] === 'git') {
            $skills[] = 'restaurantpos-git-aware-verify';
        }

        foreach ($this->buildChangedTestCommands($paths) as $command) {
            $this->pushCommand($commands, $command);
        }

        if ($this->shouldRecommendPint($paths)) {
            $this->pushCommand($commands, [
                'tier' => 'style',
                'command' => 'vendor/bin/pint --test',
                'reason' => 'Keep the fast formatting gate on for changed PHP and test files.',
            ]);
        }

        if ($this->shouldRecommendPhpstan($paths)) {
            $this->pushCommand($commands, [
                'tier' => 'static-analysis',
                'command' => 'vendor/bin/phpstan analyse',
                'reason' => 'Run static analysis when production PHP surfaces or console/runtime wiring changes.',
            ]);
        }

        foreach (self::DOMAIN_DEFINITIONS as $definition) {
            $matchedPaths = $this->matchedPaths($paths, $normalizedPaths, $definition['fragments']);
            if ($matchedPaths === []) {
                continue;
            }

            $domains[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'matched_paths' => $matchedPaths,
            ];
            $matchedDomainKeys[] = $definition['key'];

            foreach ($definition['skills'] as $skill) {
                $this->pushValue($skills, $skill);
            }

            foreach ($definition['commands'] as $command) {
                $this->pushCommand($commands, $command);
            }
        }

        foreach (self::ESCALATION_DEFINITIONS as $definition) {
            if ($this->matchedPaths($paths, $normalizedPaths, $definition['fragments']) === []) {
                continue;
            }

            $escalations[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'reason' => $definition['reason'],
            ];
            $matchedEscalationKeys[] = $definition['key'];
            $notes[] = sprintf('%s: %s', $definition['label'], $definition['reason']);

            foreach ($definition['skills'] as $skill) {
                $this->pushValue($skills, $skill);
            }
        }

        if ($commands === [] && $this->isDocsOnlyChangeSet($paths)) {
            $notes[] = 'docs-only change set: no automated commands were selected; verify command names and runbook examples manually';
        }

        if ($commands === [] && ! $this->isDocsOnlyChangeSet($paths)) {
            $this->pushCommand($commands, [
                'tier' => 'static-analysis',
                'command' => 'vendor/bin/phpstan analyse',
                'reason' => 'No domain-specific rule matched cleanly, so static analysis is the deterministic fallback.',
            ]);
            $notes[] = 'no domain-specific rule matched cleanly; selector escalated to static analysis instead of defaulting to the full suite';
        }

        if ($this->shouldRecommendFullSuite($matchedDomainKeys, $matchedEscalationKeys)) {
            $this->pushCommand($commands, [
                'tier' => 'full-suite',
                'command' => 'php artisan test',
                'reason' => 'Cross-domain changes span enough risky seams that the full automated suite is justified after targeted gates.',
            ]);
        }

        if ($this->touchesDocs($paths)) {
            $notes[] = 'docs touched: keep README/runbooks aligned with the command surface and escalation semantics';
            $this->pushValue($skills, 'restaurantpos-runbook-sync');
        }

        return [
            'paths' => $paths,
            'source' => $selection['source'],
            'domains' => $domains,
            'skills' => $skills,
            'commands' => $commands,
            'notes' => array_values(array_unique($notes)),
            'escalations' => $escalations,
            'meta' => [
                'generated_at_utc' => now('UTC')->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return list<array{tier: string, command: string, reason: string}>
     */
    protected function buildChangedTestCommands(array $paths): array
    {
        $tests = array_values(array_filter($paths, static function (string $path): bool {
            $normalized = str_replace('\\', '/', $path);

            return str_starts_with($normalized, 'tests/') && str_ends_with(strtolower($normalized), '.php');
        }));

        if ($tests === []) {
            return [];
        }

        return [[
            'tier' => 'changed-tests',
            'command' => 'php artisan test '.implode(' ', $tests),
            'reason' => 'Run the exact changed PHPUnit files first before broader domain coverage.',
        ]];
    }

    /**
     * @param  list<string>  $paths
     */
    protected function shouldRecommendPint(array $paths): bool
    {
        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if (str_ends_with($normalized, '.php') || str_ends_with($normalized, '.phtml')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function shouldRecommendPhpstan(array $paths): bool
    {
        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if (
                str_starts_with($normalized, 'app/')
                || str_starts_with($normalized, 'routes/')
                || str_starts_with($normalized, 'config/')
                || $normalized === 'composer.json'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $matchedDomainKeys
     * @param  list<string>  $matchedEscalationKeys
     */
    protected function shouldRecommendFullSuite(array $matchedDomainKeys, array $matchedEscalationKeys): bool
    {
        if (count(array_unique($matchedDomainKeys)) >= 4) {
            return true;
        }

        if (
            in_array('schema_contract', $matchedEscalationKeys, true)
            && in_array('route_surface', $matchedEscalationKeys, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function isDocsOnlyChangeSet(array $paths): bool
    {
        if ($paths === []) {
            return false;
        }

        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if (! str_ends_with($normalized, '.md') && $normalized !== 'readme.md') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function touchesDocs(array $paths): bool
    {
        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if (str_starts_with($normalized, 'docs/') || $normalized === 'readme.md') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $normalizedPaths
     * @param  list<string>  $fragments
     * @return list<string>
     */
    protected function matchedPaths(array $paths, array $normalizedPaths, array $fragments): array
    {
        $matched = [];
        $normalizedFragments = array_map([$this, 'normalizePath'], $fragments);

        foreach ($normalizedPaths as $index => $path) {
            foreach ($normalizedFragments as $fragment) {
                if (str_contains($path, $fragment)) {
                    $matched[] = $paths[$index];
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @param  list<string>  $values
     */
    protected function pushValue(array &$values, string $value): void
    {
        if (! in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param  list<array{tier: string, command: string, reason: string}>  $commands
     * @param  array{tier: string, command: string, reason: string}  $candidate
     */
    protected function pushCommand(array &$commands, array $candidate): void
    {
        foreach ($commands as $command) {
            if ($command['command'] === $candidate['command']) {
                return;
            }
        }

        $commands[] = $candidate;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function uniquePaths(array $paths): array
    {
        $ordered = [];
        $seen = [];

        foreach ($paths as $path) {
            $normalized = str_replace('\\', '/', trim($path));
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $ordered[] = $normalized;
        }

        return $ordered;
    }

    protected function sanitizeBaseRef(?string $base): ?string
    {
        $base = trim((string) $base);

        return $base !== '' ? $base : null;
    }

    /**
     * @return list<string>
     */
    protected function parseStdinPaths(?string $stdin): array
    {
        if ($stdin === null) {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $stdin) ?: [];
    }

    protected function normalizePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', trim($path)));
    }

    protected function isGitRepository(): bool
    {
        $result = $this->runGit(['rev-parse', '--is-inside-work-tree']);

        return $result['exit_code'] === 0 && trim($result['stdout']) === 'true';
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function collectGitPaths(?string $base): array
    {
        $paths = [];
        $notes = [];

        if ($base !== null) {
            $result = $this->runGit(['diff', '--name-only', '--diff-filter=ACMR', $base.'...HEAD']);
            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(trim($result['stderr']) !== '' ? trim($result['stderr']) : sprintf('Unable to diff against base `%s`.', $base));
            }

            $paths = array_merge($paths, $this->parseStdinPaths($result['stdout']));
            $notes[] = sprintf('collected branch diff against %s', $base);
            $notes[] = 'considered local staged, unstaged, and untracked files alongside the base diff';
        }

        foreach (
            [
                [['diff', '--name-only', '--diff-filter=ACMR'], 'unstaged changes'],
                [['diff', '--cached', '--name-only', '--diff-filter=ACMR'], 'staged changes'],
                [['ls-files', '--others', '--exclude-standard'], 'untracked files'],
            ] as [$arguments, $label]
        ) {
            $result = $this->runGit($arguments);
            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(trim($result['stderr']) !== '' ? trim($result['stderr']) : 'Git command failed.');
            }

            $currentPaths = $this->parseStdinPaths($result['stdout']);
            if ($currentPaths !== []) {
                $paths = array_merge($paths, $currentPaths);
                $notes[] = sprintf('included %s', $label);
            }
        }

        return [$this->uniquePaths($paths), $notes];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    protected function runGit(array $arguments): array
    {
        $process = new Process(['git', ...$arguments], base_path());
        $process->run();

        return [
            'exit_code' => (int) ($process->getExitCode() ?? 1),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
