import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { staffClient } from './client';
import { getKitchenChanges, getTableBoardChanges, getWaitingListChanges } from './staff-api';

const rawApiRequestOperationAllowlist = [
  'GET /staff/finance/invoices/{id}',
  'GET /staff/finance/reconciliation',
  'GET /staff/finance/reconciliation/{id}',
  'GET /staff/reservations/{id}/active-order',
  'GET /staff/tables/{id}/active-order',
  'PATCH /reservations/{id}/status',
  'PATCH /staff/orders/{id}/items/{id}',
  'POST /staff/finance/invoices/{id}/issue',
  'POST /staff/orders/{id}/items/{id}/status',
  'POST /staff/reservations/{id}/assign-best-fit',
  'POST /staff/reservations/{id}/assign-table',
  'POST /staff/reservations/{id}/move-table',
  'POST /staff/tables/{id}/release',
  'POST /staff/waiting-list',
  'POST /staff/waiting-list/{id}/advance',
  'POST /staff/waiting-list/{id}/cancel',
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
