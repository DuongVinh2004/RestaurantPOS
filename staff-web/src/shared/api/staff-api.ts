import type {
  AdminIngredientCollectionEnvelope,
  AdminBenefitSettingCollectionEnvelope,
  AdminBenefitSettingEnvelope,
  AdminCustomerDataExportEnvelope,
  AdminLoyaltyTierCollectionEnvelope,
  AdminLoyaltyTierEnvelope,
  AdminPrivacyRequestCollectionEnvelope,
  AdminPrivacyReviewEnvelope,
  AdminPurchaseOrderCollectionEnvelope,
  AdminPurchaseOrderReceiptEnvelope,
  AdminSupplierCollectionEnvelope,
  AdminVoucherCollectionEnvelope,
  AdminVoucherEnvelope,
  AddConversationInternalNoteRequest,
  AddOrderItemsRequest,
  AssignConversationRequest,
  BranchCollectionEnvelope,
  BranchScopeRequest,
  CancelReservationRequest,
  CancelWaitlistRequest,
  CashierShiftCollectionEnvelope,
  CashierShiftEnvelope,
  StaffCheckInReservationRequest as CheckInReservationRequest,
  StaffCheckoutOrderRequest as CheckoutOrderRequest,
  CreateIngredientStockMovementRequest,
  CreatePurchaseOrderReceiptRequest,
  CloseCashierShiftRequest,
  CreateRestaurantTableRequest,
  CreateWaitlistEntryRequest,
  CustomerMenuItemsCollectionEnvelope,
  DispatchKitchenTicketRequest as DispatchKitchenTicketsRequest,
  FinanceInvoiceEnvelope as SdkFinanceInvoiceEnvelope,
  FinancialReconciliationCollectionEnvelope as SdkFinancialReconciliationCollectionEnvelope,
  FinancialReconciliationDetailEnvelope as SdkFinancialReconciliationDetailEnvelope,
  FinancialReconciliationRow as SdkFinancialReconciliationRow,
  FinancialReservationSummary as SdkFinancialReservationSummary,
  GetV1AdminInventoryIngredientsQueryParams,
  GetV1AdminInventoryIngredientsIdMovementsQueryParams,
  GetV1AdminInventoryPurchaseOrdersQueryParams,
  GetV1AdminInventoryPurchaseOrdersIdReceiptsQueryParams,
  GetV1AdminInventorySuppliersQueryParams,
  GetV1AdminPrivacyRequestsQueryParams,
  GetV1AdminSettingsBranchesQueryParams,
  GetV1StaffAuditTrailQueryParams,
  GetV1StaffConversationsConversationIdQueryParams,
  GetV1StaffConversationsQueryParams,
  GetV1StaffCashierShiftsQueryParams,
  GetV1StaffFinanceReconciliationQueryParams,
  GetV1StaffReportingDailyInventoryQueryParams,
  GetV1StaffReportingDailyOperationsQueryParams,
  GetV1StaffReportingDailySalesQueryParams,
  GetV1MenuItemsQueryParams,
  GetV1StaffKitchenStationsStationIdTicketsQueryParams,
  GetV1StaffOrdersOrderIdSettlementPreviewQueryParams,
  GetV1StaffReservationsQueryParams,
  GetV1StaffReservationsReservationIdRefundPreviewQueryParams,
  GetV1StaffWaitingListQueryParams,
  GenericDataEnvelope,
  InviteWaitlistCustomerRequest as NotifyWaitingListRequest,
  LinkConversationRequest,
  MoveTableRequest,
  OpenCashierShiftRequest,
  PayOrderRequest,
  PayReservationDepositRequest,
  ReservationActionEnvelope,
  ReservationEnvelope,
  ReservationOrder,
  ReleaseTableRequest,
  RefundAndCancelReservationRequest,
  RefundReservationRequest,
  RestaurantTableEnvelope,
  SendConversationOutboundReplyRequest,
  SeatWaitlistRequest as SeatWaitingListRequest,
  StaffAuditTrailEnvelope,
  StaffAssignBestFitTableRequest,
  StaffAssignSuggestedTableRequest,
  StaffCheckoutSettlementEnvelope,
  StaffConversationCollectionEnvelope,
  StaffConversationDetailEnvelope,
  StaffConversationMutationEnvelope,
  StaffKitchenDispatchEnvelope,
  StaffKitchenStationCollectionEnvelope,
  StaffKitchenTicketActionRequest,
  StaffKitchenTicketEnvelope,
  StaffKitchenTicketCollectionEnvelope,
  StaffOperationalRealtimeEnvelope,
  StaffOrderReadEnvelope,
  StaffReservationLookupCollectionEnvelope,
  StaffReservationOrderCollectionEnvelope,
  StaffReservationOrderEnvelope,
  StaffReportingDailyInventoryCollectionEnvelope,
  StaffReportingDailyOperationsCollectionEnvelope,
  StaffReportingDailySalesCollectionEnvelope,
  StaffRefundEnvelope,
  StaffRefundPreviewEnvelope,
  StaffTableBoardEnvelope,
  StaffWaitingListCollectionEnvelope,
  StaffWaitingListEntry,
  StaffWaitingListEnvelope,
  StaffWaitingListAdvanceEnvelope,
  StaffWaitingListSeatEnvelope,
  StaffTablesBoardQueryParams,
  StoreLoyaltyTierRequest,
  StoreMenuCategoryRequest,
  StoreMenuItemPriceRequest,
  StoreMenuItemRequest,
  CreateReservationRequest as StoreReservationRequest,
  StoreVoucherRequest,
  TakeOverConversationRequest,
  UpdateConversationWorkflowStateRequest,
  UpdateLoyaltyTierRequest,
  UpdateOrderItemRequest,
  UpdateOrderItemStatusRequest,
  UpdateVoucherRequest,
  UpsertBenefitSettingRequest,
} from './sdk';
import { createIdempotencyKey } from '../utils/idempotency';
import { staffClient } from './client';
import { StaffApiError, apiRequest } from './http';
export { getCurrentStaffSession, loginStaff, logoutStaff, refreshStaffSession } from './staff-auth-api';
export type { PayReservationDepositRequest } from './sdk';
export { listBranches } from './staff-branch-api';

export type WalkInPayload = {
  branch_id?: number | null;
  user_id?: number | null;
  guest_name?: string | null;
  phone?: string | null;
  table_ids: Array<number>;
  guest_count: number;
  started_at?: string | null;
  service_minutes?: number | null;
  notes?: string | null;
};

export type CreateReservationPayload = StoreReservationRequest;

export type UpdateReservationStatusPayload = {
  status: 'Confirmed' | 'Cancelled' | 'Expired' | 'NoShow' | 'Reserved' | 'CheckedIn';
  row_version?: number | null;
  cancel_reason?: string | null;
  force?: boolean;
};

export type AssignSuggestedTablePayload = {
  table_id: number;
  row_version: number;
  board_from?: string | null;
  board_to?: string | null;
  zone?: string | null;
};

export type AssignBestFitTablePayload = {
  row_version: number;
  board_from?: string | null;
  board_to?: string | null;
  zone?: string | null;
};

export type UpdateOrderItemPayload = UpdateOrderItemRequest;

export type UpdateOrderItemStatusPayload = UpdateOrderItemStatusRequest;

export type MoveTablePayload = {
  from_table_id: number;
  to_table_id: number;
  row_version: number;
  moved_at?: string | null;
};

export type ReleaseTablePayload = {
  row_version: number;
  force?: boolean;
  notes?: string | null;
};

export type ReservationListQuery = GetV1StaffReservationsQueryParams & {
  branch_id?: number;
};

export type ConversationInboxQuery = GetV1StaffConversationsQueryParams;
export type ConversationDetailQuery = GetV1StaffConversationsConversationIdQueryParams;
export type AssignConversationPayload = {
  agent_user_id: number;
  notes?: string | null;
};
export type UnassignConversationPayload = {
  notes?: string | null;
};
export type ConversationWorkflowState = 'Open' | 'Triaged' | 'Assigned' | 'PendingCustomer' | 'Resolved' | 'Closed';
export type UpdateConversationWorkflowStatePayload = {
  workflow_state: Exclude<ConversationWorkflowState, 'Assigned'>;
  expected_workflow_state?: ConversationWorkflowState | null;
  reason?: string | null;
};
export type LinkConversationPayload = {
  reservation_id?: number | null;
  waiting_list_id?: number | null;
  customer_user_id?: number | null;
  notes?: string | null;
};
export type AuditTrailQuery = GetV1StaffAuditTrailQueryParams;

export type DailySalesReportingQuery = GetV1StaffReportingDailySalesQueryParams;
export type DailyOperationsReportingQuery = GetV1StaffReportingDailyOperationsQueryParams;
export type DailyInventoryReportingQuery = GetV1StaffReportingDailyInventoryQueryParams;
export type FinancialReconciliationQuery = {
  branch_id?: number;
  reservation_id?: number;
  reservation_code?: string;
  user_id?: number;
  status?: string;
  deposit_status?: string;
  payment_currency?: string;
  cashier_user_id?: number;
  activity_from?: string;
  activity_to?: string;
  has_discrepancy?: boolean;
  per_page?: number;
  page?: number;
  limit?: number;
  sort?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  format?: 'json' | 'csv';
};

export type BranchScopedQuery = {
  branch_id?: number;
};

export type StaffReservationVoucherPayload = {
  row_version: number;
  user_voucher_id?: number | null;
  voucher_code?: string | null;
};
export type StaffReservationVoucherRemovePayload = {
  row_version: number;
};
export type StaffReservationLoyaltyRedeemPayload = {
  row_version: number;
  points: number;
  reason?: string | null;
};
export type StaffReservationLoyaltyReleasePayload = {
  row_version: number;
  reason?: string | null;
};
export type StaffUserLoyaltyAdjustPayload = {
  points: number;
  reason: string;
};

export type AdminBenefitsQuery = {
  q?: string;
  status?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort?: string;
};

export type AdminPrivacyRequestQuery = {
  status?: 'requested' | 'rejected' | 'completed' | 'failed';
  user_id?: number;
  per_page?: number;
};

export type AdminVoucherPayload = {
  code: string;
  discount_type: 'Fixed' | 'Percent' | 'FreeItem';
  description?: string | null;
  discount_value?: number | null;
  free_item_id?: number | null;
  free_item_qty?: number | null;
  max_usage?: number | null;
  max_usage_per_user?: number | null;
  min_spend?: number | null;
  start_date?: string | null;
  expiry_date?: string | null;
  is_active?: boolean;
};
export type AdminVoucherUpdatePayload = Partial<AdminVoucherPayload> & {
  row_version: number;
};
export type AdminLoyaltyTierPayload = {
  tier_code: string;
  tier_name: string;
  min_points: number;
  benefits_json?: Array<unknown> | null;
  is_active?: boolean;
};
export type AdminLoyaltyTierUpdatePayload = Partial<AdminLoyaltyTierPayload> & {
  row_version: number;
};
export type AdminBenefitSettingPayload = {
  setting_key:
    | 'loyalty.enabled'
    | 'loyalty.earn_amount_per_point'
    | 'loyalty.redeem_amount_per_point'
    | 'loyalty.min_redeem_points'
    | 'voucher.lock_minutes';
  value: string;
  expected_updated_at?: string | null;
};
export type AdminPrivacyReviewPayload = {
  decision: 'approve' | 'reject';
  mode: 'dry_run' | 'commit';
  notes?: string | null;
};

export type AdminRestaurantTable = {
  table_id: number;
  branch_id: number | null;
  branch?: {
    branch_id: number;
    branch_code: string;
    branch_name: string;
    is_default: boolean;
  } | null;
  table_code: string;
  template_id: number | null;
  capacity?: number | null;
  seats?: number | null;
  template?: {
    template_id: number;
    template_code: string;
    seats: number;
    description?: string | null;
  } | null;
  zone: string | null;
  position?: {
    x: number | null;
    y: number | null;
  };
  pos_x?: number | null;
  pos_y?: number | null;
  status: string;
  is_deleted: boolean;
  is_active: boolean;
  is_allocatable: boolean;
  usage?: {
    active_reservation_count: number;
    active_hold_count: number;
    active_order_count: number;
    has_active_operational_links: boolean;
  };
  guards?: Record<string, boolean>;
  description: string | null;
  price: string | null;
  row_version: number | null;
  created_at: string | null;
  updated_at: string | null;
};

export type AdminRestaurantTableCollectionEnvelope = {
  data: Array<AdminRestaurantTable>;
  meta?: Record<string, unknown>;
};

export type AdminTableTemplate = {
  template_id: number;
  template_code: string;
  seats: number;
  description?: string | null;
};

export type AdminTableTemplateCollectionEnvelope = {
  data: Array<AdminTableTemplate>;
  meta?: Record<string, unknown>;
};

export type AdminMenuCategory = {
  category_id: number;
  name: string;
  description: string | null;
  sort_order: number;
  is_deleted: boolean;
};

export type AdminMenuCategoryCollectionEnvelope = {
  data: Array<AdminMenuCategory>;
  meta?: Record<string, unknown>;
};

export type AdminMenuItemPrice = {
  price_id: number;
  item_id: number;
  price: string;
  currency: string;
  effective_from: string | null;
  effective_to: string | null;
};

export type AdminMenuItem = {
  item_id: number;
  category_id: number | null;
  code: string | null;
  name: string;
  description: string | null;
  img_url: string | null;
  is_available: boolean;
  is_preorder_enabled: boolean | null;
  preorder_quota_per_day: number | null;
  preorder_cutoff_minutes: number | null;
  category?: AdminMenuCategory | null;
  current_price?: AdminMenuItemPrice | null;
  prices?: Array<AdminMenuItemPrice>;
};

export type AdminMenuItemCollectionEnvelope = {
  data: Array<AdminMenuItem>;
  meta?: Record<string, unknown>;
};

export type AdminMenuItemPriceCollectionEnvelope = {
  data: Array<AdminMenuItemPrice>;
  meta?: Record<string, unknown>;
};

export type AdminIngredientMovement = {
  movement_id: number;
  branch_id: number | null;
  ingredient_id: number;
  movement_type: string;
  quantity_delta: string;
  unit_code: string;
  reference: {
    type: string | null;
    id: string | null;
  };
  notes: string | null;
  created_by: number | null;
  created_at: string | null;
};

export type AdminIngredientMovementCollectionEnvelope = {
  data: Array<AdminIngredientMovement>;
  meta?: Record<string, unknown>;
};

export type AdminIngredientMovementEnvelope = {
  data: AdminIngredientMovement;
  meta?: Record<string, unknown>;
};

export type AdminCreateIngredientMovementPayload = {
  movement_type: 'StockIn' | 'StockOut' | 'AdjustmentIncrease' | 'AdjustmentDecrease' | 'Wastage';
  branch_id?: number | null;
  quantity: number;
  unit_code?: string | null;
  reference_type?: string | null;
  reference_id?: string | null;
  notes?: string | null;
};

export type AdminCreatePurchaseOrderReceiptPayload = CreatePurchaseOrderReceiptRequest;

export type AdminPurchaseOrderReceipt = {
  receipt_id: number;
  branch_id: number | null;
  purchase_order_id: number;
  receipt_code: string;
  receipt_status: string;
  received_at: string | null;
  supplier_document_no: string | null;
  notes: string | null;
  summary: {
    line_count: number;
    received_total_quantity: string;
  };
  created_by: number | null;
  created_at: string | null;
};

export type AdminPurchaseOrderReceiptCollectionEnvelope = {
  data: Array<AdminPurchaseOrderReceipt>;
  meta?: Record<string, unknown>;
};

export type AdminMasterDataImportDomain =
  | 'branches'
  | 'restaurant-tables'
  | 'menu-categories'
  | 'menu-items'
  | 'menu-prices'
  | 'benefit-vouchers'
  | 'loyalty-tiers';

export type AdminMasterDataImportRow = Record<string, unknown>;

export type AdminMasterDataImportResult = {
  domain: string;
  label: string;
  format: string;
  mode: 'dry_run' | 'commit';
  can_commit: boolean;
  schema: {
    columns: Array<string>;
    required_columns: Array<string>;
    errors: Array<{ field: string; message: string }>;
  };
  summary: Record<string, number>;
  rows: Array<{
    row_number: number;
    status: string;
    operation: string;
    errors: Array<{ field: string; message: string }>;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    [key: string]: unknown;
  }>;
  commit: {
    batch_id: string;
    committed_at: string;
    created: number;
    updated: number;
    unchanged: number;
  } | null;
};

export type AdminMasterDataImportEnvelope = {
  data: AdminMasterDataImportResult;
  meta?: Record<string, unknown>;
};

export type AdminMasterDataDryRunPayload = {
  mode: 'dry_run';
  format?: 'csv' | 'json' | null;
  rows?: Array<AdminMasterDataImportRow>;
  content?: string;
};

export type AdminMasterDataCommitPayload = {
  mode: 'commit';
  idempotencyKey: string;
  format?: 'csv' | 'json' | null;
  rows?: Array<AdminMasterDataImportRow>;
  content?: string;
};

export type AdminMasterDataImportPayload = AdminMasterDataDryRunPayload | AdminMasterDataCommitPayload;

export type AdminRestaurantTableQuery = BranchScopedQuery & {
  zone?: string;
  status?: string;
  template_id?: number;
  include_deleted?: boolean;
  q?: string;
};

export type AdminMenuCategoryQuery = {
  include_deleted?: boolean;
  q?: string;
  per_page?: number;
  page?: number;
  sort?: string;
};

export type AdminMenuItemQuery = {
  category_id?: number;
  is_available?: boolean;
  q?: string;
  as_of?: string;
  per_page?: number;
  page?: number;
  sort?: string;
};

export type AdminMenuItemPriceQuery = {
  as_of?: string;
  currency?: string;
  per_page?: number;
  page?: number;
  sort?: string;
};

export type AdminIngredientMovementQuery = BranchScopedQuery & {
  movement_type?: string;
  per_page?: number;
  page?: number;
  sort?: string;
};

export type ReservationActiveOrderEnvelope = {
  data: {
    reservation_id: number;
    order: ReservationOrder | null;
  };
};

export type AuditTrailEntry = StaffAuditTrailEnvelope['data'][number];

export type FinancialReservationSummary = SdkFinancialReservationSummary;
export type FinancialPaymentSummary = Record<string, unknown>;
export type FinancialReconciliationSummary = Record<string, unknown>;
export type FinancialFlags = Record<string, unknown>;
export type FinancialReconciliationRow = SdkFinancialReconciliationRow;
export type FinancialReconciliationCollectionEnvelope = SdkFinancialReconciliationCollectionEnvelope;

export type FinancialPaymentRow = {
  payment_id: number;
  reservation_id: number;
  refund_of_payment_id?: number | null;
  payment_type: string;
  status: string;
  amount: number;
  currency: string;
  payment_method: string;
  payment_provider: string;
  transaction_code?: string | null;
  paid_at?: string | null;
  created_at?: string | null;
  created_by: {
    user_id?: number | null;
    full_name?: string | null;
    email?: string | null;
  };
  refund_target_payment_type?: string | null;
  refund_source_payment?: {
    payment_id: number;
    payment_type: string;
    amount: number;
    transaction_code?: string | null;
  } | null;
};

export type FinancialMethodBreakdownRow = {
  payment_method: string;
  captured_amount: number;
  refunded_amount: number;
  net_amount: number;
  currency: string;
};

export type FinancialReconciliationDetailEnvelope = SdkFinancialReconciliationDetailEnvelope;
export type FinanceInvoiceEnvelope = SdkFinanceInvoiceEnvelope;

export type CreateBillSnapshotPayload = {
  row_version: number;
  discount_amount?: number | null;
  notes?: string | null;
};

export type CreateWaitingListPayload = {
  branch_id?: number | null;
  user_id?: number | null;
  guest_name?: string | null;
  phone?: string | null;
  guest_count: number;
  priority?: number | null;
  notes?: string | null;
};

export type CancelWaitingListPayload = {
  cancel_reason?: string | null;
  row_version: number;
};

export type AdvanceWaitingListPayload = {
  hold_minutes?: number | null;
  row_version: number;
};

export type WaitingListAdvanceEnvelope = {
  data: {
    source_waiting_list: StaffWaitingListEntry;
    advanced_waiting_list: StaffWaitingListEntry | null;
    automation: {
      mode?: string;
      source_transition?: string;
      result?: string;
      hold_minutes?: number;
      released_table?: Record<string, unknown> | null;
      selected_candidate?: Record<string, unknown> | null;
      failure?: Record<string, Array<string>> | null;
      reuses_canonical_notify_flow?: boolean;
      reuses_canonical_seat_flow?: boolean;
      [key: string]: unknown;
    };
  };
};

export function buildBoardWindow(reference = new Date()): Pick<StaffTablesBoardQueryParams, 'from' | 'to'> {
  return {
    from: new Date(reference.getTime() - 60 * 60 * 1000).toISOString(),
    to: new Date(reference.getTime() + 4 * 60 * 60 * 1000).toISOString(),
  };
}

export async function listAdminIngredients(
  query: GetV1AdminInventoryIngredientsQueryParams = { per_page: 8, sort: 'name' },
): Promise<AdminIngredientCollectionEnvelope> {
  return staffClient.getV1AdminInventoryIngredients(query);
}

export async function listAdminSuppliers(
  query: GetV1AdminInventorySuppliersQueryParams = { per_page: 8, sort: 'name' },
): Promise<AdminSupplierCollectionEnvelope> {
  return staffClient.getV1AdminInventorySuppliers(query);
}

export async function listAdminPurchaseOrders(
  query: GetV1AdminInventoryPurchaseOrdersQueryParams = { per_page: 8, sort: '-created_at' },
): Promise<AdminPurchaseOrderCollectionEnvelope> {
  return staffClient.getV1AdminInventoryPurchaseOrders(query);
}

export async function listAdminIngredientMovements(
  ingredientId: number,
  query: AdminIngredientMovementQuery = { per_page: 8, sort: '-created_at' },
): Promise<AdminIngredientMovementCollectionEnvelope> {
  return staffClient.getV1AdminInventoryIngredientsIdMovements(
    { id: ingredientId },
    query as GetV1AdminInventoryIngredientsIdMovementsQueryParams,
  );
}

export async function createAdminIngredientMovement(
  ingredientId: number,
  payload: AdminCreateIngredientMovementPayload,
): Promise<AdminIngredientMovementEnvelope> {
  return staffClient.postV1AdminInventoryIngredientsIdMovements(
    { id: ingredientId },
    payload as CreateIngredientStockMovementRequest,
    { idempotencyKey: createIdempotencyKey(`admin-inventory-movement-${ingredientId}`) },
  );
}

export async function listAdminPurchaseOrderReceipts(
  purchaseOrderId: number,
  query: GetV1AdminInventoryPurchaseOrdersIdReceiptsQueryParams = { per_page: 8, sort: '-created_at' },
): Promise<AdminPurchaseOrderReceiptCollectionEnvelope> {
  return staffClient.getV1AdminInventoryPurchaseOrdersIdReceipts({ id: purchaseOrderId }, query);
}

export async function createAdminPurchaseOrderReceipt(
  purchaseOrderId: number,
  payload: AdminCreatePurchaseOrderReceiptPayload,
): Promise<AdminPurchaseOrderReceiptEnvelope> {
  return staffClient.postV1AdminInventoryPurchaseOrdersIdReceipts(
    { id: purchaseOrderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`admin-purchase-order-receipt-${purchaseOrderId}`) },
  );
}

export async function listAdminBranches(
  query: GetV1AdminSettingsBranchesQueryParams = {},
): Promise<BranchCollectionEnvelope> {
  return staffClient.getV1AdminSettingsBranches(query);
}

export async function listAdminRestaurantTables(
  query: AdminRestaurantTableQuery = {},
): Promise<AdminRestaurantTableCollectionEnvelope> {
  return apiRequest<AdminRestaurantTableCollectionEnvelope>('/admin/restaurant/tables', { query });
}

export async function listAdminRestaurantTableTemplates(): Promise<AdminTableTemplateCollectionEnvelope> {
  return staffClient.getV1AdminRestaurantTableTemplates() as unknown as Promise<AdminTableTemplateCollectionEnvelope>;
}

export async function createAdminRestaurantTable(
  payload: CreateRestaurantTableRequest,
): Promise<GenericDataEnvelope> {
  return staffClient.postV1AdminRestaurantTables(payload, {
    idempotencyKey: createIdempotencyKey(`admin-restaurant-table-${payload.table_code}`),
  });
}

export async function listAdminMenuCategories(
  query: AdminMenuCategoryQuery = { per_page: 12, sort: 'sort_order' },
): Promise<AdminMenuCategoryCollectionEnvelope> {
  return apiRequest<AdminMenuCategoryCollectionEnvelope>('/admin/menu/categories', { query });
}

export async function listAdminMenuItems(
  query: AdminMenuItemQuery = { per_page: 12, sort: 'name' },
): Promise<AdminMenuItemCollectionEnvelope> {
  return apiRequest<AdminMenuItemCollectionEnvelope>('/admin/menu/items', { query });
}

export async function listAdminMenuItemPrices(
  itemId: number,
  query: AdminMenuItemPriceQuery = { per_page: 8, sort: '-effective_from' },
): Promise<AdminMenuItemPriceCollectionEnvelope> {
  return apiRequest<AdminMenuItemPriceCollectionEnvelope>(`/admin/menu/items/${itemId}/prices`, { query });
}

export async function createAdminMenuCategory(
  payload: StoreMenuCategoryRequest,
): Promise<GenericDataEnvelope> {
  return staffClient.postV1AdminMenuCategories(payload, {
    idempotencyKey: createIdempotencyKey(`admin-menu-category-${payload.name}`),
  });
}

export async function createAdminMenuItem(
  payload: StoreMenuItemRequest,
): Promise<GenericDataEnvelope> {
  return staffClient.postV1AdminMenuItems(payload, {
    idempotencyKey: createIdempotencyKey(`admin-menu-item-${payload.code ?? payload.name}`),
  });
}

export async function createAdminMenuItemPrice(
  itemId: number,
  payload: StoreMenuItemPriceRequest,
): Promise<GenericDataEnvelope> {
  return staffClient.postV1AdminMenuItemsItemIdPrices(
    { item_id: itemId },
    payload,
    { idempotencyKey: createIdempotencyKey(`admin-menu-price-${itemId}`) },
  );
}

export async function listAdminBenefitVouchers(query: AdminBenefitsQuery = { per_page: 20 }): Promise<AdminVoucherCollectionEnvelope> {
  void query;
  return staffClient.getV1AdminBenefitsVouchers();
}

export async function getAdminBenefitVoucher(voucherId: number): Promise<AdminVoucherEnvelope> {
  return staffClient.getV1AdminBenefitsVouchersId({ id: voucherId });
}

export async function createAdminBenefitVoucher(payload: AdminVoucherPayload): Promise<AdminVoucherEnvelope> {
  return staffClient.postV1AdminBenefitsVouchers(
    payload as StoreVoucherRequest,
    { idempotencyKey: createIdempotencyKey(`admin-benefit-voucher-${payload.code}`) },
  );
}

export async function updateAdminBenefitVoucher(voucherId: number, payload: AdminVoucherUpdatePayload): Promise<AdminVoucherEnvelope> {
  return staffClient.patchV1AdminBenefitsVouchersId(
    { id: voucherId },
    payload as UpdateVoucherRequest,
    { idempotencyKey: createIdempotencyKey(`admin-benefit-voucher-update-${voucherId}`) },
  );
}

export async function listAdminLoyaltyTiers(query: AdminBenefitsQuery = { per_page: 20 }): Promise<AdminLoyaltyTierCollectionEnvelope> {
  void query;
  return staffClient.getV1AdminBenefitsLoyaltyTiers();
}

export async function getAdminLoyaltyTier(tierId: number): Promise<AdminLoyaltyTierEnvelope> {
  return staffClient.getV1AdminBenefitsLoyaltyTiersId({ id: tierId });
}

export async function createAdminLoyaltyTier(payload: AdminLoyaltyTierPayload): Promise<AdminLoyaltyTierEnvelope> {
  return staffClient.postV1AdminBenefitsLoyaltyTiers(
    payload as StoreLoyaltyTierRequest,
    { idempotencyKey: createIdempotencyKey(`admin-loyalty-tier-${payload.tier_code}`) },
  );
}

export async function updateAdminLoyaltyTier(tierId: number, payload: AdminLoyaltyTierUpdatePayload): Promise<AdminLoyaltyTierEnvelope> {
  return staffClient.patchV1AdminBenefitsLoyaltyTiersId(
    { id: tierId },
    payload as UpdateLoyaltyTierRequest,
    { idempotencyKey: createIdempotencyKey(`admin-loyalty-tier-update-${tierId}`) },
  );
}

export async function getAdminBenefitSettings(): Promise<AdminBenefitSettingCollectionEnvelope> {
  return staffClient.getV1AdminSettingsBenefits();
}

export async function upsertAdminBenefitSetting(payload: AdminBenefitSettingPayload): Promise<AdminBenefitSettingEnvelope> {
  return staffClient.postV1AdminSettingsBenefits(
    payload as UpsertBenefitSettingRequest,
    { idempotencyKey: createIdempotencyKey(`admin-benefit-setting-${payload.setting_key}`) },
  );
}

export async function updateAdminBenefitSetting(
  payload: UpsertBenefitSettingRequest,
): Promise<AdminBenefitSettingEnvelope> {
  return staffClient.postV1AdminSettingsBenefits(payload, {
    idempotencyKey: createIdempotencyKey(`admin-benefit-setting-${payload.setting_key}`),
  });
}

export type StaffReservationPreorderStatusEnvelope = {
  data: {
    preorder_id: number;
    status: string;
    confirmed_at?: string;
    rejected_at?: string;
  };
  meta: {
    action: string;
  };
};

export type StaffReservationPreorderConvertEnvelope = {
  data: {
    order_id: number;
    order_type: string;
    status: string;
  };
  meta: {
    action: string;
  };
};

export type StaffReservationPreorderEnvelope = {
  data: {
    reservation_id: number;
    pre_order: {
      present: boolean;
      preorder_id?: number;
      order_row_version?: number;
      order_status?: string;
      service_time?: string;
      currency?: string;
      totals?: {
        quantity: number;
        subtotal: string;
      };
      lines?: Array<{
        order_item_id: number;
        item_id: number;
        name: string;
        quantity: number;
        unit_price: string;
        line_total: string;
        currency: string;
        notes?: string | null;
      }>;
    };
  };
  meta: {
    action: string;
  };
};

export async function getStaffReservationPreorder(
  reservationId: number,
): Promise<StaffReservationPreorderEnvelope> {
  return apiRequest<StaffReservationPreorderEnvelope>(`/staff/reservations/${reservationId}/preorder`);
}

export async function confirmStaffReservationPreorder(
  reservationId: number,
): Promise<StaffReservationPreorderStatusEnvelope> {
  return apiRequest<StaffReservationPreorderStatusEnvelope>(`/staff/reservations/${reservationId}/preorder/confirm`, {
    method: 'POST',
    headers: {
      'Idempotency-Key': createIdempotencyKey(`staff-reservation-preorder-confirm-${reservationId}`),
    },
  });
}

export async function rejectStaffReservationPreorder(
  reservationId: number,
): Promise<StaffReservationPreorderStatusEnvelope> {
  return apiRequest<StaffReservationPreorderStatusEnvelope>(`/staff/reservations/${reservationId}/preorder/reject`, {
    method: 'POST',
    headers: {
      'Idempotency-Key': createIdempotencyKey(`staff-reservation-preorder-reject-${reservationId}`),
    },
  });
}

export async function convertStaffReservationPreorder(
  reservationId: number,
): Promise<StaffReservationPreorderConvertEnvelope> {
  return apiRequest<StaffReservationPreorderConvertEnvelope>(`/staff/reservations/${reservationId}/preorder/convert`, {
    method: 'POST',
    headers: {
      'Idempotency-Key': createIdempotencyKey(`staff-reservation-preorder-convert-${reservationId}`),
    },
  });
}


export async function listAdminPrivacyRequests(query: AdminPrivacyRequestQuery = { per_page: 20 }): Promise<AdminPrivacyRequestCollectionEnvelope> {
  return staffClient.getV1AdminPrivacyRequests(query as GetV1AdminPrivacyRequestsQueryParams);
}

export async function exportAdminCustomerData(userId: number): Promise<AdminCustomerDataExportEnvelope> {
  return staffClient.getV1AdminPrivacyCustomersUserIdDataExport({ user_id: userId });
}

export async function reviewAdminPrivacyRequest(requestId: number, payload: AdminPrivacyReviewPayload): Promise<AdminPrivacyReviewEnvelope> {
  return staffClient.postV1AdminPrivacyRequestsRequestIdReview(
    { request_id: requestId },
    payload,
    { idempotencyKey: createIdempotencyKey(`admin-privacy-review-${requestId}-${payload.mode}`) },
  );
}

export async function importAdminMasterData(
  domain: AdminMasterDataImportDomain,
  payload: AdminMasterDataImportPayload,
): Promise<AdminMasterDataImportEnvelope> {
  if (payload.mode === 'commit' && payload.idempotencyKey.trim() === '') {
    throw new Error('Admin import commit requires an Idempotency-Key.');
  }

  switch (domain) {
    case 'branches':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/settings/branches/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'restaurant-tables':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/restaurant/tables/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'menu-categories':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/menu/categories/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'menu-items':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/menu/items/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'menu-prices':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/menu/prices/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'benefit-vouchers':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/benefits/vouchers/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    case 'loyalty-tiers':
      return apiRequest<AdminMasterDataImportEnvelope>('/admin/benefits/loyalty-tiers/import', {
        method: 'POST',
        ...adminMasterDataImportOptions(payload),
      });
    default:
      throw new Error(`Unsupported admin import domain: ${String(domain)}`);
  }
}

export async function exportAdminMasterData(domain: AdminMasterDataImportDomain): Promise<GenericDataEnvelope> {
  switch (domain) {
    case 'branches':
      return apiRequest<GenericDataEnvelope>('/admin/settings/branches/export');
    case 'restaurant-tables':
      return apiRequest<GenericDataEnvelope>('/admin/restaurant/tables/export');
    case 'menu-categories':
      return apiRequest<GenericDataEnvelope>('/admin/menu/categories/export');
    case 'menu-items':
      return apiRequest<GenericDataEnvelope>('/admin/menu/items/export');
    case 'menu-prices':
      return apiRequest<GenericDataEnvelope>('/admin/menu/prices/export');
    case 'benefit-vouchers':
      return apiRequest<GenericDataEnvelope>('/admin/benefits/vouchers/export');
    case 'loyalty-tiers':
      return apiRequest<GenericDataEnvelope>('/admin/benefits/loyalty-tiers/export');
    default:
      throw new Error(`Unsupported admin export domain: ${String(domain)}`);
  }
}

export async function getCurrentCashierShift(branchId?: number): Promise<CashierShiftEnvelope> {
  return staffClient.getV1StaffCashierShiftsCurrent({ branch_id: branchId });
}

export async function listCashierShifts(
  query: GetV1StaffCashierShiftsQueryParams = { per_page: 12, sort: '-opened_at' },
): Promise<CashierShiftCollectionEnvelope> {
  return staffClient.getV1StaffCashierShifts(query);
}

export async function getCashierShift(shiftId: number): Promise<CashierShiftEnvelope> {
  return staffClient.getV1StaffCashierShiftsShiftId({ shift_id: shiftId });
}

export async function openCashierShift(payload: OpenCashierShiftRequest): Promise<CashierShiftEnvelope> {
  return staffClient.postV1StaffCashierShiftsOpen(payload, {
    idempotencyKey: createIdempotencyKey('cashier-open'),
  });
}

export async function closeCashierShift(shiftId: number, payload: CloseCashierShiftRequest): Promise<CashierShiftEnvelope> {
  return staffClient.postV1StaffCashierShiftsShiftIdClose(
    { shift_id: shiftId },
    payload,
    { idempotencyKey: createIdempotencyKey(`cashier-close-${shiftId}`) },
  );
}

export async function getTableBoard(query: StaffTablesBoardQueryParams): Promise<StaffTableBoardEnvelope> {
  return staffClient.staffTablesBoard(query);
}

export async function getTableBoardChanges(afterVersion?: number, branchId?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffTablesBoardChanges({
    after_version: afterVersion,
    branch_id: branchId,
    limit: 20,
  });
}

export async function listWaitingList(
  query: GetV1StaffWaitingListQueryParams = { active_only: true, per_page: 20, sort: '-priority' },
): Promise<StaffWaitingListCollectionEnvelope> {
  return staffClient.getV1StaffWaitingList(query);
}

export async function getWaitingListChanges(afterVersion?: number, branchId?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffWaitingListChanges({
    after_version: afterVersion,
    branch_id: branchId,
    limit: 20,
  });
}

export async function createWaitingListEntry(payload: CreateWaitingListPayload): Promise<StaffWaitingListEnvelope> {
  return staffClient.postV1StaffWaitingList(
    payload as CreateWaitlistEntryRequest,
    { idempotencyKey: createIdempotencyKey('waiting-create') },
  );
}

export async function notifyWaitingListEntry(waitingId: number, payload: NotifyWaitingListRequest): Promise<StaffWaitingListEnvelope> {
  return staffClient.postV1StaffWaitingListIdNotify(
    { id: waitingId },
    payload,
    { idempotencyKey: createIdempotencyKey(`waiting-notify-${waitingId}`) },
  );
}

export async function seatWaitingListEntry(waitingId: number, payload: SeatWaitingListRequest): Promise<StaffWaitingListSeatEnvelope> {
  return staffClient.postV1StaffWaitingListIdSeat(
    { id: waitingId },
    payload,
    { idempotencyKey: createIdempotencyKey(`waiting-seat-${waitingId}`) },
  );
}

export async function cancelWaitingListEntry(waitingId: number, payload: CancelWaitingListPayload): Promise<StaffWaitingListEnvelope> {
  return staffClient.postV1StaffWaitingListIdCancel(
    { id: waitingId },
    payload as CancelWaitlistRequest,
    { idempotencyKey: createIdempotencyKey(`waiting-cancel-${waitingId}`) },
  );
}

export async function advanceWaitingListEntry(waitingId: number, payload: AdvanceWaitingListPayload): Promise<WaitingListAdvanceEnvelope> {
  return staffClient.postV1StaffWaitingListIdAdvance(
    { id: waitingId },
    payload,
    { idempotencyKey: createIdempotencyKey(`waiting-advance-${waitingId}`) },
  ) as Promise<StaffWaitingListAdvanceEnvelope>;
}

export async function listReservations(query: ReservationListQuery): Promise<StaffReservationLookupCollectionEnvelope> {
  return staffClient.getV1StaffReservations(query);
}

export async function createReservation(payload: CreateReservationPayload): Promise<ReservationEnvelope> {
  return staffClient.postV1Reservations(payload, {
    idempotencyKey: createIdempotencyKey(`reservation-create-${payload.table_ids?.join('-') ?? 'manual'}`),
  });
}

export async function getReservationDetail(reservationId: number): Promise<ReservationEnvelope> {
  return staffClient.getV1StaffReservationsReservationId({ reservation_id: reservationId });
}

export async function payReservationDeposit(
  reservationId: number,
  payload: PayReservationDepositRequest,
): Promise<GenericDataEnvelope> {
  return staffClient.postV1StaffReservationsReservationIdDepositPay(
    { reservation_id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`reservation-deposit-pay-${reservationId}`) },
  );
}

export async function cancelReservation(
  reservationId: number,
  payload: CancelReservationRequest,
): Promise<ReservationActionEnvelope> {
  return staffClient.postV1ReservationsIdCancel(
    { id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`reservation-cancel-${reservationId}`) },
  );
}

export async function listStaffReservationVouchers(reservationId: number): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/vouchers`);
}

export async function applyStaffReservationVoucher(
  reservationId: number,
  payload: StaffReservationVoucherPayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/voucher/apply`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-reservation-voucher-apply-${reservationId}`),
  });
}

export async function removeStaffReservationVoucher(
  reservationId: number,
  payload: StaffReservationVoucherRemovePayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/voucher/remove`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-reservation-voucher-remove-${reservationId}`),
  });
}

export async function releaseStaffReservationVoucher(
  reservationId: number,
  payload: StaffReservationVoucherRemovePayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/voucher/remove`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-reservation-voucher-release-${reservationId}`),
  });
}

export async function getStaffUserLoyalty(userId: number): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/users/${userId}/loyalty`);
}

export async function adjustStaffUserLoyalty(
  userId: number,
  payload: StaffUserLoyaltyAdjustPayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/users/${userId}/loyalty/adjust`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-user-loyalty-adjust-${userId}`),
  });
}

export async function getStaffReservationLoyalty(reservationId: number): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/loyalty`);
}

export async function redeemStaffReservationLoyalty(
  reservationId: number,
  payload: StaffReservationLoyaltyRedeemPayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/loyalty/redeem`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-reservation-loyalty-redeem-${reservationId}`),
  });
}

export async function releaseStaffReservationLoyalty(
  reservationId: number,
  payload: StaffReservationLoyaltyReleasePayload,
): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/reservations/${reservationId}/loyalty/redeem/release`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`staff-reservation-loyalty-release-${reservationId}`),
  });
}

export async function updateReservationStatus(
  reservationId: number,
  payload: UpdateReservationStatusPayload,
): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/reservations/${reservationId}/status`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-status-${reservationId}-${payload.status.toLowerCase()}`),
  });
}

export async function listConversations(query: ConversationInboxQuery): Promise<StaffConversationCollectionEnvelope> {
  return staffClient.getV1StaffConversations(query);
}

export async function getConversationDetail(
  conversationId: string,
  query: ConversationDetailQuery = {
    message_limit: 20,
    event_limit: 12,
    include_closed_assignments: true,
  },
): Promise<StaffConversationDetailEnvelope> {
  return staffClient.getV1StaffConversationsConversationId({ conversation_id: conversationId }, query);
}

export async function takeOverConversation(
  conversationId: string,
  payload: TakeOverConversationRequest = {},
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdTakeOver(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-take-over-${conversationId}`) },
  );
}

export async function assignConversation(
  conversationId: string,
  payload: AssignConversationPayload,
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdAssign(
    { conversation_id: conversationId },
    payload as AssignConversationRequest,
    { idempotencyKey: createIdempotencyKey(`conversation-assign-${conversationId}`) },
  );
}

export async function unassignConversation(
  conversationId: string,
  payload: UnassignConversationPayload = {},
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdUnassign(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-unassign-${conversationId}`) },
  );
}

export async function updateConversationWorkflowState(
  conversationId: string,
  payload: UpdateConversationWorkflowStatePayload,
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1staffconversationsconversationIdworkflowState(
    { conversation_id: conversationId },
    payload as UpdateConversationWorkflowStateRequest,
    { idempotencyKey: createIdempotencyKey(`conversation-workflow-${conversationId}`) },
  );
}

export async function linkConversation(
  conversationId: string,
  payload: LinkConversationPayload,
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdLinks(
    { conversation_id: conversationId },
    payload as LinkConversationRequest,
    { idempotencyKey: createIdempotencyKey(`conversation-link-${conversationId}`) },
  );
}

export async function unlinkConversationReservation(conversationId: string): Promise<StaffConversationMutationEnvelope> {
  return staffClient.deleteV1StaffConversationsConversationIdLinksReservation(
    { conversation_id: conversationId },
    { idempotencyKey: createIdempotencyKey(`conversation-unlink-reservation-${conversationId}`) },
  );
}

export async function unlinkConversationWaitingList(conversationId: string): Promise<StaffConversationMutationEnvelope> {
  return staffClient.deleteV1StaffConversationsConversationIdLinksWaitingList(
    { conversation_id: conversationId },
    { idempotencyKey: createIdempotencyKey(`conversation-unlink-waiting-${conversationId}`) },
  );
}

export async function addConversationInternalNote(
  conversationId: string,
  payload: AddConversationInternalNoteRequest,
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdInternalNotes(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-note-${conversationId}`) },
  );
}

export async function sendConversationOutboundReply(
  conversationId: string,
  payload: SendConversationOutboundReplyRequest,
): Promise<StaffConversationMutationEnvelope> {
  return staffClient.postV1StaffConversationsConversationIdOutboundReplies(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-reply-${conversationId}`) },
  );
}

export async function listAuditTrail(query: AuditTrailQuery): Promise<StaffAuditTrailEnvelope> {
  return staffClient.getV1StaffAuditTrail(query);
}

export async function listDailySalesReporting(query: DailySalesReportingQuery): Promise<StaffReportingDailySalesCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailySales(query);
}

export async function listDailyOperationsReporting(query: DailyOperationsReportingQuery): Promise<StaffReportingDailyOperationsCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailyOperations(query);
}

export async function listDailyInventoryReporting(query: DailyInventoryReportingQuery): Promise<StaffReportingDailyInventoryCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailyInventory(query);
}

export async function listFinancialReconciliation(query: FinancialReconciliationQuery): Promise<FinancialReconciliationCollectionEnvelope> {
  return staffClient.getV1StaffFinanceReconciliation(query as GetV1StaffFinanceReconciliationQueryParams);
}

export async function getFinancialReconciliationDetail(
  reservationId: number,
  query: BranchScopedQuery = {},
): Promise<FinancialReconciliationDetailEnvelope> {
  return staffClient.getV1StaffFinanceReconciliationReservationId({ reservation_id: reservationId }, query);
}

export async function getFinanceInvoice(reservationId: number, query: BranchScopedQuery = {}): Promise<FinanceInvoiceEnvelope> {
  return staffClient.getV1StaffFinanceInvoicesReservationId({ reservation_id: reservationId }, query);
}

export async function issueFinanceInvoice(reservationId: number, query: BranchScopedQuery = {}): Promise<FinanceInvoiceEnvelope> {
  return staffClient.postV1StaffFinanceInvoicesReservationIdIssue(
    { reservation_id: reservationId },
    query as BranchScopeRequest,
    { idempotencyKey: createIdempotencyKey(`finance-invoice-${reservationId}`) },
  );
}

export async function assignSuggestedTable(reservationId: number, payload: AssignSuggestedTablePayload): Promise<ReservationEnvelope> {
  return staffClient.postV1StaffReservationsIdAssignTable(
    { id: reservationId },
    payload as StaffAssignSuggestedTableRequest,
    { idempotencyKey: createIdempotencyKey(`reservation-assign-table-${reservationId}`) },
  );
}

export async function assignBestFitTable(reservationId: number, payload: AssignBestFitTablePayload): Promise<ReservationEnvelope> {
  return staffClient.postV1StaffReservationsIdAssignBestFit(
    { id: reservationId },
    payload as StaffAssignBestFitTableRequest,
    { idempotencyKey: createIdempotencyKey(`reservation-assign-best-fit-${reservationId}`) },
  );
}

export async function checkInReservation(reservationId: number, payload: CheckInReservationRequest): Promise<ReservationEnvelope> {
  return staffClient.postV1StaffReservationsIdCheckIn(
    { id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`reservation-check-in-${reservationId}`) },
  );
}

export async function moveReservationTable(reservationId: number, payload: MoveTablePayload): Promise<ReservationEnvelope> {
  return staffClient.postV1StaffReservationsIdMoveTable(
    { id: reservationId },
    payload as MoveTableRequest,
    { idempotencyKey: createIdempotencyKey(`reservation-move-table-${reservationId}`) },
  );
}

export async function releaseStaffTable(tableId: number, payload: ReleaseTablePayload): Promise<RestaurantTableEnvelope> {
  return staffClient.postV1StaffTablesTableIdRelease(
    { table_id: tableId },
    payload as ReleaseTableRequest,
    { idempotencyKey: createIdempotencyKey(`table-release-${tableId}`) },
  );
}

export async function createWalkInSession(payload: WalkInPayload): Promise<ReservationEnvelope> {
  return staffClient.postV1StaffServiceSessionsWalkIn(payload, {
    idempotencyKey: createIdempotencyKey(`walk-in-${payload.table_ids.join('-')}`),
  });
}

export async function listMenuItems(query: GetV1MenuItemsQueryParams): Promise<CustomerMenuItemsCollectionEnvelope> {
  return staffClient.getV1StaffMenuItems(query);
}

export async function getActiveOrderByTable(tableId: number): Promise<StaffOrderReadEnvelope> {
  return staffClient.getV1StaffTablesTableIdActiveOrder({ table_id: tableId });
}

export async function getActiveOrderByReservation(reservationId: number): Promise<ReservationActiveOrderEnvelope> {
  const reservationOrders = await listReservationOrders(reservationId);
  return {
    data: {
      reservation_id: reservationId,
      order: selectCanonicalActiveOrder(reservationId, reservationOrders.data),
    },
  };
}

export async function listReservationOrders(reservationId: number): Promise<StaffReservationOrderCollectionEnvelope> {
  return staffClient.getV1StaffReservationsReservationIdOrders({ reservation_id: reservationId });
}

export async function getOrderDetail(orderId: number): Promise<StaffOrderReadEnvelope> {
  return staffClient.getV1StaffOrdersOrderId({ order_id: orderId });
}

export async function createTableOrder(tableId: number, payload: {
  reservation_id?: number | null;
  items?: AddOrderItemsRequest['items'];
  notes?: string | null;
  row_version: number;
}): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffTablesTableIdOrders(
    { table_id: tableId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-create-${tableId}`) },
  );
}

export async function addOrderItems(orderId: number, payload: AddOrderItemsRequest): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdItems(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-items-${orderId}`) },
  );
}

export async function updateOrderItem(orderId: number, orderItemId: number, payload: UpdateOrderItemPayload): Promise<StaffReservationOrderEnvelope> {
  return staffClient.patchV1StaffOrdersOrderIdItemsOrderItemId(
    { order_id: orderId, order_item_id: orderItemId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-item-update-${orderItemId}`) },
  );
}

export async function updateOrderItemStatus(orderId: number, orderItemId: number, payload: UpdateOrderItemStatusPayload): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdItemsOrderItemIdStatus(
    { order_id: orderId, order_item_id: orderItemId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-item-status-${orderItemId}`) },
  );
}

export async function listKitchenStations(branchId?: number): Promise<StaffKitchenStationCollectionEnvelope> {
  return staffClient.getV1StaffKitchenStations({ branch_id: branchId });
}

export async function getKitchenStationTickets(
  stationId: number,
  query: GetV1StaffKitchenStationsStationIdTicketsQueryParams & BranchScopedQuery,
): Promise<StaffKitchenTicketCollectionEnvelope> {
  return staffClient.getV1StaffKitchenStationsStationIdTickets({ station_id: stationId }, query);
}

export async function getKitchenChanges(afterVersion?: number, branchId?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffKitchenChanges({
    after_version: afterVersion,
    branch_id: branchId,
    limit: 20,
  });
}

export async function dispatchKitchenOrder(orderId: number, payload: DispatchKitchenTicketsRequest): Promise<StaffKitchenDispatchEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdKitchenDispatch(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`kitchen-dispatch-${orderId}`) },
  );
}

export async function fireKitchenTicket(ticketId: number, rowVersion: StaffKitchenTicketActionRequest['row_version']): Promise<StaffKitchenTicketEnvelope> {
  return staffClient.postV1StaffKitchenTicketsTicketIdFire(
    { ticket_id: ticketId },
    { row_version: rowVersion },
    { idempotencyKey: createIdempotencyKey(`kitchen-fire-${ticketId}`) },
  );
}

export async function bumpKitchenTicket(ticketId: number, rowVersion: StaffKitchenTicketActionRequest['row_version']): Promise<StaffKitchenTicketEnvelope> {
  return staffClient.postV1StaffKitchenTicketsTicketIdBump(
    { ticket_id: ticketId },
    { row_version: rowVersion },
    { idempotencyKey: createIdempotencyKey(`kitchen-bump-${ticketId}`) },
  );
}

export async function recallKitchenTicket(ticketId: number, rowVersion: StaffKitchenTicketActionRequest['row_version']): Promise<StaffKitchenTicketEnvelope> {
  return staffClient.postV1StaffKitchenTicketsTicketIdRecall(
    { ticket_id: ticketId },
    { row_version: rowVersion },
    { idempotencyKey: createIdempotencyKey(`kitchen-recall-${ticketId}`) },
  );
}

export async function createBillSnapshot(orderId: number, payload: CreateBillSnapshotPayload): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdBillSnapshot(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`bill-snapshot-${orderId}`) },
  );
}

export async function getSettlementPreview(
  orderId: number,
  query: GetV1StaffOrdersOrderIdSettlementPreviewQueryParams = {},
): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.getV1StaffOrdersOrderIdSettlementPreview({ order_id: orderId }, query);
}

export async function payOrder(orderId: number, payload: PayOrderRequest): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdPay(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-pay-${orderId}`) },
  );
}

export async function finalizeSettlement(orderId: number, payload: CheckoutOrderRequest): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdSettlementFinalize(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`settlement-finalize-${orderId}`) },
  );
}

export async function getRefundPreview(
  reservationId: number,
  query: GetV1StaffReservationsReservationIdRefundPreviewQueryParams = {},
): Promise<StaffRefundPreviewEnvelope> {
  return staffClient.getV1StaffReservationsReservationIdRefundPreview({ reservation_id: reservationId }, query);
}

export async function refundReservation(
  reservationId: number,
  payload: RefundReservationRequest,
): Promise<StaffRefundEnvelope> {
  return staffClient.postV1StaffReservationsReservationIdRefund(
    { reservation_id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`refund-${reservationId}`) },
  );
}

export async function refundAndCancelReservation(
  reservationId: number,
  payload: RefundAndCancelReservationRequest,
): Promise<StaffRefundEnvelope> {
  return staffClient.postV1StaffReservationsReservationIdRefundCancel(
    { reservation_id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`refund-cancel-${reservationId}`) },
  );
}

function adminMasterDataImportOptions(payload: AdminMasterDataImportPayload) {
  const { idempotencyKey, ...body } = payload.mode === 'commit'
    ? payload
    : { ...payload, idempotencyKey: undefined };

  return {
    body,
    idempotencyKey,
  } as const;
}

function selectCanonicalActiveOrder(reservationId: number, orders: Array<ReservationOrder>): ReservationOrder | null {
  const activeOrders = orders.filter((order) => order.status === 'Active');

  if (activeOrders.length <= 1) {
    return activeOrders[0] ?? null;
  }

  throw new StaffApiError(409, {
    error_code: 'reservation_active_order_conflict',
    message: `Reservation ${reservationId} has multiple active orders in canonical lookup.`,
    reservation_id: reservationId,
    active_order_ids: activeOrders.map((order) => order.order_id),
  }, 'Conflict');
}
