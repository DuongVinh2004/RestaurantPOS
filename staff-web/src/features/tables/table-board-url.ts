export type TableBoardUrlState = {
  zone: string;
};

export function readTableBoardUrlState(search: string | URLSearchParams): TableBoardUrlState {
  const params = toSearchParams(search);

  return {
    zone: params.get('zone')?.trim() ?? '',
  };
}

export function buildTableBoardSearch(
  currentSearch: string | URLSearchParams,
  patch: Partial<TableBoardUrlState>,
): string {
  const params = toSearchParams(currentSearch);
  const merged = {
    ...readTableBoardUrlState(params),
    ...patch,
  } satisfies TableBoardUrlState;

  setOrDelete(params, 'zone', merged.zone !== '' ? merged.zone : null);

  return params.toString();
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
