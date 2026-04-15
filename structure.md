app/
├─ Modules/
│  ├─ IdentityAccess/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ User.php
│  │  │  │  ├─ Role.php
│  │  │  │  ├─ CustomerAccessSession.php
│  │  │  │  ├─ StaffApiKey.php
│  │  │  │  ├─ BankAccount.php
│  │  │  │  └─ NotificationPreference.php
│  │  │  ├─ Enums/
│  │  │  ├─ Policies/
│  │  │  ├─ Guards/
│  │  │  └─ ValueObjects/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ CustomerAccessSessionService.php
│  │  │     ├─ CustomerReservationSessionAccessService.php
│  │  │     └─ StaffApiKeyGovernanceService.php
│  │  ├─ Infrastructure/
│  │  │  ├─ Auth/
│  │  │  │  ├─ CustomerAuthTokenResolver.php
│  │  │  │  ├─ CustomerSessionRouteContract.php
│  │  │  │  ├─ StaffActorResolver.php
│  │  │  │  ├─ StaffCapabilityResolver.php
│  │  │  │  ├─ StaffMutationRowVersionContract.php
│  │  │  │  └─ RequestActorContext.php
│  │  │  └─ Persistence/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  └─ Auth/
│  │     │     ├─ CustomerAuthController.php
│  │     │     └─ StaffAuthController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ BranchScheduling/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Branch.php
│  │  │  │  ├─ RestaurantTable.php
│  │  │  │  ├─ TableTemplate.php
│  │  │  │  ├─ TableHold.php
│  │  │  │  └─ TableHoldDetail.php
│  │  │  ├─ Policies/
│  │  │  ├─ Guards/
│  │  │  └─ ValueObjects/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ BranchContextService.php
│  │  │     ├─ BranchManagementService.php
│  │  │     ├─ BranchSchedulingPolicyService.php
│  │  │     ├─ ReservationBranchScopeService.php
│  │  │     ├─ RestaurantTableStateService.php
│  │  │     ├─ TableAvailabilityService.php
│  │  │     ├─ TableHoldService.php
│  │  │     └─ TableTimeConflictService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ TableController.php
│  │     │  └─ TableHoldController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ Reservations/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Reservation.php
│  │  │  │  └─ ReservationTable.php
│  │  │  ├─ Guards/
│  │  │  │  ├─ ReservationAccessScope.php
│  │  │  │  ├─ HoldConflictScope.php
│  │  │  │  └─ ReservationViewProfile.php
│  │  │  ├─ Validators/
│  │  │  │  └─ ReservationConflictValidator.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ ReservationCreateService.php
│  │  │     ├─ ReservationService.php
│  │  │     ├─ ReservationCancellationService.php
│  │  │     ├─ ReservationRescheduleService.php
│  │  │     ├─ ReservationStatusTransitionService.php
│  │  │     ├─ ReservationTableAssignmentService.php
│  │  │     ├─ ReservationCodeGenerator.php
│  │  │     ├─ ReservationLockService.php
│  │  │     ├─ CustomerReservationSelfService.php
│  │  │     ├─ CustomerReservationPreorderService.php
│  │  │     ├─ ReservationPreorderService.php
│  │  │     ├─ ReservationDepositReadService.php
│  │  │     ├─ ReservationDepositSelfServiceStateService.php
│  │  │     └─ ReservationDepositRealtimePublisher.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ ReservationController.php
│  │     │  ├─ CustomerReservationSelfServiceController.php
│  │     │  ├─ CustomerReservationPreorderController.php
│  │     │  ├─ CustomerReservationDepositController.php
│  │     │  └─ Staff/
│  │     │     ├─ StaffReservationCheckInController.php
│  │     │     ├─ StaffReservationRescheduleController.php
│  │     │     ├─ StaffReservationTimelineController.php
│  │     │     └─ StaffReservationTimelineWorkbenchController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ WaitingList/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  └─ WaitingList.php
│  │  │  ├─ State/
│  │  │  │  └─ WaitingListStateMachine.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ WaitingListService.php
│  │  │     ├─ CustomerWaitingListService.php
│  │  │     └─ StaffWaitingListService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ CustomerWaitingListController.php
│  │     │  └─ Staff/StaffWaitingListController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ FloorOps/
│  │  ├─ Domain/
│  │  │  ├─ Guards/
│  │  │  │  ├─ TableReleaseGuard.php
│  │  │  │  └─ StaffReservationOperationGuard.php
│  │  │  └─ Audit/
│  │  │     └─ TableStateAuditLogger.php
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ StaffBranchContextService.php
│  │  │     ├─ StaffCheckInReadinessService.php
│  │  │     ├─ StaffCheckInService.php
│  │  │     ├─ StaffServiceSessionService.php
│  │  │     ├─ StaffMoveTableService.php
│  │  │     ├─ StaffTableReleaseService.php
│  │  │     ├─ StaffReservationBoardAssignmentService.php
│  │  │     └─ StaffTableBoardService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  └─ Staff/
│  │     │     ├─ StaffBranchContextController.php
│  │     │     ├─ StaffServiceSessionController.php
│  │     │     ├─ StaffReservationMoveTableController.php
│  │     │     ├─ StaffReservationBoardAssignmentController.php
│  │     │     ├─ StaffTableBoardController.php
│  │     │     └─ StaffTableReleaseController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ Ordering/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ ReservationOrder.php
│  │  │  │  └─ ReservationOrderItem.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ StaffTableOrderService.php
│  │  │     ├─ StaffOrderReadService.php
│  │  │     └─ StaffOrderItemLifecycleService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  └─ Staff/
│  │     │     ├─ StaffTableOrderController.php
│  │     │     ├─ StaffOrderReadController.php
│  │     │     └─ StaffOrderItemLifecycleController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ CheckoutPayments/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Payment.php
│  │  │  │  ├─ ReservationBillPaymentSession.php
│  │  │  │  ├─ ReservationDepositPaymentSession.php
│  │  │  │  ├─ BillingInvoice.php
│  │  │  │  ├─ CashierShift.php
│  │  │  │  └─ PaymentProviderWebhookReceipt.php
│  │  │  ├─ Policies/
│  │  │  │  ├─ PaymentIntegrityGuard.php
│  │  │  │  ├─ RefundAllocationPolicy.php
│  │  │  │  └─ PaymentSessionStatusTransitionPolicy.php
│  │  │  └─ ValueObjects/
│  │  │     └─ PaymentSummary.php
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ ReservationBillPaymentService.php
│  │  │     ├─ ReservationDepositPaymentService.php
│  │  │     ├─ ReservationFinancialSyncService.php
│  │  │     ├─ CustomerReservationBillPaymentService.php
│  │  │     ├─ CustomerReservationDepositPaymentService.php
│  │  │     ├─ CustomerReservationOrderBillService.php
│  │  │     ├─ CustomerReservationBillService.php
│  │  │     ├─ StaffCheckoutService.php
│  │  │     ├─ BillLockService.php
│  │  │     ├─ CheckoutResponseFactory.php
│  │  │     ├─ OrderSettlementService.php
│  │  │     ├─ PaymentCaptureService.php
│  │  │     ├─ RefundExecutionService.php
│  │  │     ├─ RefundPlannerService.php
│  │  │     ├─ SettlementAmountCalculator.php
│  │  │     ├─ SettlementFinalizerService.php
│  │  │     ├─ StaffCashierShiftService.php
│  │  │     ├─ StaffInvoiceService.php
│  │  │     ├─ StaffFinancialReconciliationService.php
│  │  │     ├─ StaffReservationDepositService.php
│  │  │     └─ StaffReservationDepositOperationalReadService.php
│  │  ├─ Infrastructure/
│  │  │  ├─ PaymentProviders/
│  │  │  │  ├─ PaymentProviderAdapter.php
│  │  │  │  ├─ GenericHttpHmacPaymentProviderAdapter.php
│  │  │  │  ├─ SimulatedPaymentProviderAdapter.php
│  │  │  │  ├─ PaymentProviderRegistry.php
│  │  │  │  ├─ PaymentProviderRolloutConfig.php
│  │  │  │  ├─ PaymentProviderSessionScopeGuard.php
│  │  │  │  ├─ PaymentWebhookIngestionService.php
│  │  │  │  ├─ ReservationBillPaymentSessionLifecycleService.php
│  │  │  │  └─ ReservationDepositPaymentSessionLifecycleService.php
│  │  │  ├─ CustomerBillPayment/
│  │  │  │  ├─ CustomerBillPaymentProvider.php
│  │  │  │  ├─ CustomerBillPaymentProviderRegistry.php
│  │  │  │  ├─ GenericHttpHmacCustomerBillPaymentProvider.php
│  │  │  │  └─ SimulatedCustomerBillPaymentProvider.php
│  │  │  ├─ CustomerDepositPayment/
│  │  │  │  ├─ CustomerDepositPaymentProvider.php
│  │  │  │  ├─ CustomerDepositPaymentProviderRegistry.php
│  │  │  │  ├─ GenericHttpHmacCustomerDepositPaymentProvider.php
│  │  │  │  └─ SimulatedCustomerDepositPaymentProvider.php
│  │  │  └─ Drivers/
│  │  │     └─ SimulatedCustomerPaymentSessionDriver.php
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ CustomerReservationBillPaymentController.php
│  │     │  ├─ CustomerReservationDepositPaymentController.php
│  │     │  ├─ CustomerReservationOrderBillController.php
│  │     │  ├─ PaymentProviderWebhookController.php
│  │     │  └─ Staff/
│  │     │     ├─ StaffCheckoutController.php
│  │     │     ├─ StaffCashierShiftController.php
│  │     │     ├─ StaffFinanceInvoiceController.php
│  │     │     ├─ StaffFinancialReconciliationController.php
│  │     │     └─ StaffReservationDepositController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ BenefitsLoyalty/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Voucher.php
│  │  │  │  ├─ UserVoucher.php
│  │  │  │  ├─ LoyaltyTier.php
│  │  │  │  ├─ UserPoint.php
│  │  │  │  ├─ UserTierHistory.php
│  │  │  │  └─ LoyaltyPointTransaction.php
│  │  │  ├─ Policies/
│  │  │  │  ├─ VoucherUsageGuard.php
│  │  │  │  ├─ VoucherRedemptionSupport.php
│  │  │  │  ├─ ReservationVoucherLifecycleSupport.php
│  │  │  │  └─ LoyaltyEarnReconciliation.php
│  │  │  └─ ValueObjects/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ CustomerBenefitsService.php
│  │  │     ├─ CustomerReservationBenefitsSelfService.php
│  │  │     ├─ ReservationVoucherPreviewService.php
│  │  │     ├─ LoyaltyPointsService.php
│  │  │     ├─ LoyaltyAdjustmentService.php
│  │  │     ├─ LoyaltyBalanceService.php
│  │  │     ├─ LoyaltyCompletionSyncService.php
│  │  │     ├─ LoyaltyLedgerWriter.php
│  │  │     ├─ LoyaltyRedemptionService.php
│  │  │     ├─ LoyaltyRefundSyncService.php
│  │  │     ├─ LoyaltyTierSyncService.php
│  │  │     ├─ AdminBenefitsService.php
│  │  │     ├─ AdminBenefitSettingService.php
│  │  │     ├─ AdminLoyaltyTierService.php
│  │  │     ├─ AdminRuntimeSettingService.php
│  │  │     ├─ AdminVoucherService.php
│  │  │     └─ StaffReservationVoucherService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ CustomerBenefitsController.php
│  │     │  ├─ CustomerReservationBenefitsActionController.php
│  │     │  ├─ Admin/
│  │     │  │  ├─ AdminBenefitSettingController.php
│  │     │  │  ├─ AdminLoyaltyTierController.php
│  │     │  │  └─ AdminVoucherController.php
│  │     │  └─ Staff/
│  │     │     ├─ StaffLoyaltyController.php
│  │     │     └─ StaffReservationVoucherController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ MenuCatalog/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ MenuCategory.php
│  │  │  │  ├─ MenuItem.php
│  │  │  │  ├─ MenuItemPrice.php
│  │  │  │  └─ MenuItemRecipe.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ AdminMenuService.php
│  │  │     ├─ AdminMenuManagementService.php
│  │  │     └─ MenuPreorderPolicyService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ CustomerMenuCatalogController.php
│  │     │  ├─ Staff/StaffMenuCatalogController.php
│  │     │  └─ Admin/
│  │     │     ├─ AdminMenuController.php
│  │     │     ├─ AdminMenuCategoryController.php
│  │     │     ├─ AdminMenuItemController.php
│  │     │     └─ AdminMenuItemPriceController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ InventoryPurchasing/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Ingredient.php
│  │  │  │  ├─ IngredientStockMovement.php
│  │  │  │  ├─ Supplier.php
│  │  │  │  ├─ PurchaseOrder.php
│  │  │  │  ├─ PurchaseOrderLine.php
│  │  │  │  ├─ PurchaseReceipt.php
│  │  │  │  └─ PurchaseReceiptLine.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ AdminInventoryService.php
│  │  │     └─ AdminPurchasingService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  └─ Admin/
│  │     │     ├─ AdminInventoryController.php
│  │     │     └─ AdminPurchasingController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ KitchenDispatch/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ KitchenStation.php
│  │  │  │  ├─ KitchenStationCategoryRoute.php
│  │  │  │  └─ KitchenOrderItemTicket.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     └─ KitchenRoutingService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ Staff/StaffKitchenController.php
│  │     │  └─ Admin/AdminKitchenRoutingController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ Conversations/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ Conversation.php
│  │  │  │  ├─ AgentAssignment.php
│  │  │  │  ├─ ConversationMessage.php
│  │  │  │  ├─ ConversationFile.php
│  │  │  │  ├─ ConversationEvent.php
│  │  │  │  ├─ ConversationAnalysis.php
│  │  │  │  ├─ MessageEntity.php
│  │  │  │  └─ ConversationAggregate.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  ├─ Actions/
│  │  │  ├─ Queries/
│  │  │  └─ Services/
│  │  │     ├─ StaffConversationInboxService.php
│  │  │     ├─ StaffConversationWorkflowService.php
│  │  │     ├─ StaffConversationOutboundReplySupportService.php
│  │  │     └─ StaffReservationInboxService.php
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  └─ Staff/
│  │     │     ├─ StaffConversationInboxController.php
│  │     │     └─ StaffReservationInboxController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ Notifications/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ NotificationOutbox.php
│  │  │  │  └─ NotificationDeliveryAttempt.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  └─ Services/
│  │  │     ├─ NotificationOutboxService.php
│  │  │     ├─ NotificationOutboxHealthService.php
│  │  │     └─ NotificationPreferenceService.php
│  │  ├─ Infrastructure/
│  │  │  ├─ Drivers/
│  │  │  ├─ Contracts/
│  │  │  └─ NotificationChannelManager.php
│  │  └─ Http/
│  │
│  ├─ PrivacyAudit/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ CustomerPrivacyRequest.php
│  │  │  │  ├─ AuditLog.php
│  │  │  │  └─ AuditLogSubject.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  └─ Services/
│  │  │     ├─ AuditTrailQueryService.php
│  │  │     └─ DataLifecycle/
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ CustomerDataLifecycleController.php
│  │     │  ├─ Staff/StaffAuditTrailController.php
│  │     │  └─ Admin/AdminCustomerDataLifecycleController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  ├─ Reporting/
│  │  ├─ Domain/
│  │  │  ├─ Models/
│  │  │  │  ├─ ReportingDailySalesSnapshot.php
│  │  │  │  ├─ ReportingDailyOperationSnapshot.php
│  │  │  │  └─ ReportingDailyInventoryMovementSnapshot.php
│  │  │  └─ Policies/
│  │  ├─ Application/
│  │  │  └─ Services/
│  │  │     ├─ StaffOperationalRealtimeService.php
│  │  │     └─ Reporting/
│  │  ├─ Infrastructure/
│  │  └─ Http/
│  │     ├─ Controllers/
│  │     │  ├─ Staff/
│  │     │  │  ├─ StaffOperationalRealtimeController.php
│  │     │  │  └─ StaffReportingController.php
│  │     │  └─ Admin/AdminReportingController.php
│  │     ├─ Requests/
│  │     └─ Resources/
│  │
│  └─ AdminMasterDataBulk/
│  │  ├─ Domain/
│  │  │  ├─ Contracts/
│  │  │  │  └─ MasterDataBulkDomain.php
│  │  │  └─ Domains/
│  │  │     ├─ BranchesBulkDomain.php
│  │  │     ├─ LoyaltyTiersBulkDomain.php
│  │  │     ├─ MenuCategoriesBulkDomain.php
│  │  │     ├─ MenuItemsBulkDomain.php
│  │  │     ├─ MenuPricesBulkDomain.php
│  │  │     ├─ RestaurantTablesBulkDomain.php
│  │  │     └─ VouchersBulkDomain.php
│  │  ├─ Application/
│  │  │  └─ Services/
│  │  │     ├─ AdminMasterDataBulkService.php
│  │  │     └─ MasterDataBulkRegistry.php
│  │  ├─ Infrastructure/
│  │  │  ├─ Parsers/
│  │  │  │  └─ MasterDataImportSourceParser.php
│  │  │  └─ Support/
│  │  │     └─ AbstractMasterDataBulkDomain.php
│  │  └─ Http/
│  │     └─ Controllers/
│  │        └─ Admin/AdminMasterDataBulkController.php
│  │
├─ Platform/
│  ├─ ApiContract/
│  │  ├─ Services/
│  │  │  ├─ RouteContractReconcilerService.php
│  │  │  ├─ RouteInventoryGateService.php
│  │  │  ├─ DatabaseContractInspector.php
│  │  │  └─ OpsGateArtifactService.php
│  │  ├─ ApiArtifacts/
│  │  └─ Verification/
│  ├─ Release/
│  │  ├─ Services/
│  │  │  ├─ ReleaseArtifactManifestService.php
│  │  │  ├─ ReleaseArtifactNormalizerService.php
│  │  │  ├─ ReleaseBuildMetadataService.php
│  │  │  ├─ ReleaseBuildService.php
│  │  │  ├─ ReleaseLoopService.php
│  │  │  ├─ ReleasePackageService.php
│  │  │  ├─ BookingDeploySafetyService.php
│  │  │  ├─ LaunchReadinessService.php
│  │  │  ├─ CoreOpsGateService.php
│  │  │  ├─ RoundFiveGateService.php
│  │  │  └─ SiteBootstrapService.php
│  ├─ Health/
│  │  ├─ Services/
│  │  │  ├─ BookingDoctorService.php
│  │  │  ├─ BookingEnvironmentValidator.php
│  │  │  ├─ BookingMaintenanceService.php
│  │  │  ├─ OpsHeartbeatService.php
│  │  │  └─ OperationalHealthEvaluator.php
│  │  └─ Http/
│  │     └─ HealthController.php
│  ├─ Metrics/
│  │  ├─ Services/
│  │  │  ├─ MetricsService.php
│  │  │  ├─ OperationalInsightsService.php
│  │  │  └─ OperationalAlertService.php
│  │  └─ Http/
│  │     └─ MetricsController.php
│  ├─ FeatureFlags/
│  │  ├─ Domain/
│  │  │  └─ Models/FeatureFlag.php
│  │  └─ Services/
│  │     ├─ FeatureFlagService.php
│  │     ├─ FeatureFlagManagementService.php
│  │     └─ RuntimeSettingService.php
│  ├─ Backup/
│  │  ├─ DisasterRecovery/
│  │  └─ Support/
│  ├─ Performance/
│  ├─ Harness/
│  ├─ Uat/
│  └─ Verification/
│
├─ Shared/
│  ├─ Domain/
│  │  ├─ Enums/
│  │  └─ Casts/
│  ├─ Http/
│  │  ├─ Concerns/
│  │  └─ Resources/
│  ├─ Infrastructure/
│  │  ├─ Providers/
│  │  └─ Persistence/
│  └─ Support/
│     ├─ ApiErrorResponse.php
│     ├─ ApiPayloadEncodingNormalizer.php
│     ├─ DatabaseWriteConflictMapper.php
│     ├─ ValidationExceptionFactory.php
│     └─ PortableSqlSanitizer.php
│
├─ Providers/
└─ Console/   (nếu sau này muốn gom command)
