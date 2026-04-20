import type {
  CustomerMenuCategoriesCollectionEnvelope,
  CustomerMenuItemEnvelope,
  CustomerMenuItemsCollectionEnvelope,
  CustomerMenuPreorderPreviewEnvelope,
  GetV1MenuItemsQueryParams,
  PreviewMenuPreorderRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";

export type MenuFilters = {
  categoryId?: number | null;
  q?: string | null;
  preorderOnly?: boolean | null;
};

export type MenuCategories = CustomerMenuCategoriesCollectionEnvelope["data"];
export type MenuItems = CustomerMenuItemsCollectionEnvelope["data"];
export type MenuItem = CustomerMenuItemEnvelope["data"];
export type MenuPreorderPreview = CustomerMenuPreorderPreviewEnvelope["data"];

export function listMenuCategories(): Promise<MenuCategories> {
  return apiCall((client) => client.getV1MenuCategories({})).then(unwrapData);
}

export function listMenuItems(filters: MenuFilters = {}): Promise<MenuItems> {
  const query: GetV1MenuItemsQueryParams = {
    category_id: filters.categoryId ?? undefined,
    q: filters.q ?? undefined,
    preorder_only: filters.preorderOnly ?? undefined,
    per_page: 40,
  };

  return apiCall((client) => client.getV1MenuItems(query)).then(unwrapData);
}

export function getMenuItem(id: number): Promise<MenuItem> {
  return apiCall((client) => client.getV1MenuItemsId({ id }, {})).then(unwrapData);
}

export function previewMenuPreorder(body: PreviewMenuPreorderRequest): Promise<MenuPreorderPreview> {
  return apiCall((client) => client.postV1MenuPreorderPreview(body, idempotentOptions("menu-preorder-preview"))).then(unwrapData);
}
