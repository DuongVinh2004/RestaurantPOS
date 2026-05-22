import { useQuery } from '@tanstack/react-query';
import { getStaffToken } from '../../../shared/api/client';

// ── Types ───────────────────────────────────────────────────────────────────

export type ActionPriority = 'high' | 'normal' | 'low';

export type ActionType =
  | 'reservation_upcoming'
  | 'reservation_needs_check_in'
  | 'deposit_pending'
  | 'deposit_expired'
  | 'preorder_pending'
  | 'bill_payment_pending'
  | 'checkout_pending'
  | 'waiting_list_pending';

export interface CommandCenterAction {
  id: string;
  type: ActionType;
  priority: ActionPriority;
  status: 'open';
  title: string;
  description: string;
  entity_type: string;
  entity_id: number;
  branch_id: number;
  due_at: string | null;
  deep_link: string;
  meta: Record<string, unknown>;
}

export interface CommandCenterSummary {
  open_actions: number;
  high_priority: number;
  deposit_pending: number;
  preorder_pending: number;
  payment_pending: number;
  reservation_upcoming: number;
}

export interface CommandCenterData {
  summary: CommandCenterSummary;
  actions: CommandCenterAction[];
}

export interface CommandCenterFilters {
  type?: ActionType;
  priority?: ActionPriority;
  branch_id?: number;
  horizon_hours?: number;
  limit?: number;
}

// ── API ─────────────────────────────────────────────────────────────────────

async function fetchCommandCenter(filters: CommandCenterFilters): Promise<CommandCenterData> {
  const token = getStaffToken();
  if (!token) throw new Error('Unauthenticated');

  const params = new URLSearchParams();
  if (filters.type) params.append('type', filters.type);
  if (filters.priority) params.append('priority', filters.priority);
  if (filters.branch_id) params.append('branch_id', String(filters.branch_id));
  if (filters.horizon_hours) params.append('horizon_hours', String(filters.horizon_hours));
  if (filters.limit) params.append('limit', String(filters.limit));

  const res = await fetch(`/api/v1/staff/operations/command-center?${params.toString()}`, {
    headers: { 'X-Staff-Key': token, Accept: 'application/json' },
  });

  if (!res.ok) throw new Error(`Command center API error: ${res.status}`);
  const envelope = await res.json();
  return envelope.data as CommandCenterData;
}

// ── Hook ─────────────────────────────────────────────────────────────────────

export function useCommandCenter(filters: CommandCenterFilters = {}) {
  return useQuery({
    queryKey: ['command-center', filters],
    queryFn: () => fetchCommandCenter(filters),
    staleTime: 30_000,       // 30 s
    refetchInterval: 60_000, // auto-refresh every 60 s
  });
}
