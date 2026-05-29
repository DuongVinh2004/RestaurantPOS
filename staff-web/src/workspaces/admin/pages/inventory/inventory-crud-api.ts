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

export async function createAdminIngredient(payload: AdminCreateIngredientPayload) {
  return apiRequest('/api/v1/admin/inventory/ingredients', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-ingredient-create'),
  });
}

export async function updateAdminIngredient(id: number, payload: AdminUpdateIngredientPayload) {
  return apiRequest(`/api/v1/admin/inventory/ingredients/${id}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-ingredient-update-${id}`),
  });
}

export async function createAdminSupplier(payload: AdminCreateSupplierPayload) {
  return apiRequest('/api/v1/admin/inventory/suppliers', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-supplier-create'),
  });
}

export async function updateAdminSupplier(id: number, payload: AdminUpdateSupplierPayload) {
  return apiRequest(`/api/v1/admin/inventory/suppliers/${id}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-supplier-update-${id}`),
  });
}

export async function createAdminPurchaseOrder(payload: AdminCreatePurchaseOrderPayload) {
  return apiRequest('/api/v1/admin/inventory/purchase-orders', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-po-create'),
  });
}
