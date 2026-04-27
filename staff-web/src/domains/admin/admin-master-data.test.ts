import { describe, expect, it } from 'vitest';
import {
  createAdminImportCommitPayload,
  parseAdminImportRows,
  summarizeAdminImportResult,
} from './admin-master-data';

describe('admin master-data import helpers', () => {
  it('parses JSON row payloads for dry-run preview', () => {
    expect(parseAdminImportRows('[{"name":"Breakfast"}]')).toEqual({
      ok: true,
      rows: [{ name: 'Breakfast' }],
      error: null,
    });
    expect(parseAdminImportRows('{"rows":[{"branch_code":"MAIN","branch_name":"Main"}]}')).toEqual({
      ok: true,
      rows: [{ branch_code: 'MAIN', branch_name: 'Main' }],
      error: null,
    });
    expect(parseAdminImportRows('{"name":"Missing rows"}')).toMatchObject({
      ok: false,
    });
  });

  it('builds commit payloads with an idempotency key', () => {
    const payload = createAdminImportCommitPayload('menu-items', [{ code: 'COF', name: 'Coffee' }]);

    expect(payload.mode).toBe('commit');
    expect(payload.format).toBe('json');
    expect(payload.idempotencyKey).toMatch(/^sw:admin-import-menu-/);
    expect(payload.rows).toEqual([{ code: 'COF', name: 'Coffee' }]);
  });

  it('summarizes import preview and commit results', () => {
    expect(summarizeAdminImportResult({
      domain: 'menu-items',
      label: 'Menu Items',
      format: 'json',
      mode: 'commit',
      can_commit: true,
      schema: { columns: [], required_columns: [], errors: [] },
      summary: {
        total_rows: 3,
        valid_rows: 2,
        invalid_rows: 1,
        create_rows: 1,
        update_rows: 1,
        noop_rows: 0,
      },
      rows: [],
      commit: {
        batch_id: 'bulk-import-menu-items-1',
        committed_at: '2026-04-17T00:00:00Z',
        created: 1,
        updated: 1,
        unchanged: 0,
      },
    })).toMatchObject({
      totalRows: 3,
      validRows: 2,
      invalidRows: 1,
      createRows: 1,
      updateRows: 1,
      noopRows: 0,
      canCommit: true,
      batchId: 'bulk-import-menu-items-1',
      committedRows: 2,
    });
  });
});
