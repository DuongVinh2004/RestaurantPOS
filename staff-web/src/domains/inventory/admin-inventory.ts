import type {
  GetV1AdminInventoryIngredientsQueryParams,
  GetV1AdminInventoryPurchaseOrdersQueryParams,
  GetV1AdminInventorySuppliersQueryParams,
} from '../../shared/api/sdk';
import type { AdminIngredientMovementQuery } from '../../shared/api/staff-api';

export type AdminInventoryFilterState = {
  ingredientQuery: string;
  ingredientActiveOnly: boolean;
  supplierQuery: string;
  supplierActiveOnly: boolean;
  purchaseOrderQuery: string;
  purchaseOrderStatus: string;
  branchIdInput: string;
};

type AdminPurchaseOrderStatus =
  | 'Draft'
  | 'Ordered'
  | 'PartiallyReceived'
  | 'Received'
  | 'Cancelled';

type IngredientLike = {
  is_active: boolean;
  recipe_usage_count: number;
  stock: {
    on_hand: string | number | null;
  };
};

type SupplierLike = {
  is_active: boolean;
  phone?: string | null;
};

type PurchaseOrderLike = {
  purchase_order_status: string;
  summary: {
    receipt_count: number;
    remaining_total_quantity: string | number | null;
  };
};

type IngredientMovementLike = {
  movement_type: string;
  quantity_delta: string | number | null;
  created_by?: number | null;
};

type PurchaseReceiptLike = {
  receipt_status: string;
  summary: {
    received_total_quantity: string | number | null;
  };
};

export const inventoryMovementTypeOptions = [
  { value: 'AdjustmentIncrease', label: 'Điều chỉnh tăng' },
  { value: 'AdjustmentDecrease', label: 'Điều chỉnh giảm' },
  { value: 'Wastage', label: 'Hao hụt' },
  { value: 'StockIn', label: 'Nhập kho' },
  { value: 'StockOut', label: 'Xuất kho' },
] as const;

export const adminInventoryLaneNotes = [
  'Nguyên liệu, nhà cung cấp và đơn mua hàng được quản lý cùng một khu vực kho.',
  'Danh sách đơn mua hàng ưu tiên chi nhánh đang chọn trong shell vận hành.',
  'Chi tiết nhận hàng và định lượng món vẫn tuân theo hợp đồng backend hiện có.',
];

export function buildAdminIngredientQuery(
  filters: AdminInventoryFilterState,
  perPage = 8,
): GetV1AdminInventoryIngredientsQueryParams {
  return {
    q: normalizedString(filters.ingredientQuery),
    is_active: filters.ingredientActiveOnly ? true : undefined,
    per_page: perPage,
    sort: 'name',
  };
}

export function buildAdminSupplierQuery(
  filters: AdminInventoryFilterState,
  perPage = 8,
): GetV1AdminInventorySuppliersQueryParams {
  return {
    q: normalizedString(filters.supplierQuery),
    is_active: filters.supplierActiveOnly ? true : undefined,
    per_page: perPage,
    sort: 'name',
  };
}

export function buildAdminPurchaseOrderQuery(
  filters: AdminInventoryFilterState,
  fallbackBranchId: number | null,
  perPage = 8,
): GetV1AdminInventoryPurchaseOrdersQueryParams {
  return {
    q: normalizedString(filters.purchaseOrderQuery),
    branch_id: parsePositiveInteger(filters.branchIdInput) ?? fallbackBranchId ?? undefined,
    purchase_order_status: normalizedPurchaseOrderStatus(filters.purchaseOrderStatus),
    per_page: perPage,
    sort: '-created_at',
  };
}

export function buildAdminIngredientMovementQuery(
  branchId: number | null,
  perPage = 8,
): AdminIngredientMovementQuery {
  return {
    branch_id: branchId ?? undefined,
    per_page: perPage,
    sort: '-created_at',
  };
}

export function summarizeAdminIngredients<TIngredient extends IngredientLike>(ingredients: Array<TIngredient>) {
  return {
    displayedCount: ingredients.length,
    activeCount: ingredients.filter((ingredient) => ingredient.is_active).length,
    zeroStockCount: ingredients.filter((ingredient) => numericValue(ingredient.stock.on_hand) === 0).length,
    recipeUsageCount: ingredients.reduce((sum, ingredient) => sum + ingredient.recipe_usage_count, 0),
  };
}

export function summarizeAdminSuppliers<TSupplier extends SupplierLike>(suppliers: Array<TSupplier>) {
  return {
    displayedCount: suppliers.length,
    activeCount: suppliers.filter((supplier) => supplier.is_active).length,
    withPhoneCount: suppliers.filter((supplier) => normalizedString(supplier.phone ?? '') !== undefined).length,
  };
}

export function summarizeAdminPurchaseOrders<TPurchaseOrder extends PurchaseOrderLike>(purchaseOrders: Array<TPurchaseOrder>) {
  return {
    displayedCount: purchaseOrders.length,
    openCount: purchaseOrders.filter((purchaseOrder) => !['Received', 'Cancelled'].includes(purchaseOrder.purchase_order_status)).length,
    receiptCount: purchaseOrders.reduce((sum, purchaseOrder) => sum + purchaseOrder.summary.receipt_count, 0),
    remainingQuantity: purchaseOrders.reduce((sum, purchaseOrder) => sum + numericValue(purchaseOrder.summary.remaining_total_quantity), 0),
  };
}

export function summarizeAdminIngredientMovements<TMovement extends IngredientMovementLike>(movements: Array<TMovement>) {
  return {
    displayedCount: movements.length,
    adjustmentCount: movements.filter((movement) => movement.movement_type.startsWith('Adjustment')).length,
    wastageCount: movements.filter((movement) => movement.movement_type === 'Wastage').length,
    netQuantity: movements.reduce((sum, movement) => sum + numericValue(movement.quantity_delta), 0),
    auditedCount: movements.filter((movement) => movement.created_by !== null && movement.created_by !== undefined).length,
  };
}

export function summarizeAdminPurchaseReceipts<TReceipt extends PurchaseReceiptLike>(receipts: Array<TReceipt>) {
  return {
    displayedCount: receipts.length,
    receivedCount: receipts.filter((receipt) => receipt.receipt_status === 'Received').length,
    receivedQuantity: receipts.reduce((sum, receipt) => sum + numericValue(receipt.summary.received_total_quantity), 0),
  };
}

export function adminPurchaseOrderTone(status: string): 'success' | 'warning' | 'error' | 'processing' {
  switch (status) {
    case 'Received':
      return 'success';
    case 'Cancelled':
      return 'error';
    case 'PartiallyReceived':
      return 'processing';
    default:
      return 'warning';
  }
}

export function adminPurchaseOrderStatusLabel(status: string): string {
  switch (status) {
    case 'Draft':
      return 'Nháp';
    case 'Ordered':
      return 'Đã đặt hàng';
    case 'PartiallyReceived':
      return 'Nhận một phần';
    case 'Received':
      return 'Đã nhận đủ';
    case 'Cancelled':
      return 'Đã hủy';
    default:
      return status || 'Không rõ';
  }
}

export function inventoryMovementTypeLabel(type: string): string {
  return inventoryMovementTypeOptions.find((option) => option.value === type)?.label ?? type;
}

export function inventoryReceiptStatusLabel(status: string): string {
  switch (status) {
    case 'Received':
      return 'Đã nhận';
    case 'Draft':
      return 'Nháp';
    case 'Cancelled':
      return 'Đã hủy';
    default:
      return status || 'Không rõ';
  }
}

export function inventoryMovementTone(type: string): 'success' | 'warning' | 'error' | 'processing' | 'default' {
  switch (type) {
    case 'StockIn':
    case 'AdjustmentIncrease':
      return 'success';
    case 'StockOut':
    case 'AdjustmentDecrease':
      return 'warning';
    case 'Wastage':
      return 'error';
    default:
      return 'default';
  }
}

export function formatInventoryQuantity(value: string | number | null | undefined): string {
  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(numericValue(value));
}

function parsePositiveInteger(value: string): number | null {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function normalizedString(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
}

function normalizedPurchaseOrderStatus(value: string): AdminPurchaseOrderStatus | undefined {
  const normalized = value.trim();

  switch (normalized) {
    case 'Draft':
    case 'Ordered':
    case 'PartiallyReceived':
    case 'Received':
    case 'Cancelled':
      return normalized;
    default:
      return undefined;
  }
}

function numericValue(value: string | number | null | undefined): number {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : 0;
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return 0;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}
