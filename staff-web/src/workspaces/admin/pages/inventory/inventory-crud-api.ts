import { apiRequest } from '../../../../shared/api/http';
import { createIdempotencyKey } from '../../../../shared/utils/idempotency';

export type AdminCreateIngredientPayload = {
  name: string;
  unit_code: string;
  code?: string | null;
  description?: string | null;
  is_active?: boolean;
};

export type AdminUpdateIngredientPayload = AdminCreateIngredientPayload;

export type AdminCreateSupplierPayload = {
  name: string;
  contact_name?: string | null;
  phone?: string | null;
  email?: string | null;
  notes?: string | null;
  is_active?: boolean;
};

export type AdminUpdateSupplierPayload = AdminCreateSupplierPayload;

export type AdminPurchaseOrderLinePayload = {
  ingredient_id: number;
  ordered_quantity: number;
  unit_code?: string | null;
  unit_cost?: number | null;
  notes?: string | null;
  sort_order?: number | null;
};

export type AdminCreatePurchaseOrderPayload = {
  supplier_id: number;
  branch_id?: number | null;
  order_code?: string | null;
  purchase_order_status?: string | null;
  expected_at?: string | null;
  supplier_reference?: string | null;
  notes?: string | null;
  lines: Array<AdminPurchaseOrderLinePayload>;
};

export type AdminCreatePurchaseOrderReceiptLinePayload = {
  purchase_order_line_id: number;
  received_quantity: number;
  unit_code?: string | null;
  unit_cost?: number | null;
  notes?: string | null;
};

export type AdminCreatePurchaseOrderReceiptPayload = {
  receipt_code?: string | null;
  received_at?: string | null;
  supplier_document_no?: string | null;
  notes?: string | null;
  lines: Array<AdminCreatePurchaseOrderReceiptLinePayload>;
};

export type AdminRecipeLinePayload = {
  ingredient_id: number;
  quantity: number;
  unit_code?: string | null;
  notes?: string | null;
};

export type AdminUpsertMenuItemRecipePayload = {
  row_version?: number;
  lines: Array<AdminRecipeLinePayload>;
};

export async function createAdminIngredient(payload: AdminCreateIngredientPayload) {
  return apiRequest('/admin/inventory/ingredients', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-ingredient-create'),
  });
}

export async function updateAdminIngredient(id: number, payload: AdminUpdateIngredientPayload) {
  return apiRequest(`/admin/inventory/ingredients/${id}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-ingredient-update-${id}`),
  });
}

export async function createAdminSupplier(payload: AdminCreateSupplierPayload) {
  return apiRequest('/admin/inventory/suppliers', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-supplier-create'),
  });
}

export async function updateAdminSupplier(id: number, payload: AdminUpdateSupplierPayload) {
  return apiRequest(`/admin/inventory/suppliers/${id}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-supplier-update-${id}`),
  });
}

export async function createAdminPurchaseOrder(payload: AdminCreatePurchaseOrderPayload) {
  return apiRequest('/admin/inventory/purchase-orders', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-po-create'),
  });
}

export async function createAdminPurchaseOrderReceipt(
  purchaseOrderId: number,
  payload: AdminCreatePurchaseOrderReceiptPayload,
) {
  return apiRequest(`/admin/inventory/purchase-orders/${purchaseOrderId}/receipts`, {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-po-receipt-${purchaseOrderId}`),
  });
}

export async function getAdminMenuItemRecipe(menuItemId: number) {
  return apiRequest(`/admin/inventory/menu-items/${menuItemId}/recipe`, {
    method: 'GET',
  });
}

export async function upsertAdminMenuItemRecipe(
  menuItemId: number,
  payload: AdminUpsertMenuItemRecipePayload,
) {
  return apiRequest(`/admin/inventory/menu-items/${menuItemId}/recipe`, {
    method: 'PUT',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-menu-item-recipe-${menuItemId}`),
  });
}
