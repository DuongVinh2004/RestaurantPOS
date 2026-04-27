import type {
  AdminMasterDataCommitPayload,
  AdminMasterDataImportDomain,
  AdminMasterDataImportResult,
  AdminMasterDataImportRow,
} from '../../shared/api/staff-api';
import { createIdempotencyKey } from '../../shared/utils/idempotency';

export type AdminImportDomainOption = {
  domain: AdminMasterDataImportDomain;
  label: string;
  capability: string;
  requiredColumns: Array<string>;
};

export type ParsedAdminImportRows =
  | {
      ok: true;
      rows: Array<AdminMasterDataImportRow>;
      error: null;
    }
  | {
      ok: false;
      rows: [];
      error: string;
    };

export const settingsImportDomains: Array<AdminImportDomainOption> = [
  {
    domain: 'branches',
    label: 'Branches',
    capability: 'settings.manage',
    requiredColumns: ['branch_code', 'branch_name'],
  },
  {
    domain: 'restaurant-tables',
    label: 'Restaurant tables',
    capability: 'settings.manage',
    requiredColumns: ['branch_code', 'table_code', 'template_code'],
  },
];

export const catalogImportDomains: Array<AdminImportDomainOption> = [
  {
    domain: 'menu-categories',
    label: 'Menu categories',
    capability: 'menu.manage',
    requiredColumns: ['name'],
  },
  {
    domain: 'menu-items',
    label: 'Menu items',
    capability: 'menu.manage',
    requiredColumns: ['code', 'name'],
  },
  {
    domain: 'menu-prices',
    label: 'Menu prices',
    capability: 'menu.manage',
    requiredColumns: ['item_code', 'price', 'effective_from'],
  },
];

export function parseAdminImportRows(input: string): ParsedAdminImportRows {
  const trimmed = input.trim();

  if (trimmed === '') {
    return {
      ok: false,
      rows: [],
      error: 'Provide a JSON array of import rows before previewing.',
    };
  }

  try {
    const decoded = JSON.parse(trimmed) as unknown;
    const rows = normalizeDecodedRows(decoded);

    if (!rows) {
      return {
        ok: false,
        rows: [],
        error: 'Import JSON must be an array of objects or an object with a rows array.',
      };
    }

    if (rows.length === 0) {
      return {
        ok: false,
        rows: [],
        error: 'Import rows cannot be empty.',
      };
    }

    return {
      ok: true,
      rows,
      error: null,
    };
  } catch {
    return {
      ok: false,
      rows: [],
      error: 'Import JSON is malformed.',
    };
  }
}

export function createAdminImportCommitPayload(
  domain: AdminMasterDataImportDomain,
  rows: Array<AdminMasterDataImportRow>,
): AdminMasterDataCommitPayload {
  return {
    mode: 'commit',
    format: 'json',
    rows,
    idempotencyKey: createIdempotencyKey(`admin-import-${domain}`),
  };
}

export function summarizeAdminImportResult(result: AdminMasterDataImportResult | null) {
  const summary = result?.summary ?? {};
  const commit = result?.commit ?? null;

  return {
    totalRows: numberValue(summary.total_rows),
    validRows: numberValue(summary.valid_rows),
    invalidRows: numberValue(summary.invalid_rows),
    createRows: numberValue(summary.create_rows),
    updateRows: numberValue(summary.update_rows),
    noopRows: numberValue(summary.noop_rows),
    canCommit: result?.can_commit === true,
    batchId: commit?.batch_id ?? null,
    committedRows: commit ? commit.created + commit.updated + commit.unchanged : 0,
  };
}

function normalizeDecodedRows(decoded: unknown): Array<AdminMasterDataImportRow> | null {
  const source = Array.isArray(decoded)
    ? decoded
    : decoded && typeof decoded === 'object' && Array.isArray((decoded as { rows?: unknown }).rows)
      ? (decoded as { rows: Array<unknown> }).rows
      : null;

  if (!source) {
    return null;
  }

  if (!source.every((row) => row && typeof row === 'object' && !Array.isArray(row))) {
    return null;
  }

  return source.map((row) => row as AdminMasterDataImportRow);
}

function numberValue(value: unknown): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : 0;
}
