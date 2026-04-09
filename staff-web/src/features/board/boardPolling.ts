import type { StaffOperationalRealtimeEnvelope } from '../../api/sdk';

const VISIBLE_POLL_FLOOR_MS = 15000;
const HIDDEN_POLL_FLOOR_MS = 60000;

export function resolveOperationalPollDelay(hints: Array<number | null | undefined>, isDocumentVisible: boolean): number {
  const positiveHints = hints.filter((value): value is number => typeof value === 'number' && value > 0);
  const hintedDelay = positiveHints.length > 0 ? Math.max(...positiveHints) : 30000;

  return Math.max(hintedDelay, isDocumentVisible ? VISIBLE_POLL_FLOOR_MS : HIDDEN_POLL_FLOOR_MS);
}

export function shouldRefetchOperationalSlice(payload: StaffOperationalRealtimeEnvelope | null): boolean {
  return Boolean(payload?.data.has_changes || payload?.data.stale_cursor);
}
