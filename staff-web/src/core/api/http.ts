import { readStoredStaffToken } from '../auth/storage';
import { notifyStaffAuthFailure } from '../auth/session-events';
import { apiBaseUrl } from '../config/env';

export class StaffApiError<TPayload = unknown> extends Error {
  status: number;
  payload: TPayload;

  constructor(status: number, payload: TPayload, message: string) {
    super(message);
    this.name = 'StaffApiError';
    this.status = status;
    this.payload = payload;
  }
}

export type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
  query?: Record<string, unknown>;
  body?: unknown;
  signal?: AbortSignal;
  token?: string | null;
  idempotencyKey?: string;
  headers?: Record<string, string>;
};

export async function apiRequest<TResponse>(path: string, options: RequestOptions = {}): Promise<TResponse> {
  const normalizedPath = normalizePath(path);
  const url = new URL(resolvePath(normalizedPath), window.location.origin);

  appendQuery(url, options.query);

  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');

  const token = options.token ?? readStoredStaffToken();
  if (token) {
    headers.set('X-Staff-Key', token);
  }

  if (options.idempotencyKey) {
    headers.set('Idempotency-Key', options.idempotencyKey);
  }

  let body: BodyInit | undefined;
  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(options.body);
  }

  const response = await fetch(url.toString(), {
    method: options.method ?? (options.body === undefined ? 'GET' : 'POST'),
    headers,
    body,
    signal: options.signal,
  });

  const payload = await parseResponse(response);

  if (!response.ok) {
    if (response.status === 401 && token && normalizedPath !== '/auth/staff/logout') {
      notifyStaffAuthFailure({
        status: response.status,
        path: normalizedPath,
      });
    }

    throw new StaffApiError(response.status, payload, response.statusText || 'Request failed');
  }

  return payload as TResponse;
}

function resolvePath(path: string): string {
  return `${apiBaseUrl}${normalizePath(path)}`;
}

function normalizePath(path: string): string {
  return path.startsWith('/') ? path : `/${path}`;
}

function appendQuery(url: URL, query?: Record<string, unknown>): void {
  if (!query) {
    return;
  }

  for (const [key, value] of Object.entries(query)) {
    if (
      value === null
      || value === undefined
      || value === ''
      || typeof value === 'object'
      || typeof value === 'function'
      || typeof value === 'symbol'
      || typeof value === 'bigint'
    ) {
      continue;
    }

    url.searchParams.set(key, String(value));
  }
}

async function parseResponse(response: Response): Promise<unknown> {
  const contentType = response.headers.get('content-type') ?? '';

  if (contentType.includes('application/json')) {
    return response.json();
  }

  const text = await response.text();
  return text.trim() === '' ? null : { message: text };
}
