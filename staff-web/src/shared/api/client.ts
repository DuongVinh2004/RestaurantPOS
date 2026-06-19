import {
  RestaurantPosClient,
  type AdminIngredientCollectionEnvelope,
  type AdminPurchaseOrderCollectionEnvelope,
  type AdminSupplierCollectionEnvelope,
  type BranchCollectionEnvelope,
  type CashierShiftCollectionEnvelope,
  type AddConversationInternalNoteRequest,
  type AddOrderItemsRequest,
  type CashierShiftEnvelope,
  type StaffCheckInReservationRequest as CheckInReservationRequest,
  type StaffCheckoutOrderRequest as CheckoutOrderRequest,
  type CloseCashierShiftRequest,
  type StaffCloseOrderRequest as CloseOrderRequest,
  type CreateTableOrderRequest,
  type CustomerMenuItemsCollectionEnvelope,
  type GetV1AdminInventoryIngredientsQueryParams,
  type GetV1AdminInventoryPurchaseOrdersQueryParams,
  type GetV1AdminInventorySuppliersQueryParams,
  type GetV1AdminSettingsBranchesQueryParams,
  type DispatchKitchenTicketRequest as DispatchKitchenTicketsRequest,
  type GetV1StaffCashierShiftsQueryParams,
  type GetV1StaffKitchenChangesQueryParams,
  type GetV1StaffKitchenStationsStationIdTicketsQueryParams,
  type GetV1MenuItemsQueryParams,
  type GetV1StaffConversationsQueryParams,
  type GetV1StaffOrdersOrderIdSettlementPreviewQueryParams,
  type GetV1StaffReportingDailyInventoryQueryParams,
  type GetV1StaffReportingDailyOperationsQueryParams,
  type GetV1StaffReportingDailySalesQueryParams,
  type GetV1StaffReservationsQueryParams,
  type GetV1StaffReservationsReservationIdRefundPreviewQueryParams,
  type GetV1StaffWaitingListQueryParams,
  type StaffLoginRequest as LoginStaffAuthRequest,
  type InviteWaitlistCustomerRequest as NotifyWaitingListRequest,
  type OpenCashierShiftRequest,
  type PayOrderRequest,
  type RefundAndCancelReservationRequest,
  type RefundReservationRequest,
  type SeatWaitlistRequest as SeatWaitingListRequest,
  type SendConversationOutboundReplyRequest,
  type StaffCheckoutSettlementEnvelope,
  type StaffConversationCollectionEnvelope,
  type StaffConversationDetailEnvelope,
  type StaffKitchenDispatchEnvelope,
  type StaffKitchenStationCollectionEnvelope,
  type StaffKitchenTicketActionRequest,
  type StaffKitchenTicketCollectionEnvelope,
  type StaffKitchenTicketEnvelope,
  type StaffOperationalRealtimeEnvelope,
  type StaffOrderReadEnvelope,
  type StaffReportingDailyInventoryCollectionEnvelope,
  type StaffReportingDailyOperationsCollectionEnvelope,
  type StaffReportingDailySalesCollectionEnvelope,
  type StaffRefundEnvelope,
  type StaffRefundPreviewEnvelope,
  type StaffReservationLookupCollectionEnvelope,
  type StaffReservationOrderCollectionEnvelope,
  type StaffReservationOrderEnvelope,
  type StaffTableBoardEnvelope,
  type StaffWaitingListCollectionEnvelope,
  type StaffWaitingListEnvelope,
  type StaffWaitingListSeatEnvelope,
  type TakeOverConversationRequest,
} from './sdk';
import {
  persistStaffSessionToken,
  readStoredStaffToken,
  writeStoredStaffToken,
  type StaffSession as SharedStaffSession,
} from '../auth/storage';
import { notifyStaffAuthFailure } from '../auth/session-events';
import { resolveApiBaseUrl } from '../config/env';
import { createIdempotencyKey } from '../utils/idempotency';
import { formatApiError, isApiStatus, isRestaurantPosApiError, normalizeApiError } from './errors';

const RAW_API_BASE = resolveApiBaseUrl(import.meta.env.VITE_API_URL as string | undefined);

export const apiBaseUrl = normalizeApiBaseUrl(RAW_API_BASE);

export type StaffSession = SharedStaffSession;
export type StaffBoardWindow = {
  from: string;
  to: string;
};

export const staffClient = new RestaurantPosClient({
  baseUrl: apiBaseUrl,
  fetchImpl: staffAuthAwareFetch,
  staffApiKey: () => getStaffToken() ?? undefined,
});

export function getStaffToken(): string | null {
  return readStoredStaffToken();
}

export function clearStaffSession(): void {
  writeStoredStaffToken(null);
}

export async function loginStaff(
  identifier: LoginStaffAuthRequest['identifier'],
  password: LoginStaffAuthRequest['password'],
  deviceName: LoginStaffAuthRequest['device_name'],
): Promise<StaffSession> {
  const response = await staffClient.postV1AuthStaffLogin({
    identifier,
    password,
    device_name: deviceName,
  });

  persist(response.data);
  return response.data;
}

export async function getCurrentStaffSession(): Promise<StaffSession> {
  const response = await staffClient.getV1AuthStaffMe();
  persist(response.data);
  return response.data;
}

export async function refreshStaffSession(): Promise<StaffSession> {
  const response = await staffClient.postV1AuthStaffRefresh();
  persist(response.data);
  return response.data;
}

export async function logoutStaff(): Promise<void> {
  try {
    await staffClient.postV1AuthStaffLogout();
  } finally {
    clearStaffSession();
  }
}

export async function loadTableBoard(window = boardWindow()): Promise<StaffTableBoardEnvelope> {
  return staffClient.staffTablesBoard({
    from: window.from,
    to: window.to,
    include_holds: true,
    group_by: 'zone',
  });
}

export async function loadTableBoardChanges(afterVersion?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffTablesBoardChanges({
    after_version: afterVersion,
    limit: 20,
  });
}

export async function loadWaitingList(
  query: GetV1StaffWaitingListQueryParams = { active_only: true, per_page: 12, sort: '-priority' },
): Promise<StaffWaitingListCollectionEnvelope> {
  return staffClient.getV1StaffWaitingList(query);
}

export async function loadWaitingListChanges(afterVersion?: number): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffWaitingListChanges({
    after_version: afterVersion,
    limit: 20,
  });
}

export async function checkInReservation(
  reservationId: number,
  payload: CheckInReservationRequest,
): Promise<void> {
  await staffClient.postV1StaffReservationsIdCheckIn(
    { id: reservationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`check-in-${reservationId}`) },
  );
}

export async function notifyWaitingListEntry(
  waitingId: number,
  payload: NotifyWaitingListRequest,
): Promise<StaffWaitingListEnvelope> {
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

export async function loadMenuItems(
  query: GetV1MenuItemsQueryParams = { per_page: 12, service_time: new Date().toISOString() },
): Promise<CustomerMenuItemsCollectionEnvelope> {
  return staffClient.getV1MenuItems(query);
}

export async function createTableOrder(
  tableId: number,
  payload: CreateTableOrderRequest,
): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffTablesTableIdOrders(
    { table_id: tableId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-create-${tableId}`) },
  );
}

export async function addOrderItems(
  orderId: number,
  payload: AddOrderItemsRequest,
): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdItems(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-items-${orderId}`) },
  );
}

export async function createBillSnapshot(
  orderId: number,
  payload: CloseOrderRequest,
): Promise<StaffReservationOrderEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdBillSnapshot(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`bill-snapshot-${orderId}`) },
  );
}

export async function loadOrderDetail(orderId: number): Promise<StaffOrderReadEnvelope> {
  return staffClient.getV1StaffOrdersOrderId({ order_id: orderId });
}

export async function loadStaffReservations(
  query: GetV1StaffReservationsQueryParams = { bucket: 'all', per_page: 8, sort: '-start_time' },
): Promise<StaffReservationLookupCollectionEnvelope> {
  return staffClient.getV1StaffReservations(query);
}

export async function loadReservationOrders(reservationId: number): Promise<StaffReservationOrderCollectionEnvelope> {
  return staffClient.getV1StaffReservationsReservationIdOrders({ reservation_id: reservationId });
}

export async function loadSettlementPreview(
  orderId: number,
  query: GetV1StaffOrdersOrderIdSettlementPreviewQueryParams = {},
): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.getV1StaffOrdersOrderIdSettlementPreview({ order_id: orderId }, query);
}

export async function loadKitchenChanges(
  query: GetV1StaffKitchenChangesQueryParams = { limit: 20 },
): Promise<StaffOperationalRealtimeEnvelope> {
  return staffClient.getV1StaffKitchenChanges(query);
}

export async function loadKitchenStations(): Promise<StaffKitchenStationCollectionEnvelope> {
  return staffClient.getV1StaffKitchenStations({});
}

export async function loadDailySalesReporting(
  query: GetV1StaffReportingDailySalesQueryParams = { per_page: 7, sort: '-business_date' },
): Promise<StaffReportingDailySalesCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailySales(query);
}

export async function loadDailyOperationsReporting(
  query: GetV1StaffReportingDailyOperationsQueryParams = { per_page: 7, sort: '-business_date' },
): Promise<StaffReportingDailyOperationsCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailyOperations(query);
}

export async function loadDailyInventoryReporting(
  query: GetV1StaffReportingDailyInventoryQueryParams = { per_page: 7, sort: '-business_date' },
): Promise<StaffReportingDailyInventoryCollectionEnvelope> {
  return staffClient.getV1StaffReportingDailyInventory(query);
}

export async function loadAdminIngredients(
  query: GetV1AdminInventoryIngredientsQueryParams = { per_page: 8, sort: 'name' },
): Promise<AdminIngredientCollectionEnvelope> {
  return staffClient.getV1AdminInventoryIngredients(query);
}

export async function loadAdminSuppliers(
  query: GetV1AdminInventorySuppliersQueryParams = { per_page: 8, sort: 'name' },
): Promise<AdminSupplierCollectionEnvelope> {
  return staffClient.getV1AdminInventorySuppliers(query);
}

export async function loadAdminPurchaseOrders(
  query: GetV1AdminInventoryPurchaseOrdersQueryParams = { per_page: 8, sort: '-created_at' },
): Promise<AdminPurchaseOrderCollectionEnvelope> {
  return staffClient.getV1AdminInventoryPurchaseOrders(query);
}

export async function loadAdminBranches(
  query: GetV1AdminSettingsBranchesQueryParams = {},
): Promise<BranchCollectionEnvelope> {
  return staffClient.getV1AdminSettingsBranches(query);
}

export async function loadKitchenStationTickets(
  stationId: number,
  query: GetV1StaffKitchenStationsStationIdTicketsQueryParams = {},
): Promise<StaffKitchenTicketCollectionEnvelope> {
  return staffClient.getV1StaffKitchenStationsStationIdTickets({ station_id: stationId }, query);
}

export async function dispatchKitchenOrder(
  orderId: number,
  payload: DispatchKitchenTicketsRequest,
): Promise<StaffKitchenDispatchEnvelope> {
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

export async function finalizeSettlement(
  orderId: number,
  payload: CheckoutOrderRequest,
): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdSettlementFinalize(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`settlement-finalize-${orderId}`) },
  );
}

export async function payOrder(orderId: number, payload: PayOrderRequest): Promise<StaffCheckoutSettlementEnvelope> {
  return staffClient.postV1StaffOrdersOrderIdPay(
    { order_id: orderId },
    payload,
    { idempotencyKey: createIdempotencyKey(`order-pay-${orderId}`) },
  );
}

export async function loadRefundPreview(
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

export async function loadCurrentCashierShift(): Promise<CashierShiftEnvelope> {
  return staffClient.getV1StaffCashierShiftsCurrent({});
}

export async function loadCashierShifts(
  query: GetV1StaffCashierShiftsQueryParams = { per_page: 8, sort: '-opened_at' },
): Promise<CashierShiftCollectionEnvelope> {
  return staffClient.getV1StaffCashierShifts(query);
}

export async function openCashierShift(payload: OpenCashierShiftRequest): Promise<CashierShiftEnvelope> {
  return staffClient.postV1StaffCashierShiftsOpen(payload, {
    idempotencyKey: createIdempotencyKey('cashier-open'),
  });
}

export async function loadCashierShift(shiftId: number): Promise<CashierShiftEnvelope> {
  return staffClient.getV1StaffCashierShiftsShiftId({ shift_id: shiftId });
}

export async function closeCashierShift(
  shiftId: number,
  payload: CloseCashierShiftRequest,
): Promise<CashierShiftEnvelope> {
  return staffClient.postV1StaffCashierShiftsShiftIdClose(
    { shift_id: shiftId },
    payload,
    { idempotencyKey: createIdempotencyKey(`cashier-close-${shiftId}`) },
  );
}

export async function loadConversations(
  query: GetV1StaffConversationsQueryParams = { per_page: 16 },
): Promise<StaffConversationCollectionEnvelope> {
  return staffClient.getV1StaffConversations(query);
}

export async function loadConversationDetail(conversationId: string): Promise<StaffConversationDetailEnvelope> {
  return staffClient.getV1StaffConversationsConversationId(
    { conversation_id: conversationId },
    { message_limit: 20, event_limit: 12, include_closed_assignments: false },
  );
}

export async function takeOverConversation(
  conversationId: string,
  payload: TakeOverConversationRequest = { notes: 'Taken over from staff-web.' },
): Promise<void> {
  await staffClient.postV1StaffConversationsConversationIdTakeOver(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-take-over-${conversationId}`) },
  );
}

export async function addConversationInternalNote(
  conversationId: string,
  payload: AddConversationInternalNoteRequest,
): Promise<void> {
  await staffClient.postV1StaffConversationsConversationIdInternalNotes(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-note-${conversationId}`) },
  );
}

export async function sendConversationOutboundReply(
  conversationId: string,
  payload: SendConversationOutboundReplyRequest,
): Promise<void> {
  await staffClient.postV1StaffConversationsConversationIdOutboundReplies(
    { conversation_id: conversationId },
    payload,
    { idempotencyKey: createIdempotencyKey(`conversation-reply-${conversationId}`) },
  );
}

export function readApiMessage(error: unknown, fallback: string): string {
  return formatApiError(error, fallback);
}

export function isUnauthorized(error: unknown): boolean {
  return isApiStatus(error, 401);
}

export function isMissingResource(error: unknown): boolean {
  return isApiStatus(error, 404);
}

export function isConflictError(error: unknown): boolean {
  return isApiStatus(error, 409);
}

export { formatApiError, isRestaurantPosApiError, normalizeApiError };

function persist(session: StaffSession): void {
  persistStaffSessionToken(session);
}

export function boardWindow(reference = new Date()): StaffBoardWindow {
  return {
    from: new Date(reference.getTime() - 60 * 60 * 1000).toISOString(),
    to: new Date(reference.getTime() + 4 * 60 * 60 * 1000).toISOString(),
  };
}

function normalizeApiBaseUrl(value: string): string {
  return value.trim().replace(/\/+$/, '').replace(/\/api\/v1$/i, '');
}

async function staffAuthAwareFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const response = await fetch(input, init);
  const headers = new Headers(init?.headers);
  const token = headers.get('X-Staff-Key');
  const path = resolveRequestPath(input);

  if (response.status === 401 && token && path !== '/api/v1/auth/staff/logout') {
    notifyStaffAuthFailure({
      status: response.status,
      path,
    });
  }

  return response;
}

function resolveRequestPath(input: RequestInfo | URL): string {
  const value = typeof input === 'string'
    ? input
    : input instanceof URL
      ? input.toString()
      : input.url;

  try {
    const url = new URL(value, typeof window === 'undefined' ? 'http://localhost' : window.location.origin);

    return `${url.pathname}${url.search}`;
  } catch {
    return value;
  }
}
