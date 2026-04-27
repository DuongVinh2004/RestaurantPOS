import { describe, expect, it } from 'vitest';
import {
  adminPurchaseOrderTone,
  buildAdminIngredientMovementQuery,
  buildAdminIngredientQuery,
  buildAdminPurchaseOrderQuery,
  buildAdminSupplierQuery,
  formatInventoryQuantity,
  inventoryMovementTone,
  summarizeAdminIngredientMovements,
  summarizeAdminIngredients,
  summarizeAdminPurchaseOrders,
  summarizeAdminPurchaseReceipts,
  summarizeAdminSuppliers,
  type AdminInventoryFilterState,
} from './admin-inventory';

const defaultFilters: AdminInventoryFilterState = {
  ingredientQuery: 'beans',
  ingredientActiveOnly: true,
  supplierQuery: 'roaster',
  supplierActiveOnly: false,
  purchaseOrderQuery: '',
  purchaseOrderStatus: 'Draft',
  branchIdInput: '',
};

describe('admin inventory helpers', () => {
  it('builds scoped admin inventory queries', () => {
    expect(buildAdminIngredientQuery(defaultFilters)).toEqual({
      q: 'beans',
      is_active: true,
      per_page: 8,
      sort: 'name',
    });
    expect(buildAdminSupplierQuery(defaultFilters)).toEqual({
      q: 'roaster',
      is_active: undefined,
      per_page: 8,
      sort: 'name',
    });
    expect(buildAdminPurchaseOrderQuery(defaultFilters, 7)).toEqual({
      q: undefined,
      branch_id: 7,
      purchase_order_status: 'Draft',
      per_page: 8,
      sort: '-created_at',
    });
    expect(buildAdminIngredientMovementQuery(7)).toEqual({
      branch_id: 7,
      per_page: 8,
      sort: '-created_at',
    });
  });

  it('summarizes ingredient, supplier, and purchase-order reads', () => {
    expect(summarizeAdminIngredients([
      { is_active: true, recipe_usage_count: 2, stock: { on_hand: 0 } },
      { is_active: false, recipe_usage_count: 4, stock: { on_hand: '3.5' } },
    ])).toEqual({
      displayedCount: 2,
      activeCount: 1,
      zeroStockCount: 1,
      recipeUsageCount: 6,
    });

    expect(summarizeAdminSuppliers([
      { is_active: true, phone: '0909' },
      { is_active: false, phone: null },
    ])).toEqual({
      displayedCount: 2,
      activeCount: 1,
      withPhoneCount: 1,
    });

    expect(summarizeAdminPurchaseOrders([
      { purchase_order_status: 'Draft', summary: { receipt_count: 1, remaining_total_quantity: 4 } },
      { purchase_order_status: 'Received', summary: { receipt_count: 3, remaining_total_quantity: '0' } },
    ])).toEqual({
      displayedCount: 2,
      openCount: 1,
      receiptCount: 4,
      remainingQuantity: 4,
    });
    expect(summarizeAdminIngredientMovements([
      { movement_type: 'AdjustmentIncrease', quantity_delta: '3.5', created_by: 7 },
      { movement_type: 'Wastage', quantity_delta: '-1', created_by: null },
    ])).toEqual({
      displayedCount: 2,
      adjustmentCount: 1,
      wastageCount: 1,
      netQuantity: 2.5,
      auditedCount: 1,
    });
    expect(summarizeAdminPurchaseReceipts([
      { receipt_status: 'Received', summary: { received_total_quantity: '4' } },
    ])).toEqual({
      displayedCount: 1,
      receivedCount: 1,
      receivedQuantity: 4,
    });
  });

  it('formats quantities and PO tone safely', () => {
    expect(formatInventoryQuantity('3.125')).toBe('3.125');
    expect(adminPurchaseOrderTone('Received')).toBe('success');
    expect(adminPurchaseOrderTone('Cancelled')).toBe('error');
    expect(inventoryMovementTone('Wastage')).toBe('error');
  });
});
