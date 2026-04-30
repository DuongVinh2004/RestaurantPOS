import type { AdminMenuCategoryQuery, AdminMenuItemPriceQuery, AdminMenuItemQuery } from '../../shared/api/staff-api';

export type AdminCatalogFilterState = {
  categoryQuery: string;
  includeDeletedCategories: boolean;
  itemQuery: string;
  availableOnly: boolean;
  categoryIdInput: string;
  selectedItemIdInput: string;
};

type CategoryLike = {
  is_deleted: boolean;
};

type ItemLike = {
  is_available: boolean;
  current_price?: unknown | null;
};

type PriceLike = {
  price: string | number | null;
};

export function buildAdminMenuCategoryQuery(
  filters: AdminCatalogFilterState,
  perPage = 12,
): AdminMenuCategoryQuery {
  return {
    q: normalizedString(filters.categoryQuery),
    include_deleted: filters.includeDeletedCategories ? true : undefined,
    per_page: perPage,
    sort: 'sort_order',
  };
}

export function buildAdminMenuItemQuery(
  filters: AdminCatalogFilterState,
  perPage = 12,
): AdminMenuItemQuery {
  return {
    q: normalizedString(filters.itemQuery),
    category_id: parsePositiveInteger(filters.categoryIdInput) ?? undefined,
    is_available: filters.availableOnly ? true : undefined,
    per_page: perPage,
    sort: 'name',
  };
}

export function buildAdminMenuItemPriceQuery(perPage = 8): AdminMenuItemPriceQuery {
  return {
    per_page: perPage,
    sort: '-effective_from',
  };
}

export function readSelectedCatalogItemId(filters: AdminCatalogFilterState): number | null {
  return parsePositiveInteger(filters.selectedItemIdInput);
}

export function summarizeAdminCatalog<TCategory extends CategoryLike, TItem extends ItemLike, TPrice extends PriceLike>(
  categories: Array<TCategory>,
  items: Array<TItem>,
  prices: Array<TPrice>,
) {
  return {
    categories: categories.length,
    deletedCategories: categories.filter((category) => category.is_deleted).length,
    items: items.length,
    availableItems: items.filter((item) => item.is_available).length,
    pricedItems: items.filter((item) => item.current_price !== null && item.current_price !== undefined).length,
    priceRows: prices.length,
  };
}

export function formatCatalogPrice(value: string | number | null | undefined, currency: string | null | undefined): string {
  const amount = numericValue(value);
  const normalizedCurrency = currency?.trim() || 'VND';
  const formattedAmount = new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount);

  return normalizedCurrency === 'VND' ? `${formattedAmount} ₫` : `${formattedAmount} ${normalizedCurrency}`;
}

function normalizedString(value: string): string | undefined {
  const normalized = value.trim();
  return normalized === '' ? undefined : normalized;
}

function parsePositiveInteger(value: string): number | null {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
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
