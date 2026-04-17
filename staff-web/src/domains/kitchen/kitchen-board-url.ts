import type { KitchenTicketStatusFilter } from './kitchen-workspace';
import { isKitchenTicketStatusFilter } from './kitchen-workspace';

export type KitchenBoardUrlState = {
  status: KitchenTicketStatusFilter;
  ticketId: number | null;
};

export function readKitchenBoardUrlState(search: string | URLSearchParams): KitchenBoardUrlState {
  const params = toSearchParams(search);

  return {
    status: readStatus(params.get('status')),
    ticketId: readPositiveInteger(params.get('ticket')),
  };
}

export function buildKitchenBoardSearch(
  currentSearch: string | URLSearchParams,
  patch: Partial<KitchenBoardUrlState>,
): string {
  const params = toSearchParams(currentSearch);
  const merged = {
    ...readKitchenBoardUrlState(params),
    ...patch,
  } satisfies KitchenBoardUrlState;

  setOrDelete(params, 'status', merged.status !== 'all' ? merged.status : null);
  setOrDelete(params, 'ticket', merged.ticketId ? String(merged.ticketId) : null);

  return params.toString();
}

function readStatus(value: string | null): KitchenTicketStatusFilter {
  return value !== null && isKitchenTicketStatusFilter(value)
    ? value
    : 'all';
}

function readPositiveInteger(value: string | null): number | null {
  if (!value) {
    return null;
  }

  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function setOrDelete(params: URLSearchParams, key: string, value: string | null): void {
  if (!value) {
    params.delete(key);
    return;
  }

  params.set(key, value);
}
