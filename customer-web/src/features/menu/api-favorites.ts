import { publicEnv } from "@/lib/config/env";
import { getCustomerToken } from "@/lib/auth/storage";
import { normalizeApiError } from "@/lib/api/errors";
import { createIdempotencyKey } from "@/lib/api/idempotency";

export async function fetchFavorites(): Promise<number[]> {
  const token = getCustomerToken();
  if (!token) return [];

  try {
    const res = await fetch(`${publicEnv.apiBaseUrl}/api/v1/me/favorites`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "customer-web",
        "X-Customer-Token": token,
      },
    });
    if (!res.ok) throw new Error("Failed to fetch favorites");
    const json = await res.json();
    return json.data || [];
  } catch (error) {
    throw normalizeApiError(error);
  }
}

export async function addFavorite(menuItemId: number): Promise<void> {
  const token = getCustomerToken();
  if (!token) return;

  try {
    const res = await fetch(`${publicEnv.apiBaseUrl}/api/v1/me/favorites`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "customer-web",
        "X-Customer-Token": token,
        "Idempotency-Key": createIdempotencyKey(`favorite-add-${menuItemId}`),
      },
      body: JSON.stringify({ menu_item_id: menuItemId }),
    });
    if (!res.ok) throw new Error("Failed to add favorite");
  } catch (error) {
    throw normalizeApiError(error);
  }
}

export async function removeFavorite(menuItemId: number): Promise<void> {
  const token = getCustomerToken();
  if (!token) return;

  try {
    const res = await fetch(`${publicEnv.apiBaseUrl}/api/v1/me/favorites/${menuItemId}`, {
      method: "DELETE",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "customer-web",
        "X-Customer-Token": token,
        "Idempotency-Key": createIdempotencyKey(`favorite-remove-${menuItemId}`),
      },
    });
    if (!res.ok) throw new Error("Failed to remove favorite");
  } catch (error) {
    throw normalizeApiError(error);
  }
}

export async function syncFavorites(menuItemIds: number[]): Promise<void> {
  const token = getCustomerToken();
  if (!token || menuItemIds.length === 0) return;

  try {
    const res = await fetch(`${publicEnv.apiBaseUrl}/api/v1/me/favorites/sync`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "customer-web",
        "X-Customer-Token": token,
        "Idempotency-Key": createIdempotencyKey(`favorite-sync-${menuItemIds.join(",")}`),
      },
      body: JSON.stringify({ menu_item_ids: menuItemIds }),
    });
    if (!res.ok) throw new Error("Failed to sync favorites");
  } catch (error) {
    throw normalizeApiError(error);
  }
}
