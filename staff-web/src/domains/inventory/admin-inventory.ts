import type {
  GetV1AdminInventoryIngredientsQueryParams,
  GetV1AdminInventoryPurchaseOrdersQueryParams,
  GetV1AdminInventorySuppliersQueryParams,
} from '../../shared/api/sdk';

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

export const adminInventoryLaneNotes = [
  'Ingredients, suppliers, and purchase orders stay together under one admin supply lane.',
  'Purchase-order reads honor the current shell branch when a branch context is selected.',
  'Receiving and recipe details remain backend-owned even when the first page is read-heavy.',
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

export function formatInventoryQuantity(value: string | number | null | undefined): string {
  return new Intl.NumberFormat('en-US', {
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
