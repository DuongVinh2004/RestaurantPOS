/**
 * Admin Settings CRUD API
 *
 * Typed wrappers for admin/settings endpoints that are not yet covered by the
 * generated SDK client (RestaurantPosClient).  All calls go through apiRequest()
 * from the shared http helper so auth tokens, idempotency keys, and error
 * normalisation are handled consistently.
 *
 * Capability: settings.manage (enforced by backend middleware)
 */
import { apiRequest } from '../../../../shared/api/http';
import { createIdempotencyKey } from '../../../../shared/utils/idempotency';

// ─────────────────────────────────────────────────────────────
// Type definitions
// ─────────────────────────────────────────────────────────────

export type AdminBranch = {
  branch_id: number;
  branch_code: string;
  branch_name: string;
  is_default: boolean;
  is_active: boolean;
  phone: string | null;
  email: string | null;
  address: string | null;
  timezone: string | null;
  currency: string | null;
  row_version: number | null;
  created_at: string | null;
  updated_at: string | null;
  business_hours: Array<{
    day_of_week: number;
    periods: Array<{ start_time: string; end_time: string }>;
  }>;
  closure_windows: Array<{
    start_local: string | null;
    end_local: string | null;
    reason: string | null;
  }>;
  booking_policy: Record<string, unknown>;
};

export type AdminBranchEnvelope = {
  data: AdminBranch;
  meta?: Record<string, unknown>;
};

export type AdminBranchCollectionEnvelope = {
  data: Array<AdminBranch>;
  meta?: Record<string, unknown>;
};

export type AdminCreateBranchPayload = {
  branch_code: string;
  branch_name: string;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  timezone?: string | null;
  currency?: string | null;
  is_active?: boolean;
  business_hours?: Array<{ day_of_week: number; periods: Array<{ start_time: string; end_time: string }> }>;
  closure_windows?: Array<{ start_local: string | null; end_local: string | null; reason: string | null }>;
};

export type AdminUpdateBranchPayload = Partial<AdminCreateBranchPayload> & {
  row_version: number;
};

export type AdminZone = {
  zone: string;
  table_count: number;
};

export type AdminZoneCollectionEnvelope = {
  data: Array<AdminZone>;
  meta?: Record<string, unknown>;
};

export type AdminKitchenStation = {
  station_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type AdminKitchenStationEnvelope = {
  data: AdminKitchenStation;
  meta?: Record<string, unknown>;
};

export type AdminKitchenStationCollectionEnvelope = {
  data: Array<AdminKitchenStation>;
  meta?: Record<string, unknown>;
};

export type AdminCreateKitchenStationPayload = {
  name: string;
  description?: string | null;
  is_active?: boolean;
};

export type AdminUpdateKitchenStationPayload = Partial<AdminCreateKitchenStationPayload>;

export type AdminCategoryRoute = {
  route_id: number;
  station_id: number;
  category_id: number;
  category_name?: string | null;
};

export type AdminCategoryRouteCollectionEnvelope = {
  data: Array<AdminCategoryRoute>;
  meta?: Record<string, unknown>;
};

export type AdminSyncCategoryRoutesPayload = {
  routes: Array<{ category_id: number }>;
};

export type AdminTaxProfile = {
  tax_rate: number;
  service_charge_rate: number;
  currency: string | null;
  is_tax_inclusive: boolean;
  notes: string | null;
  updated_at: string | null;
};

export type AdminTaxProfileEnvelope = {
  data: AdminTaxProfile;
  meta?: Record<string, unknown>;
};

export type AdminUpsertTaxProfilePayload = {
  tax_rate: number;
  service_charge_rate?: number;
  currency?: string | null;
  is_tax_inclusive?: boolean;
  notes?: string | null;
};

// ─────────────────────────────────────────────────────────────
// Branch API
// ─────────────────────────────────────────────────────────────

export async function showAdminBranch(branchId: number): Promise<AdminBranchEnvelope> {
  return apiRequest<AdminBranchEnvelope>(`/admin/settings/branches/${branchId}`, {
    method: 'GET',
  });
}

export async function createAdminBranch(payload: AdminCreateBranchPayload): Promise<AdminBranchEnvelope> {
  return apiRequest<AdminBranchEnvelope>('/admin/settings/branches', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-branch-create-${payload.branch_code}`),
  });
}

export async function updateAdminBranch(branchId: number, payload: AdminUpdateBranchPayload): Promise<AdminBranchEnvelope> {
  return apiRequest<AdminBranchEnvelope>(`/admin/settings/branches/${branchId}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-branch-update-${branchId}`),
  });
}

// ─────────────────────────────────────────────────────────────
// Zone API
// ─────────────────────────────────────────────────────────────

export async function listAdminZones(): Promise<AdminZoneCollectionEnvelope> {
  return apiRequest<AdminZoneCollectionEnvelope>('/admin/restaurant/zones', {
    method: 'GET',
  });
}

export async function renameAdminZone(from: string, to: string): Promise<Record<string, unknown>> {
  return apiRequest<Record<string, unknown>>('/admin/restaurant/zones/rename', {
    method: 'POST',
    body: { from, to },
    idempotencyKey: createIdempotencyKey(`admin-zone-rename-${from}`),
  });
}

// ─────────────────────────────────────────────────────────────
// Restaurant Table API (update + delete)
// ─────────────────────────────────────────────────────────────

export type AdminUpdateTablePayload = {
  table_code?: string;
  branch_id?: number | null;
  template_id?: number | null;
  zone?: string | null;
  status?: 'Available' | 'Blocked' | 'Maintenance';
  price?: number | null;
  row_version: number;
};

export type AdminDeleteTablePayload = {
  row_version: number;
  force?: boolean;
};

export async function updateAdminRestaurantTable(tableId: number, payload: AdminUpdateTablePayload): Promise<Record<string, unknown>> {
  return apiRequest<Record<string, unknown>>(`/admin/restaurant/tables/${tableId}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-table-update-${tableId}`),
  });
}

export async function deleteAdminRestaurantTable(tableId: number, payload: AdminDeleteTablePayload): Promise<Record<string, unknown>> {
  return apiRequest<Record<string, unknown>>(`/admin/restaurant/tables/${tableId}`, {
    method: 'DELETE',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-table-delete-${tableId}`),
  });
}

// ─────────────────────────────────────────────────────────────
// Kitchen Station API
// ─────────────────────────────────────────────────────────────

export async function listAdminKitchenStations(): Promise<AdminKitchenStationCollectionEnvelope> {
  return apiRequest<AdminKitchenStationCollectionEnvelope>('/admin/kitchen/stations', {
    method: 'GET',
  });
}

export async function createAdminKitchenStation(payload: AdminCreateKitchenStationPayload): Promise<AdminKitchenStationEnvelope> {
  return apiRequest<AdminKitchenStationEnvelope>('/admin/kitchen/stations', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-kitchen-station-create-${payload.name}`),
  });
}

export async function updateAdminKitchenStation(stationId: number, payload: AdminUpdateKitchenStationPayload): Promise<AdminKitchenStationEnvelope> {
  return apiRequest<AdminKitchenStationEnvelope>(`/admin/kitchen/stations/${stationId}`, {
    method: 'PATCH',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-kitchen-station-update-${stationId}`),
  });
}

// ─────────────────────────────────────────────────────────────
// Category Route API
// ─────────────────────────────────────────────────────────────

export async function listAdminCategoryRoutes(stationId: number): Promise<AdminCategoryRouteCollectionEnvelope> {
  return apiRequest<AdminCategoryRouteCollectionEnvelope>(`/admin/kitchen/stations/${stationId}/category-routes`, {
    method: 'GET',
  });
}

export async function syncAdminCategoryRoutes(stationId: number, payload: AdminSyncCategoryRoutesPayload): Promise<AdminCategoryRouteCollectionEnvelope> {
  return apiRequest<AdminCategoryRouteCollectionEnvelope>(`/admin/kitchen/stations/${stationId}/category-routes`, {
    method: 'PUT',
    body: payload,
    idempotencyKey: createIdempotencyKey(`admin-category-routes-sync-${stationId}`),
  });
}

// ─────────────────────────────────────────────────────────────
// Tax Profile API
// ─────────────────────────────────────────────────────────────

export async function getAdminTaxProfile(): Promise<AdminTaxProfileEnvelope> {
  return apiRequest<AdminTaxProfileEnvelope>('/admin/settings/finance/tax-profile', {
    method: 'GET',
  });
}

export async function upsertAdminTaxProfile(payload: AdminUpsertTaxProfilePayload): Promise<AdminTaxProfileEnvelope> {
  return apiRequest<AdminTaxProfileEnvelope>('/admin/settings/finance/tax-profile', {
    method: 'POST',
    body: payload,
    idempotencyKey: createIdempotencyKey('admin-tax-profile-upsert'),
  });
}
