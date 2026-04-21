# Module Ownership Map

This map is the canonical ownership reference for `app/` after the six-batch modularization.

## Platform

- Owns cross-cutting release, artifact, metrics, observability, verification, backup, and harness code under `app/Platform/`.
- Representative paths:
  - `app/Platform/ApiContract/`
  - `app/Platform/Metrics/`
  - `app/Platform/Release/`
  - `app/Platform/Verification/`

## BranchScheduling

- Owns branch-local time/policy/availability/hold behavior.
- Representative paths:
  - Models: `app/Modules/BranchScheduling/Domain/Models/Branch.php`, `TableHold.php`, `RestaurantTable.php`
  - Services: `app/Modules/BranchScheduling/Application/Services/BranchContextService.php`, `BranchSchedulingPolicyService.php`, `TableAvailabilityService.php`, `TableHoldService.php`
  - HTTP: `app/Modules/BranchScheduling/Http/Controllers/TableController.php`, `TableHoldController.php`

## Reservations

- Owns reservation lifecycle, table assignment coordination, customer reservation self-service, and reservation write safety.
- Representative paths:
  - Models: `app/Modules/Reservations/Domain/Models/Reservation.php`
  - Services: `app/Modules/Reservations/Application/Services/ReservationService.php`, `ReservationCreateService.php`, `ReservationRescheduleService.php`, `ReservationLockService.php`
  - HTTP: `app/Modules/Reservations/Http/Controllers/`

## FloorOps

- Owns table board, check-in, walk-in service sessions, and branch-scoped staff operational context.
- Representative paths:
  - Services: `app/Modules/FloorOps/Application/Services/StaffTableBoardService.php`, `StaffCheckInService.php`, `StaffServiceSessionService.php`, `StaffBranchContextService.php`
  - HTTP: `app/Modules/FloorOps/Http/Controllers/Staff/`

## Ordering

- Owns active order reads, order writes, and item lifecycle operations.
- Representative paths:
  - Models: `app/Modules/Ordering/Domain/Models/ReservationOrder.php`, `ReservationOrderItem.php`
  - Services: `app/Modules/Ordering/Application/Services/StaffTableOrderService.php`, `StaffOrderReadService.php`, `StaffOrderItemLifecycleService.php`
  - HTTP: `app/Modules/Ordering/Http/Controllers/Staff/`

## KitchenDispatch

- Owns kitchen routing, dispatch, ticket state changes, and kitchen read surfaces.
- Representative paths:
  - Models: `app/Modules/KitchenDispatch/Domain/Models/KitchenStation.php`, `KitchenOrderItemTicket.php`
  - Services: `app/Modules/KitchenDispatch/Application/Services/KitchenRoutingService.php`, `KitchenTicketReconciliationService.php`
  - HTTP: `app/Modules/KitchenDispatch/Http/Controllers/Staff/`, `Admin/`

## CheckoutPayments

- Owns settlement, payment session lifecycle, provider/webhook integration, cashier shifts, invoice/reconciliation, and refund execution.
- Representative paths:
  - Models: `app/Modules/CheckoutPayments/Domain/Models/Payment.php`, `BillingInvoice.php`, `CashierShift.php`
  - Services: `app/Modules/CheckoutPayments/Application/Services/StaffCheckoutService.php`, `ReservationDepositPaymentService.php`, `ReservationBillPaymentService.php`
  - HTTP: `app/Modules/CheckoutPayments/Http/Controllers/Customer/`, `Staff/`, `PaymentProviderWebhookController.php`

## BenefitsLoyalty

- Owns voucher, loyalty, points, tier sync, and benefits preview/apply/remove flows.
- Representative paths:
  - Models: `app/Modules/BenefitsLoyalty/Domain/Models/Voucher.php`, `LoyaltyTier.php`
  - Services: `app/Modules/BenefitsLoyalty/Application/Services/LoyaltyPointsService.php`, `StaffReservationVoucherService.php`, `CustomerBenefitsService.php`
  - HTTP: `app/Modules/BenefitsLoyalty/Http/Controllers/Customer/`, `Staff/`, `Admin/`

## WaitingList

- Owns waiting-list lifecycle, customer owner actions, staff queue actions, and waiting-list transition rules.
- Representative paths:
  - Models: `app/Modules/WaitingList/Domain/Models/WaitingList.php`
  - State: `app/Modules/WaitingList/Domain/State/WaitingListStateMachine.php`
  - Services: `app/Modules/WaitingList/Application/Services/CustomerWaitingListService.php`, `StaffWaitingListService.php`, `WaitingListInviteLifecycleService.php`, `WaitingListOperationalOrchestrationService.php`
  - HTTP: `app/Modules/WaitingList/Http/Controllers/Customer/CustomerWaitingListController.php`, `Staff/StaffWaitingListController.php`
  - Requests/resources: `app/Modules/WaitingList/Http/Requests/`, `app/Modules/WaitingList/Http/Resources/`

## Conversations

- Owns conversation inbox reads, assignment/workflow actions, linked reservation or waiting-list context, and outbound reply support.
- Representative paths:
  - Models: `app/Modules/Conversations/Domain/Models/Conversation.php`, `AgentAssignment.php`, `ConversationMessage.php`, `ConversationEvent.php`, `ConversationAnalysis.php`
  - Services: `app/Modules/Conversations/Application/Services/StaffConversationInboxService.php`, `StaffConversationWorkflowService.php`, `StaffConversationOutboundReplySupportService.php`, `StaffReservationInboxService.php`
  - HTTP: `app/Modules/Conversations/Http/Controllers/Staff/ConversationInboxController.php`, `ReservationInboxController.php`
  - Requests/resources: `app/Modules/Conversations/Http/Requests/Staff/`, `app/Modules/Conversations/Http/Resources/`

## Notifications

- Owns outbox rows, delivery attempts, preference evaluation, channel driver resolution, and notification delivery health.
- Representative paths:
  - Models: `app/Modules/Notifications/Domain/Models/NotificationOutbox.php`, `NotificationDeliveryAttempt.php`, `NotificationPreference.php`
  - Services: `app/Modules/Notifications/Application/Services/NotificationOutboxService.php`, `NotificationOutboxHealthService.php`, `NotificationPreferenceService.php`
- Channel runtime: `app/Modules/Notifications/Application/Services/NotificationChannelManager.php`, `Infrastructure/Contracts/`, `Infrastructure/Drivers/`

## PrivacyCompliance

- Owns customer privacy requests, export/anonymize orchestration, retention-safe privacy application, and audit-trail querying.
- Representative paths:
  - Models: `app/Modules/PrivacyCompliance/Domain/Models/CustomerPrivacyRequest.php`, `AuditLog.php`, `AuditLogSubject.php`
  - Application: `app/Modules/PrivacyCompliance/Application/UseCases/Requests/CustomerDataExportHandler.php`, `Application/Workflows/Redaction/CustomerAnonymizationWorkflow.php`, `Application/Workflows/Requests/PrivacyRequestWorkflow.php`, `Application/Workflows/Retention/RetentionEnforcementWorkflow.php`, `Application/Queries/Audit/AuditTrailQueryHandler.php`
  - HTTP: `app/Modules/PrivacyCompliance/Http/Controllers/Customer/PrivacyRequestController.php`, `Admin/PrivacyController.php`, `Staff/AuditTrailController.php`
  - Requests: `app/Modules/PrivacyCompliance/Http/Requests/`

## Reporting

- Owns reporting snapshot rebuilds and reporting read-model queries.
- Representative paths:
  - Models: `app/Modules/Reporting/Domain/Models/ReportingDailySalesSnapshot.php`, `ReportingDailyOperationSnapshot.php`, `ReportingDailyInventoryMovementSnapshot.php`
  - Services: `app/Modules/Reporting/Application/Services/ReportingSnapshotService.php`
  - HTTP: `app/Modules/Reporting/Http/Controllers/Staff/OperationalReportController.php`, `Admin/ReportingSnapshotController.php`
  - Requests/resources: `app/Modules/Reporting/Http/Requests/`, `app/Modules/Reporting/Http/Resources/`

## MasterDataExchange

- Owns exchange-only import/export orchestration, domain registry lookup, parser selection, dry-run and commit gates, and batch reporting.
- Representative paths:
  - Contracts/domains: `app/Modules/MasterDataExchange/Domain/Contracts/MasterDataDomain.php`, `Domain/Registries/*.php`
  - Application: `app/Modules/MasterDataExchange/Application/Workflows/MasterDataExchangeWorkflow.php`, `Application/UseCases/Registry/MasterDataDomainRegistry.php`
  - Infrastructure: `app/Modules/MasterDataExchange/Infrastructure/Files/Parsers/MasterDataImportSourceParser.php`, `Infrastructure/Internal/AbstractMasterDataDomain.php`
  - HTTP: `app/Modules/MasterDataExchange/Http/Controllers/Admin/MasterDataExchangeController.php`
