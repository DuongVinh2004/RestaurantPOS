import type {
  AdminIngredientCollectionEnvelope,
  AdminPurchaseOrderCollectionEnvelope,
  AdminSupplierCollectionEnvelope,
  AddConversationInternalNoteRequest,
  AddOrderItemsRequest,
  BranchCollectionEnvelope,
  CashierShiftCollectionEnvelope,
  CashierShiftEnvelope,
  CheckInReservationRequest,
  CheckoutOrderRequest,
  CloseCashierShiftRequest,
  CustomerMenuItemsCollectionEnvelope,
  DispatchKitchenTicketsRequest,
  GetV1AdminInventoryIngredientsQueryParams,
  GetV1AdminInventoryPurchaseOrdersQueryParams,
  GetV1AdminInventorySuppliersQueryParams,
  GetV1AdminSettingsBranchesQueryParams,
  GetV1StaffAuditTrailQueryParams,
  GetV1StaffConversationsConversationIdQueryParams,
  GetV1StaffConversationsQueryParams,
  GetV1StaffCashierShiftsQueryParams,
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
  LoginStaffAuthRequest,
  NotifyWaitingListRequest,
  OpenCashierShiftRequest,
  ReservationEnvelope,
  RefundAndCancelReservationRequest,
  RefundReservationRequest,
  SendConversationOutboundReplyRequest,
  SeatWaitingListRequest,
  StaffAuthSessionEnvelope,
  StaffAuditTrailEnvelope,
  StaffCheckoutSettlementEnvelope,
  StaffConversationCollectionEnvelope,
  StaffConversationDetailEnvelope,
  StaffConversationMutationEnvelope,
  StaffKitchenDispatchEnvelope,
  StaffKitchenStationCollectionEnvelope,
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
  StaffWaitingListSeatEnvelope,
  StaffTablesBoardQueryParams,
  StoreReservationRequest,
  TakeOverConversationRequest,
} from './sdk';
import { persistStaffSessionToken, readStoredStaffToken, writeStoredStaffToken, type StaffSession } from '../auth/storage';
import { createIdempotencyKey } from '../utils/idempotency';
import { apiRequest } from './http';
export { getCurrentStaffSession, loginStaff, logoutStaff, refreshStaffSession } from './staff-auth-api';
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

export type UpdateOrderItemPayload = {
  qty?: number;
  note?: string | null;
  order_row_version: number;
  row_version: number;
};

export type UpdateOrderItemStatusPayload = {
  status: 'InProgress' | 'Served' | 'Cancelled';
  order_row_version: number;
  row_version: number;
};

export type MoveTablePayload = {
  from_table_id: number;
  to_table_id: number;
  row_version?: number | null;
  moved_at?: string | null;
};

export type ReleaseTablePayload = {
  row_version?: number | null;
  force?: boolean;
  notes?: string | null;
};

export type ReservationListQuery = GetV1StaffReservationsQueryParams & {
  branch_id?: number;
};

export type ConversationInboxQuery = GetV1StaffConversationsQueryParams;
export type ConversationDetailQuery = GetV1StaffConversationsConversationIdQueryParams;
export type UnassignConversationPayload = {
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

export type AuditTrailEntry = StaffAuditTrailEnvelope['data'][number];

export type FinancialReservationSummary = {
  reservation_id: number;
  reservation_code: string;
  row_version?: number | null;
  status: string;
  deposit_status: string;
  start_time?: string | null;
  end_time?: string | null;
  billed_at?: string | null;
  updated_at?: string | null;
  bill_currency?: string | null;
  customer: {
    user_id?: number | null;
    full_name?: string | null;
    email?: string | null;
    phone?: string | null;
  };
};

export type FinancialPaymentSummary = {
  payment_count: number;
  refund_count: number;
  captured_amount: number;
  refunded_amount: number;
  net_paid_amount: number;
  deposit_captured_amount: number;
  deposit_refunded_amount: number;
  deposit_net_amount: number;
  final_captured_amount: number;
  final_refunded_amount: number;
  final_net_amount: number;
  over_refunded_amount: number;
  last_payment_activity_at?: string | null;
  last_refund_at?: string | null;
  currency: {
    currency?: string | null;
    has_mixed_currencies: boolean;
  };
};

export type FinancialReconciliationSummary = {
  deposit_required_amount: number;
  deposit_recorded_paid_amount: number;
  deposit_computed_net_amount: number;
  deposit_sync_gap_amount: number;
  final_bill_amount?: number | null;
  bill_outstanding_amount?: number | null;
  bill_overpaid_amount?: number | null;
};

export type FinancialFlags = {
  has_refunds: boolean;
  has_payments: boolean;
  has_discrepancy: boolean;
  has_deposit_sync_gap: boolean;
  has_over_refund: boolean;
  has_mixed_payment_currencies: boolean;
  has_bill_outstanding: boolean;
  has_bill_overpaid: boolean;
  discrepancy_reasons: Array<string>;
  is_fully_settled: boolean;
};

export type FinancialReconciliationRow = {
  reservation: FinancialReservationSummary;
  payment_summary: FinancialPaymentSummary;
  reconciliation: FinancialReconciliationSummary;
  flags: FinancialFlags;
};

export type FinancialReconciliationCollectionEnvelope = {
  data: Array<FinancialReconciliationRow>;
  meta?: {
    action: string;
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    filters: Record<string, unknown>;
  };
};

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

export type FinancialReconciliationDetailEnvelope = {
  data: {
    reservation: FinancialReservationSummary;
    summary: FinancialReconciliationRow;
    payments: Array<FinancialPaymentRow>;
    method_breakdown: Array<FinancialMethodBreakdownRow>;
  };
  meta?: {
    action: string;
  };
};

export type FinanceInvoiceEnvelope = {
  data: {
    invoice: {
      billing_invoice_id: number;
      reservation_id: number;
      invoice_number: string;
      invoice_status: string;
      currency: string;
      bill_amounts: {
        subtotal_amount: number;
        discount_amount: number;
        total_amount: number;
      };
      tax: {
        tax_code?: string | null;
        tax_name?: string | null;
        tax_rate_percentage: number;
        prices_include_tax: boolean;
        taxable_amount: number;
        tax_amount: number;
      };
      seller: {
        seller_name?: string | null;
        seller_tax_id?: string | null;
        seller_address?: string | null;
      };
      issued_at?: string | null;
      issued_by: {
        user_id?: number | null;
        full_name?: string | null;
        email?: string | null;
      };
      row_version: number;
      metadata: Record<string, unknown>;
    };
    reservation: FinancialReservationSummary;
    reconciliation: FinancialReconciliationRow;
    method_breakdown: Array<FinancialMethodBreakdownRow>;
  };
  meta?: {
    action: string;
    created?: boolean;
  };
};

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
  return apiRequest<AdminIngredientCollectionEnvelope>('/admin/inventory/ingredients', { query });
}

export async function listAdminSuppliers(
  query: GetV1AdminInventorySuppliersQueryParams = { per_page: 8, sort: 'name' },
): Promise<AdminSupplierCollectionEnvelope> {
  return apiRequest<AdminSupplierCollectionEnvelope>('/admin/inventory/suppliers', { query });
}

export async function listAdminPurchaseOrders(
  query: GetV1AdminInventoryPurchaseOrdersQueryParams = { per_page: 8, sort: '-created_at' },
): Promise<AdminPurchaseOrderCollectionEnvelope> {
  return apiRequest<AdminPurchaseOrderCollectionEnvelope>('/admin/inventory/purchase-orders', { query });
}

export async function listAdminBranches(
  query: GetV1AdminSettingsBranchesQueryParams = {},
): Promise<BranchCollectionEnvelope> {
  return apiRequest<BranchCollectionEnvelope>('/admin/settings/branches', { query });
}

export async function getCurrentCashierShift(branchId?: number): Promise<CashierShiftEnvelope> {
  return apiRequest<CashierShiftEnvelope>('/staff/cashier/shifts/current', {
    query: {
      branch_id: branchId,
    },
  });
}

export async function listCashierShifts(
  query: GetV1StaffCashierShiftsQueryParams = { per_page: 12, sort: '-opened_at' },
): Promise<CashierShiftCollectionEnvelope> {
  return apiRequest<CashierShiftCollectionEnvelope>('/staff/cashier/shifts', { query });
}

export async function getCashierShift(shiftId: number): Promise<CashierShiftEnvelope> {
  return apiRequest<CashierShiftEnvelope>(`/staff/cashier/shifts/${shiftId}`);
}

export async function openCashierShift(payload: OpenCashierShiftRequest): Promise<CashierShiftEnvelope> {
  return apiRequest<CashierShiftEnvelope>('/staff/cashier/shifts/open', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('cashier-open'),
  });
}

export async function closeCashierShift(shiftId: number, payload: CloseCashierShiftRequest): Promise<CashierShiftEnvelope> {
  return apiRequest<CashierShiftEnvelope>(`/staff/cashier/shifts/${shiftId}/close`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`cashier-close-${shiftId}`),
  });
}

export async function getTableBoard(query: StaffTablesBoardQueryParams): Promise<StaffTableBoardEnvelope> {
  return apiRequest<StaffTableBoardEnvelope>('/staff/tables/board', { query });
}

export async function getTableBoardChanges(afterVersion?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return apiRequest<StaffOperationalRealtimeEnvelope>('/staff/tables/board/changes', {
    query: {
      after_version: afterVersion,
      limit: 20,
    },
  });
}

export async function listWaitingList(
  query: GetV1StaffWaitingListQueryParams = { active_only: true, per_page: 20, sort: '-priority' },
): Promise<StaffWaitingListCollectionEnvelope> {
  return apiRequest<StaffWaitingListCollectionEnvelope>('/staff/waiting-list', { query });
}

export async function getWaitingListChanges(afterVersion?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return apiRequest<StaffOperationalRealtimeEnvelope>('/staff/waiting-list/changes', {
    query: {
      after_version: afterVersion,
      limit: 20,
    },
  });
}

export async function createWaitingListEntry(payload: CreateWaitingListPayload): Promise<StaffWaitingListEnvelope> {
  return apiRequest<StaffWaitingListEnvelope>('/staff/waiting-list', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('waiting-create'),
  });
}

export async function notifyWaitingListEntry(waitingId: number, payload: NotifyWaitingListRequest): Promise<StaffWaitingListEnvelope> {
  return apiRequest<StaffWaitingListEnvelope>(`/staff/waiting-list/${waitingId}/notify`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`waiting-notify-${waitingId}`),
  });
}

export async function seatWaitingListEntry(waitingId: number, payload: SeatWaitingListRequest): Promise<StaffWaitingListSeatEnvelope> {
  return apiRequest<StaffWaitingListSeatEnvelope>(`/staff/waiting-list/${waitingId}/seat`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`waiting-seat-${waitingId}`),
  });
}

export async function cancelWaitingListEntry(waitingId: number, payload: CancelWaitingListPayload): Promise<StaffWaitingListEnvelope> {
  return apiRequest<StaffWaitingListEnvelope>(`/staff/waiting-list/${waitingId}/cancel`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`waiting-cancel-${waitingId}`),
  });
}

export async function advanceWaitingListEntry(waitingId: number, payload: AdvanceWaitingListPayload): Promise<WaitingListAdvanceEnvelope> {
  return apiRequest<WaitingListAdvanceEnvelope>(`/staff/waiting-list/${waitingId}/advance`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`waiting-advance-${waitingId}`),
  });
}

export async function listReservations(query: ReservationListQuery): Promise<StaffReservationLookupCollectionEnvelope> {
  return apiRequest<StaffReservationLookupCollectionEnvelope>('/staff/reservations', { query });
}

export async function createReservation(payload: CreateReservationPayload): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>('/reservations', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-create-${payload.table_ids?.join('-') ?? 'manual'}`),
  });
}

export async function getReservationDetail(reservationId: number): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/staff/reservations/${reservationId}`);
}

export async function listConversations(query: ConversationInboxQuery): Promise<StaffConversationCollectionEnvelope> {
  return apiRequest<StaffConversationCollectionEnvelope>('/staff/conversations', { query });
}

export async function getConversationDetail(
  conversationId: string,
  query: ConversationDetailQuery = {
    message_limit: 20,
    event_limit: 12,
    include_closed_assignments: true,
  },
): Promise<StaffConversationDetailEnvelope> {
  return apiRequest<StaffConversationDetailEnvelope>(`/staff/conversations/${conversationId}`, { query });
}

export async function takeOverConversation(
  conversationId: string,
  payload: TakeOverConversationRequest = {},
): Promise<StaffConversationMutationEnvelope> {
  return apiRequest<StaffConversationMutationEnvelope>(`/staff/conversations/${conversationId}/take-over`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`conversation-take-over-${conversationId}`),
  });
}

export async function unassignConversation(
  conversationId: string,
  payload: UnassignConversationPayload = {},
): Promise<StaffConversationMutationEnvelope> {
  return apiRequest<StaffConversationMutationEnvelope>(`/staff/conversations/${conversationId}/unassign`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`conversation-unassign-${conversationId}`),
  });
}

export async function addConversationInternalNote(
  conversationId: string,
  payload: AddConversationInternalNoteRequest,
): Promise<StaffConversationMutationEnvelope> {
  return apiRequest<StaffConversationMutationEnvelope>(`/staff/conversations/${conversationId}/internal-notes`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`conversation-note-${conversationId}`),
  });
}

export async function sendConversationOutboundReply(
  conversationId: string,
  payload: SendConversationOutboundReplyRequest,
): Promise<StaffConversationMutationEnvelope> {
  return apiRequest<StaffConversationMutationEnvelope>(`/staff/conversations/${conversationId}/outbound-replies`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`conversation-reply-${conversationId}`),
  });
}

export async function listAuditTrail(query: AuditTrailQuery): Promise<StaffAuditTrailEnvelope> {
  return apiRequest<StaffAuditTrailEnvelope>('/staff/audit-trail', { query });
}

export async function listDailySalesReporting(query: DailySalesReportingQuery): Promise<StaffReportingDailySalesCollectionEnvelope> {
  return apiRequest<StaffReportingDailySalesCollectionEnvelope>('/staff/reporting/daily-sales', { query });
}

export async function listDailyOperationsReporting(query: DailyOperationsReportingQuery): Promise<StaffReportingDailyOperationsCollectionEnvelope> {
  return apiRequest<StaffReportingDailyOperationsCollectionEnvelope>('/staff/reporting/daily-operations', { query });
}

export async function listDailyInventoryReporting(query: DailyInventoryReportingQuery): Promise<StaffReportingDailyInventoryCollectionEnvelope> {
  return apiRequest<StaffReportingDailyInventoryCollectionEnvelope>('/staff/reporting/daily-inventory', { query });
}

export async function listFinancialReconciliation(query: FinancialReconciliationQuery): Promise<FinancialReconciliationCollectionEnvelope> {
  return apiRequest<FinancialReconciliationCollectionEnvelope>('/staff/finance/reconciliation', { query });
}

export async function getFinancialReconciliationDetail(
  reservationId: number,
  query: BranchScopedQuery = {},
): Promise<FinancialReconciliationDetailEnvelope> {
  return apiRequest<FinancialReconciliationDetailEnvelope>(`/staff/finance/reconciliation/${reservationId}`, { query });
}

export async function getFinanceInvoice(reservationId: number, query: BranchScopedQuery = {}): Promise<FinanceInvoiceEnvelope> {
  return apiRequest<FinanceInvoiceEnvelope>(`/staff/finance/invoices/${reservationId}`, { query });
}

export async function issueFinanceInvoice(reservationId: number, query: BranchScopedQuery = {}): Promise<FinanceInvoiceEnvelope> {
  return apiRequest<FinanceInvoiceEnvelope>(`/staff/finance/invoices/${reservationId}/issue`, {
    method: 'POST',
    query,
    idempotencyKey: createIdempotencyKey(`finance-invoice-${reservationId}`),
  });
}

export async function assignSuggestedTable(reservationId: number, payload: AssignSuggestedTablePayload): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/staff/reservations/${reservationId}/assign-table`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-assign-table-${reservationId}`),
  });
}

export async function assignBestFitTable(reservationId: number, payload: AssignBestFitTablePayload): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/staff/reservations/${reservationId}/assign-best-fit`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-assign-best-fit-${reservationId}`),
  });
}

export async function checkInReservation(reservationId: number, payload: CheckInReservationRequest): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/staff/reservations/${reservationId}/check-in`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-check-in-${reservationId}`),
  });
}

export async function moveReservationTable(reservationId: number, payload: MoveTablePayload): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>(`/staff/reservations/${reservationId}/move-table`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`reservation-move-table-${reservationId}`),
  });
}

export async function releaseStaffTable(tableId: number, payload: ReleaseTablePayload = {}): Promise<GenericDataEnvelope> {
  return apiRequest<GenericDataEnvelope>(`/staff/tables/${tableId}/release`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`table-release-${tableId}`),
  });
}

export async function createWalkInSession(payload: WalkInPayload): Promise<ReservationEnvelope> {
  return apiRequest<ReservationEnvelope>('/staff/service-sessions/walk-in', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`walk-in-${payload.table_ids.join('-')}`),
  });
}

export async function listMenuItems(query: GetV1MenuItemsQueryParams): Promise<CustomerMenuItemsCollectionEnvelope> {
  return apiRequest<CustomerMenuItemsCollectionEnvelope>('/staff/menu/items', { query });
}

export async function getActiveOrderByTable(tableId: number): Promise<StaffOrderReadEnvelope> {
  return apiRequest<StaffOrderReadEnvelope>(`/staff/tables/${tableId}/active-order`);
}

export async function getActiveOrderByReservation(reservationId: number): Promise<StaffOrderReadEnvelope> {
  return apiRequest<StaffOrderReadEnvelope>(`/staff/reservations/${reservationId}/active-order`);
}

export async function listReservationOrders(reservationId: number): Promise<StaffReservationOrderCollectionEnvelope> {
  return apiRequest<StaffReservationOrderCollectionEnvelope>(`/staff/reservations/${reservationId}/orders`);
}

export async function getOrderDetail(orderId: number): Promise<StaffOrderReadEnvelope> {
  return apiRequest<StaffOrderReadEnvelope>(`/staff/orders/${orderId}`);
}

export async function createTableOrder(tableId: number, payload: {
  reservation_id?: number | null;
  items?: AddOrderItemsRequest['items'];
  notes?: string | null;
  row_version: number;
}): Promise<StaffReservationOrderEnvelope> {
  return apiRequest<StaffReservationOrderEnvelope>(`/staff/tables/${tableId}/orders`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`order-create-${tableId}`),
  });
}

export async function addOrderItems(orderId: number, payload: AddOrderItemsRequest): Promise<StaffReservationOrderEnvelope> {
  return apiRequest<StaffReservationOrderEnvelope>(`/staff/orders/${orderId}/items`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`order-items-${orderId}`),
  });
}

export async function updateOrderItem(orderId: number, orderItemId: number, payload: UpdateOrderItemPayload): Promise<StaffReservationOrderEnvelope> {
  return apiRequest<StaffReservationOrderEnvelope>(`/staff/orders/${orderId}/items/${orderItemId}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`order-item-update-${orderItemId}`),
  });
}

export async function updateOrderItemStatus(orderId: number, orderItemId: number, payload: UpdateOrderItemStatusPayload): Promise<StaffReservationOrderEnvelope> {
  return apiRequest<StaffReservationOrderEnvelope>(`/staff/orders/${orderId}/items/${orderItemId}/status`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`order-item-status-${orderItemId}`),
  });
}

export async function listKitchenStations(branchId?: number): Promise<StaffKitchenStationCollectionEnvelope> {
  return apiRequest<StaffKitchenStationCollectionEnvelope>('/staff/kitchen/stations', {
    query: {
      branch_id: branchId,
    },
  });
}

export async function getKitchenStationTickets(
  stationId: number,
  query: GetV1StaffKitchenStationsStationIdTicketsQueryParams & BranchScopedQuery,
): Promise<StaffKitchenTicketCollectionEnvelope> {
  return apiRequest<StaffKitchenTicketCollectionEnvelope>(`/staff/kitchen/stations/${stationId}/tickets`, { query });
}

export async function getKitchenChanges(afterVersion?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return apiRequest<StaffOperationalRealtimeEnvelope>('/staff/kitchen/changes', {
    query: {
      after_version: afterVersion,
      limit: 20,
    },
  });
}

export async function dispatchKitchenOrder(orderId: number, payload: DispatchKitchenTicketsRequest = {}): Promise<StaffKitchenDispatchEnvelope> {
  return apiRequest<StaffKitchenDispatchEnvelope>(`/staff/orders/${orderId}/kitchen/dispatch`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`kitchen-dispatch-${orderId}`),
  });
}

export async function fireKitchenTicket(ticketId: number): Promise<StaffKitchenTicketEnvelope> {
  return apiRequest<StaffKitchenTicketEnvelope>(`/staff/kitchen/tickets/${ticketId}/fire`, {
    method: 'POST',
    idempotencyKey: createIdempotencyKey(`kitchen-fire-${ticketId}`),
  });
}

export async function bumpKitchenTicket(ticketId: number): Promise<StaffKitchenTicketEnvelope> {
  return apiRequest<StaffKitchenTicketEnvelope>(`/staff/kitchen/tickets/${ticketId}/bump`, {
    method: 'POST',
    idempotencyKey: createIdempotencyKey(`kitchen-bump-${ticketId}`),
  });
}

export async function recallKitchenTicket(ticketId: number): Promise<StaffKitchenTicketEnvelope> {
  return apiRequest<StaffKitchenTicketEnvelope>(`/staff/kitchen/tickets/${ticketId}/recall`, {
    method: 'POST',
    idempotencyKey: createIdempotencyKey(`kitchen-recall-${ticketId}`),
  });
}

export async function createBillSnapshot(orderId: number, payload: CreateBillSnapshotPayload): Promise<StaffReservationOrderEnvelope> {
  return apiRequest<StaffReservationOrderEnvelope>(`/staff/orders/${orderId}/bill-snapshot`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`bill-snapshot-${orderId}`),
  });
}

export async function getSettlementPreview(
  orderId: number,
  query: GetV1StaffOrdersOrderIdSettlementPreviewQueryParams = {},
): Promise<StaffCheckoutSettlementEnvelope> {
  return apiRequest<StaffCheckoutSettlementEnvelope>(`/staff/orders/${orderId}/settlement-preview`, { query });
}

export async function finalizeSettlement(orderId: number, payload: CheckoutOrderRequest): Promise<StaffCheckoutSettlementEnvelope> {
  return apiRequest<StaffCheckoutSettlementEnvelope>(`/staff/orders/${orderId}/settlement/finalize`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`settlement-finalize-${orderId}`),
  });
}

export async function getRefundPreview(
  reservationId: number,
  query: GetV1StaffReservationsReservationIdRefundPreviewQueryParams = {},
): Promise<StaffRefundPreviewEnvelope> {
  return apiRequest<StaffRefundPreviewEnvelope>(`/staff/reservations/${reservationId}/refund-preview`, { query });
}

export async function refundReservation(
  reservationId: number,
  payload: RefundReservationRequest,
): Promise<StaffRefundEnvelope> {
  return apiRequest<StaffRefundEnvelope>(`/staff/reservations/${reservationId}/refund`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`refund-${reservationId}`),
  });
}

export async function refundAndCancelReservation(
  reservationId: number,
  payload: RefundAndCancelReservationRequest,
): Promise<StaffRefundEnvelope> {
  return apiRequest<StaffRefundEnvelope>(`/staff/reservations/${reservationId}/refund-cancel`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`refund-cancel-${reservationId}`),
  });
}
