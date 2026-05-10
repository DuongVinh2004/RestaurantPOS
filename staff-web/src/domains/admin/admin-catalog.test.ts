import { describe, expect, it } from 'vitest';
import {
  buildAdminMenuCategoryQuery,
  buildAdminMenuItemQuery,
  formatCatalogPrice,
  readSelectedCatalogItemId,
  resolveSelectedCatalogItem,
  summarizeAdminCatalog,
  type AdminCatalogFilterState,
} from './admin-catalog';

const filters: AdminCatalogFilterState = {
  categoryQuery: ' breakfast ',
  includeDeletedCategories: false,
  itemQuery: ' coffee ',
  availableOnly: true,
  categoryIdInput: '3',
  selectedItemIdInput: '12',
};

describe('admin catalog helpers', () => {
  it('builds menu category and item read queries', () => {
    expect(buildAdminMenuCategoryQuery(filters)).toEqual({
      q: 'breakfast',
      include_deleted: undefined,
      per_page: 12,
      sort: 'sort_order',
    });
    expect(buildAdminMenuItemQuery(filters)).toEqual({
      q: 'coffee',
      category_id: 3,
      is_available: true,
      per_page: 12,
      sort: 'name',
    });
    expect(readSelectedCatalogItemId(filters)).toBe(12);
  });

  it('keeps direct item id selection even when the current list no longer includes that item', () => {
    expect(resolveSelectedCatalogItem(77, [
      { item_id: 12, name: 'Coffee' },
    ])).toEqual({
      itemId: 77,
      item: null,
      displayLabel: 'Món #77',
      outsideCurrentResults: true,
    });
  });

  it('summarizes catalog reads and prices', () => {
    expect(summarizeAdminCatalog(
      [{ is_deleted: false }, { is_deleted: true }],
      [{ is_available: true, current_price: { price: '4' } }, { is_available: false, current_price: null }],
      [{ price: '4' }],
    )).toEqual({
      categories: 2,
      deletedCategories: 1,
      items: 2,
      availableItems: 1,
      pricedItems: 1,
      priceRows: 1,
    });
    expect(formatCatalogPrice('1234.5', 'VND')).toBe('1.234,5 ₫');
  });
});
