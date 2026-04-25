import { useQuery } from '@tanstack/react-query';
import {
  getKitchenChanges,
  getKitchenStationTickets,
  listKitchenStations,
} from '../../shared/api/staff-api';
import type { KitchenTicketStatusFilter } from './kitchen-workspace';

export const kitchenQueryKeys = {
  stationsRoot: () => ['kitchen-stations'] as const,
  stations: (branchId: number | null) => ['kitchen-stations', branchId] as const,
  ticketsRoot: () => ['kitchen-tickets'] as const,
  tickets: (branchId: number | null, stationId: number | null, status: KitchenTicketStatusFilter) => [
    'kitchen-tickets',
    branchId,
    stationId,
    status,
  ] as const,
  changes: (branchId: number | null, currentVersion: number | null | undefined) => ['kitchen-changes', branchId, currentVersion] as const,
};

export function useKitchenStationsQuery({
  branchId,
  enabled = true,
}: {
  branchId: number | null;
  enabled?: boolean;
}) {
  return useQuery({
    queryKey: kitchenQueryKeys.stations(branchId),
    queryFn: () => listKitchenStations(branchId ?? undefined),
    enabled,
    refetchInterval: 20_000,
  });
}

export function useKitchenTicketsQuery({
  branchId,
  stationId,
  status,
  enabled = true,
}: {
  branchId: number | null;
  stationId: number | null;
  status: KitchenTicketStatusFilter;
  enabled?: boolean;
}) {
  return useQuery({
    queryKey: kitchenQueryKeys.tickets(branchId, stationId, status),
    queryFn: () => getKitchenStationTickets(stationId as number, {
      branch_id: branchId ?? undefined,
      status: status === 'all' ? undefined : status,
      include_terminal: status === 'Completed' || status === 'Cancelled',
    }),
    enabled: enabled && stationId !== null,
    refetchInterval: 15_000,
  });
}

export function useKitchenChangesQuery({
  branchId,
  currentVersion,
  enabled = true,
}: {
  branchId: number | null;
  currentVersion: number | null | undefined;
  enabled?: boolean;
}) {
  return useQuery({
    queryKey: kitchenQueryKeys.changes(branchId, currentVersion),
    queryFn: () => getKitchenChanges(currentVersion ?? undefined, branchId ?? undefined),
    enabled: enabled && currentVersion !== null && currentVersion !== undefined,
    refetchInterval: 20_000,
  });
}
