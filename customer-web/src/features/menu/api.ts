import type {
  CustomerMenuCategoriesCollectionEnvelope,
  CustomerMenuItemEnvelope,
  CustomerMenuItemsCollectionEnvelope,
  CustomerMenuPreorderPreviewEnvelope,
  GetV1MenuItemsQueryParams,
  PreviewMenuPreorderRequest,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";

export type MenuFilters = {
  categoryId?: number | null;
  q?: string | null;
  preorderOnly?: boolean | null;
};

export function listMenuCategories(): Promise<CustomerMenuCategoriesCollectionEnvelope> {
  return apiCall((client) => client.getV1MenuCategories({}));
}

export function listMenuItems(filters: MenuFilters = {}): Promise<CustomerMenuItemsCollectionEnvelope> {
  const query: GetV1MenuItemsQueryParams = {
    category_id: filters.categoryId ?? undefined,
    q: filters.q ?? undefined,
    preorder_only: filters.preorderOnly ?? undefined,
    per_page: 40,
  };

  return apiCall((client) => client.getV1MenuItems(query));
}

export function getMenuItem(id: number): Promise<CustomerMenuItemEnvelope> {
  return apiCall((client) => client.getV1MenuItemsId({ id }, {}));
}

export function previewMenuPreorder(body: PreviewMenuPreorderRequest): Promise<CustomerMenuPreorderPreviewEnvelope> {
  return apiCall((client) => client.postV1MenuPreorderPreview(body, idempotentOptions("menu-preorder-preview")));
}
