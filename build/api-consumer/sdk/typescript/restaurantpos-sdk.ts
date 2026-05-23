/* Generated from storage/app/booking_release/openapi-v1.json. Do not edit by hand. */

export type AuthMode = 'auto' | 'none' | 'customer' | 'staff' | 'session' | 'customerOrSession' | 'staffBrowserSession';

export interface RestaurantPosClientOptions {
  baseUrl: string;
  fetchImpl?: typeof fetch;
  customerToken?: string | (() => string | null | undefined);
  staffApiKey?: string | (() => string | null | undefined);
  staffCsrfToken?: string | (() => string | null | undefined);
  customerSessionId?: string | (() => string | null | undefined);
  credentials?: RequestCredentials;
  defaultHeaders?: Record<string, string>;
}

export interface RequestOptions {
  headers?: Record<string, string>;
  signal?: AbortSignal;
  authMode?: AuthMode;
  idempotencyKey?: string;
  credentials?: RequestCredentials;
  staffCsrfToken?: string | (() => string | null | undefined);
}

export class RestaurantPosApiError<T = unknown> extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: T,
  ) {
    super(message);
    this.name = 'RestaurantPosApiError';
  }
}

export type AcknowledgeCustomerReservationDepositRequest = {
  row_version: number;
  session_id?: (string) | null;
};

export type AddConversationInternalNoteRequest = {
  message_text: string;
  related_reservation_id?: (number) | null;
  related_order_id?: (number) | null;
};

export type AddOrderItemsRequest = {
  items: Array<{
  menu_item_id: number;
  qty: number;
  note?: (string) | null;
}>;
  staff_user_id?: (number) | null;
  row_version: number;
};

export type AdjustUserLoyaltyPointsRequest = {
  points: number;
  reason: string;
};

export type AdminBenefitSetting = {
  setting_key: string;
  value: string;
  updated_at?: (string) | null;
  [key: string]: unknown;
};

export type AdminBenefitSettingCollectionEnvelope = {
  data: Array<AdminBenefitSetting>;
  meta?: Record<string, unknown>;
};

export type AdminBenefitSettingEnvelope = {
  data: AdminBenefitSetting;
  meta?: Record<string, unknown>;
};

export type AdminCustomerDataExportEnvelope = {
  data: Record<string, unknown>;
  meta?: {
  action?: string;
  [key: string]: unknown;
};
};

export type AdminIngredient = {
  ingredient_id: number;
  code: (string) | null;
  name: string;
  unit_code: string;
  description?: (string) | null;
  is_active: boolean;
  row_version: number;
  stock: {
  on_hand: string;
  unit_code: string;
};
  recipe_usage_count: number;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type AdminIngredientCollectionEnvelope = {
  data: Array<AdminIngredient>;
  meta?: AdminIngredientCollectionMeta;
};

export type AdminIngredientCollectionMeta = {
  filters: {
  is_active: (boolean) | null;
  q: (string) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
};

export type AdminIngredientMovement = {
  movement_id: number;
  branch_id: (number) | null;
  ingredient_id: number;
  movement_type: string;
  quantity_delta: string;
  unit_code: string;
  reference: {
  type: (string) | null;
  id: (string) | null;
};
  notes: (string) | null;
  created_by: (number) | null;
  created_at: (string) | null;
};

export type AdminIngredientMovementCollectionEnvelope = {
  data: Array<AdminIngredientMovement>;
  meta?: {
  ingredient?: Record<string, unknown>;
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  filters?: Record<string, unknown>;
  sort?: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  query_contract?: ListingQueryContract;
  [key: string]: unknown;
};
};

export type AdminIngredientMovementEnvelope = {
  data: AdminIngredientMovement;
  meta?: {
  stock_on_hand?: string;
  [key: string]: unknown;
};
};

export type AdminLoyaltyTier = {
  tier_id: number;
  tier_code: string;
  tier_name: string;
  min_points: number;
  benefits_json?: (Array<Record<string, unknown>>) | null;
  is_active: boolean;
  row_version: number;
  created_at?: (string) | null;
  updated_at?: (string) | null;
  [key: string]: unknown;
};

export type AdminLoyaltyTierCollectionEnvelope = {
  data: Array<AdminLoyaltyTier>;
  meta?: Record<string, unknown>;
};

export type AdminLoyaltyTierEnvelope = {
  data: AdminLoyaltyTier;
};

export type AdminPrivacyRequest = {
  request_id: number;
  privacy_request_id?: number;
  customer_privacy_request_id?: number;
  user_id: (number) | null;
  customer_user_id?: (number) | null;
  request_type: string;
  status: string;
  decision?: (string) | null;
  requested_at?: (string) | null;
  created_at?: (string) | null;
  reviewed_at?: (string) | null;
  processed_at?: (string) | null;
  notes?: (string) | null;
  resolution_notes?: (string) | null;
  result_summary?: (string) | null;
  [key: string]: unknown;
};

export type AdminPrivacyRequestCollectionEnvelope = {
  data: Array<AdminPrivacyRequest>;
  meta?: {
  action?: string;
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  [key: string]: unknown;
};
};

export type AdminPrivacyReviewEnvelope = {
  data: Record<string, unknown>;
  meta?: {
  action?: string;
  mode?: string;
  committed?: boolean;
  [key: string]: unknown;
};
};

export type AdminPurchaseOrder = {
  purchase_order_id: number;
  branch_id: (number) | null;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
} | null;
  order_code: string;
  purchase_order_status: string;
  supplier_id: number;
  supplier?: {
  supplier_id: number;
  code: (string) | null;
  name: string;
  is_active: boolean;
} | null;
  ordered_at?: (string) | null;
  expected_at?: (string) | null;
  received_at?: (string) | null;
  supplier_reference?: (string) | null;
  notes?: (string) | null;
  row_version: number;
  summary: {
  line_count: number;
  receipt_count: number;
  ordered_total_quantity: string;
  received_total_quantity: string;
  remaining_total_quantity: string;
};
  lines?: Array<AdminPurchaseOrderLine>;
  receipts?: Array<AdminPurchaseOrderReceipt>;
  created_by?: (number) | null;
  updated_by?: (number) | null;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type AdminPurchaseOrderCollectionEnvelope = {
  data: Array<AdminPurchaseOrder>;
  meta?: AdminPurchaseOrderCollectionMeta;
};

export type AdminPurchaseOrderCollectionMeta = {
  filters: {
  supplier_id: (number) | null;
  branch_id: (number) | null;
  purchase_order_status: (string) | null;
  q: (string) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
};

export type AdminPurchaseOrderLine = {
  po_line_id: number;
  ingredient_id: number;
  ingredient?: {
  ingredient_id: number;
  code: string;
  name: string;
  unit_code: string;
  is_active: boolean;
} | null;
  ordered_quantity: string;
  received_quantity: string;
  remaining_quantity: string;
  unit_code: string;
  unit_cost: (string) | null;
  notes: (string) | null;
  sort_order: number;
};

export type AdminPurchaseOrderReceipt = {
  receipt_id: number;
  branch_id: (number) | null;
  purchase_order_id: number;
  receipt_code: string;
  receipt_status: string;
  received_at: (string) | null;
  supplier_document_no: (string) | null;
  notes: (string) | null;
  summary: {
  line_count: number;
  received_total_quantity: string;
};
  lines?: Array<AdminPurchaseOrderReceiptLine>;
  created_by: (number) | null;
  created_at: (string) | null;
};

export type AdminPurchaseOrderReceiptCollectionEnvelope = {
  data: Array<AdminPurchaseOrderReceipt>;
  meta?: {
  purchase_order: AdminPurchaseOrder;
  count: number;
};
};

export type AdminPurchaseOrderReceiptEnvelope = {
  data: AdminPurchaseOrderReceipt;
  meta?: {
  purchase_order: AdminPurchaseOrder;
};
};

export type AdminPurchaseOrderReceiptLine = {
  receipt_line_id: number;
  purchase_order_line_id: number;
  ingredient_id: number;
  ingredient?: {
  ingredient_id: number;
  code: string;
  name: string;
  unit_code: string;
  is_active: boolean;
} | null;
  received_quantity: string;
  unit_code: string;
  unit_cost: (string) | null;
  notes: (string) | null;
  [key: string]: unknown;
};

export type AdminSupplier = {
  supplier_id: number;
  code: (string) | null;
  name: string;
  contact_name: (string) | null;
  phone: (string) | null;
  email: (string) | null;
  notes: (string) | null;
  is_active: boolean;
  row_version: number;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type AdminSupplierCollectionEnvelope = {
  data: Array<AdminSupplier>;
  meta?: AdminSupplierCollectionMeta;
};

export type AdminSupplierCollectionMeta = {
  filters: {
  is_active: (boolean) | null;
  q: (string) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
};

export type AdminVoucher = {
  voucher_id: number;
  code: string;
  description?: (string) | null;
  discount_type: string;
  discount_value: (number) | null;
  free_item_id?: (number) | null;
  free_item_qty?: (number) | null;
  max_usage?: (number) | null;
  max_usage_per_user?: (number) | null;
  min_spend?: (number) | null;
  start_date?: (string) | null;
  expiry_date?: (string) | null;
  is_active: boolean;
  row_version: number;
  created_at?: (string) | null;
  updated_at?: (string) | null;
  [key: string]: unknown;
};

export type AdminVoucherCollectionEnvelope = {
  data: Array<AdminVoucher>;
  meta?: Record<string, unknown>;
};

export type AdminVoucherEnvelope = {
  data: AdminVoucher;
};

export type AdvanceWaitlistRequest = {
  hold_minutes?: (number) | null;
  row_version: number;
};

export type AssignConversationRequest = {
  agent_user_id: number;
  notes?: (string) | null;
};

export type AvailableTablesCollectionEnvelope = {
  data: Array<RestaurantTable>;
  meta?: {
  timezone?: string;
  branch_id?: (number) | null;
  branch_timezone?: (string) | null;
  from_utc?: (string) | null;
  to_utc?: (string) | null;
  filters?: Record<string, unknown>;
  availability_policy?: Record<string, unknown>;
  count?: number;
  suggestions?: Array<Record<string, unknown>>;
  [key: string]: unknown;
};
};

export type BranchCollectionEnvelope = {
  data: Array<{
  branch_id: number;
  branch_code: string;
  branch_name: string;
  description?: (string) | null;
  timezone?: (string) | null;
  currency?: (string) | null;
  business_hours: Array<{
  day_of_week: number;
  periods: Array<{
  start_time: string;
  end_time: string;
}>;
}>;
  closure_windows: Array<{
  start_local: string;
  end_local: string;
  type: string;
  reason: string;
}>;
  booking_policy: {
  reservation: Record<string, unknown>;
  waiting_list: Record<string, unknown>;
  [key: string]: unknown;
};
  is_active: boolean;
  is_default: boolean;
  row_version?: (number) | null;
  created_at?: (string) | null;
  updated_at?: (string) | null;
}>;
  meta?: {
  action: string;
  count: number;
  branch_access?: StaffBranchAccessContext;
  accessible_branch_ids?: Array<number>;
  default_branch_id?: (number) | null;
  current_branch_id?: (number) | null;
  has_multi_branch_access?: boolean;
};
};

export type BranchScopeRequest = {
  branch_id?: (number) | null;
};

export type CancelReservationRequest = {
  row_version: number;
  cancel_reason?: (string) | null;
  session_id?: string;
};

export type CancelWaitlistRequest = {
  cancel_reason?: (string) | null;
  row_version: number;
};

export type CashierShift = {
  cashier_shift_id: number;
  branch_id: number;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
} | null;
  shift_code: string;
  status: string;
  currency: string;
  terminal_code?: (string) | null;
  row_version: number;
  opened_at?: (string) | null;
  closed_at?: (string) | null;
  opening_float_amount?: string;
  expected_cash_amount?: (string) | null;
  actual_cash_amount?: (string) | null;
  cash_discrepancy_amount?: (string) | null;
  opening_note?: (string) | null;
  closing_note?: (string) | null;
  cashier?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  opened_by?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  closed_by?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  summary?: {
  payments: {
  captured_total: string;
  refunded_total: string;
  net_paid_total: string;
  deposit_net: string;
  final_net: string;
  payment_count: number;
  refund_count: number;
  currency: {
  currency: (string) | null;
  currencies: Array<string>;
  has_mixed_currencies: boolean;
};
};
  cash: {
  currency: string;
  opening_float_amount: string;
  captured_amount: string;
  refunded_amount: string;
  expected_cash_amount: string;
  excluded_cash_currencies: Array<string>;
  has_excluded_cash_currencies: boolean;
};
  methods: Array<{
  payment_method: string;
  currency: string;
  captured_amount: string;
  refunded_amount: string;
  net_amount: string;
  payment_count: number;
  refund_count: number;
}>;
};
  flags?: {
  is_open: boolean;
  has_payments: boolean;
  has_refunds: boolean;
  has_mixed_payment_currencies: boolean;
};
};

export type CashierShiftCollectionEnvelope = {
  data: Array<CashierShift>;
  meta?: CashierShiftCollectionMeta;
};

export type CashierShiftCollectionMeta = {
  filters: {
  status: (string) | null;
  branch_id: (number) | null;
  shift_code: (string) | null;
  terminal_code: (string) | null;
  q: (string) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
  action: string;
  count: number;
  scope: string;
};

export type CashierShiftEnvelope = {
  data: {
  cashier_shift_id: number;
  branch_id: number;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
} | null;
  shift_code: string;
  status: string;
  currency: string;
  terminal_code?: (string) | null;
  row_version: number;
  opened_at?: (string) | null;
  closed_at?: (string) | null;
  opening_float_amount?: string;
  expected_cash_amount?: (string) | null;
  actual_cash_amount?: (string) | null;
  cash_discrepancy_amount?: (string) | null;
  opening_note?: (string) | null;
  closing_note?: (string) | null;
  cashier?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  opened_by?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  closed_by?: ({
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
}) | null;
  summary?: {
  payments: {
  captured_total: string;
  refunded_total: string;
  net_paid_total: string;
  deposit_net: string;
  final_net: string;
  payment_count: number;
  refund_count: number;
  currency: {
  currency: (string) | null;
  currencies: Array<string>;
  has_mixed_currencies: boolean;
};
};
  cash: {
  currency: string;
  opening_float_amount: string;
  captured_amount: string;
  refunded_amount: string;
  expected_cash_amount: string;
  excluded_cash_currencies: Array<string>;
  has_excluded_cash_currencies: boolean;
};
  methods: Array<{
  payment_method: string;
  currency: string;
  captured_amount: string;
  refunded_amount: string;
  net_amount: string;
  payment_count: number;
  refund_count: number;
}>;
};
  flags?: {
  is_open: boolean;
  has_payments: boolean;
  has_refunds: boolean;
  has_mixed_payment_currencies: boolean;
};
};
  meta?: {
  action: string;
};
};

export type CloseCashierShiftRequest = {
  actual_cash_amount: number;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type CreateIngredientStockMovementRequest = {
  movement_type: "StockIn" | "StockOut" | "AdjustmentIncrease" | "AdjustmentDecrease" | "Wastage";
  branch_id?: (number) | null;
  quantity: number;
  unit_code?: (string) | null;
  reference_type?: (string) | null;
  reference_id?: (string) | null;
  notes?: (string) | null;
};

export type CreatePrivacyRequestRequest = {
  request_type: "anonymize";
  reason?: (string) | null;
};

export type CreatePurchaseOrderReceiptRequest = {
  receipt_code?: (string) | null;
  received_at?: (string) | null;
  supplier_document_no?: (string) | null;
  notes?: (string) | null;
  lines: Array<{
  purchase_order_line_id: number;
  received_quantity: number;
  unit_code?: (string) | null;
  unit_cost?: (number) | null;
  notes?: (string) | null;
}>;
};

export type CreateReservationRequest = {
  user_id?: (number) | null;
  branch_id?: (number) | null;
  guest_name?: string;
  guest_phone?: string;
  guest_email?: string;
  start_time: string;
  end_time: string;
  guest_count: number;
  hold_id?: (string) | null;
  session_id?: string;
  table_ids?: Array<number>;
  notes?: (string) | null;
  pre_order_items?: Array<{
  item_id?: number;
  quantity?: number;
}>;
};

export type CreateRestaurantTableRequest = {
  table_code: string;
  branch_id?: (number) | null;
  template_id: number;
  zone?: (string) | null;
  pos_x?: (number) | null;
  pos_y?: (number) | null;
  status?: ("Available" | "Blocked" | "Maintenance") | null;
  description?: (string) | null;
  price?: (number) | null;
  is_deleted?: (boolean) | null;
};

export type CreateTableHoldRequest = {
  session_id: string;
  user_id?: (number) | null;
  branch_id?: (number) | null;
  start_time: string;
  end_time: string;
  table_ids: Array<number>;
  hold_minutes?: (number) | null;
};

export type CreateTableOrderRequest = {
  reservation_id?: (number) | null;
  items?: (Array<{
  menu_item_id?: number;
  qty?: number;
  note?: (string) | null;
}>) | null;
  notes?: (string) | null;
  staff_user_id?: (number) | null;
  row_version: number;
};

export type CreateWaitlistEntryRequest = {
  branch_id?: (number) | null;
  user_id?: (number) | null;
  guest_name?: (string) | null;
  phone?: (string) | null;
  guest_count: number;
  priority?: (number) | null;
  notes?: (string) | null;
};

export type CreateWalkInServiceSessionRequest = {
  branch_id?: (number) | null;
  user_id?: (number) | null;
  guest_name?: (string) | null;
  phone?: (string) | null;
  table_ids: Array<number>;
  guest_count: number;
  started_at?: (string) | null;
  service_minutes?: (number) | null;
  notes?: (string) | null;
  staff_user_id?: (number) | null;
};

export type CustomerActiveOrderEnvelope = {
  data: {
  reservation_id: number;
  active_order?: (Record<string, unknown>) | null;
};
};

export type CustomerApplyReservationVoucherRequest = {
  user_voucher_id?: (number) | null;
  voucher_code?: (string) | null;
  row_version: number;
};

export type CustomerAuthSessionEnvelope = {
  data: {
  auth_mode: "customer_access_session";
  token_type: "opaque";
  auth_header: string;
  access_token?: (string) | null;
  access_session_id: number;
  session_id: string;
  expires_at_utc?: (string) | null;
  user?: Record<string, unknown>;
};
};

export type CustomerBillPaymentSession = CustomerDepositPaymentSession & {
  bill_payment_session_id: number;
  order_id?: (number) | null;
};

export type CustomerBillPaymentSessionEnvelope = {
  data: {
  reservation_id: number;
  bill: Record<string, unknown>;
  payment_session: CustomerBillPaymentSession;
};
};

export type CustomerBillPreviewEnvelope = {
  data: {
  reservation_id: number;
  active_order?: (Record<string, unknown>) | null;
  bill_preview?: Record<string, unknown>;
};
};

export type CustomerDataExportEnvelope = {
  data: Record<string, unknown>;
  meta?: {
  action?: string;
  [key: string]: unknown;
};
};

export type CustomerDepositPaymentSession = {
  deposit_payment_session_id: number;
  reservation_id: number;
  provider_code: string;
  provider_session_code: string;
  provider_payment_code?: (string) | null;
  payment_method?: (string) | null;
  amount?: (string) | null;
  currency?: (string) | null;
  session_status: string;
  settlement_status: string;
  linked_payment_id?: (number) | null;
  failure_code?: (string) | null;
  failure_message?: (string) | null;
  provider_payload?: (Record<string, unknown>) | null;
  provider_expires_at?: (string) | null;
  last_reconciled_at?: (string) | null;
  confirmed_at?: (string) | null;
  failed_at?: (string) | null;
  cancelled_at?: (string) | null;
  expired_at?: (string) | null;
  row_version: number;
  created_at?: (string) | null;
  updated_at?: (string) | null;
};

export type CustomerDepositPaymentSessionEnvelope = {
  data: {
  reservation_id: number;
  deposit: Record<string, unknown>;
  payment_session: CustomerDepositPaymentSession;
};
};

export type CustomerLoginRequest = {
  identifier: string;
  password: string;
  session_id?: (string) | null;
  guest_name?: (string) | null;
  phone?: (string) | null;
  device_id?: (string) | null;
  session_label?: (string) | null;
};

export type CustomerLoyaltySummary = {
  user: CustomerLoyaltyUserSummary;
  transactions: Array<LoyaltyPointTransaction>;
};

export type CustomerLoyaltySummaryEnvelope = {
  data: CustomerLoyaltySummary;
};

export type CustomerLoyaltyTier = {
  tier_id: number;
  tier_code: string;
  tier_name: string;
  min_points: number;
  points_to_unlock?: (number) | null;
};

export type CustomerLoyaltyUserSummary = {
  user_id: number;
  full_name: (string) | null;
  email: (string) | null;
  phone: (string) | null;
  total_points: number;
  current_tier: CustomerLoyaltyTier | null;
  next_tier: CustomerLoyaltyTier | null;
};

export type CustomerMenuCategoriesCollectionEnvelope = {
  data: Array<CustomerMenuCategory>;
  meta: {
  service_time?: (string) | null;
  preorder_only?: boolean;
  count?: number;
  [key: string]: unknown;
};
};

export type CustomerMenuCategory = {
  category_id: number;
  name: string;
  description: (string) | null;
  sort_order: (number) | null;
  items: Array<CustomerMenuItem>;
  [key: string]: unknown;
};

export type CustomerMenuItem = {
  item_id: number;
  category_id: (number) | null;
  category_name: (string) | null;
  code: string;
  name: string;
  description: (string) | null;
  img_url: (string) | null;
  is_available: boolean;
  price: CustomerMenuItemPrice;
  preorder: CustomerMenuItemPreorderPolicy;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type CustomerMenuItemEnvelope = {
  data: CustomerMenuItem;
  meta?: {
  service_time?: (string) | null;
  [key: string]: unknown;
};
};

export type CustomerMenuItemPreorderPolicy = {
  enabled: boolean;
  cutoff_minutes: number;
  quota_per_day: (number) | null;
  requires_preview_validation: boolean;
};

export type CustomerMenuItemPrice = {
  price_id: (number) | null;
  amount: (string) | null;
  currency: (string) | null;
  effective_from: (string) | null;
  effective_to: (string) | null;
};

export type CustomerMenuItemsCollectionEnvelope = {
  data: Array<CustomerMenuItem>;
  meta: CustomerMenuItemsMeta;
};

export type CustomerMenuItemsMeta = {
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  service_time: string;
  filters: {
  category_id: (number) | null;
  preorder_only: boolean;
  q: (string) | null;
};
};

export type CustomerMenuPreorderPreviewEnvelope = {
  data: {
  items?: Array<Record<string, unknown>>;
  totals?: Record<string, unknown>;
  warnings?: Array<string>;
  policy?: Record<string, unknown>;
  [key: string]: unknown;
};
};

export type CustomerMutateReservationBillPaymentSessionRequest = {
  row_version: number;
  session_id?: (string) | null;
  simulation_outcome?: ("pending" | "succeeded" | "failed") | null;
};

export type CustomerMutateReservationDepositPaymentSessionRequest = {
  row_version: number;
  session_id?: (string) | null;
  simulation_outcome?: ("pending" | "succeeded" | "failed") | null;
};

export type CustomerPrivacyRequest = {
  customer_privacy_request_id: number;
  request_type: string;
  status: string;
  reason?: (string) | null;
  requested_via?: (string) | null;
  requested_at: (string) | null;
  reviewed_at?: (string) | null;
  processed_at?: (string) | null;
  resolution_notes?: (string) | null;
  result_summary?: (string) | null;
  [key: string]: unknown;
};

export type CustomerPrivacyRequestCollectionEnvelope = {
  data: Array<CustomerPrivacyRequest>;
  meta?: {
  action?: string;
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  [key: string]: unknown;
};
};

export type CustomerPrivacyRequestEnvelope = {
  data: {
  request: CustomerPrivacyRequest;
  created: boolean;
  [key: string]: unknown;
};
  meta?: {
  action?: string;
  [key: string]: unknown;
};
};

export type CustomerRedeemReservationPointsRequest = {
  points: number;
  reason?: (string) | null;
  row_version: number;
};

export type CustomerReleaseReservationPointsRequest = {
  reason?: (string) | null;
  row_version: number;
};

export type CustomerRemoveReservationVoucherRequest = {
  row_version: number;
};

export type CustomerRescheduleReservationRequest = {
  row_version: number;
  start_time?: (string) | null;
  end_time?: (string) | null;
  guest_count?: number;
  notes?: (string) | null;
  table_ids?: Array<number>;
  reason?: (string) | null;
  session_id?: string;
};

export type CustomerReservationBenefitsPreview = {
  reservation: CustomerReservationBenefitsReservation;
  available_vouchers: Array<CustomerVoucher>;
};

export type CustomerReservationBenefitsPreviewEnvelope = {
  data: CustomerReservationBenefitsPreview;
};

export type CustomerReservationBenefitsReservation = {
  reservation_id: number;
  reservation_code: string;
  user_id: number;
  status: string;
  row_version: number;
  bill: {
  subtotal_amount: string;
  manual_discount_amount: string;
  loyalty_discount_amount: string;
  discount_amount: string;
  payable_amount: string;
  currency: string;
};
  loyalty: {
  enabled: boolean;
  available_points: number;
  redeemed_points: number;
  discount_amount: number;
  redeem_amount_per_point: string;
  earn_amount_per_point: string;
  min_redeem_points: number;
  max_redeemable_points: number;
  earn_preview_points: number;
  earned_points_current: number;
  can_redeem: boolean;
  can_release: boolean;
};
  user: CustomerLoyaltyUserSummary | null;
};

export type CustomerReservationBillEnvelope = {
  data: {
  reservation_id: number;
  access_scope?: string;
  bill: Record<string, unknown>;
  settlement: Record<string, unknown>;
  orders: Array<Record<string, unknown>>;
  workflow: Record<string, unknown>;
};
};

export type CustomerReservationDepositPreviewEnvelope = {
  data: {
  reservation: ReservationSummary;
  deposit: Record<string, unknown>;
};
};

export type CustomerReservationLoyaltyActionEnvelope = {
  data: {
  reservation: CustomerReservationBenefitsReservation;
  transactions: Array<LoyaltyPointTransaction>;
  [key: string]: unknown;
};
};

export type CustomerReservationPreorderEnvelope = {
  data: CustomerReservationPreorderPayload;
  meta: {
  action: string;
  access_scope: string;
};
};

export type CustomerReservationPreorderLine = {
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  name: string;
  code: (string) | null;
  unit_price: string;
  line_total: string;
  currency: string;
  notes: (string) | null;
  updated_at: (string) | null;
};

export type CustomerReservationPreorderManagementPolicy = {
  can_manage: boolean;
  reservation_status: string;
  cutoff_minutes: number;
  service_start: string;
  manage_until: string;
  reasons: Array<string>;
};

export type CustomerReservationPreorderNormalizedItem = {
  item_id: number;
  quantity: number;
};

export type CustomerReservationPreorderPayload = {
  reservation_id: number;
  reservation_code: string;
  reservation_status: string;
  reservation_row_version: number;
  pre_order: CustomerReservationPreorderSnapshot;
  management_policy: CustomerReservationPreorderManagementPolicy;
};

export type CustomerReservationPreorderSnapshot = {
  present: boolean;
  order_id: (number) | null;
  order_row_version: (number) | null;
  order_status: (string) | null;
  service_time: string;
  currency: string;
  lines: Array<CustomerReservationPreorderLine>;
  totals: CustomerReservationPreorderTotals;
  normalized_pre_order_items: Array<CustomerReservationPreorderNormalizedItem>;
};

export type CustomerReservationPreorderTotals = {
  item_count: number;
  quantity: number;
  subtotal: string;
};

export type CustomerReservationVoucherActionEnvelope = {
  data: {
  reservation: CustomerReservationBenefitsReservation;
  available_vouchers: Array<CustomerVoucher>;
  voucher?: CustomerVoucher | null;
  removed_voucher?: CustomerVoucher | null;
  [key: string]: unknown;
};
};

export type CustomerRespondWaitlistInviteRequest = {
  row_version: number;
  cancel_reason?: (string) | null;
};

export type CustomerSessionLogoutEnvelope = {
  data: {
  auth_mode: "customer_access_session";
  access_session_id: number;
  revoked_at_utc?: (string) | null;
};
};

export type CustomerVoucher = {
  user_voucher_id: number;
  voucher_id: number;
  voucher_code: string;
  description: string;
  discount_type: string;
  discount_value: (string) | null;
  min_spend: (string) | null;
  free_item: CustomerVoucherFreeItem | null;
  assigned_at: (string) | null;
  used_at: (string) | null;
  used_reservation_id: (number) | null;
  starts_at: (string) | null;
  expires_at: (string) | null;
  is_used: boolean;
  current_status: string;
  is_usable_now: boolean;
  is_locked: boolean;
  is_locked_by_other: boolean;
  locked_until: (string) | null;
  row_version: (number) | null;
  is_currently_applied: boolean;
  preview_discount_amount: (string) | null;
  preview_subtotal_amount: (string) | null;
  preview_currency: (string) | null;
  can_apply: boolean;
  applicability_reason_codes: Array<string>;
  applicability_reasons: Array<string>;
};

export type CustomerVoucherCollectionEnvelope = {
  data: Array<CustomerVoucher>;
  meta?: {
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  filters?: Record<string, unknown>;
  [key: string]: unknown;
};
};

export type CustomerVoucherFreeItem = {
  item_id: number;
  quantity: number;
  item_name: string;
};

export type CustomerWaitingListArrivalEnvelope = {
  data: CustomerWaitingListEntry;
  meta: {
  action: string;
  staff_seat_required: boolean;
  message?: (string) | null;
};
};

export type CustomerWaitingListCollectionEnvelope = {
  data: Array<{
  waiting_id: number;
  branch_id: (number) | null;
  guest_name: (string) | null;
  phone: (string) | null;
  guest_count: number;
  requested_at: (string) | null;
  status: string;
  priority: number;
  notified_at: (string) | null;
  notify_expires_at: (string) | null;
  seated_at: (string) | null;
  cancelled_at: (string) | null;
  cancel_reason: (string) | null;
  notes: (string) | null;
  row_version: number;
  response_state: "none" | "accepted" | "arrival_confirmed" | "declined";
  can_accept: boolean;
  can_decline: boolean;
  can_confirm_arrival: boolean;
  can_cancel: boolean;
  notify_window: {
  is_open: boolean;
  expires_at: (string) | null;
};
  window: {
  is_notified_window_open: boolean;
};
  available_actions: {
  accept: boolean;
  decline: boolean;
  confirm_arrival: boolean;
  cancel: boolean;
};
  staff_seat_required: boolean;
  next_step: (string) | null;
  arrival_confirmation: {
  supported: boolean;
  staff_seat_required: boolean;
  message: (string) | null;
};
}>;
};

export type CustomerWaitingListEntry = {
  waiting_id: number;
  branch_id: (number) | null;
  guest_name: (string) | null;
  phone: (string) | null;
  guest_count: number;
  requested_at: (string) | null;
  status: string;
  priority: number;
  notified_at: (string) | null;
  notify_expires_at: (string) | null;
  seated_at: (string) | null;
  cancelled_at: (string) | null;
  cancel_reason: (string) | null;
  notes: (string) | null;
  row_version: number;
  response_state: "none" | "accepted" | "arrival_confirmed" | "declined";
  can_accept: boolean;
  can_decline: boolean;
  can_confirm_arrival: boolean;
  can_cancel: boolean;
  notify_window: {
  is_open: boolean;
  expires_at: (string) | null;
};
  window: {
  is_notified_window_open: boolean;
};
  available_actions: {
  accept: boolean;
  decline: boolean;
  confirm_arrival: boolean;
  cancel: boolean;
};
  staff_seat_required: boolean;
  next_step: (string) | null;
  arrival_confirmation: {
  supported: boolean;
  staff_seat_required: boolean;
  message: (string) | null;
};
};

export type CustomerWaitingListEnvelope = {
  data: {
  waiting_id: number;
  branch_id: (number) | null;
  guest_name: (string) | null;
  phone: (string) | null;
  guest_count: number;
  requested_at: (string) | null;
  status: string;
  priority: number;
  notified_at: (string) | null;
  notify_expires_at: (string) | null;
  seated_at: (string) | null;
  cancelled_at: (string) | null;
  cancel_reason: (string) | null;
  notes: (string) | null;
  row_version: number;
  response_state: "none" | "accepted" | "arrival_confirmed" | "declined";
  can_accept: boolean;
  can_decline: boolean;
  can_confirm_arrival: boolean;
  can_cancel: boolean;
  notify_window: {
  is_open: boolean;
  expires_at: (string) | null;
};
  window: {
  is_notified_window_open: boolean;
};
  available_actions: {
  accept: boolean;
  decline: boolean;
  confirm_arrival: boolean;
  cancel: boolean;
};
  staff_seat_required: boolean;
  next_step: (string) | null;
  arrival_confirmation: {
  supported: boolean;
  staff_seat_required: boolean;
  message: (string) | null;
};
};
};

export type DeleteV1ReservationsIdPreorderPathParams = {
  id: number;
};

export type DeleteV1ReservationsIdPreorderQueryParams = {
  row_version: number;
  pre_order_row_version?: (number) | null;
};

export type DeleteV1StaffConversationsConversationIdLinksReservationPathParams = {
  conversation_id: string;
};

export type DeleteV1StaffConversationsConversationIdLinksWaitingListPathParams = {
  conversation_id: string;
};

export type DeleteV1TableHoldsHoldIdPathParams = {
  hold_id: string;
};

export type DeleteV1TableHoldsHoldIdQueryParams = {
  session_id?: (string) | null;
  row_version?: (number) | null;
};

export type DispatchKitchenTicketRequest = {
  row_version: number;
};

export type FinanceInvoiceEnvelope = {
  data: {
  invoice: {
  billing_invoice_id: number;
  reservation_id: number;
  invoice_number: string;
  invoice_status: string;
  currency: string;
  bill_amounts?: Record<string, unknown>;
  tax?: Record<string, unknown>;
  seller?: Record<string, unknown>;
  issued_at?: (string) | null;
  issued_by?: Record<string, unknown>;
  row_version: number;
  metadata?: Record<string, unknown>;
  [key: string]: unknown;
};
  reservation: FinancialReservationSummary;
  reconciliation: FinancialReconciliationRow;
  method_breakdown: Array<Record<string, unknown>>;
  [key: string]: unknown;
};
  meta?: {
  action?: string;
  created?: boolean;
  branch_id?: (number) | null;
  [key: string]: unknown;
};
};

export type FinancialReconciliationCollectionEnvelope = {
  data: Array<FinancialReconciliationRow>;
  meta?: {
  action?: string;
  filters?: Record<string, unknown>;
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  query_contract?: ListingQueryContract;
  [key: string]: unknown;
};
};

export type FinancialReconciliationDetailEnvelope = {
  data: {
  reservation: FinancialReservationSummary;
  summary: FinancialReconciliationRow;
  payments: Array<Record<string, unknown>>;
  method_breakdown: Array<Record<string, unknown>>;
  [key: string]: unknown;
};
  meta?: {
  action?: string;
  branch_id?: (number) | null;
  [key: string]: unknown;
};
};

export type FinancialReconciliationRow = {
  reservation: FinancialReservationSummary;
  payment_summary: Record<string, unknown>;
  reconciliation: Record<string, unknown>;
  flags: Record<string, unknown>;
  [key: string]: unknown;
};

export type FinancialReservationSummary = {
  reservation_id: number;
  reservation_code: string;
  row_version?: (number) | null;
  status: string;
  deposit_status: string;
  start_time?: (string) | null;
  end_time?: (string) | null;
  billed_at?: (string) | null;
  updated_at?: (string) | null;
  bill_currency?: (string) | null;
  customer: Record<string, unknown>;
  [key: string]: unknown;
};

export type GenericDataEnvelope = {
  data: Record<string, unknown>;
};

export type GetV1AdminBenefitsLoyaltyTiersIdPathParams = {
  id: number;
};

export type GetV1AdminBenefitsVouchersIdPathParams = {
  id: number;
};

export type GetV1AdminInventoryIngredientsIdMovementsPathParams = {
  id: number;
};

export type GetV1AdminInventoryIngredientsIdMovementsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  movement_type?: ("StockIn" | "StockOut" | "AdjustmentIncrease" | "AdjustmentDecrease" | "Wastage") | null;
  branch_id?: (number) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("created_at" | "-created_at" | "movement_id" | "-movement_id" | "movement_type" | "-movement_type" | "quantity_delta" | "-quantity_delta") | null;
  sort_by?: ("created_at" | "movement_id" | "movement_type" | "quantity_delta") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminInventoryIngredientsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  is_active?: (boolean) | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("name" | "-name" | "code" | "-code" | "ingredient_id" | "-ingredient_id" | "stock_on_hand_quantity" | "-stock_on_hand_quantity" | "recipe_usage_count" | "-recipe_usage_count" | "updated_at" | "-updated_at") | null;
  sort_by?: ("name" | "code" | "ingredient_id" | "stock_on_hand_quantity" | "recipe_usage_count" | "updated_at") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminInventoryPurchaseOrdersIdReceiptsPathParams = {
  id: number;
};

export type GetV1AdminInventoryPurchaseOrdersIdReceiptsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  supplier_id?: (number) | null;
  branch_id?: (number) | null;
  purchase_order_status?: ("Draft" | "Ordered" | "PartiallyReceived" | "Received" | "Cancelled") | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("created_at" | "-created_at" | "ordered_at" | "-ordered_at" | "expected_at" | "-expected_at" | "purchase_order_id" | "-purchase_order_id" | "purchase_order_status" | "-purchase_order_status" | "supplier_id" | "-supplier_id" | "branch_id" | "-branch_id") | null;
  sort_by?: ("created_at" | "ordered_at" | "expected_at" | "purchase_order_id" | "purchase_order_status" | "supplier_id" | "branch_id") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminInventoryPurchaseOrdersQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  supplier_id?: (number) | null;
  branch_id?: (number) | null;
  purchase_order_status?: ("Draft" | "Ordered" | "PartiallyReceived" | "Received" | "Cancelled") | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("created_at" | "-created_at" | "ordered_at" | "-ordered_at" | "expected_at" | "-expected_at" | "purchase_order_id" | "-purchase_order_id" | "purchase_order_status" | "-purchase_order_status" | "supplier_id" | "-supplier_id" | "branch_id" | "-branch_id") | null;
  sort_by?: ("created_at" | "ordered_at" | "expected_at" | "purchase_order_id" | "purchase_order_status" | "supplier_id" | "branch_id") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminInventorySuppliersQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  is_active?: (boolean) | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("name" | "-name" | "code" | "-code" | "supplier_id" | "-supplier_id" | "updated_at" | "-updated_at") | null;
  sort_by?: ("name" | "code" | "supplier_id" | "updated_at") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminMenuCategoriesExportQueryParams = {
  format: "csv" | "json";
};

export type GetV1AdminMenuCategoriesQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  include_deleted?: boolean;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("sort_order" | "-sort_order" | "name" | "-name" | "category_id" | "-category_id" | "updated_at" | "-updated_at") | null;
  sort_by?: ("sort_order" | "name" | "category_id" | "updated_at") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminMenuItemsExportQueryParams = {
  format: "csv" | "json";
};

export type GetV1AdminMenuItemsItemIdPathParams = {
  item_id: number;
};

export type GetV1AdminMenuItemsItemIdPricesPathParams = {
  item_id: number;
};

export type GetV1AdminMenuItemsItemIdPricesQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  as_of?: (string) | null;
  currency?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("effective_from" | "-effective_from" | "effective_to" | "-effective_to" | "price" | "-price" | "price_id" | "-price_id") | null;
  sort_by?: ("effective_from" | "effective_to" | "price" | "price_id") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminMenuItemsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  category_id?: (number) | null;
  is_available?: (boolean) | null;
  q?: (string) | null;
  as_of?: (string) | null;
  page?: number;
  per_page?: number;
  sort?: ("name" | "-name" | "code" | "-code" | "item_id" | "-item_id" | "category_id" | "-category_id" | "updated_at" | "-updated_at") | null;
  sort_by?: ("name" | "code" | "item_id" | "category_id" | "updated_at") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1AdminMenuPricesExportQueryParams = {
  format: "csv" | "json";
};

export type GetV1AdminMenuPricesPriceIdPathParams = {
  price_id: number;
};

export type GetV1AdminPrivacyCustomersUserIdDataExportPathParams = {
  user_id: number;
};

export type GetV1AdminPrivacyRequestsQueryParams = {
  status?: ("requested" | "rejected" | "completed" | "failed") | null;
  user_id?: (number) | null;
  per_page?: (number) | null;
};

export type GetV1AdminRestaurantTablesExportQueryParams = {
  format: "csv" | "json";
};

export type GetV1AdminRestaurantTablesQueryParams = {
  zone?: (string) | null;
  branch_id?: (number) | null;
  status?: ("Available" | "Reserved" | "Occupied" | "Blocked" | "Maintenance") | null;
  template_id?: (number) | null;
  include_deleted?: (boolean) | null;
  q?: (string) | null;
};

export type GetV1AdminSettingsBranchesQueryParams = {
  is_active?: (boolean) | null;
  q?: (string) | null;
};

export type GetV1MeLoyaltyQueryParams = {
  limit?: number;
};

export type GetV1MePrivacyRequestsQueryParams = {
  status?: ("requested" | "rejected" | "completed" | "failed") | null;
  per_page?: (number) | null;
};

export type GetV1MeVouchersQueryParams = {
  bucket?: "active" | "unused" | "used" | "all";
  q?: (string) | null;
  per_page?: number;
  page?: number;
};

export type GetV1MenuCategoriesQueryParams = {
  service_time?: (string) | null;
  preorder_only?: (boolean) | null;
};

export type GetV1MenuItemsIdPathParams = {
  id: number;
};

export type GetV1MenuItemsIdQueryParams = {
  service_time?: (string) | null;
};

export type GetV1MenuItemsQueryParams = {
  service_time?: (string) | null;
  category_id?: (number) | null;
  preorder_only?: (boolean) | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
};

export type GetV1ReservationsIdBenefitsPreviewPathParams = {
  id: number;
};

export type GetV1ReservationsIdDepositPreviewPathParams = {
  id: number;
};

export type GetV1ReservationsIdPathParams = {
  id: number;
};

export type GetV1ReservationsIdPreorderPathParams = {
  id: number;
};

export type GetV1ReservationsQueryParams = {
  bucket?: "upcoming" | "history" | "all";
  status?: Array<Record<string, never>>;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
  session_id?: string;
};

export type GetV1ReservationsReservationIdActiveOrderPathParams = {
  reservation_id: number;
};

export type GetV1ReservationsReservationIdBillPathParams = {
  reservation_id: number;
};

export type GetV1ReservationsReservationIdBillPaymentSessionsSessionIdPathParams = {
  reservation_id: number;
  session_id: number;
};

export type GetV1ReservationsReservationIdBillPreviewPathParams = {
  reservation_id: number;
};

export type GetV1ReservationsReservationIdDepositPaymentSessionsSessionIdPathParams = {
  reservation_id: number;
  session_id: number;
};

export type GetV1StaffAuditTrailQueryParams = {
  reservation_id?: (number) | null;
  order_id?: (number) | null;
  payment_id?: (number) | null;
  waiting_id?: (number) | null;
  table_id?: (number) | null;
  cashier_shift_id?: (number) | null;
  actor_user_id?: (number) | null;
  branch_id?: (number) | null;
  request_id?: (string) | null;
  q?: (string) | null;
  action?: (string) | null;
  actor_type?: (string) | null;
  subject_type?: (string) | null;
  subject_id?: (string) | null;
  date_from?: (string) | null;
  date_to?: (string) | null;
  per_page?: (number) | null;
  page?: (number) | null;
};

export type GetV1StaffCashierShiftsCurrentQueryParams = {
  branch_id?: (number) | null;
};

export type GetV1StaffCashierShiftsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  status?: ("Open" | "Closed") | null;
  branch_id?: (number) | null;
  shift_code?: (string) | null;
  terminal_code?: (string) | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("opened_at" | "-opened_at" | "closed_at" | "-closed_at" | "cashier_shift_id" | "-cashier_shift_id" | "shift_code" | "-shift_code") | null;
  sort_by?: ("opened_at" | "closed_at" | "cashier_shift_id" | "shift_code") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1StaffCashierShiftsShiftIdPathParams = {
  shift_id: number;
};

export type GetV1StaffConversationsConversationIdPathParams = {
  conversation_id: string;
};

export type GetV1StaffConversationsConversationIdQueryParams = {
  message_limit?: (number) | null;
  event_limit?: (number) | null;
  include_closed_assignments?: (boolean) | null;
};

export type GetV1StaffConversationsQueryParams = {
  status?: ("Open" | "Pending" | "Closed" | "Spam") | null;
  workflow_state?: ("Open" | "Triaged" | "Assigned" | "PendingCustomer" | "Resolved" | "Closed") | null;
  inbox_view?: ("all" | "unassigned" | "overdue" | "waiting_on_customer" | "resolved_today") | null;
  channel?: ("WebChat" | "Facebook" | "Zalo" | "Whatsapp" | "Instagram" | "Line" | "Other") | null;
  assigned_agent_user_id?: (number) | null;
  assignment_state?: ("all" | "assigned" | "unassigned" | "mine") | null;
  branch_id?: (number) | null;
  reservation_id?: (number) | null;
  waiting_list_id?: (number) | null;
  user_id?: (number) | null;
  q?: (string) | null;
  created_from?: (string) | null;
  created_to?: (string) | null;
  sort_by?: ("latest_activity" | "created_at" | "message_count") | null;
  sort_dir?: ("asc" | "desc") | null;
  per_page?: (number) | null;
  page?: (number) | null;
};

export type GetV1StaffFinanceInvoicesReservationIdPathParams = {
  reservation_id: number;
};

export type GetV1StaffFinanceInvoicesReservationIdQueryParams = {
  branch_id?: (number) | null;
};

export type GetV1StaffFinanceReconciliationQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  branch_id?: (number) | null;
  reservation_id?: (number) | null;
  reservation_code?: (string) | null;
  user_id?: (number) | null;
  status?: ("Confirmed" | "Reserved" | "Cancelled" | "Expired" | "Completed" | "NoShow") | null;
  deposit_status?: ("NotRequired" | "Pending" | "Paid" | "Refunded" | "PartiallyRefunded" | "Forfeited") | null;
  payment_currency?: (string) | null;
  cashier_user_id?: (number) | null;
  activity_from?: (string) | null;
  activity_to?: (string) | null;
  has_discrepancy?: (boolean) | null;
  per_page?: (number) | null;
  page?: (number) | null;
  limit?: (number) | null;
  sort?: ("reservation_id" | "-reservation_id" | "start_time" | "-start_time" | "updated_at" | "-updated_at" | "final_bill_amount" | "-final_bill_amount" | "net_paid_amount" | "-net_paid_amount" | "refunded_amount" | "-refunded_amount" | "last_payment_activity_at" | "-last_payment_activity_at") | null;
  sort_by?: ("reservation_id" | "start_time" | "updated_at" | "final_bill_amount" | "net_paid_amount" | "refunded_amount" | "last_payment_activity_at") | null;
  sort_dir?: ("asc" | "desc") | null;
  format?: ("json" | "csv") | null;
};

export type GetV1StaffFinanceReconciliationReservationIdPathParams = {
  reservation_id: number;
};

export type GetV1StaffFinanceReconciliationReservationIdQueryParams = {
  branch_id?: (number) | null;
};

export type GetV1StaffKitchenChangesQueryParams = {
  after_version?: (number) | null;
  limit?: (number) | null;
  branch_id?: (number) | null;
};

export type GetV1StaffKitchenStationsQueryParams = {
  branch_id?: (number) | null;
};

export type GetV1StaffKitchenStationsStationIdTicketsPathParams = {
  station_id: number;
};

export type GetV1StaffKitchenStationsStationIdTicketsQueryParams = {
  branch_id?: (number) | null;
  status?: ("Queued" | "Fired" | "Ready" | "Completed" | "Cancelled") | null;
  include_terminal?: (boolean) | null;
};

export type GetV1StaffMenuItemsQueryParams = {
  service_time?: (string) | null;
  category_id?: (number) | null;
  preorder_only?: (boolean) | null;
  q?: (string) | null;
  page?: (number) | null;
  per_page?: (number) | null;
};

export type GetV1StaffOrdersOrderIdPathParams = {
  order_id: number;
};

export type GetV1StaffOrdersOrderIdSettlementPreviewPathParams = {
  order_id: number;
};

export type GetV1StaffOrdersOrderIdSettlementPreviewQueryParams = {
  currency?: string;
};

export type GetV1StaffReportingDailyInventoryQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  branch_id?: (number) | null;
  ingredient_id?: (number) | null;
  start_date?: (string) | null;
  end_date?: (string) | null;
  per_page?: (number) | null;
  page?: (number) | null;
  sort?: ("business_date" | "-business_date" | "branch_id" | "-branch_id" | "ingredient_id" | "-ingredient_id" | "movement_count" | "-movement_count" | "net_quantity_delta" | "-net_quantity_delta" | "last_movement_at" | "-last_movement_at") | null;
  sort_by?: ("business_date" | "branch_id" | "ingredient_id" | "movement_count" | "net_quantity_delta" | "last_movement_at") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1StaffReportingDailyOperationsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  branch_id?: (number) | null;
  start_date?: (string) | null;
  end_date?: (string) | null;
  per_page?: (number) | null;
  page?: (number) | null;
  sort?: ("business_date" | "-business_date" | "branch_id" | "-branch_id" | "scheduled_reservation_count" | "-scheduled_reservation_count" | "completed_count" | "-completed_count" | "waiting_list_created_count" | "-waiting_list_created_count" | "waiting_list_seated_count" | "-waiting_list_seated_count") | null;
  sort_by?: ("business_date" | "branch_id" | "scheduled_reservation_count" | "completed_count" | "waiting_list_created_count" | "waiting_list_seated_count") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1StaffReportingDailySalesQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  branch_id?: (number) | null;
  currency?: (string) | null;
  start_date?: (string) | null;
  end_date?: (string) | null;
  per_page?: (number) | null;
  page?: (number) | null;
  sort?: ("business_date" | "-business_date" | "branch_id" | "-branch_id" | "currency" | "-currency" | "gross_bill_amount" | "-gross_bill_amount" | "net_paid_amount" | "-net_paid_amount" | "billed_reservation_count" | "-billed_reservation_count") | null;
  sort_by?: ("business_date" | "branch_id" | "currency" | "gross_bill_amount" | "net_paid_amount" | "billed_reservation_count") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1StaffReservationsQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  bucket?: ("upcoming" | "history" | "all" | "today") | null;
  status?: ("Confirmed" | "Reserved" | "Cancelled" | "Expired" | "Completed" | "NoShow") | null;
  reservation_code?: (string) | null;
  source?: (string) | null;
  q?: (string) | null;
  phone?: (string) | null;
  deposit_acknowledged?: (boolean) | null;
  deposit_intent_status?: ("None" | "Submitted" | "Revoked") | null;
  user_id?: (number) | null;
  table_id?: (number) | null;
  start_from?: (string) | null;
  start_to?: (string) | null;
  per_page?: (number) | null;
  page?: (number) | null;
  sort?: ("start_time" | "-start_time" | "end_time" | "-end_time" | "created_at" | "-created_at" | "updated_at" | "-updated_at" | "reservation_id" | "-reservation_id" | "guest_count" | "-guest_count") | null;
  sort_by?: ("start_time" | "end_time" | "created_at" | "updated_at" | "reservation_id" | "guest_count") | null;
  sort_dir?: ("asc" | "desc") | null;
  include_financials?: (boolean) | null;
};

export type GetV1StaffReservationsReservationIdDepositPreviewPathParams = {
  reservation_id: number;
};

export type GetV1StaffReservationsReservationIdLoyaltyPathParams = {
  reservation_id: number;
};

export type GetV1StaffReservationsReservationIdOrdersPathParams = {
  reservation_id: number;
};

export type GetV1StaffReservationsReservationIdPathParams = {
  reservation_id: number;
};

export type GetV1StaffReservationsReservationIdRefundPreviewPathParams = {
  reservation_id: number;
};

export type GetV1StaffReservationsReservationIdRefundPreviewQueryParams = {
  refund_scope?: ("deposit" | "final" | "all") | null;
  refund_amount?: (number) | null;
  currency?: (string) | null;
  cancel_after_payment?: (boolean) | null;
};

export type GetV1StaffReservationsReservationIdVouchersPathParams = {
  reservation_id: number;
};

export type GetV1StaffTablesBoardChangesQueryParams = {
  after_version?: (number) | null;
  limit?: (number) | null;
  branch_id?: (number) | null;
};

export type GetV1StaffTablesTableIdActiveOrderPathParams = {
  table_id: number;
};

export type GetV1StaffUsersUserIdLoyaltyPathParams = {
  user_id: number;
};

export type GetV1StaffWaitingListChangesQueryParams = {
  after_version?: (number) | null;
  limit?: (number) | null;
  branch_id?: (number) | null;
};

export type GetV1StaffWaitingListQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  status?: ("Waiting" | "Notified" | "Seated" | "Cancelled") | null;
  active_only?: (boolean) | null;
  phone?: (string) | null;
  guest_name?: (string) | null;
  branch_id?: (number) | null;
  page?: (number) | null;
  per_page?: (number) | null;
  sort?: ("priority" | "-priority" | "requested_at" | "-requested_at" | "notified_at" | "-notified_at" | "guest_name" | "-guest_name" | "guest_count" | "-guest_count" | "waiting_id" | "-waiting_id") | null;
  sort_by?: ("priority" | "requested_at" | "notified_at" | "guest_name" | "guest_count" | "waiting_id") | null;
  sort_dir?: ("asc" | "desc") | null;
};

export type GetV1TableHoldsHoldIdPathParams = {
  hold_id: string;
};

export type GetV1TablesAvailableQueryParams = {
  from: string;
  to: string;
  branch_id?: (number) | null;
  zone?: (string) | null;
  template_id?: (number) | null;
  min_seats?: (number) | null;
  guest_count?: (number) | null;
  session_id?: (string) | null;
  suggest?: (boolean) | null;
  max_suggestions?: (number) | null;
};

export type GetV1WaitingListIdPathParams = {
  id: number;
};

export type GetV1WaitingListQueryParams = {
  status?: ("Waiting" | "Notified" | "Seated" | "Cancelled") | null;
  active_only?: (boolean) | null;
};

export type GetV1staffreservationsidpreorderPathParams = {
  id: number;
};

export type HealthDetailedEnvelope = {
  status: "ok" | "degraded" | "fail";
  checks: Record<string, unknown>;
  meta: Record<string, unknown>;
};

export type HealthRedisEnvelope = {
  status: "ok" | "degraded" | "fail";
  checks: Record<string, unknown>;
  meta: Record<string, unknown>;
};

export type HealthStatusEnvelope = {
  status: "ok" | "degraded" | "fail";
  service: string;
  timestamp_utc: string;
};

export type InviteWaitlistCustomerRequest = {
  table_id: number;
  hold_minutes?: (number) | null;
  row_version: number;
};

export type JoinWaitlistRequest = {
  branch_id?: (number) | null;
  guest_count: number;
  guest_name?: (string) | null;
  phone?: (string) | null;
  notes?: (string) | null;
};

export type KitchenOrderItemTicket = {
  ticket_id: number;
  row_version: (number) | null;
  ticket_status: string;
  route_source: (string) | null;
  dispatch_count: number;
  recall_count: number;
  output_mode: string;
  printer_target: (string) | null;
  ticket_notes: (string) | null;
  order: {
  order_id: number;
  reservation_id: number;
};
  station: ({
  station_id: number;
  code: string;
  name: string;
}) | null;
  route: ({
  route_id: number;
  category_id: number;
  sort_order: number;
  is_active: boolean;
}) | null;
  routing: {
  route_present: boolean;
  route_active: (boolean) | null;
  station_matches_route: (boolean) | null;
};
  order_item: ({
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  row_version: (number) | null;
  notes: (string) | null;
  item_name_snapshot: (string) | null;
}) | null;
  item: ({
  item_id: number;
  name: string;
  category_id: (number) | null;
  category_name: (string) | null;
}) | null;
  lifecycle: {
  status: string;
  state_reason: string;
  is_terminal: boolean;
  allowed_actions: Array<string>;
};
  reconciliation: {
  sync_status: string;
  routing_status: string;
  order_item_expected_status: (string) | null;
  order_item_matches_ticket: (boolean) | null;
  station_active: (boolean) | null;
  drift_reasons: Array<string>;
  next_actions: Array<string>;
};
  first_dispatched_at: (string) | null;
  fired_at: (string) | null;
  ready_at: (string) | null;
  completed_at: (string) | null;
  cancelled_at: (string) | null;
  last_recalled_at: (string) | null;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type KitchenStation = {
  station_id: number;
  branch_id: number;
  code: string;
  name: string;
  description: (string) | null;
  output_mode: string;
  printer_target: (string) | null;
  is_active: boolean;
  route_count: number;
  ticket_counts: {
  queued: number;
  fired: number;
  ready: number;
};
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type LinkConversationRequest = {
  reservation_id?: (number) | null;
  waiting_list_id?: (number) | null;
  customer_user_id?: (number) | null;
  notes?: (string) | null;
};

export type ListingQueryContract = {
  parameters: {
  filter: string;
  sort: string;
  page: (string) | null;
  per_page: (string) | null;
};
  filter_keys: Array<string>;
  sort_fields: Array<string>;
  default_sort: (string) | null;
  pagination: {
  supported: boolean;
  max_per_page: (number) | null;
};
  legacy_aliases: Record<string, string>;
};

export type LoyaltyPointTransaction = {
  txn_id: (number) | null;
  user_id: (number) | null;
  reservation_id: (number) | null;
  txn_type: (string) | null;
  points: number;
  amount_basis: (string) | null;
  currency: string;
  reason: (string) | null;
  created_at: (string) | null;
  created_by: (number) | null;
};

export type MoveTableRequest = {
  from_table_id: number;
  to_table_id: number;
  moved_at?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type OpenCashierShiftRequest = {
  opening_float_amount?: (number) | null;
  branch_id?: (number) | null;
  currency?: (string) | null;
  terminal_code?: (string) | null;
  notes?: (string) | null;
  staff_user_id?: (number) | null;
};

export type PatchV1AdminBenefitsLoyaltyTiersIdPathParams = {
  id: number;
};

export type PatchV1AdminBenefitsVouchersIdPathParams = {
  id: number;
};

export type PatchV1StaffOrdersOrderIdItemsOrderItemIdPathParams = {
  order_id: number;
  order_item_id: number;
};

export type PatchV1TableHoldsHoldIdRefreshPathParams = {
  hold_id: string;
};

export type PayOrderRequest = {
  payment_method: string;
  payment_provider?: ("MoMo" | "VNPay" | "Cash" | "Card" | "BankTransfer" | "Other") | null;
  paid_amount: number;
  currency?: (string) | null;
  transaction_code?: (string) | null;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type PayReservationDepositRequest = {
  payment_method: string;
  payment_provider?: ("MoMo" | "VNPay" | "Cash" | "Card" | "BankTransfer" | "Other") | null;
  amount: number;
  currency?: (string) | null;
  transaction_code?: (string) | null;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type PostV1AdminInventoryIngredientsIdMovementsPathParams = {
  id: number;
};

export type PostV1AdminInventoryPurchaseOrdersIdReceiptsPathParams = {
  id: number;
};

export type PostV1AdminMenuItemsItemIdPricesPathParams = {
  item_id: number;
};

export type PostV1AdminPrivacyRequestsRequestIdReviewPathParams = {
  request_id: number;
};

export type PostV1PaymentsProvidersProviderCodeWebhooksBody = {
  payment_scope?: "deposit" | "bill";
  provider_session_code: string;
  provider_event_code: string;
  event_type?: string;
  session_status: string;
  provider_payment_code?: (string) | null;
  occurred_at?: string;
  [key: string]: unknown;
};

export type PostV1PaymentsProvidersProviderCodeWebhooksPathParams = {
  provider_code: number;
};

export type PostV1ReservationsIdCancelPathParams = {
  id: number;
};

export type PostV1ReservationsIdDepositAcknowledgePathParams = {
  id: number;
};

export type PostV1ReservationsIdDepositIntentPathParams = {
  id: number;
};

export type PostV1ReservationsIdDepositIntentRevokePathParams = {
  id: number;
};

export type PostV1ReservationsIdLoyaltyRedeemPathParams = {
  id: number;
};

export type PostV1ReservationsIdLoyaltyRedeemReleasePathParams = {
  id: number;
};

export type PostV1ReservationsIdPreorderPreviewPathParams = {
  id: number;
};

export type PostV1ReservationsIdReschedulePathParams = {
  id: number;
};

export type PostV1ReservationsIdVoucherApplyPathParams = {
  id: number;
};

export type PostV1ReservationsIdVoucherRemovePathParams = {
  id: number;
};

export type PostV1ReservationsReservationIdBillPaymentSessionsPathParams = {
  reservation_id: number;
};

export type PostV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirmPathParams = {
  reservation_id: number;
  session_id: number;
};

export type PostV1ReservationsReservationIdBillPaymentSessionsSessionIdRefreshPathParams = {
  reservation_id: number;
  session_id: number;
};

export type PostV1ReservationsReservationIdDepositPaymentSessionsPathParams = {
  reservation_id: number;
};

export type PostV1ReservationsReservationIdDepositPaymentSessionsSessionIdConfirmPathParams = {
  reservation_id: number;
  session_id: number;
};

export type PostV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefreshPathParams = {
  reservation_id: number;
  session_id: number;
};

export type PostV1StaffCashierShiftsShiftIdClosePathParams = {
  shift_id: number;
};

export type PostV1StaffConversationsConversationIdAssignPathParams = {
  conversation_id: string;
};

export type PostV1StaffConversationsConversationIdInternalNotesPathParams = {
  conversation_id: string;
};

export type PostV1StaffConversationsConversationIdLinksPathParams = {
  conversation_id: string;
};

export type PostV1StaffConversationsConversationIdOutboundRepliesPathParams = {
  conversation_id: string;
};

export type PostV1StaffConversationsConversationIdTakeOverPathParams = {
  conversation_id: string;
};

export type PostV1StaffConversationsConversationIdUnassignPathParams = {
  conversation_id: string;
};

export type PostV1StaffFinanceInvoicesReservationIdIssuePathParams = {
  reservation_id: number;
};

export type PostV1StaffKitchenTicketsTicketIdBumpPathParams = {
  ticket_id: number;
};

export type PostV1StaffKitchenTicketsTicketIdFirePathParams = {
  ticket_id: number;
};

export type PostV1StaffKitchenTicketsTicketIdRecallPathParams = {
  ticket_id: number;
};

export type PostV1StaffOrdersOrderIdBillSnapshotPathParams = {
  order_id: number;
};

export type PostV1StaffOrdersOrderIdItemsOrderItemIdStatusPathParams = {
  order_id: number;
  order_item_id: number;
};

export type PostV1StaffOrdersOrderIdItemsPathParams = {
  order_id: number;
};

export type PostV1StaffOrdersOrderIdKitchenDispatchPathParams = {
  order_id: number;
};

export type PostV1StaffOrdersOrderIdPayPathParams = {
  order_id: number;
};

export type PostV1StaffOrdersOrderIdSettlementFinalizePathParams = {
  order_id: number;
};

export type PostV1StaffReservationsIdAssignBestFitPathParams = {
  id: number;
};

export type PostV1StaffReservationsIdAssignTablePathParams = {
  id: number;
};

export type PostV1StaffReservationsIdCheckInPathParams = {
  id: number;
};

export type PostV1StaffReservationsIdMoveTablePathParams = {
  id: number;
};

export type PostV1StaffReservationsReservationIdDepositPayPathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdLoyaltyRedeemPathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdLoyaltyRedeemReleasePathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdLoyaltyReleasePathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdRefundCancelPathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdRefundPathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdVoucherApplyPathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdVoucherReleasePathParams = {
  reservation_id: number;
};

export type PostV1StaffReservationsReservationIdVoucherRemovePathParams = {
  reservation_id: number;
};

export type PostV1StaffTablesTableIdOrdersPathParams = {
  table_id: number;
};

export type PostV1StaffTablesTableIdReleasePathParams = {
  table_id: number;
};

export type PostV1StaffUsersUserIdLoyaltyAdjustPathParams = {
  user_id: number;
};

export type PostV1StaffWaitingListIdAdvancePathParams = {
  id: number;
};

export type PostV1StaffWaitingListIdCancelPathParams = {
  id: number;
};

export type PostV1StaffWaitingListIdNotifyPathParams = {
  id: number;
};

export type PostV1StaffWaitingListIdSeatPathParams = {
  id: number;
};

export type PostV1WaitingListIdAcceptPathParams = {
  id: number;
};

export type PostV1WaitingListIdCancelPathParams = {
  id: number;
};

export type PostV1WaitingListIdConfirmArrivalPathParams = {
  id: number;
};

export type PostV1WaitingListIdDeclinePathParams = {
  id: number;
};

export type PostV1reservationsidpreordersubmitPathParams = {
  id: number;
};

export type PostV1staffconversationsconversationIdworkflowStatePathParams = {
  conversation_id: string;
};

export type PostV1staffreservationsidpreorderconfirmPathParams = {
  id: number;
};

export type PostV1staffreservationsidpreorderconvertPathParams = {
  id: number;
};

export type PostV1staffreservationsidpreorderrejectPathParams = {
  id: number;
};

export type PreviewCustomerReservationPreorderRequest = {
  pre_order_items: Array<{
  item_id: number;
  quantity: number;
}>;
};

export type PreviewMenuPreorderRequest = {
  start_time: string;
  pre_order_items: Array<{
  item_id: number;
  quantity: number;
}>;
};

export type PutV1ReservationsIdPreorderPathParams = {
  id: number;
};

export type RefreshSessionRequest = Record<string, never>;

export type RefreshTableHoldRequest = {
  session_id?: (string) | null;
  extend_minutes?: (number) | null;
  row_version?: (number) | null;
};

export type RefundAndCancelReservationRequest = {
  payment_method: string;
  payment_provider?: ("MoMo" | "VNPay" | "Cash" | "Card" | "BankTransfer" | "Other") | null;
  refund_scope?: ("deposit" | "final" | "all") | null;
  refund_amount?: (number) | null;
  currency?: (string) | null;
  transaction_code?: (string) | null;
  notes?: (string) | null;
  reason?: (string) | null;
  cancel_reason?: (string) | null;
  row_version: number;
};

export type RefundReservationRequest = {
  payment_method: string;
  payment_provider?: ("MoMo" | "VNPay" | "Cash" | "Card" | "BankTransfer" | "Other") | null;
  refund_scope?: ("deposit" | "final" | "all") | null;
  refund_amount?: (number) | null;
  currency?: (string) | null;
  transaction_code?: (string) | null;
  notes?: (string) | null;
  reason?: (string) | null;
  row_version: number;
};

export type RegisterRequest = {
  full_name: string;
  email?: (string) | null;
  phone?: (string) | null;
  password: string;
  password_confirmation: string;
  session_id?: (string) | null;
  session_label?: (string) | null;
  device_id?: (string) | null;
};

export type ReleaseTableRequest = {
  force?: (boolean) | null;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type ReplaceCustomerReservationPreorderRequest = {
  pre_order_items: Array<{
  item_id: number;
  quantity: number;
}>;
  row_version: number;
  pre_order_row_version?: (number) | null;
};

export type ReportingDailyInventoryMovementSnapshot = {
  snapshot_id: number;
  business_date: (string) | null;
  branch_id: number;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
};
  ingredient_id: number;
  ingredient?: {
  ingredient_id: number;
  code: string;
  name: string;
  unit_code: string;
  is_active: boolean;
};
  unit_code: string;
  movement_summary: {
  movement_count: number;
  purchase_receipt_movement_count: number;
  stock_in_quantity: number;
  stock_out_quantity: number;
  adjustment_increase_quantity: number;
  adjustment_decrease_quantity: number;
  wastage_quantity: number;
  net_quantity_delta: number;
  last_movement_at: (string) | null;
};
  freshness: {
  refreshed_at: (string) | null;
};
};

export type ReportingDailyOperationSnapshot = {
  snapshot_id: number;
  business_date: (string) | null;
  branch_id: number;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
};
  reservations: {
  scheduled_count: number;
  scheduled_guest_count: number;
  scheduled_minutes_total: number;
  checked_in_count: number;
  completed_count: number;
  cancelled_count: number;
  no_show_count: number;
};
  turn_time: {
  turn_count: number;
  turn_minutes_total: number;
  avg_turn_minutes: (number) | null;
};
  waiting_list: {
  created_count: number;
  notified_count: number;
  seated_count: number;
  cancelled_count: number;
  confirmed_arrival_count: number;
  seated_conversion_rate: (number) | null;
  arrival_confirmation_rate: (number) | null;
};
  freshness: {
  refreshed_at: (string) | null;
};
};

export type ReportingDailySalesSnapshot = {
  snapshot_id: number;
  business_date: (string) | null;
  currency: string;
  branch_id: number;
  branch?: {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
};
  billed: {
  reservation_count: number;
  guest_count: number;
  gross_bill_amount: number;
  discount_amount: number;
  billed_total_amount: number;
};
  invoices: {
  issued_count: number;
  issued_total_amount: number;
  tax_amount: number;
};
  payments: {
  payment_row_count: number;
  refund_row_count: number;
  captured_amount: number;
  refunded_amount: number;
  net_paid_amount: number;
  deposit_net_amount: number;
  final_net_amount: number;
};
  cashier: {
  closed_shift_count: number;
  cash_discrepancy_amount: number;
};
  freshness: {
  refreshed_at: (string) | null;
};
};

export type ReservationActionEnvelope = {
  data: {
  reservation_id: number;
  reservation_code: string;
  access_scope?: string;
  booking_time?: (string) | null;
  reserved_at?: (string) | null;
  start_time?: (string) | null;
  end_time?: (string) | null;
  guest_count?: number;
  status: string;
  deposit_status?: (string) | null;
  deposit_required_amount?: (string) | null;
  deposit_paid_amount?: (string) | null;
  final_bill_amount?: (string) | null;
  bill_currency?: (string) | null;
  row_version: number;
  status_flags?: Record<string, unknown>;
  customer_self_service?: Record<string, unknown>;
  table_ids?: Array<number>;
  table_summary?: Record<string, unknown>;
  user?: (ReservationCustomerSummary) | null;
  guest?: (ReservationGuestSnapshot) | null;
  payments?: (Array<Record<string, unknown>>) | null;
  payment_summary?: (Record<string, unknown>) | null;
  deposit_summary?: (Record<string, unknown>) | null;
  [key: string]: unknown;
};
  meta?: {
  action: string;
  access_scope: string;
};
};

export type ReservationCustomerSummary = {
  user_id: (number) | null;
  full_name: (string) | null;
  email: (string) | null;
  phone: (string) | null;
  current_points: (number) | null;
  current_tier: (CustomerLoyaltyTier) | null;
};

export type ReservationEnvelope = {
  data: {
  reservation_id: number;
  reservation_code: string;
  access_scope?: string;
  booking_time?: (string) | null;
  reserved_at?: (string) | null;
  start_time?: (string) | null;
  end_time?: (string) | null;
  guest_count?: number;
  status: string;
  deposit_status?: (string) | null;
  deposit_required_amount?: (string) | null;
  deposit_paid_amount?: (string) | null;
  final_bill_amount?: (string) | null;
  bill_currency?: (string) | null;
  row_version: number;
  status_flags?: Record<string, unknown>;
  customer_self_service?: Record<string, unknown>;
  table_ids?: Array<number>;
  table_summary?: Record<string, unknown>;
  user?: (ReservationCustomerSummary) | null;
  guest?: (ReservationGuestSnapshot) | null;
  payments?: (Array<Record<string, unknown>>) | null;
  payment_summary?: (Record<string, unknown>) | null;
  deposit_summary?: (Record<string, unknown>) | null;
  [key: string]: unknown;
};
};

export type ReservationGuestSnapshot = {
  full_name: (string) | null;
  phone: (string) | null;
  email: (string) | null;
  is_snapshot_only: boolean;
};

export type ReservationOrder = {
  order_id: number;
  reservation_id: number;
  order_type: string;
  status: string;
  row_version?: (number) | null;
  created_at?: (string) | null;
  created_by?: (number) | null;
  updated_by?: (number) | null;
  notes?: (string) | null;
  workflow?: {
  settlement_scope: string;
  canonical_bill_snapshot_action: string;
  legacy_bill_snapshot_action: string;
};
  payment_status?: (string) | null;
  items?: (Array<{
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  row_version: (number) | null;
  item_name_snapshot: (string) | null;
  unit_price: string;
  currency: string;
  line_total: string;
  notes: (string) | null;
  item: ({
  name: (string) | null;
  code: (string) | null;
}) | null;
}>) | null;
  totals?: {
  subtotal: (string) | null;
  discount: (string) | null;
  total_due: (string) | null;
  paid: (string) | null;
  deposit_applied: (string) | null;
  deposit_net: (string) | null;
  final_paid: (string) | null;
  outstanding: (string) | null;
  currency: (string) | null;
};
};

export type ReservationSelfServiceCollectionEnvelope = {
  data: Array<{
  reservation_id: number;
  reservation_code: string;
  access_scope?: string;
  booking_time?: (string) | null;
  reserved_at?: (string) | null;
  start_time?: (string) | null;
  end_time?: (string) | null;
  guest_count?: number;
  status: string;
  deposit_status?: (string) | null;
  deposit_required_amount?: (string) | null;
  deposit_paid_amount?: (string) | null;
  final_bill_amount?: (string) | null;
  bill_currency?: (string) | null;
  row_version: number;
  status_flags?: Record<string, unknown>;
  customer_self_service?: Record<string, unknown>;
  table_ids?: Array<number>;
  table_summary?: Record<string, unknown>;
  user?: (ReservationCustomerSummary) | null;
  guest?: (ReservationGuestSnapshot) | null;
  payments?: (Array<Record<string, unknown>>) | null;
  payment_summary?: (Record<string, unknown>) | null;
  deposit_summary?: (Record<string, unknown>) | null;
  [key: string]: unknown;
}>;
  meta?: {
  access_scope: string;
  pagination: {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  count: number;
  has_more_pages: boolean;
};
};
};

export type ReservationSummary = {
  reservation_id: number;
  reservation_code: string;
  access_scope?: string;
  booking_time?: (string) | null;
  reserved_at?: (string) | null;
  start_time?: (string) | null;
  end_time?: (string) | null;
  guest_count?: number;
  status: string;
  deposit_status?: (string) | null;
  deposit_required_amount?: (string) | null;
  deposit_paid_amount?: (string) | null;
  final_bill_amount?: (string) | null;
  bill_currency?: (string) | null;
  row_version: number;
  status_flags?: Record<string, unknown>;
  customer_self_service?: Record<string, unknown>;
  table_ids?: Array<number>;
  table_summary?: Record<string, unknown>;
  user?: (ReservationCustomerSummary) | null;
  guest?: (ReservationGuestSnapshot) | null;
  payments?: (Array<Record<string, unknown>>) | null;
  payment_summary?: (Record<string, unknown>) | null;
  deposit_summary?: (Record<string, unknown>) | null;
  [key: string]: unknown;
};

export type RestaurantProfile = {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  timezone: string;
  business_hours: Array<{
  day_of_week: number;
  periods: Array<{
  start_time: string;
  end_time: string;
}>;
}>;
  today_hours: {
  day_of_week: number;
  periods: Array<{
  start_time: string;
  end_time: string;
}>;
  is_closed: boolean;
};
  current_status: {
  is_open: boolean;
  reason: (string) | null;
  checked_at_local: string;
  timezone: string;
};
};

export type RestaurantProfileEnvelope = {
  data: RestaurantProfile;
  meta?: {
  action: string;
};
};

export type RestaurantTable = {
  table_id: number;
  branch_id: (number) | null;
  table_code: (string) | null;
  template_id: (number) | null;
  seats: (number) | null;
  zone: (string) | null;
  pos_x: (number) | null;
  pos_y: (number) | null;
  status: string;
  description: (string) | null;
  price: (string) | null;
  row_version: (number) | null;
  pivot: {
  reservation_id: (number) | null;
  table_id: (number) | null;
  reservation_table_id: (number) | null;
} | null;
  created_at: (string) | null;
  updated_at: (string) | null;
};

export type RestaurantTableEnvelope = {
  data: RestaurantTable;
};

export type ReviewPrivacyRequestRequest = {
  decision: "approve" | "reject";
  mode: "dry_run" | "commit";
  notes?: (string) | null;
};

export type RevokeCustomerReservationDepositIntentRequest = {
  row_version: number;
  session_id?: (string) | null;
};

export type SeatWaitlistRequest = {
  user_id?: (number) | null;
  checked_in_at?: (string) | null;
  service_minutes?: (number) | null;
  notes?: (string) | null;
  row_version: number;
};

export type SendConversationOutboundReplyRequest = {
  message_text: string;
  related_reservation_id?: (number) | null;
  related_order_id?: (number) | null;
};

export type StaffApplyReservationVoucherRequest = {
  user_voucher_id?: (number) | null;
  voucher_code?: (string) | null;
  row_version: number;
};

export type StaffAssignBestFitTableRequest = {
  row_version: number;
  board_from?: (string) | null;
  board_to?: (string) | null;
  zone?: (string) | null;
  staff_user_id?: (number) | null;
};

export type StaffAssignSuggestedTableRequest = {
  table_id: number;
  row_version: number;
  board_from?: (string) | null;
  board_to?: (string) | null;
  zone?: (string) | null;
  staff_user_id?: (number) | null;
};

export type StaffAuditTrailActor = {
  user_id: (number) | null;
  type: (string) | null;
  key: (string) | null;
  user: StaffAuditTrailActorUser | null;
};

export type StaffAuditTrailActorUser = {
  user_id: number;
  full_name: string;
};

export type StaffAuditTrailCollectionMeta = {
  action: string;
  page: number;
  filters: Record<string, unknown>;
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
};

export type StaffAuditTrailEntry = {
  audit_id: number;
  action: string;
  occurred_at: (string) | null;
  primary_subject: StaffAuditTrailPrimarySubject;
  subjects: Array<StaffAuditTrailSubject>;
  actor: StaffAuditTrailActor;
  request: StaffAuditTrailRequest;
  before: (Record<string, unknown>) | null;
  after: (Record<string, unknown>) | null;
  summary: (Record<string, unknown>) | null;
  meta: (Record<string, unknown>) | null;
};

export type StaffAuditTrailEnvelope = {
  data: Array<StaffAuditTrailEntry>;
  meta?: StaffAuditTrailCollectionMeta;
};

export type StaffAuditTrailPrimarySubject = {
  type: string;
  id: string;
};

export type StaffAuditTrailRequest = {
  request_id: (string) | null;
  branch_id: (number) | null;
  ip: (string) | null;
  user_agent: (string) | null;
  method: (string) | null;
  path: (string) | null;
};

export type StaffAuditTrailSubject = {
  type: string;
  id: string;
  role: (string) | null;
};

export type StaffAuthSessionEnvelope = {
  data: {
  auth_mode: "staff_api_key" | "staff_browser_session";
  session_transport?: ("refresh_cookie") | null;
  token_type: "opaque";
  auth_header: string;
  access_token?: (string) | null;
  staff_api_key_id: number;
  expires_at_utc?: (string) | null;
  user?: (StaffAuthUser) | null;
  capabilities: Array<string>;
  known_capabilities: Array<string>;
  capability_source: string;
  startup: StaffStartupContext;
};
};

export type StaffAuthUser = {
  user_id: number;
  username: string;
  full_name: string;
  email: (string) | null;
  phone: (string) | null;
  role_id: (number) | null;
  role_name: (string) | null;
};

export type StaffBranchAccessContext = {
  accessible_branch_ids: Array<number>;
  default_branch_id: (number) | null;
  current_branch_id: (number) | null;
  has_default_branch_access: boolean;
  has_multi_branch_access: boolean;
  branch_selector_enabled: boolean;
  access_source: string;
  branches_uri: string;
};

export type StaffCheckInReservationRequest = {
  table_ids?: (Array<number>) | null;
  checked_in_at?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type StaffCheckoutOrderRequest = {
  payment_method: string;
  payment_provider?: ("MoMo" | "VNPay" | "Cash" | "Card" | "BankTransfer" | "Other") | null;
  discount_amount?: (number) | null;
  paid_amount: number;
  currency?: (string) | null;
  transaction_code?: (string) | null;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type StaffCheckoutSettlementEnvelope = {
  data: {
  order_id: number;
  reservation_id: number;
  row_version: number;
  total_amount: number;
  currency: string;
  paid_amount: number;
  deposit_applied_amount: number;
  final_paid_amount: number;
  outstanding_amount: number;
  payment_status: string;
  status: string;
  order_status: string;
  reservation_status: (string) | null;
};
};

export type StaffCloseOrderRequest = {
  discount_amount?: (number) | null;
  notes?: (string) | null;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type StaffConversationAiAssist = {
  status: "ready" | "disabled" | "unavailable";
  feature_key: string;
  provider?: (string) | null;
  model?: (string) | null;
  priority?: ("high" | "normal" | "low") | null;
  summary?: (string) | null;
  suggested_actions: Array<StaffConversationAiAssistAction>;
  risk_flags: Array<StaffConversationAiAssistRiskFlag>;
  fallback_reason_code?: (string) | null;
  fallback_reason?: (string) | null;
  disclaimer: string;
  latency_budget_ms?: (number) | null;
  cost_tier?: (string) | null;
  generated_from: {
  message_count: number;
  customer_message_count: number;
  internal_note_count: number;
  analysis_count: number;
};
};

export type StaffConversationAiAssistAction = {
  code: string;
  label: string;
  reason?: (string) | null;
};

export type StaffConversationAiAssistRiskFlag = {
  code: string;
  label: string;
  severity: "low" | "medium" | "high";
};

export type StaffConversationAnalysis = {
  analysis_id: number;
  conversation_id: string;
  analyzer_name?: (string) | null;
  is_spam: boolean;
  quality_score?: (string) | null;
  extracted_info?: (Record<string, unknown>) | null;
  created_at?: (string) | null;
};

export type StaffConversationAssignment = {
  assignment_id: number;
  conversation_id: string;
  agent_user_id: number;
  agent?: (Record<string, unknown>) | null;
  assigned_at?: (string) | null;
  released_at?: (string) | null;
  is_active: boolean;
  notes?: (string) | null;
};

export type StaffConversationCollectionEnvelope = {
  data: Array<{
  conversation_id: string;
  branch_id: number;
  branch?: (Record<string, unknown>) | null;
  status: string;
  workflow: {
  state: string;
  state_reason: (string) | null;
  state_changed_at: (string) | null;
  first_triaged_at: (string) | null;
  resolved_at: (string) | null;
  closed_at: (string) | null;
  is_terminal: boolean;
  allowed_actions: Array<string>;
};
  channel: string;
  intent_detected?: (string) | null;
  customer_session_id?: (string) | null;
  session_id?: (string) | null;
  created_at?: (string) | null;
  closed_at?: (string) | null;
  latest_activity_at?: (string) | null;
  user?: (Record<string, unknown>) | null;
  linked_reservation?: (Record<string, unknown>) | null;
  linked_waiting_list?: (Record<string, unknown>) | null;
  active_assignment?: StaffConversationAssignment | null;
  latest_message?: StaffConversationMessage | null;
  latest_analysis?: StaffConversationAnalysis | null;
  counts: {
  messages: number;
  internal_notes: number;
  events: number;
  analyses: number;
};
  assignment_state: {
  is_assigned: boolean;
  is_unassigned: boolean;
  is_mine: boolean;
};
  operational: {
  is_overdue: boolean;
  overdue_after_minutes: number;
  queue_bucket: string;
};
}>;
  meta?: {
  action: string;
  filters: Record<string, unknown>;
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
  summary: Record<string, unknown>;
};
};

export type StaffConversationDetailEnvelope = {
  data: {
  conversation: StaffConversationSummary;
  messages: Array<StaffConversationMessage>;
  events: Array<StaffConversationEvent>;
  analyses: Array<StaffConversationAnalysis>;
  ai_assist: StaffConversationAiAssist;
  assignment_history: Array<StaffConversationAssignment>;
  capabilities: Record<string, unknown>;
};
  meta?: {
  message_limit: number;
  event_limit: number;
  include_closed_assignments: boolean;
  returned_counts: Record<string, unknown>;
};
};

export type StaffConversationEvent = {
  event_id: number;
  conversation_id: string;
  event_type: string;
  event_by_user_id?: (number) | null;
  by_user?: (Record<string, unknown>) | null;
  event_data?: (Record<string, unknown>) | null;
  created_at?: (string) | null;
};

export type StaffConversationMessage = {
  message_id: number;
  conversation_id: string;
  sender: string;
  sender_id?: (number) | null;
  sender_user?: (Record<string, unknown>) | null;
  message_text: string;
  message_type: string;
  is_internal_note: boolean;
  attachment_url?: (string) | null;
  attachment?: ({
  file_id: (number) | null;
  message_id: number;
  access_url: string;
  access_expires_at: string;
  mime_type: (string) | null;
}) | null;
  is_processed?: boolean;
  processing_status?: (string) | null;
  confidence?: (string) | null;
  related_reservation_id?: (number) | null;
  related_order_id?: (number) | null;
  created_at?: (string) | null;
  files?: Array<{
  file_id: number;
  file_url: string;
  access_expires_at: string;
  mime_type?: (string) | null;
  created_at?: (string) | null;
}>;
  entities?: Array<{
  message_entity_id: number;
  entity_type: string;
  entity_text: string;
  entity_normalized?: (string) | null;
  extra_json?: (Record<string, unknown>) | null;
  created_at?: (string) | null;
}>;
};

export type StaffConversationMutationEnvelope = {
  data: {
  action: string;
  conversation: StaffConversationSummary;
  assignment?: StaffConversationAssignment | null;
  event?: StaffConversationEvent | null;
  message?: StaffConversationMessage | null;
};
  meta?: {
  conversation_id: string;
};
};

export type StaffConversationSummary = {
  conversation_id: string;
  branch_id: number;
  branch?: (Record<string, unknown>) | null;
  status: string;
  workflow: {
  state: string;
  state_reason: (string) | null;
  state_changed_at: (string) | null;
  first_triaged_at: (string) | null;
  resolved_at: (string) | null;
  closed_at: (string) | null;
  is_terminal: boolean;
  allowed_actions: Array<string>;
};
  channel: string;
  intent_detected?: (string) | null;
  customer_session_id?: (string) | null;
  session_id?: (string) | null;
  created_at?: (string) | null;
  closed_at?: (string) | null;
  latest_activity_at?: (string) | null;
  user?: (Record<string, unknown>) | null;
  linked_reservation?: (Record<string, unknown>) | null;
  linked_waiting_list?: (Record<string, unknown>) | null;
  active_assignment?: StaffConversationAssignment | null;
  latest_message?: StaffConversationMessage | null;
  latest_analysis?: StaffConversationAnalysis | null;
  counts: {
  messages: number;
  internal_notes: number;
  events: number;
  analyses: number;
};
  assignment_state: {
  is_assigned: boolean;
  is_unassigned: boolean;
  is_mine: boolean;
};
  operational: {
  is_overdue: boolean;
  overdue_after_minutes: number;
  queue_bucket: string;
};
};

export type StaffKitchenDispatchEnvelope = {
  data: Array<KitchenOrderItemTicket>;
  meta?: {
  action: string;
  created_count: number;
  reused_count: number;
  unrouted_count: number;
  pinned_route_count: number;
};
};

export type StaffKitchenStationCollectionEnvelope = {
  data: Array<KitchenStation>;
  meta?: {
  count: number;
  branch_id: (number) | null;
  branch_scope: {
  requested_branch_id: (number) | null;
  accessible_branch_ids: Array<number>;
  uses_explicit_entitlement: boolean;
};
  realtime: StaffOperationalRealtimeDescriptor;
};
};

export type StaffKitchenTicketActionRequest = {
  row_version: number;
};

export type StaffKitchenTicketCollectionEnvelope = {
  data: Array<KitchenOrderItemTicket>;
  meta?: {
  station_id: number;
  branch_id: (number) | null;
  count: number;
  branch_scope: {
  requested_branch_id: (number) | null;
  accessible_branch_ids: Array<number>;
  uses_explicit_entitlement: boolean;
};
};
};

export type StaffKitchenTicketEnvelope = {
  data: KitchenOrderItemTicket;
  meta?: {
  action: string;
};
};

export type StaffLoginRequest = {
  identifier: string;
  password: string;
  label?: (string) | null;
  device_name?: (string) | null;
  session_transport?: ("refresh_cookie") | null;
};

export type StaffNavigationContext = Record<string, StaffNavigationItem>;

export type StaffNavigationItem = {
  key: string;
  required_capabilities: Array<string>;
  can_access: boolean;
  primary_route: string;
};

export type StaffOperationalRealtimeDescriptor = {
  enabled: boolean;
  topic: string;
  channel: string;
  current_version: number;
  changes_uri: string;
  polling_compatible: boolean;
  default_refresh_targets: Array<string>;
  poll_hint_ms: number;
};

export type StaffOperationalRealtimeEnvelope = {
  data: StaffOperationalRealtimeState;
};

export type StaffOperationalRealtimeEvent = {
  topic: string;
  channel: string;
  version: number;
  type: string;
  occurred_at: string;
  refresh_targets: Array<string>;
  payload: Record<string, unknown>;
};

export type StaffOperationalRealtimeState = {
  enabled: boolean;
  topic: string;
  channel: string;
  after_version: number;
  current_version: number;
  oldest_available_version: number;
  events: Array<StaffOperationalRealtimeEvent>;
  has_changes: boolean;
  stale_cursor: boolean;
  poll_hint_ms: number;
};

export type StaffOrderReadCustomer = {
  user_id: (number) | null;
  full_name: (string) | null;
  email: (string) | null;
  phone: (string) | null;
  current_points: (number) | null;
  current_tier: (CustomerLoyaltyTier) | null;
};

export type StaffOrderReadEnvelope = {
  data: StaffOrderReadPayload;
  meta: {
  action: string;
  selection_policy: string;
};
};

export type StaffOrderReadItemMenuItem = {
  name: (string) | null;
  code: (string) | null;
};

export type StaffOrderReadPayload = {
  order: ReservationOrder;
  table: (RestaurantTable) | null;
  tables: Array<RestaurantTable>;
  reservation: (ReservationSummary) | null;
  customer: (StaffOrderReadCustomer) | null;
  items: Array<{
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  row_version: (number) | null;
  item_name_snapshot: (string) | null;
  unit_price: string;
  currency: string;
  line_total: string;
  notes: (string) | null;
  item: (StaffOrderReadItemMenuItem) | null;
}>;
  item_summary: {
  line_count: number;
  quantity_total: number;
  active_quantity: number;
  cancelled_quantity: number;
  status_counts: Record<string, number>;
  status_quantities: Record<string, number>;
};
  financial_summary: {
  settlement_scope: string;
  subtotal: (string) | null;
  discount: (string) | null;
  total_due: (string) | null;
  paid: (string) | null;
  deposit_applied: (string) | null;
  deposit_net: (string) | null;
  final_paid: (string) | null;
  outstanding: (string) | null;
  currency: (string) | null;
  payment_status: (string) | null;
  reservation_payment_summary: (Record<string, unknown>) | null;
};
};

export type StaffRedeemReservationPointsRequest = {
  points: number;
  reason?: (string) | null;
  row_version: number;
};

export type StaffRefundEnvelope = {
  data: {
  reservation: ReservationSummary;
  refund: {
  refund_payment_ids: Array<number>;
  refund_amount: string;
  currency: string;
  refund_scope: string;
  cancelled: boolean;
  reservation_status: string;
  payment_summary: Record<string, unknown>;
};
};
};

export type StaffRefundPreviewEnvelope = {
  data: {
  reservation: ReservationSummary;
  refund: {
  refund_payment_ids: Array<number>;
  refund_amount: string;
  currency: string;
  refund_scope: string;
  cancelled: boolean;
  reservation_status: string;
  payment_summary: Record<string, unknown>;
};
};
  meta?: {
  action: string;
};
};

export type StaffReleaseReservationPointsRequest = {
  reason?: (string) | null;
  row_version: number;
};

export type StaffRemoveReservationVoucherRequest = {
  row_version: number;
};

export type StaffReportingCollectionMeta = {
  filters: Record<string, unknown>;
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
  action: string;
  snapshot_health: {
  family: string;
  row_count: number;
  date_range: {
  start_date: string;
  end_date: string;
};
  latest_business_date: (string) | null;
  latest_refreshed_at_utc: (string) | null;
  latest_refresh_age_seconds: (number) | null;
  scope_count: number;
  healthy_scope_count: number;
  stale_scope_count: number;
  stale_scope_examples: Array<Record<string, unknown>>;
  health_reference_refreshed_at_utc: (string) | null;
  health_reference_refresh_age_seconds: (number) | null;
  stale_threshold_seconds: number;
  is_empty: boolean;
  is_stale: boolean;
  status: "ok" | "degraded";
  reasons: Array<string>;
};
};

export type StaffReportingDailyInventoryCollectionEnvelope = {
  data: Array<ReportingDailyInventoryMovementSnapshot>;
  meta?: StaffReportingCollectionMeta;
};

export type StaffReportingDailyOperationsCollectionEnvelope = {
  data: Array<ReportingDailyOperationSnapshot>;
  meta?: StaffReportingCollectionMeta;
};

export type StaffReportingDailySalesCollectionEnvelope = {
  data: Array<ReportingDailySalesSnapshot>;
  meta?: StaffReportingCollectionMeta;
};

export type StaffReservationLookupCollectionEnvelope = {
  data: Array<StaffReservationLookupEntry>;
  meta?: StaffReservationLookupCollectionMeta;
};

export type StaffReservationLookupCollectionMeta = {
  filters: {
  bucket: string;
  status: (string) | null;
  reservation_code: (string) | null;
  source: (string) | null;
  q: (string) | null;
  phone: (string) | null;
  deposit_acknowledged: (boolean) | null;
  deposit_intent_status: (string) | null;
  user_id: (number) | null;
  table_id: (number) | null;
  start_from: (string) | null;
  start_to: (string) | null;
  include_financials: boolean;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
};

export type StaffReservationLookupEntry = {
  reservation_id: number;
  reservation_code: string;
  status: string;
  source: (string) | null;
  guest_count: number;
  start_time: (string) | null;
  end_time: (string) | null;
  checked_in_at: (string) | null;
  checked_out_at: (string) | null;
  cancelled_at: (string) | null;
  cancel_reason: (string) | null;
  no_show_at: (string) | null;
  notes: (string) | null;
  row_version: number;
  created_at: (string) | null;
  updated_at: (string) | null;
  user: (StaffReservationLookupUser) | null;
  guest: (ReservationGuestSnapshot) | null;
  table_ids: Array<number>;
  tables: Array<StaffReservationLookupTable>;
  summary: {
  table_count: number;
  is_active: boolean;
  is_checked_in: boolean;
  is_cancelled: boolean;
  is_completed: boolean;
  deposit_acknowledged: boolean;
  deposit_intent_submitted: boolean;
  deposit_self_service_follow_up: boolean;
};
  deposit_self_service: Record<string, unknown>;
  financials: (Record<string, unknown>) | null;
  deposit_status?: (string) | null;
};

export type StaffReservationLookupTable = {
  table_id: number;
  table_code: string;
  zone: (string) | null;
  status: (string) | null;
  seats: (number) | null;
};

export type StaffReservationLookupUser = {
  user_id: (number) | null;
  full_name: (string) | null;
  email: (string) | null;
  phone: (string) | null;
};

export type StaffReservationOrderCollectionEnvelope = {
  data: Array<ReservationOrder>;
  meta?: StaffReservationOrderCollectionMeta;
};

export type StaffReservationOrderCollectionMeta = {
  action: string;
  reservation_id: number;
  count: number;
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "none";
  supported: boolean;
};
  query_contract: ListingQueryContract;
};

export type StaffReservationOrderEnvelope = {
  data: {
  order_id: number;
  reservation_id: number;
  order_type: string;
  status: string;
  row_version?: (number) | null;
  created_at?: (string) | null;
  created_by?: (number) | null;
  updated_by?: (number) | null;
  notes?: (string) | null;
  workflow?: {
  settlement_scope: string;
  canonical_bill_snapshot_action: string;
  legacy_bill_snapshot_action: string;
};
  payment_status?: (string) | null;
  items?: (Array<{
  order_item_id: number;
  item_id: number;
  quantity: number;
  status: string;
  row_version: (number) | null;
  item_name_snapshot: (string) | null;
  unit_price: string;
  currency: string;
  line_total: string;
  notes: (string) | null;
  item: ({
  name: (string) | null;
  code: (string) | null;
}) | null;
}>) | null;
  totals?: {
  subtotal: (string) | null;
  discount: (string) | null;
  total_due: (string) | null;
  paid: (string) | null;
  deposit_applied: (string) | null;
  deposit_net: (string) | null;
  final_paid: (string) | null;
  outstanding: (string) | null;
  currency: (string) | null;
};
};
  meta?: {
  action: string;
  legacy_route_alias?: (string) | null;
  legacy_route_deprecated?: (boolean) | null;
  semantics?: (string) | null;
};
};

export type StaffSessionLogoutEnvelope = {
  data: {
  auth_mode: "staff_api_key" | "staff_browser_session";
  session_transport?: ("refresh_cookie") | null;
  staff_api_key_id: number;
  revoked_at_utc?: (string) | null;
};
};

export type StaffStartupBranch = {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  timezone: (string) | null;
  currency: (string) | null;
  is_default: boolean;
  is_active: boolean;
};

export type StaffStartupCashierShift = {
  cashier_shift_id: number;
  branch_id: number;
  branch?: (StaffStartupBranch) | null;
  shift_code: string;
  status: string;
  currency: string;
  terminal_code: (string) | null;
  row_version: number;
  opened_at: (string) | null;
};

export type StaffStartupContext = {
  primary_workspace: "ops" | "kitchen" | "admin";
  available_workspaces: Array<"ops" | "kitchen" | "admin">;
  default_branch_id: (number) | null;
  allowed_branch_ids: Array<number>;
  assigned_station_ids: Array<number>;
  default_branch: (StaffStartupBranch) | null;
  branch_access: StaffBranchAccessContext;
  active_cashier_shift: (StaffStartupCashierShift) | null;
  navigation: StaffNavigationContext;
  readiness: StaffStartupReadiness;
};

export type StaffStartupReadiness = {
  access: "ready" | "capability_missing";
  branch: "ready" | "missing";
  cashier_shift: "ready" | "action_required" | "not_applicable";
  operator_ready: boolean;
  requires_cashier_shift: boolean;
  granted_capability_count: number;
  known_capability_count: number;
};

export type StaffTableBoardActiveOrder = {
  order_id: number;
  status: string;
  order_type: string;
  row_version: number;
};

export type StaffTableBoardAssignedReservation = {
  reservation_id: number;
  reservation_code: string;
  status: string;
  row_version: number;
  table_ids: Array<number>;
  start_time: (string) | null;
  end_time: (string) | null;
  guest_count: number;
  checked_in_at: (string) | null;
  user: (StaffTableBoardReservationUser) | null;
  guest: (ReservationGuestSnapshot) | null;
  deposit: StaffTableBoardReservationDeposit;
  flags: {
  deposit_self_service_follow_up: boolean;
};
};

export type StaffTableBoardAssignmentRequestContext = {
  board_from: string;
  board_to: string;
  zone: (string) | null;
  include_slot_only_candidates: boolean;
};

export type StaffTableBoardCandidateTable = {
  table_id: number;
  table_code: string;
  zone: (string) | null;
  board_state: string;
  rank: number;
  fit: StaffTableBoardFit;
  score: number;
  reason_codes: Array<string>;
  policy_flags: {
  board_window_open: boolean;
  slot_only_candidate: boolean;
};
  assignment_window: {
  availability_mode: string;
  reservation_window_start: string;
  reservation_window_end: string;
  board_window_start: string;
  board_window_end: string;
};
  assignment_request_context: StaffTableBoardAssignmentRequestContext;
};

export type StaffTableBoardCheckInAction = {
  available: boolean;
  blocked_reason_code: (string) | null;
  method: string;
  endpoint: string;
  required_payload: Array<string>;
  preferred_payload: {
  row_version: number;
  table_ids: Array<number>;
};
  checks: Record<string, boolean>;
};

export type StaffTableBoardEnvelope = {
  data: Array<StaffTableBoardRow>;
  zones: Array<StaffTableBoardZoneSummary>;
  summary: {
  zone_count: number;
  active_order_count: number;
  unassigned_reservation_count: number;
  unassigned_with_slot_only_candidate_count: number;
  deposit_acknowledged_reservation_count: number;
  deposit_intent_submitted_reservation_count: number;
  deposit_self_service_follow_up_count: number;
};
  unassigned_reservations: Array<StaffTableBoardUnassignedReservation>;
  orchestration: {
  mode: string;
  write_side: {
  assign_suggested_table_supported: boolean;
  assign_best_fit_supported: boolean;
  assign_suggested_table_requires_current_candidate: boolean;
};
  capacity_policy: {
  close_fit_max_extra_seats: number;
};
};
  meta: StaffTableBoardMeta;
};

export type StaffTableBoardFit = {
  status: string;
  extra_seats: number;
  reason_code: string;
};

export type StaffTableBoardHold = {
  hold_id: string;
  hold_status: string;
  row_version: number;
  start_time: (string) | null;
  end_time: (string) | null;
  expire_at: (string) | null;
};

export type StaffTableBoardMeta = {
  filters: {
  from: string;
  to: string;
  zone: (string) | null;
  include_holds: boolean;
  group_by: (string) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "none";
  supported: boolean;
};
  query_contract: ListingQueryContract;
  action: string;
  supported_actions: Record<string, Record<string, string>>;
  realtime: StaffOperationalRealtimeDescriptor;
};

export type StaffTableBoardMoveTableAction = {
  available: boolean;
  method: string;
  endpoint: string;
  required_payload: Array<string>;
  preferred_payload: {
  from_table_id: number;
  row_version: number;
};
};

export type StaffTableBoardReservationDeposit = {
  status: string;
  required_amount: string;
  paid_amount: string;
  outstanding_amount: string;
  currency: string;
  self_service: Record<string, unknown>;
};

export type StaffTableBoardReservationUser = {
  user_id?: (number) | null;
  full_name?: (string) | null;
  email?: (string) | null;
  phone?: (string) | null;
};

export type StaffTableBoardRow = {
  table_id: number;
  table_code: string;
  zone: (string) | null;
  pos_x: (number) | null;
  pos_y: (number) | null;
  row_version: number;
  realtime_status: string;
  board_state: string;
  reservations: Array<StaffTableBoardAssignedReservation>;
  holds: Array<StaffTableBoardHold>;
  reservation: (StaffTableBoardAssignedReservation) | null;
  hold: (StaffTableBoardHold) | null;
  capacity: {
  template_id: (number) | null;
  seats: (number) | null;
};
  availability: {
  accepts_new_assignment: boolean;
  is_operationally_blocked: boolean;
  is_realtime_occupied: boolean;
  has_reservation_in_range: boolean;
  has_hold_in_range: boolean;
  requires_deposit_follow_up: boolean;
};
  operational_hints: {
  assignment_candidate: boolean;
  preferred_action: string;
};
  actions: {
  check_in: (StaffTableBoardCheckInAction) | null;
  move_table: (StaffTableBoardMoveTableAction) | null;
};
  candidate_reservations: Array<{
  reservation_id: number;
  reservation_code: string;
  row_version: number;
  guest_count: number;
  user: (StaffTableBoardReservationUser) | null;
  guest: (ReservationGuestSnapshot) | null;
  flags: {
  due_soon: boolean;
  late: boolean;
  overdue: boolean;
};
  policy_flags: Record<string, boolean>;
  deposit: {
  self_service: Record<string, unknown>;
};
}>;
  current_fit: (StaffTableBoardFit) | null;
  active_order: (StaffTableBoardActiveOrder) | null;
};

export type StaffTableBoardUnassignedReservation = {
  reservation_id: number;
  reservation_code: string;
  status: string;
  row_version: number;
  guest_count: number;
  start_time: (string) | null;
  end_time: (string) | null;
  user: (StaffTableBoardReservationUser) | null;
  guest: (ReservationGuestSnapshot) | null;
  flags: {
  due_soon: boolean;
  late: boolean;
  overdue: boolean;
  deposit_self_service_follow_up: boolean;
};
  deposit: StaffTableBoardReservationDeposit;
  orchestration: {
  candidate_table_count: number;
  candidate_tables: Array<StaffTableBoardCandidateTable>;
  best_fit_table: (StaffTableBoardCandidateTable) | null;
  assignment_request_context: StaffTableBoardAssignmentRequestContext;
};
};

export type StaffTableBoardZoneSummary = {
  zone: string;
  summary: {
  table_count: number;
  available_count: number;
  occupied_now_count: number;
  reserved_in_range_count: number;
  held_in_range_count: number;
};
};

export type StaffTablesBoardQueryParams = {
  filter?: Array<Record<string, never>>;
  filters?: Array<Record<string, never>>;
  date?: (string) | null;
  from: string;
  to: string;
  branch_id?: (number) | null;
  zone?: (string) | null;
  include_holds?: (boolean) | null;
  group_by?: ("zone" | "capacity" | "zone_capacity" | "status") | null;
};

export type StaffWaitingListAdvanceEnvelope = {
  data: {
  source_waiting_list: StaffWaitingListEntry;
  advanced_waiting_list: StaffWaitingListEntry | null;
  automation: Record<string, unknown>;
};
};

export type StaffWaitingListCollectionEnvelope = {
  data: Array<StaffWaitingListEntry>;
  meta?: StaffWaitingListCollectionMeta;
};

export type StaffWaitingListCollectionMeta = {
  filters: {
  status: (string) | null;
  active_only: boolean;
  phone: (string) | null;
  guest_name: (string) | null;
  branch_id: (number) | null;
};
  sort: {
  supported: boolean;
  value: (string) | null;
  by: (string) | null;
  dir: (string) | null;
};
  pagination: {
  mode: "paged" | "legacy_unbounded";
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
};
  current_page: number;
  per_page: number;
  from: (number) | null;
  to: (number) | null;
  total: number;
  last_page: number;
  has_more_pages: boolean;
  query_contract: ListingQueryContract;
  summary: {
  mode: string;
  ready_to_seat_count: number;
  advance_queue_ready_count: number;
  advance_queue_blocked_count: number;
  awaiting_customer_follow_up_count: number;
  hold_investigation_count: number;
};
  realtime: StaffOperationalRealtimeDescriptor;
};

export type StaffWaitingListEntry = {
  waiting_id: number;
  branch_id: (number) | null;
  user_id: (number) | null;
  guest_name: (string) | null;
  phone: (string) | null;
  guest_count: number;
  requested_at: (string) | null;
  status: string;
  priority: number;
  notified_at: (string) | null;
  notify_expires_at: (string) | null;
  notified_by: (number) | null;
  seated_at: (string) | null;
  cancelled_at: (string) | null;
  cancel_reason: (string) | null;
  notes: (string) | null;
  updated_by: (number) | null;
  row_version: number;
  current_response_state: string;
  response: {
  status: (string) | null;
  responded_at: (string) | null;
  confirmed_arrival_at: (string) | null;
};
  invite_window: {
  notified_at: (string) | null;
  expires_at: (string) | null;
  is_active: boolean;
  is_expired: boolean;
  seconds_remaining: number;
};
  invite_lifecycle: {
  requires_explicit_staff_seat: boolean;
  auto_convert_to_reservation: boolean;
  seat_readiness: string;
  customer_next_step: string;
  staff_next_step: string;
  can_staff_seat_now: boolean;
};
  invite_hold: {
  has_active_hold: boolean;
  active: ({
  hold_id: string;
  status: string;
  session_id: string;
  expires_at: (string) | null;
  confirmed_reservation_id: (number) | null;
  table_ids: Array<number>;
}) | null;
  latest: ({
  hold_id: string;
  status: string;
  session_id: string;
  expires_at: (string) | null;
  confirmed_reservation_id: (number) | null;
  table_ids: Array<number>;
}) | null;
};
  orchestration: {
  mode: string;
  actionable_state: string;
  recommended_action: string;
  released_table: ({
  table_id: number;
  table_ids: Array<number>;
  table_code: (string) | null;
  zone: (string) | null;
  status: (string) | null;
  seats: (number) | null;
}) | null;
  advance_queue: {
  supported: boolean;
  can_apply_now: boolean;
  resulting_action: string;
  released_table_available: boolean;
  next_candidate: ({
  waiting_id: number;
  user_id: (number) | null;
  guest_name: (string) | null;
  guest_count: number;
  priority: number;
  requested_at: (string) | null;
  row_version: number;
  capacity_fit: {
  table_seats: number;
  seat_delta: number;
};
}) | null;
  disabled_reason: (string) | null;
};
  actions: Array<{
  key: string;
  method: string;
  href: string;
  enabled: boolean;
  reason: string;
}>;
};
  user?: (Record<string, unknown>) | null;
};

export type StaffWaitingListEnvelope = {
  data: StaffWaitingListEntry;
};

export type StaffWaitingListSeatEnvelope = {
  data: {
  waiting_list: StaffWaitingListEntry;
  reservation: ReservationSummary;
};
};

export type StartReservationBillPaymentRequest = {
  row_version: number;
  session_id?: (string) | null;
  provider_code?: (string) | null;
  payment_method?: (string) | null;
  amount?: (number) | null;
  currency?: (string) | null;
  notes?: (string) | null;
};

export type StartReservationDepositPaymentRequest = {
  row_version: number;
  session_id?: (string) | null;
  provider_code?: (string) | null;
  payment_method?: (string) | null;
  amount?: (number) | null;
  currency?: (string) | null;
  notes?: (string) | null;
};

export type StoreLoyaltyTierRequest = {
  tier_code: string;
  tier_name: string;
  min_points: number;
  benefits_json?: (Array<Record<string, never>>) | null;
  is_active?: boolean;
};

export type StoreMenuCategoryRequest = {
  name: string;
  description?: (string) | null;
  sort_order?: number;
  is_deleted?: boolean;
};

export type StoreMenuItemPriceRequest = {
  price: number;
  currency?: (string) | null;
  effective_from: string;
  effective_to?: (string) | null;
};

export type StoreMenuItemRequest = {
  category_id?: (number) | null;
  code?: (string) | null;
  name: string;
  description?: (string) | null;
  img_url?: (string) | null;
  is_available?: boolean;
  is_preorder_enabled?: boolean;
  preorder_quota_per_day?: (number) | null;
  preorder_cutoff_minutes?: number;
};

export type StoreVoucherRequest = {
  code: string;
  description?: (string) | null;
  discount_type: "Fixed" | "Percent" | "FreeItem";
  discount_value?: (number) | null;
  free_item_id?: (number) | null;
  free_item_qty?: (number) | null;
  max_usage?: (number) | null;
  max_usage_per_user?: (number) | null;
  min_spend?: (number) | null;
  start_date?: (string) | null;
  expiry_date?: (string) | null;
  is_active?: boolean;
};

export type SubmitCustomerReservationDepositIntentRequest = {
  row_version: number;
  session_id?: (string) | null;
};

export type SubmitCustomerReservationPreorderRequest = {
  row_version: number;
  pre_order_row_version?: (number) | null;
};

export type TableHold = {
  hold_id: string;
  session_hash: (string) | null;
  start_time: (string) | null;
  end_time: (string) | null;
  duration_minutes: (number) | null;
  hold_status: string;
  confirmed_reservation_id: (number) | null;
  row_version: number;
  created_at?: (string) | null;
  updated_at?: (string) | null;
  expire_at?: (string) | null;
  tables: Array<RestaurantTable>;
  [key: string]: unknown;
};

export type TableHoldEnvelope = {
  data: TableHold;
};

export type TakeOverConversationRequest = {
  notes?: (string) | null;
};

export type UnassignConversationRequest = {
  notes?: (string) | null;
};

export type UpdateConversationWorkflowStateRequest = {
  workflow_state: "Open" | "Triaged" | "PendingCustomer" | "Resolved" | "Closed";
  expected_workflow_state?: ("Open" | "Triaged" | "Assigned" | "PendingCustomer" | "Resolved" | "Closed") | null;
  reason?: (string) | null;
};

export type UpdateLoyaltyTierRequest = {
  row_version: number;
  tier_code?: string;
  tier_name?: string;
  min_points?: number;
  benefits_json?: (Array<Record<string, never>>) | null;
  is_active?: boolean;
};

export type UpdateOrderItemRequest = {
  qty?: number;
  note?: (string) | null;
  order_row_version: number;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type UpdateOrderItemStatusRequest = {
  status: "InProgress" | "Served" | "Cancelled";
  order_row_version: number;
  row_version: number;
  staff_user_id?: (number) | null;
};

export type UpdateVoucherRequest = {
  row_version: number;
  code?: string;
  description?: (string) | null;
  discount_type?: "Fixed" | "Percent" | "FreeItem";
  discount_value?: (number) | null;
  free_item_id?: (number) | null;
  free_item_qty?: (number) | null;
  max_usage?: (number) | null;
  max_usage_per_user?: (number) | null;
  min_spend?: (number) | null;
  start_date?: (string) | null;
  expiry_date?: (string) | null;
  is_active?: boolean;
};

export type UpsertBenefitSettingRequest = {
  setting_key: "loyalty.enabled" | "loyalty.earn_amount_per_point" | "loyalty.redeem_amount_per_point" | "loyalty.min_redeem_points" | "voucher.lock_minutes";
  value: string;
  expected_updated_at?: (string) | null;
};

export type WebhookReceiptEnvelope = {
  data: {
  duplicate: boolean;
  provider_code: string;
  provider_event_code: string;
  provider_session_code: string;
  payment_scope?: (string) | null;
  delivery_status: string;
  receipt_id: number;
  ignored_reason?: (string) | null;
  failure_message?: (string) | null;
  message?: (string) | null;
};
};

export class RestaurantPosClient {
  private readonly fetchImpl: typeof fetch;

  constructor(private readonly options: RestaurantPosClientOptions) {
    if (typeof options.fetchImpl === 'function') {
      const providedFetch = options.fetchImpl;
      this.fetchImpl = providedFetch === globalThis.fetch
        ? globalThis.fetch.bind(globalThis)
        : ((input, init) => providedFetch(input, init)) as typeof fetch;
      return;
    }

    if (typeof globalThis.fetch !== 'function') {
      throw new Error('RestaurantPosClient requires a fetch implementation.');
    }

    this.fetchImpl = globalThis.fetch.bind(globalThis);
  }

  async postV1AuthCustomerRegister(body: RegisterRequest, options: RequestOptions = {}): Promise<CustomerAuthSessionEnvelope> {
    return this.request<CustomerAuthSessionEnvelope>(
      'POST',
      '/api/v1/auth/customer/register',
      'none',
      true,
      false,
      undefined,
      body,
      options,
    );
  }

  async postV1AuthCustomerLogin(body: CustomerLoginRequest, options: RequestOptions = {}): Promise<CustomerAuthSessionEnvelope> {
    return this.request<CustomerAuthSessionEnvelope>(
      'POST',
      '/api/v1/auth/customer/login',
      'none',
      true,
      false,
      undefined,
      body,
      options,
    );
  }

  async getV1AuthCustomerMe(options: RequestOptions = {}): Promise<CustomerAuthSessionEnvelope> {
    return this.request<CustomerAuthSessionEnvelope>(
      'GET',
      '/api/v1/auth/customer/me',
      'customer',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AuthCustomerRefresh(body: RefreshSessionRequest, options: RequestOptions = {}): Promise<CustomerAuthSessionEnvelope> {
    return this.request<CustomerAuthSessionEnvelope>(
      'POST',
      '/api/v1/auth/customer/refresh',
      'customer',
      false,
      false,
      undefined,
      body,
      options,
    );
  }

  async postV1AuthCustomerLogout(options: RequestOptions = {}): Promise<CustomerSessionLogoutEnvelope> {
    return this.request<CustomerSessionLogoutEnvelope>(
      'POST',
      '/api/v1/auth/customer/logout',
      'customer',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AuthStaffLogin(body: StaffLoginRequest, options: RequestOptions = {}): Promise<StaffAuthSessionEnvelope> {
    return this.request<StaffAuthSessionEnvelope>(
      'POST',
      '/api/v1/auth/staff/login',
      'none',
      false,
      false,
      undefined,
      body,
      options,
    );
  }

  async getV1AuthStaffMe(options: RequestOptions = {}): Promise<StaffAuthSessionEnvelope> {
    return this.request<StaffAuthSessionEnvelope>(
      'GET',
      '/api/v1/auth/staff/me',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AuthStaffRefresh(options: RequestOptions = {}): Promise<StaffAuthSessionEnvelope> {
    return this.request<StaffAuthSessionEnvelope>(
      'POST',
      '/api/v1/auth/staff/refresh',
      'staffBrowserSession',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AuthStaffLogout(options: RequestOptions = {}): Promise<StaffSessionLogoutEnvelope> {
    return this.request<StaffSessionLogoutEnvelope>(
      'POST',
      '/api/v1/auth/staff/logout',
      'staffBrowserSession',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1RestaurantProfile(options: RequestOptions = {}): Promise<RestaurantProfileEnvelope> {
    return this.request<RestaurantProfileEnvelope>(
      'GET',
      '/api/v1/restaurant/profile',
      'none',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1TablesAvailable(query: GetV1TablesAvailableQueryParams, options: RequestOptions = {}): Promise<AvailableTablesCollectionEnvelope> {
    return this.request<AvailableTablesCollectionEnvelope>(
      'GET',
      '/api/v1/tables/available',
      'none',
      true,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1TableHolds(body: CreateTableHoldRequest, options: RequestOptions = {}): Promise<TableHoldEnvelope> {
    return this.request<TableHoldEnvelope>(
      'POST',
      '/api/v1/table-holds',
      'none',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1TableHoldsHoldId(pathParams: GetV1TableHoldsHoldIdPathParams, options: RequestOptions = {}): Promise<TableHoldEnvelope> {
    return this.request<TableHoldEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/table-holds/{hold_id}', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async patchV1TableHoldsHoldIdRefresh(pathParams: PatchV1TableHoldsHoldIdRefreshPathParams, body: RefreshTableHoldRequest, options: RequestOptions = {}): Promise<TableHoldEnvelope> {
    return this.request<TableHoldEnvelope>(
      'PATCH',
      this.interpolatePath('/api/v1/table-holds/{hold_id}/refresh', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async deleteV1TableHoldsHoldId(pathParams: DeleteV1TableHoldsHoldIdPathParams, query: DeleteV1TableHoldsHoldIdQueryParams, options: RequestOptions = {}): Promise<TableHoldEnvelope> {
    return this.request<TableHoldEnvelope>(
      'DELETE',
      this.interpolatePath('/api/v1/table-holds/{hold_id}', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      query,
      undefined,
      options,
    );
  }

  async getV1MenuCategories(query: GetV1MenuCategoriesQueryParams, options: RequestOptions = {}): Promise<CustomerMenuCategoriesCollectionEnvelope> {
    return this.request<CustomerMenuCategoriesCollectionEnvelope>(
      'GET',
      '/api/v1/menu/categories',
      'none',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1MenuItems(query: GetV1MenuItemsQueryParams, options: RequestOptions = {}): Promise<CustomerMenuItemsCollectionEnvelope> {
    return this.request<CustomerMenuItemsCollectionEnvelope>(
      'GET',
      '/api/v1/menu/items',
      'none',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1MenuItemsId(pathParams: GetV1MenuItemsIdPathParams, query: GetV1MenuItemsIdQueryParams, options: RequestOptions = {}): Promise<CustomerMenuItemEnvelope> {
    return this.request<CustomerMenuItemEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/menu/items/{id}', pathParams as Record<string, string | number>),
      'none',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1MenuPreorderPreview(body: PreviewMenuPreorderRequest, options: RequestOptions = {}): Promise<CustomerMenuPreorderPreviewEnvelope> {
    return this.request<CustomerMenuPreorderPreviewEnvelope>(
      'POST',
      '/api/v1/menu/preorder/preview',
      'none',
      false,
      false,
      undefined,
      body,
      options,
    );
  }

  async postV1Reservations(body: CreateReservationRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      '/api/v1/reservations',
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1Reservations(query: GetV1ReservationsQueryParams, options: RequestOptions = {}): Promise<ReservationSelfServiceCollectionEnvelope> {
    return this.request<ReservationSelfServiceCollectionEnvelope>(
      'GET',
      '/api/v1/reservations',
      'auto',
      true,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1ReservationsId(pathParams: GetV1ReservationsIdPathParams, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{id}', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsIdCancel(pathParams: PostV1ReservationsIdCancelPathParams, body: CancelReservationRequest, options: RequestOptions = {}): Promise<ReservationActionEnvelope> {
    return this.request<ReservationActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/cancel', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdReschedule(pathParams: PostV1ReservationsIdReschedulePathParams, body: CustomerRescheduleReservationRequest, options: RequestOptions = {}): Promise<ReservationActionEnvelope> {
    return this.request<ReservationActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/reschedule', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1ReservationsIdPreorder(pathParams: GetV1ReservationsIdPreorderPathParams, options: RequestOptions = {}): Promise<CustomerReservationPreorderEnvelope> {
    return this.request<CustomerReservationPreorderEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{id}/preorder', pathParams as Record<string, string | number>),
      'customerOrSession',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsIdPreorderPreview(pathParams: PostV1ReservationsIdPreorderPreviewPathParams, body: PreviewCustomerReservationPreorderRequest, options: RequestOptions = {}): Promise<CustomerReservationPreorderEnvelope> {
    return this.request<CustomerReservationPreorderEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/preorder/preview', pathParams as Record<string, string | number>),
      'customerOrSession',
      true,
      false,
      undefined,
      body,
      options,
    );
  }

  async putV1ReservationsIdPreorder(pathParams: PutV1ReservationsIdPreorderPathParams, body: ReplaceCustomerReservationPreorderRequest, options: RequestOptions = {}): Promise<CustomerReservationPreorderEnvelope> {
    return this.request<CustomerReservationPreorderEnvelope>(
      'PUT',
      this.interpolatePath('/api/v1/reservations/{id}/preorder', pathParams as Record<string, string | number>),
      'customerOrSession',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1reservationsidpreordersubmit(pathParams: PostV1reservationsidpreordersubmitPathParams, body: SubmitCustomerReservationPreorderRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/preorder/submit', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async deleteV1ReservationsIdPreorder(pathParams: DeleteV1ReservationsIdPreorderPathParams, query: DeleteV1ReservationsIdPreorderQueryParams, options: RequestOptions = {}): Promise<CustomerReservationPreorderEnvelope> {
    return this.request<CustomerReservationPreorderEnvelope>(
      'DELETE',
      this.interpolatePath('/api/v1/reservations/{id}/preorder', pathParams as Record<string, string | number>),
      'customerOrSession',
      true,
      true,
      query,
      undefined,
      options,
    );
  }

  async getV1ReservationsIdDepositPreview(pathParams: GetV1ReservationsIdDepositPreviewPathParams, options: RequestOptions = {}): Promise<CustomerReservationDepositPreviewEnvelope> {
    return this.request<CustomerReservationDepositPreviewEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{id}/deposit-preview', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsIdDepositAcknowledge(pathParams: PostV1ReservationsIdDepositAcknowledgePathParams, body: AcknowledgeCustomerReservationDepositRequest, options: RequestOptions = {}): Promise<CustomerReservationDepositPreviewEnvelope> {
    return this.request<CustomerReservationDepositPreviewEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/deposit/acknowledge', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdDepositIntent(pathParams: PostV1ReservationsIdDepositIntentPathParams, body: SubmitCustomerReservationDepositIntentRequest, options: RequestOptions = {}): Promise<CustomerReservationDepositPreviewEnvelope> {
    return this.request<CustomerReservationDepositPreviewEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/deposit/intent', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdDepositIntentRevoke(pathParams: PostV1ReservationsIdDepositIntentRevokePathParams, body: RevokeCustomerReservationDepositIntentRequest, options: RequestOptions = {}): Promise<CustomerReservationDepositPreviewEnvelope> {
    return this.request<CustomerReservationDepositPreviewEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/deposit/intent/revoke', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsReservationIdDepositPaymentSessions(pathParams: PostV1ReservationsReservationIdDepositPaymentSessionsPathParams, body: StartReservationDepositPaymentRequest, options: RequestOptions = {}): Promise<CustomerDepositPaymentSessionEnvelope> {
    return this.request<CustomerDepositPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/deposit/payment-sessions', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1ReservationsReservationIdDepositPaymentSessionsSessionId(pathParams: GetV1ReservationsReservationIdDepositPaymentSessionsSessionIdPathParams, options: RequestOptions = {}): Promise<CustomerDepositPaymentSessionEnvelope> {
    return this.request<CustomerDepositPaymentSessionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefresh(pathParams: PostV1ReservationsReservationIdDepositPaymentSessionsSessionIdRefreshPathParams, body: CustomerMutateReservationDepositPaymentSessionRequest, options: RequestOptions = {}): Promise<CustomerDepositPaymentSessionEnvelope> {
    return this.request<CustomerDepositPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsReservationIdDepositPaymentSessionsSessionIdConfirm(pathParams: PostV1ReservationsReservationIdDepositPaymentSessionsSessionIdConfirmPathParams, body: CustomerMutateReservationDepositPaymentSessionRequest, options: RequestOptions = {}): Promise<CustomerDepositPaymentSessionEnvelope> {
    return this.request<CustomerDepositPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffMenuItems(query: GetV1StaffMenuItemsQueryParams, options: RequestOptions = {}): Promise<CustomerMenuItemsCollectionEnvelope> {
    return this.request<CustomerMenuItemsCollectionEnvelope>(
      'GET',
      '/api/v1/staff/menu/items',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async staffTablesBoard(query: StaffTablesBoardQueryParams, options: RequestOptions = {}): Promise<StaffTableBoardEnvelope> {
    return this.request<StaffTableBoardEnvelope>(
      'GET',
      '/api/v1/staff/tables/board',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffTablesBoardChanges(query: GetV1StaffTablesBoardChangesQueryParams, options: RequestOptions = {}): Promise<StaffOperationalRealtimeEnvelope> {
    return this.request<StaffOperationalRealtimeEnvelope>(
      'GET',
      '/api/v1/staff/tables/board/changes',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffServiceSessionsWalkIn(body: CreateWalkInServiceSessionRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      '/api/v1/staff/service-sessions/walk-in',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsIdCheckIn(pathParams: PostV1StaffReservationsIdCheckInPathParams, body: StaffCheckInReservationRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/check-in', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsIdAssignTable(pathParams: PostV1StaffReservationsIdAssignTablePathParams, body: StaffAssignSuggestedTableRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/assign-table', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsIdAssignBestFit(pathParams: PostV1StaffReservationsIdAssignBestFitPathParams, body: StaffAssignBestFitTableRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/assign-best-fit', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsIdMoveTable(pathParams: PostV1StaffReservationsIdMoveTablePathParams, body: MoveTableRequest, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/move-table', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffTablesTableIdRelease(pathParams: PostV1StaffTablesTableIdReleasePathParams, body: ReleaseTableRequest, options: RequestOptions = {}): Promise<RestaurantTableEnvelope> {
    return this.request<RestaurantTableEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/tables/{table_id}/release', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffTablesTableIdOrders(pathParams: PostV1StaffTablesTableIdOrdersPathParams, body: CreateTableOrderRequest, options: RequestOptions = {}): Promise<StaffReservationOrderEnvelope> {
    return this.request<StaffReservationOrderEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/tables/{table_id}/orders', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffTablesTableIdActiveOrder(pathParams: GetV1StaffTablesTableIdActiveOrderPathParams, options: RequestOptions = {}): Promise<StaffOrderReadEnvelope> {
    return this.request<StaffOrderReadEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/tables/{table_id}/active-order', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffOrdersOrderIdItems(pathParams: PostV1StaffOrdersOrderIdItemsPathParams, body: AddOrderItemsRequest, options: RequestOptions = {}): Promise<StaffReservationOrderEnvelope> {
    return this.request<StaffReservationOrderEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/items', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async patchV1StaffOrdersOrderIdItemsOrderItemId(pathParams: PatchV1StaffOrdersOrderIdItemsOrderItemIdPathParams, body: UpdateOrderItemRequest, options: RequestOptions = {}): Promise<StaffReservationOrderEnvelope> {
    return this.request<StaffReservationOrderEnvelope>(
      'PATCH',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/items/{order_item_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffOrdersOrderIdItemsOrderItemIdStatus(pathParams: PostV1StaffOrdersOrderIdItemsOrderItemIdStatusPathParams, body: UpdateOrderItemStatusRequest, options: RequestOptions = {}): Promise<StaffReservationOrderEnvelope> {
    return this.request<StaffReservationOrderEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/items/{order_item_id}/status', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1ReservationsReservationIdActiveOrder(pathParams: GetV1ReservationsReservationIdActiveOrderPathParams, options: RequestOptions = {}): Promise<CustomerActiveOrderEnvelope> {
    return this.request<CustomerActiveOrderEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/active-order', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffOrdersOrderIdBillSnapshot(pathParams: PostV1StaffOrdersOrderIdBillSnapshotPathParams, body: StaffCloseOrderRequest, options: RequestOptions = {}): Promise<StaffReservationOrderEnvelope> {
    return this.request<StaffReservationOrderEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/bill-snapshot', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1ReservationsReservationIdBillPreview(pathParams: GetV1ReservationsReservationIdBillPreviewPathParams, options: RequestOptions = {}): Promise<CustomerBillPreviewEnvelope> {
    return this.request<CustomerBillPreviewEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill-preview', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1ReservationsReservationIdBill(pathParams: GetV1ReservationsReservationIdBillPathParams, options: RequestOptions = {}): Promise<CustomerReservationBillEnvelope> {
    return this.request<CustomerReservationBillEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsReservationIdBillPaymentSessions(pathParams: PostV1ReservationsReservationIdBillPaymentSessionsPathParams, body: StartReservationBillPaymentRequest, options: RequestOptions = {}): Promise<CustomerBillPaymentSessionEnvelope> {
    return this.request<CustomerBillPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill/payment-sessions', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1ReservationsReservationIdBillPaymentSessionsSessionId(pathParams: GetV1ReservationsReservationIdBillPaymentSessionsSessionIdPathParams, options: RequestOptions = {}): Promise<CustomerBillPaymentSessionEnvelope> {
    return this.request<CustomerBillPaymentSessionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}', pathParams as Record<string, string | number>),
      'auto',
      true,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh(pathParams: PostV1ReservationsReservationIdBillPaymentSessionsSessionIdRefreshPathParams, body: CustomerMutateReservationBillPaymentSessionRequest, options: RequestOptions = {}): Promise<CustomerBillPaymentSessionEnvelope> {
    return this.request<CustomerBillPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirm(pathParams: PostV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirmPathParams, body: CustomerMutateReservationBillPaymentSessionRequest, options: RequestOptions = {}): Promise<CustomerBillPaymentSessionEnvelope> {
    return this.request<CustomerBillPaymentSessionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm', pathParams as Record<string, string | number>),
      'auto',
      true,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffOrdersOrderId(pathParams: GetV1StaffOrdersOrderIdPathParams, options: RequestOptions = {}): Promise<StaffOrderReadEnvelope> {
    return this.request<StaffOrderReadEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/orders/{order_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffCashierShiftsCurrent(query: GetV1StaffCashierShiftsCurrentQueryParams, options: RequestOptions = {}): Promise<CashierShiftEnvelope> {
    return this.request<CashierShiftEnvelope>(
      'GET',
      '/api/v1/staff/cashier/shifts/current',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffCashierShiftsOpen(body: OpenCashierShiftRequest, options: RequestOptions = {}): Promise<CashierShiftEnvelope> {
    return this.request<CashierShiftEnvelope>(
      'POST',
      '/api/v1/staff/cashier/shifts/open',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffCashierShiftsShiftId(pathParams: GetV1StaffCashierShiftsShiftIdPathParams, options: RequestOptions = {}): Promise<CashierShiftEnvelope> {
    return this.request<CashierShiftEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/cashier/shifts/{shift_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffCashierShiftsShiftIdClose(pathParams: PostV1StaffCashierShiftsShiftIdClosePathParams, body: CloseCashierShiftRequest, options: RequestOptions = {}): Promise<CashierShiftEnvelope> {
    return this.request<CashierShiftEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/cashier/shifts/{shift_id}/close', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffOrdersOrderIdSettlementPreview(pathParams: GetV1StaffOrdersOrderIdSettlementPreviewPathParams, query: GetV1StaffOrdersOrderIdSettlementPreviewQueryParams, options: RequestOptions = {}): Promise<StaffCheckoutSettlementEnvelope> {
    return this.request<StaffCheckoutSettlementEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/settlement-preview', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffOrdersOrderIdPay(pathParams: PostV1StaffOrdersOrderIdPayPathParams, body: PayOrderRequest, options: RequestOptions = {}): Promise<StaffCheckoutSettlementEnvelope> {
    return this.request<StaffCheckoutSettlementEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/pay', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffOrdersOrderIdSettlementFinalize(pathParams: PostV1StaffOrdersOrderIdSettlementFinalizePathParams, body: StaffCheckoutOrderRequest, options: RequestOptions = {}): Promise<StaffCheckoutSettlementEnvelope> {
    return this.request<StaffCheckoutSettlementEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/settlement/finalize', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1staffreservationsidpreorder(pathParams: GetV1staffreservationsidpreorderPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{id}/preorder', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1staffreservationsidpreorderconfirm(pathParams: PostV1staffreservationsidpreorderconfirmPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/preorder/confirm', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      undefined,
      options,
    );
  }

  async postV1staffreservationsidpreorderreject(pathParams: PostV1staffreservationsidpreorderrejectPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/preorder/reject', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      undefined,
      options,
    );
  }

  async postV1staffreservationsidpreorderconvert(pathParams: PostV1staffreservationsidpreorderconvertPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{id}/preorder/convert', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffKitchenChanges(query: GetV1StaffKitchenChangesQueryParams, options: RequestOptions = {}): Promise<StaffOperationalRealtimeEnvelope> {
    return this.request<StaffOperationalRealtimeEnvelope>(
      'GET',
      '/api/v1/staff/kitchen/changes',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffKitchenStations(query: GetV1StaffKitchenStationsQueryParams, options: RequestOptions = {}): Promise<StaffKitchenStationCollectionEnvelope> {
    return this.request<StaffKitchenStationCollectionEnvelope>(
      'GET',
      '/api/v1/staff/kitchen/stations',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffKitchenStationsStationIdTickets(pathParams: GetV1StaffKitchenStationsStationIdTicketsPathParams, query: GetV1StaffKitchenStationsStationIdTicketsQueryParams, options: RequestOptions = {}): Promise<StaffKitchenTicketCollectionEnvelope> {
    return this.request<StaffKitchenTicketCollectionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/kitchen/stations/{station_id}/tickets', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffOrdersOrderIdKitchenDispatch(pathParams: PostV1StaffOrdersOrderIdKitchenDispatchPathParams, body: DispatchKitchenTicketRequest, options: RequestOptions = {}): Promise<StaffKitchenDispatchEnvelope> {
    return this.request<StaffKitchenDispatchEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/orders/{order_id}/kitchen/dispatch', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffKitchenTicketsTicketIdFire(pathParams: PostV1StaffKitchenTicketsTicketIdFirePathParams, body: StaffKitchenTicketActionRequest, options: RequestOptions = {}): Promise<StaffKitchenTicketEnvelope> {
    return this.request<StaffKitchenTicketEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/kitchen/tickets/{ticket_id}/fire', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffKitchenTicketsTicketIdBump(pathParams: PostV1StaffKitchenTicketsTicketIdBumpPathParams, body: StaffKitchenTicketActionRequest, options: RequestOptions = {}): Promise<StaffKitchenTicketEnvelope> {
    return this.request<StaffKitchenTicketEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/kitchen/tickets/{ticket_id}/bump', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffKitchenTicketsTicketIdRecall(pathParams: PostV1StaffKitchenTicketsTicketIdRecallPathParams, body: StaffKitchenTicketActionRequest, options: RequestOptions = {}): Promise<StaffKitchenTicketEnvelope> {
    return this.request<StaffKitchenTicketEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/kitchen/tickets/{ticket_id}/recall', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffBranches(options: RequestOptions = {}): Promise<BranchCollectionEnvelope> {
    return this.request<BranchCollectionEnvelope>(
      'GET',
      '/api/v1/staff/branches',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffReservations(query: GetV1StaffReservationsQueryParams, options: RequestOptions = {}): Promise<StaffReservationLookupCollectionEnvelope> {
    return this.request<StaffReservationLookupCollectionEnvelope>(
      'GET',
      '/api/v1/staff/reservations',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffReservationsReservationId(pathParams: GetV1StaffReservationsReservationIdPathParams, options: RequestOptions = {}): Promise<ReservationEnvelope> {
    return this.request<ReservationEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffReservationsReservationIdOrders(pathParams: GetV1StaffReservationsReservationIdOrdersPathParams, options: RequestOptions = {}): Promise<StaffReservationOrderCollectionEnvelope> {
    return this.request<StaffReservationOrderCollectionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/orders', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffCashierShifts(query: GetV1StaffCashierShiftsQueryParams, options: RequestOptions = {}): Promise<CashierShiftCollectionEnvelope> {
    return this.request<CashierShiftCollectionEnvelope>(
      'GET',
      '/api/v1/staff/cashier/shifts',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffAuditTrail(query: GetV1StaffAuditTrailQueryParams, options: RequestOptions = {}): Promise<StaffAuditTrailEnvelope> {
    return this.request<StaffAuditTrailEnvelope>(
      'GET',
      '/api/v1/staff/audit-trail',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffReportingDailySales(query: GetV1StaffReportingDailySalesQueryParams, options: RequestOptions = {}): Promise<StaffReportingDailySalesCollectionEnvelope> {
    return this.request<StaffReportingDailySalesCollectionEnvelope>(
      'GET',
      '/api/v1/staff/reporting/daily-sales',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffReportingDailyOperations(query: GetV1StaffReportingDailyOperationsQueryParams, options: RequestOptions = {}): Promise<StaffReportingDailyOperationsCollectionEnvelope> {
    return this.request<StaffReportingDailyOperationsCollectionEnvelope>(
      'GET',
      '/api/v1/staff/reporting/daily-operations',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffReportingDailyInventory(query: GetV1StaffReportingDailyInventoryQueryParams, options: RequestOptions = {}): Promise<StaffReportingDailyInventoryCollectionEnvelope> {
    return this.request<StaffReportingDailyInventoryCollectionEnvelope>(
      'GET',
      '/api/v1/staff/reporting/daily-inventory',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminInventoryIngredients(query: GetV1AdminInventoryIngredientsQueryParams, options: RequestOptions = {}): Promise<AdminIngredientCollectionEnvelope> {
    return this.request<AdminIngredientCollectionEnvelope>(
      'GET',
      '/api/v1/admin/inventory/ingredients',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminInventorySuppliers(query: GetV1AdminInventorySuppliersQueryParams, options: RequestOptions = {}): Promise<AdminSupplierCollectionEnvelope> {
    return this.request<AdminSupplierCollectionEnvelope>(
      'GET',
      '/api/v1/admin/inventory/suppliers',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminInventoryPurchaseOrders(query: GetV1AdminInventoryPurchaseOrdersQueryParams, options: RequestOptions = {}): Promise<AdminPurchaseOrderCollectionEnvelope> {
    return this.request<AdminPurchaseOrderCollectionEnvelope>(
      'GET',
      '/api/v1/admin/inventory/purchase-orders',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminInventoryIngredientsIdMovements(pathParams: GetV1AdminInventoryIngredientsIdMovementsPathParams, query: GetV1AdminInventoryIngredientsIdMovementsQueryParams, options: RequestOptions = {}): Promise<AdminIngredientMovementCollectionEnvelope> {
    return this.request<AdminIngredientMovementCollectionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/inventory/ingredients/{id}/movements', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1AdminInventoryIngredientsIdMovements(pathParams: PostV1AdminInventoryIngredientsIdMovementsPathParams, body: CreateIngredientStockMovementRequest, options: RequestOptions = {}): Promise<AdminIngredientMovementEnvelope> {
    return this.request<AdminIngredientMovementEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/admin/inventory/ingredients/{id}/movements', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminInventoryPurchaseOrdersIdReceipts(pathParams: GetV1AdminInventoryPurchaseOrdersIdReceiptsPathParams, query: GetV1AdminInventoryPurchaseOrdersIdReceiptsQueryParams, options: RequestOptions = {}): Promise<AdminPurchaseOrderReceiptCollectionEnvelope> {
    return this.request<AdminPurchaseOrderReceiptCollectionEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/inventory/purchase-orders/{id}/receipts', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1AdminInventoryPurchaseOrdersIdReceipts(pathParams: PostV1AdminInventoryPurchaseOrdersIdReceiptsPathParams, body: CreatePurchaseOrderReceiptRequest, options: RequestOptions = {}): Promise<AdminPurchaseOrderReceiptEnvelope> {
    return this.request<AdminPurchaseOrderReceiptEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/admin/inventory/purchase-orders/{id}/receipts', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminSettingsBranches(query: GetV1AdminSettingsBranchesQueryParams, options: RequestOptions = {}): Promise<BranchCollectionEnvelope> {
    return this.request<BranchCollectionEnvelope>(
      'GET',
      '/api/v1/admin/settings/branches',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffReservationsReservationIdRefundPreview(pathParams: GetV1StaffReservationsReservationIdRefundPreviewPathParams, query: GetV1StaffReservationsReservationIdRefundPreviewQueryParams, options: RequestOptions = {}): Promise<StaffRefundPreviewEnvelope> {
    return this.request<StaffRefundPreviewEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/refund-preview', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffReservationsReservationIdRefund(pathParams: PostV1StaffReservationsReservationIdRefundPathParams, body: RefundReservationRequest, options: RequestOptions = {}): Promise<StaffRefundEnvelope> {
    return this.request<StaffRefundEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/refund', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsReservationIdRefundCancel(pathParams: PostV1StaffReservationsReservationIdRefundCancelPathParams, body: RefundAndCancelReservationRequest, options: RequestOptions = {}): Promise<StaffRefundEnvelope> {
    return this.request<StaffRefundEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/refund-cancel', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffReservationsReservationIdDepositPreview(pathParams: GetV1StaffReservationsReservationIdDepositPreviewPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/deposit-preview', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffReservationsReservationIdDepositPay(pathParams: PostV1StaffReservationsReservationIdDepositPayPathParams, body: PayReservationDepositRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/deposit/pay', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffFinanceReconciliation(query: GetV1StaffFinanceReconciliationQueryParams, options: RequestOptions = {}): Promise<FinancialReconciliationCollectionEnvelope> {
    return this.request<FinancialReconciliationCollectionEnvelope>(
      'GET',
      '/api/v1/staff/finance/reconciliation',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffFinanceReconciliationReservationId(pathParams: GetV1StaffFinanceReconciliationReservationIdPathParams, query: GetV1StaffFinanceReconciliationReservationIdQueryParams, options: RequestOptions = {}): Promise<FinancialReconciliationDetailEnvelope> {
    return this.request<FinancialReconciliationDetailEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/finance/reconciliation/{reservation_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffFinanceInvoicesReservationId(pathParams: GetV1StaffFinanceInvoicesReservationIdPathParams, query: GetV1StaffFinanceInvoicesReservationIdQueryParams, options: RequestOptions = {}): Promise<FinanceInvoiceEnvelope> {
    return this.request<FinanceInvoiceEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/finance/invoices/{reservation_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffFinanceInvoicesReservationIdIssue(pathParams: PostV1StaffFinanceInvoicesReservationIdIssuePathParams, body: BranchScopeRequest, options: RequestOptions = {}): Promise<FinanceInvoiceEnvelope> {
    return this.request<FinanceInvoiceEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/finance/invoices/{reservation_id}/issue', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1WaitingList(query: GetV1WaitingListQueryParams, options: RequestOptions = {}): Promise<CustomerWaitingListCollectionEnvelope> {
    return this.request<CustomerWaitingListCollectionEnvelope>(
      'GET',
      '/api/v1/waiting-list',
      'customer',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1WaitingList(body: JoinWaitlistRequest, options: RequestOptions = {}): Promise<CustomerWaitingListEnvelope> {
    return this.request<CustomerWaitingListEnvelope>(
      'POST',
      '/api/v1/waiting-list',
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1WaitingListId(pathParams: GetV1WaitingListIdPathParams, options: RequestOptions = {}): Promise<CustomerWaitingListEnvelope> {
    return this.request<CustomerWaitingListEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/waiting-list/{id}', pathParams as Record<string, string | number>),
      'customer',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1StaffWaitingList(query: GetV1StaffWaitingListQueryParams, options: RequestOptions = {}): Promise<StaffWaitingListCollectionEnvelope> {
    return this.request<StaffWaitingListCollectionEnvelope>(
      'GET',
      '/api/v1/staff/waiting-list',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffWaitingListChanges(query: GetV1StaffWaitingListChangesQueryParams, options: RequestOptions = {}): Promise<StaffOperationalRealtimeEnvelope> {
    return this.request<StaffOperationalRealtimeEnvelope>(
      'GET',
      '/api/v1/staff/waiting-list/changes',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffWaitingList(body: CreateWaitlistEntryRequest, options: RequestOptions = {}): Promise<StaffWaitingListEnvelope> {
    return this.request<StaffWaitingListEnvelope>(
      'POST',
      '/api/v1/staff/waiting-list',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffWaitingListIdNotify(pathParams: PostV1StaffWaitingListIdNotifyPathParams, body: InviteWaitlistCustomerRequest, options: RequestOptions = {}): Promise<StaffWaitingListEnvelope> {
    return this.request<StaffWaitingListEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/waiting-list/{id}/notify', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1WaitingListIdAccept(pathParams: PostV1WaitingListIdAcceptPathParams, body: CustomerRespondWaitlistInviteRequest, options: RequestOptions = {}): Promise<CustomerWaitingListEnvelope> {
    return this.request<CustomerWaitingListEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/waiting-list/{id}/accept', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1WaitingListIdConfirmArrival(pathParams: PostV1WaitingListIdConfirmArrivalPathParams, body: CustomerRespondWaitlistInviteRequest, options: RequestOptions = {}): Promise<CustomerWaitingListArrivalEnvelope> {
    return this.request<CustomerWaitingListArrivalEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/waiting-list/{id}/confirm-arrival', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1WaitingListIdDecline(pathParams: PostV1WaitingListIdDeclinePathParams, body: CustomerRespondWaitlistInviteRequest, options: RequestOptions = {}): Promise<CustomerWaitingListEnvelope> {
    return this.request<CustomerWaitingListEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/waiting-list/{id}/decline', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1WaitingListIdCancel(pathParams: PostV1WaitingListIdCancelPathParams, body: CustomerRespondWaitlistInviteRequest, options: RequestOptions = {}): Promise<CustomerWaitingListEnvelope> {
    return this.request<CustomerWaitingListEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/waiting-list/{id}/cancel', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffWaitingListIdCancel(pathParams: PostV1StaffWaitingListIdCancelPathParams, body: CancelWaitlistRequest, options: RequestOptions = {}): Promise<StaffWaitingListEnvelope> {
    return this.request<StaffWaitingListEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/waiting-list/{id}/cancel', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffWaitingListIdAdvance(pathParams: PostV1StaffWaitingListIdAdvancePathParams, body: AdvanceWaitlistRequest, options: RequestOptions = {}): Promise<StaffWaitingListAdvanceEnvelope> {
    return this.request<StaffWaitingListAdvanceEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/waiting-list/{id}/advance', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffWaitingListIdSeat(pathParams: PostV1StaffWaitingListIdSeatPathParams, body: SeatWaitlistRequest, options: RequestOptions = {}): Promise<StaffWaitingListSeatEnvelope> {
    return this.request<StaffWaitingListSeatEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/waiting-list/{id}/seat', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1MeLoyalty(query: GetV1MeLoyaltyQueryParams, options: RequestOptions = {}): Promise<CustomerLoyaltySummaryEnvelope> {
    return this.request<CustomerLoyaltySummaryEnvelope>(
      'GET',
      '/api/v1/me/loyalty',
      'customer',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1MeVouchers(query: GetV1MeVouchersQueryParams, options: RequestOptions = {}): Promise<CustomerVoucherCollectionEnvelope> {
    return this.request<CustomerVoucherCollectionEnvelope>(
      'GET',
      '/api/v1/me/vouchers',
      'customer',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1ReservationsIdBenefitsPreview(pathParams: GetV1ReservationsIdBenefitsPreviewPathParams, options: RequestOptions = {}): Promise<CustomerReservationBenefitsPreviewEnvelope> {
    return this.request<CustomerReservationBenefitsPreviewEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/reservations/{id}/benefits-preview', pathParams as Record<string, string | number>),
      'customer',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1ReservationsIdVoucherApply(pathParams: PostV1ReservationsIdVoucherApplyPathParams, body: CustomerApplyReservationVoucherRequest, options: RequestOptions = {}): Promise<CustomerReservationVoucherActionEnvelope> {
    return this.request<CustomerReservationVoucherActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/voucher/apply', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdVoucherRemove(pathParams: PostV1ReservationsIdVoucherRemovePathParams, body: CustomerRemoveReservationVoucherRequest, options: RequestOptions = {}): Promise<CustomerReservationVoucherActionEnvelope> {
    return this.request<CustomerReservationVoucherActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/voucher/remove', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdLoyaltyRedeem(pathParams: PostV1ReservationsIdLoyaltyRedeemPathParams, body: CustomerRedeemReservationPointsRequest, options: RequestOptions = {}): Promise<CustomerReservationLoyaltyActionEnvelope> {
    return this.request<CustomerReservationLoyaltyActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/loyalty/redeem', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1ReservationsIdLoyaltyRedeemRelease(pathParams: PostV1ReservationsIdLoyaltyRedeemReleasePathParams, body: CustomerReleaseReservationPointsRequest, options: RequestOptions = {}): Promise<CustomerReservationLoyaltyActionEnvelope> {
    return this.request<CustomerReservationLoyaltyActionEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/reservations/{id}/loyalty/redeem/release', pathParams as Record<string, string | number>),
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffReservationsReservationIdVouchers(pathParams: GetV1StaffReservationsReservationIdVouchersPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/vouchers', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffReservationsReservationIdVoucherApply(pathParams: PostV1StaffReservationsReservationIdVoucherApplyPathParams, body: StaffApplyReservationVoucherRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/voucher/apply', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsReservationIdVoucherRemove(pathParams: PostV1StaffReservationsReservationIdVoucherRemovePathParams, body: StaffRemoveReservationVoucherRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/voucher/remove', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsReservationIdVoucherRelease(pathParams: PostV1StaffReservationsReservationIdVoucherReleasePathParams, body: StaffRemoveReservationVoucherRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/voucher/release', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffUsersUserIdLoyalty(pathParams: GetV1StaffUsersUserIdLoyaltyPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/users/{user_id}/loyalty', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffUsersUserIdLoyaltyAdjust(pathParams: PostV1StaffUsersUserIdLoyaltyAdjustPathParams, body: AdjustUserLoyaltyPointsRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/users/{user_id}/loyalty/adjust', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffReservationsReservationIdLoyalty(pathParams: GetV1StaffReservationsReservationIdLoyaltyPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/loyalty', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffReservationsReservationIdLoyaltyRedeem(pathParams: PostV1StaffReservationsReservationIdLoyaltyRedeemPathParams, body: StaffRedeemReservationPointsRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/loyalty/redeem', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsReservationIdLoyaltyRedeemRelease(pathParams: PostV1StaffReservationsReservationIdLoyaltyRedeemReleasePathParams, body: StaffReleaseReservationPointsRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffReservationsReservationIdLoyaltyRelease(pathParams: PostV1StaffReservationsReservationIdLoyaltyReleasePathParams, body: StaffReleaseReservationPointsRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/reservations/{reservation_id}/loyalty/release', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1MeDataExport(options: RequestOptions = {}): Promise<CustomerDataExportEnvelope> {
    return this.request<CustomerDataExportEnvelope>(
      'GET',
      '/api/v1/me/data-export',
      'customer',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1MePrivacyRequests(query: GetV1MePrivacyRequestsQueryParams, options: RequestOptions = {}): Promise<CustomerPrivacyRequestCollectionEnvelope> {
    return this.request<CustomerPrivacyRequestCollectionEnvelope>(
      'GET',
      '/api/v1/me/privacy-requests',
      'customer',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1MePrivacyRequests(body: CreatePrivacyRequestRequest, options: RequestOptions = {}): Promise<CustomerPrivacyRequestEnvelope> {
    return this.request<CustomerPrivacyRequestEnvelope>(
      'POST',
      '/api/v1/me/privacy-requests',
      'customer',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminRestaurantTables(query: GetV1AdminRestaurantTablesQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/restaurant/tables',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminRestaurantTablesExport(query: GetV1AdminRestaurantTablesExportQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/restaurant/tables/export',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminRestaurantTableTemplates(options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/restaurant/table-templates',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AdminRestaurantTables(body: CreateRestaurantTableRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      '/api/v1/admin/restaurant/tables',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminMenuCategories(query: GetV1AdminMenuCategoriesQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/menu/categories',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminMenuCategoriesExport(query: GetV1AdminMenuCategoriesExportQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/menu/categories/export',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1AdminMenuCategories(body: StoreMenuCategoryRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      '/api/v1/admin/menu/categories',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminMenuItems(query: GetV1AdminMenuItemsQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/menu/items',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminMenuItemsExport(query: GetV1AdminMenuItemsExportQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/menu/items/export',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminMenuItemsItemId(pathParams: GetV1AdminMenuItemsItemIdPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/menu/items/{item_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1AdminMenuItemsItemIdPrices(pathParams: GetV1AdminMenuItemsItemIdPricesPathParams, query: GetV1AdminMenuItemsItemIdPricesQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/menu/items/{item_id}/prices', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1AdminMenuItems(body: StoreMenuItemRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      '/api/v1/admin/menu/items',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1AdminMenuItemsItemIdPrices(pathParams: PostV1AdminMenuItemsItemIdPricesPathParams, body: StoreMenuItemPriceRequest, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/admin/menu/items/{item_id}/prices', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminMenuPricesExport(query: GetV1AdminMenuPricesExportQueryParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      '/api/v1/admin/menu/prices/export',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminMenuPricesPriceId(pathParams: GetV1AdminMenuPricesPriceIdPathParams, options: RequestOptions = {}): Promise<GenericDataEnvelope> {
    return this.request<GenericDataEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/menu/prices/{price_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1AdminBenefitsVouchers(options: RequestOptions = {}): Promise<AdminVoucherCollectionEnvelope> {
    return this.request<AdminVoucherCollectionEnvelope>(
      'GET',
      '/api/v1/admin/benefits/vouchers',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1AdminBenefitsVouchersId(pathParams: GetV1AdminBenefitsVouchersIdPathParams, options: RequestOptions = {}): Promise<AdminVoucherEnvelope> {
    return this.request<AdminVoucherEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/benefits/vouchers/{id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AdminBenefitsVouchers(body: StoreVoucherRequest, options: RequestOptions = {}): Promise<AdminVoucherEnvelope> {
    return this.request<AdminVoucherEnvelope>(
      'POST',
      '/api/v1/admin/benefits/vouchers',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async patchV1AdminBenefitsVouchersId(pathParams: PatchV1AdminBenefitsVouchersIdPathParams, body: UpdateVoucherRequest, options: RequestOptions = {}): Promise<AdminVoucherEnvelope> {
    return this.request<AdminVoucherEnvelope>(
      'PATCH',
      this.interpolatePath('/api/v1/admin/benefits/vouchers/{id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminBenefitsLoyaltyTiers(options: RequestOptions = {}): Promise<AdminLoyaltyTierCollectionEnvelope> {
    return this.request<AdminLoyaltyTierCollectionEnvelope>(
      'GET',
      '/api/v1/admin/benefits/loyalty-tiers',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1AdminBenefitsLoyaltyTiersId(pathParams: GetV1AdminBenefitsLoyaltyTiersIdPathParams, options: RequestOptions = {}): Promise<AdminLoyaltyTierEnvelope> {
    return this.request<AdminLoyaltyTierEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/benefits/loyalty-tiers/{id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AdminBenefitsLoyaltyTiers(body: StoreLoyaltyTierRequest, options: RequestOptions = {}): Promise<AdminLoyaltyTierEnvelope> {
    return this.request<AdminLoyaltyTierEnvelope>(
      'POST',
      '/api/v1/admin/benefits/loyalty-tiers',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async patchV1AdminBenefitsLoyaltyTiersId(pathParams: PatchV1AdminBenefitsLoyaltyTiersIdPathParams, body: UpdateLoyaltyTierRequest, options: RequestOptions = {}): Promise<AdminLoyaltyTierEnvelope> {
    return this.request<AdminLoyaltyTierEnvelope>(
      'PATCH',
      this.interpolatePath('/api/v1/admin/benefits/loyalty-tiers/{id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminSettingsBenefits(options: RequestOptions = {}): Promise<AdminBenefitSettingCollectionEnvelope> {
    return this.request<AdminBenefitSettingCollectionEnvelope>(
      'GET',
      '/api/v1/admin/settings/benefits',
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AdminSettingsBenefits(body: UpsertBenefitSettingRequest, options: RequestOptions = {}): Promise<AdminBenefitSettingEnvelope> {
    return this.request<AdminBenefitSettingEnvelope>(
      'POST',
      '/api/v1/admin/settings/benefits',
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1AdminPrivacyRequests(query: GetV1AdminPrivacyRequestsQueryParams, options: RequestOptions = {}): Promise<AdminPrivacyRequestCollectionEnvelope> {
    return this.request<AdminPrivacyRequestCollectionEnvelope>(
      'GET',
      '/api/v1/admin/privacy/requests',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1AdminPrivacyCustomersUserIdDataExport(pathParams: GetV1AdminPrivacyCustomersUserIdDataExportPathParams, options: RequestOptions = {}): Promise<AdminCustomerDataExportEnvelope> {
    return this.request<AdminCustomerDataExportEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/admin/privacy/customers/{user_id}/data-export', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async postV1AdminPrivacyRequestsRequestIdReview(pathParams: PostV1AdminPrivacyRequestsRequestIdReviewPathParams, body: ReviewPrivacyRequestRequest, options: RequestOptions = {}): Promise<AdminPrivacyReviewEnvelope> {
    return this.request<AdminPrivacyReviewEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/admin/privacy/requests/{request_id}/review', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async getV1StaffConversations(query: GetV1StaffConversationsQueryParams, options: RequestOptions = {}): Promise<StaffConversationCollectionEnvelope> {
    return this.request<StaffConversationCollectionEnvelope>(
      'GET',
      '/api/v1/staff/conversations',
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async getV1StaffConversationsConversationId(pathParams: GetV1StaffConversationsConversationIdPathParams, query: GetV1StaffConversationsConversationIdQueryParams, options: RequestOptions = {}): Promise<StaffConversationDetailEnvelope> {
    return this.request<StaffConversationDetailEnvelope>(
      'GET',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}', pathParams as Record<string, string | number>),
      'staff',
      false,
      false,
      query,
      undefined,
      options,
    );
  }

  async postV1StaffConversationsConversationIdAssign(pathParams: PostV1StaffConversationsConversationIdAssignPathParams, body: AssignConversationRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/assign', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffConversationsConversationIdTakeOver(pathParams: PostV1StaffConversationsConversationIdTakeOverPathParams, body: TakeOverConversationRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/take-over', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffConversationsConversationIdUnassign(pathParams: PostV1StaffConversationsConversationIdUnassignPathParams, body: UnassignConversationRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/unassign', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1staffconversationsconversationIdworkflowState(pathParams: PostV1staffconversationsconversationIdworkflowStatePathParams, body: UpdateConversationWorkflowStateRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/workflow-state', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffConversationsConversationIdLinks(pathParams: PostV1StaffConversationsConversationIdLinksPathParams, body: LinkConversationRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/links', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async deleteV1StaffConversationsConversationIdLinksReservation(pathParams: DeleteV1StaffConversationsConversationIdLinksReservationPathParams, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'DELETE',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/links/reservation', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      undefined,
      options,
    );
  }

  async deleteV1StaffConversationsConversationIdLinksWaitingList(pathParams: DeleteV1StaffConversationsConversationIdLinksWaitingListPathParams, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'DELETE',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/links/waiting-list', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      undefined,
      options,
    );
  }

  async postV1StaffConversationsConversationIdInternalNotes(pathParams: PostV1StaffConversationsConversationIdInternalNotesPathParams, body: AddConversationInternalNoteRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/internal-notes', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1StaffConversationsConversationIdOutboundReplies(pathParams: PostV1StaffConversationsConversationIdOutboundRepliesPathParams, body: SendConversationOutboundReplyRequest, options: RequestOptions = {}): Promise<StaffConversationMutationEnvelope> {
    return this.request<StaffConversationMutationEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/staff/conversations/{conversation_id}/outbound-replies', pathParams as Record<string, string | number>),
      'staff',
      false,
      true,
      undefined,
      body,
      options,
    );
  }

  async postV1PaymentsProvidersProviderCodeWebhooks(pathParams: PostV1PaymentsProvidersProviderCodeWebhooksPathParams, body: PostV1PaymentsProvidersProviderCodeWebhooksBody, options: RequestOptions = {}): Promise<WebhookReceiptEnvelope> {
    return this.request<WebhookReceiptEnvelope>(
      'POST',
      this.interpolatePath('/api/v1/payments/providers/{provider_code}/webhooks', pathParams as Record<string, string | number>),
      'none',
      false,
      false,
      undefined,
      body,
      options,
    );
  }

  async health(options: RequestOptions = {}): Promise<HealthStatusEnvelope> {
    return this.request<HealthStatusEnvelope>(
      'GET',
      '/api/v1/health',
      'none',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async getV1healthdetailed(options: RequestOptions = {}): Promise<HealthDetailedEnvelope> {
    return this.request<HealthDetailedEnvelope>(
      'GET',
      '/api/v1/health/detailed',
      'none',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  async healthRedis(options: RequestOptions = {}): Promise<HealthRedisEnvelope> {
    return this.request<HealthRedisEnvelope>(
      'GET',
      '/api/v1/health/redis',
      'none',
      false,
      false,
      undefined,
      undefined,
      options,
    );
  }

  private async request<T>(
    method: string,
    path: string,
    authMode: AuthMode,
    routeSupportsCustomerSession: boolean,
    requiresIdempotency: boolean,
    query?: Record<string, unknown>,
    body?: unknown,
    options: RequestOptions = {},
  ): Promise<T> {
    const baseUrl = this.options.baseUrl.replace(/\/+$/, '');
    const url = new URL(baseUrl + path);

    if (query) {
      Object.entries(query).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          return;
        }
        url.searchParams.set(key, this.serializeQueryParam(value));
      });
    }

    const headers = new Headers(this.options.defaultHeaders ?? {});
    headers.set('Accept', 'application/json');

    if (body !== undefined) {
      headers.set('Content-Type', 'application/json');
    }

    const staffCsrfToken = options.staffCsrfToken === undefined
      ? this.resolveValue(this.options.staffCsrfToken)
      : this.resolveValue(options.staffCsrfToken);

    this.applyAuthHeaders(headers, authMode, options.authMode ?? 'auto', routeSupportsCustomerSession, staffCsrfToken);

    if (requiresIdempotency && options.idempotencyKey) {
      headers.set('Idempotency-Key', options.idempotencyKey);
    }

    if (options.headers) {
      Object.entries(options.headers).forEach(([key, value]) => {
        headers.set(key, value);
      });
    }

    const response = await this.fetchImpl(url.toString(), {
      method,
      headers,
      signal: options.signal,
      credentials: options.credentials ?? this.options.credentials,
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    const raw = await response.text();
    const payload = raw === '' ? undefined : JSON.parse(raw);

    if (!response.ok) {
      throw new RestaurantPosApiError(
        'RestaurantPOS request failed with status ' + response.status + '.',
        response.status,
        payload,
      );
    }

    return payload as T;
  }

  private serializeQueryParam(value: unknown): string {
    if (typeof value === 'boolean') {
      return value ? '1' : '0';
    }

    return String(value);
  }

  private interpolatePath(template: string, values: Record<string, string | number>): string {
    return template.replace(/\{([^}]+)\}/g, (_, key) => encodeURIComponent(String(values[key])));
  }

  private applyAuthHeaders(
    headers: Headers,
    routeAuthMode: AuthMode,
    requestedAuthMode: AuthMode,
    routeSupportsCustomerSession: boolean,
    staffCsrfToken: string | undefined,
  ): void {
    if (routeAuthMode === 'none' || requestedAuthMode === 'none') {
      return;
    }

    const customerToken = this.resolveValue(this.options.customerToken);
    const customerSessionId = this.resolveValue(this.options.customerSessionId);
    const staffApiKey = this.resolveValue(this.options.staffApiKey);

    const selectedMode = requestedAuthMode === 'auto' ? routeAuthMode : requestedAuthMode;

    if (selectedMode === 'customerOrSession') {
      if (customerToken) {
        this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
        return;
      }

      if (routeSupportsCustomerSession && customerSessionId) {
        headers.set('X-Session-Id', customerSessionId);
      }
      return;
    }

    if (selectedMode === 'customer' && customerToken) {
      this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
      return;
    }

    if (selectedMode === 'staff' && staffApiKey) {
      headers.set('X-Staff-Key', staffApiKey);
      return;
    }

    if (selectedMode === 'staffBrowserSession') {
      if (staffApiKey) {
        headers.set('X-Staff-Key', staffApiKey);
      }
      if (staffCsrfToken) {
        headers.set("X-Staff-CSRF", staffCsrfToken);
      }
      return;
    }

    if (selectedMode === 'session' && routeSupportsCustomerSession && customerSessionId) {
      headers.set('X-Session-Id', customerSessionId);
      return;
    }

    if (selectedMode === 'auto') {
      if (customerToken) {
        this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
        return;
      }
      if (routeSupportsCustomerSession && customerSessionId) {
        headers.set('X-Session-Id', customerSessionId);
        return;
      }
      if (staffApiKey) {
        headers.set('X-Staff-Key', staffApiKey);
      }
    }
  }

  private applyCustomerHeaders(
    headers: Headers,
    customerToken: string,
    customerSessionId: string | undefined,
    routeSupportsCustomerSession: boolean,
  ): void {
    headers.set('X-Customer-Token', customerToken);

    if (routeSupportsCustomerSession && customerSessionId) {
      headers.set('X-Session-Id', customerSessionId);
    }
  }

  private resolveValue(value: string | (() => string | null | undefined) | undefined): string | undefined {
    if (typeof value === 'function') {
      return value() ?? undefined;
    }

    return value ?? undefined;
  }
}