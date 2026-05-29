import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { staffClient } from './client';
import {
  createAdminMenuCategory,
  createAdminMenuItem,
  createAdminMenuItemPrice,
  createAdminRestaurantTable,
  getKitchenChanges,
  getTableBoardChanges,
  getWaitingListChanges,
  importAdminMasterData,
  listAdminRestaurantTableTemplates,
  payOrder,
  updateOrderItem,
  updateOrderItemStatus,
} from './staff-api';

const rawApiRequestOperationAllowlist = [
  'GET /admin/benefits/loyalty-tiers/export',
  'GET /admin/benefits/vouchers/export',
  'GET /admin/inventory/purchase-orders/{id}',
  'GET /admin/menu/categories/export',
  'GET /admin/menu/items/export',
  'GET /admin/menu/prices/export',
  'GET /admin/restaurant/tables/export',
  'GET /admin/settings/branches/export',
  'PATCH /reservations/{id}/status',
  'POST /admin/benefits/loyalty-tiers/import',
  'POST /admin/benefits/vouchers/import',
  'POST /admin/menu/categories/import',
  'POST /admin/menu/items/import',
  'POST /admin/menu/prices/import',
  'POST /admin/restaurant/tables/import',
  'POST /admin/settings/branches/import',
];

describe('staff api generated client delegates', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('uses generated SDK methods for branch-scoped operational change feeds', async () => {
    const tableBoardSpy = vi.spyOn(staffClient, 'getV1StaffTablesBoardChanges').mockResolvedValue({} as never);
    const kitchenSpy = vi.spyOn(staffClient, 'getV1StaffKitchenChanges').mockResolvedValue({} as never);
    const waitingListSpy = vi.spyOn(staffClient, 'getV1StaffWaitingListChanges').mockResolvedValue({} as never);

    await getTableBoardChanges(11, 3);
    await getKitchenChanges(12, 4);
    await getWaitingListChanges(13, 5);

    expect(tableBoardSpy).toHaveBeenCalledWith({ after_version: 11, branch_id: 3, limit: 20 });
    expect(kitchenSpy).toHaveBeenCalledWith({ after_version: 12, branch_id: 4, limit: 20 });
    expect(waitingListSpy).toHaveBeenCalledWith({ after_version: 13, branch_id: 5, limit: 20 });
  });

  it('uses generated SDK methods for admin create contracts', async () => {
    const tableSpy = vi.spyOn(staffClient, 'postV1AdminRestaurantTables').mockResolvedValue({ data: {} } as never);
    const templatesSpy = vi.spyOn(staffClient, 'getV1AdminRestaurantTableTemplates').mockResolvedValue({ data: [] } as never);
    const categorySpy = vi.spyOn(staffClient, 'postV1AdminMenuCategories').mockResolvedValue({ data: {} } as never);
    const itemSpy = vi.spyOn(staffClient, 'postV1AdminMenuItems').mockResolvedValue({ data: {} } as never);
    const priceSpy = vi.spyOn(staffClient, 'postV1AdminMenuItemsItemIdPrices').mockResolvedValue({ data: {} } as never);

    await listAdminRestaurantTableTemplates();
    await createAdminRestaurantTable({ table_code: 'T-1', template_id: 2, branch_id: 1 });
    await createAdminMenuCategory({ name: 'Breakfast' });
    await createAdminMenuItem({ name: 'Coffee', code: 'COF' });
    await createAdminMenuItemPrice(9, { price: 45000, effective_from: '2026-04-17T00:00:00Z' });

    expect(templatesSpy).toHaveBeenCalled();
    expect(tableSpy).toHaveBeenCalledWith(
      { table_code: 'T-1', template_id: 2, branch_id: 1 },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
    expect(categorySpy).toHaveBeenCalledWith(
      { name: 'Breakfast' },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
    expect(itemSpy).toHaveBeenCalledWith(
      { name: 'Coffee', code: 'COF' },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
    expect(priceSpy).toHaveBeenCalledWith(
      { item_id: 9 },
      { price: 45000, effective_from: '2026-04-17T00:00:00Z' },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
  });

  it('uses generated SDK methods for guarded order item writes', async () => {
    const updateSpy = vi.spyOn(staffClient, 'patchV1StaffOrdersOrderIdItemsOrderItemId').mockResolvedValue({ data: {} } as never);
    const statusSpy = vi.spyOn(staffClient, 'postV1StaffOrdersOrderIdItemsOrderItemIdStatus').mockResolvedValue({ data: {} } as never);

    await updateOrderItem(101, 202, {
      qty: 3,
      note: 'No onion',
      order_row_version: 4,
      row_version: 2,
    });
    await updateOrderItemStatus(101, 202, {
      status: 'Served',
      order_row_version: 5,
      row_version: 3,
    });

    expect(updateSpy).toHaveBeenCalledWith(
      { order_id: 101, order_item_id: 202 },
      {
        qty: 3,
        note: 'No onion',
        order_row_version: 4,
        row_version: 2,
      },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
    expect(statusSpy).toHaveBeenCalledWith(
      { order_id: 101, order_item_id: 202 },
      {
        status: 'Served',
        order_row_version: 5,
        row_version: 3,
      },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
  });

  it('uses generated SDK method for guarded staff order pay writes', async () => {
    const paySpy = vi.spyOn(staffClient, 'postV1StaffOrdersOrderIdPay').mockResolvedValue({ data: {} } as never);

    await payOrder(303, {
      payment_method: 'Cash',
      payment_provider: 'Cash',
      paid_amount: 125000,
      currency: 'VND',
      transaction_code: 'SW-PAY-303',
      notes: 'Counter payment',
      row_version: 6,
    });

    expect(paySpy).toHaveBeenCalledWith(
      { order_id: 303 },
      {
        payment_method: 'Cash',
        payment_provider: 'Cash',
        paid_amount: 125000,
        currency: 'VND',
        transaction_code: 'SW-PAY-303',
        notes: 'Counter payment',
        row_version: 6,
      },
      expect.objectContaining({ idempotencyKey: expect.stringMatching(/^sw:/) }),
    );
  });

  it('fails admin import commit before transport when an idempotency key is missing', async () => {
    await expect(importAdminMasterData('branches', {
      mode: 'commit',
      format: 'json',
      rows: [{ branch_code: 'MAIN', branch_name: 'Main' }],
      idempotencyKey: '',
    })).rejects.toThrow('Idempotency-Key');
  });

  it('keeps raw apiRequest operation additions explicit and method-scoped', () => {
    const staffApiPath = [
      resolve(process.cwd(), 'src/shared/api/staff-api.ts'),
      resolve(process.cwd(), 'staff-web/src/shared/api/staff-api.ts'),
    ].find((candidate) => existsSync(candidate));

    if (!staffApiPath) {
      throw new Error('Unable to locate staff-api.ts for raw apiRequest contract check.');
    }

    const source = readFileSync(staffApiPath, 'utf8');
    const rawOperations = collectRawApiRequestOperations(source);

    expect(rawOperations).toEqual([...rawApiRequestOperationAllowlist].sort());
  });
});

function collectRawApiRequestOperations(source: string): Array<string> {
  const operations = new Set<string>();
  const callPattern = /apiRequest(?:<[^>]+>)?\(\s*([`'"])(\/[^`'"]+)\1/g;

  for (const match of source.matchAll(callPattern)) {
    const callStart = match.index ?? 0;
    const callTail = source.slice(callStart);
    const callEnd = callTail.indexOf(');');
    const callSource = callEnd >= 0 ? callTail.slice(0, callEnd) : callTail;
    const method = callSource.match(/method:\s*['"]([A-Z]+)['"]/)?.[1] ?? 'GET';
    const path = match[2].replace(/\$\{[^}]+\}/g, '{id}');

    operations.add(`${method} ${path}`);
  }

  return [...operations].sort();
}
