import type {
  KitchenOrderItemTicket,
  KitchenStation,
  StaffOperationalRealtimeState,
} from '../../shared/api/sdk';
import { can } from '../../shared/auth/capabilities';
import type { StaffSession } from '../../shared/auth/storage';
import { isWorkspaceAvailable } from '../../workspaces/workspaces';

export const kitchenTicketStatusOptions = [
  { value: 'all', label: 'All tickets' },
  { value: 'Queued', label: 'Queued' },
  { value: 'Fired', label: 'In prep' },
  { value: 'Ready', label: 'Ready' },
  { value: 'Completed', label: 'Completed' },
  { value: 'Cancelled', label: 'Cancelled' },
] as const;

export type KitchenTicketStatusFilter = (typeof kitchenTicketStatusOptions)[number]['value'];
export type KitchenActiveLaneStatus = 'Queued' | 'Fired' | 'Ready';
export type KitchenTicketAction = 'fire' | 'bump' | 'recall';

export type KitchenGuardKind =
  | 'missing-workspace-access'
  | 'missing-branch'
  | 'invalid-branch'
  | 'missing-assigned-station'
  | 'station-selection-required'
  | 'invalid-station';

export type KitchenGuard = {
  kind: KitchenGuardKind;
  title: string;
  description: string;
  meta?: string;
};

export type KitchenStationContext = {
  assignedStationIds: Array<number>;
  selectableStations: Array<KitchenStation>;
  selectedStation: KitchenStation | null;
  selectedStationId: number | null;
  guard: KitchenGuard | null;
};

export type KitchenLane = {
  status: KitchenActiveLaneStatus;
  label: string;
  description: string;
  tickets: Array<KitchenOrderItemTicket>;
};

export type KitchenTicketTimelineEntry = {
  key: string;
  label: string;
  value: string | null;
  active: boolean;
};

export const kitchenActiveLaneDefinitions: Array<Omit<KitchenLane, 'tickets'>> = [
  {
    status: 'Queued',
    label: 'Queue',
    description: 'Tickets waiting to be fired.',
  },
  {
    status: 'Fired',
    label: 'In prep',
    description: 'Tickets actively being prepared.',
  },
  {
    status: 'Ready',
    label: 'Ready',
    description: 'Tickets waiting for service pickup.',
  },
];

export function isKitchenTicketStatusFilter(value: string): value is KitchenTicketStatusFilter {
  return kitchenTicketStatusOptions.some((option) => option.value === value);
}

export function getAssignedKitchenStationIds(session: StaffSession | null): Array<number> {
  return session?.startup.assigned_station_ids ?? [];
}

export function resolveKitchenWorkspaceGuard(session: StaffSession | null): KitchenGuard | null {
  if (!session || isWorkspaceAvailable(session, 'kitchen')) {
    return null;
  }

  return {
    kind: 'missing-workspace-access',
    title: 'Kitchen workspace is not available',
    description: 'This staff session does not include kitchen workspace access. Use an allowed workspace or refresh the staff session after access changes.',
  };
}

export function canDispatchKitchenOrder(session: StaffSession | null): boolean {
  return !!session && can(session, 'order.manage');
}

export function resolveKitchenBranchGuard(session: StaffSession | null, branchId: number | null): KitchenGuard | null {
  if (!session) {
    return null;
  }

  const allowedBranchIds = session.startup.allowed_branch_ids ?? session.startup.branch_access.accessible_branch_ids ?? [];

  if (branchId === null) {
    return {
      kind: 'missing-branch',
      title: 'Select a branch before opening the line',
      description: 'Kitchen tickets are branch scoped. Choose a branch from the shell before loading stations or changing ticket state.',
    };
  }

  if (allowedBranchIds.length > 0 && !allowedBranchIds.includes(branchId)) {
    return {
      kind: 'invalid-branch',
      title: 'Branch is outside this staff session',
      description: 'The selected branch is not included in the startup branch contract for this staff session.',
      meta: `Branch #${branchId}`,
    };
  }

  return null;
}

export function resolveKitchenStationContext({
  session,
  stations,
  requestedStationId,
}: {
  session: StaffSession | null;
  stations: Array<KitchenStation>;
  requestedStationId: number | null;
}): KitchenStationContext {
  const assignedStationIds = getAssignedKitchenStationIds(session);
  const assignedStationSet = new Set(assignedStationIds);
  const selectableStations = assignedStationIds.length > 0
    ? stations.filter((station) => assignedStationSet.has(station.station_id))
    : stations;

  if (session && assignedStationIds.length === 0) {
    return {
      assignedStationIds,
      selectableStations: [],
      selectedStation: null,
      selectedStationId: null,
      guard: {
        kind: 'missing-assigned-station',
        title: 'No kitchen station is assigned',
        description: 'The startup contract did not include an assigned station for this staff session. Ask an operator to assign a station before taking ticket actions.',
      },
    };
  }

  if (requestedStationId !== null) {
    if (assignedStationIds.length > 0 && !assignedStationSet.has(requestedStationId)) {
      return invalidStationContext(assignedStationIds, selectableStations, requestedStationId);
    }

    const requestedStation = stations.find((station) => station.station_id === requestedStationId) ?? null;
    if (requestedStation) {
      return {
        assignedStationIds,
        selectableStations,
        selectedStation: requestedStation,
        selectedStationId: requestedStation.station_id,
        guard: null,
      };
    }

    if (stations.length > 0) {
      return invalidStationContext(assignedStationIds, selectableStations, requestedStationId);
    }
  }

  if (assignedStationIds.length === 1) {
    const assignedStation = stations.find((station) => station.station_id === assignedStationIds[0]) ?? null;

    if (assignedStation) {
      return {
        assignedStationIds,
        selectableStations,
        selectedStation: assignedStation,
        selectedStationId: assignedStation.station_id,
        guard: null,
      };
    }

    if (stations.length > 0) {
      return invalidStationContext(assignedStationIds, selectableStations, assignedStationIds[0]);
    }
  }

  if (!session && stations[0]) {
    return {
      assignedStationIds,
      selectableStations,
      selectedStation: stations[0],
      selectedStationId: stations[0].station_id,
      guard: null,
    };
  }

  if (selectableStations.length === 1) {
    return {
      assignedStationIds,
      selectableStations,
      selectedStation: selectableStations[0],
      selectedStationId: selectableStations[0].station_id,
      guard: null,
    };
  }

  return {
    assignedStationIds,
    selectableStations,
    selectedStation: null,
    selectedStationId: null,
    guard: selectableStations.length > 1
      ? {
        kind: 'station-selection-required',
        title: 'Choose a station to lock the line',
        description: 'This session can operate more than one station. Select one station before loading a ticket queue or using fast actions.',
      }
      : null,
  };
}

export function groupKitchenTicketsByLane(tickets: Array<KitchenOrderItemTicket>): Array<KitchenLane> {
  return kitchenActiveLaneDefinitions.map((lane) => ({
    ...lane,
    tickets: sortKitchenTickets(tickets.filter((ticket) => ticket.ticket_status === lane.status)),
  }));
}

export function summarizeKitchenTickets(tickets: Array<KitchenOrderItemTicket>) {
  const counts = {
    all: tickets.length,
    queued: 0,
    fired: 0,
    ready: 0,
    terminal: 0,
    drift: 0,
  };

  tickets.forEach((ticket) => {
    if (ticket.ticket_status === 'Queued') {
      counts.queued += 1;
    } else if (ticket.ticket_status === 'Fired') {
      counts.fired += 1;
    } else if (ticket.ticket_status === 'Ready') {
      counts.ready += 1;
    } else if (ticket.lifecycle?.is_terminal) {
      counts.terminal += 1;
    }

    if ((ticket.reconciliation?.drift_reasons ?? []).length > 0) {
      counts.drift += 1;
    }
  });

  return counts;
}

export function ticketDisplayName(ticket: KitchenOrderItemTicket): string {
  return ticket.item?.name
    ?? ticket.order_item?.item_name_snapshot
    ?? `Ticket #${ticket.ticket_id}`;
}

export function ticketAllowedActions(ticket: KitchenOrderItemTicket): Array<KitchenTicketAction> {
  const lifecycleActions = ticket.lifecycle?.allowed_actions ?? [];
  const normalizedLifecycleActions = lifecycleActions.filter(isKitchenTicketAction);

  if (normalizedLifecycleActions.length > 0) {
    return normalizedLifecycleActions;
  }

  if (ticket.ticket_status === 'Queued') {
    return ['fire'];
  }

  if (ticket.ticket_status === 'Fired') {
    return ['bump'];
  }

  if (ticket.ticket_status === 'Ready') {
    return ['recall'];
  }

  return [];
}

export function ticketTimeline(ticket: KitchenOrderItemTicket): Array<KitchenTicketTimelineEntry> {
  return [
    {
      key: 'dispatched',
      label: 'Dispatched',
      value: ticket.first_dispatched_at,
      active: Boolean(ticket.first_dispatched_at),
    },
    {
      key: 'fired',
      label: 'Fired',
      value: ticket.fired_at,
      active: Boolean(ticket.fired_at) || ticket.ticket_status === 'Fired',
    },
    {
      key: 'ready',
      label: 'Ready',
      value: ticket.ready_at,
      active: Boolean(ticket.ready_at) || ticket.ticket_status === 'Ready',
    },
    {
      key: 'recalled',
      label: 'Recalled',
      value: ticket.last_recalled_at,
      active: Boolean(ticket.last_recalled_at),
    },
    {
      key: 'closed',
      label: ticket.ticket_status === 'Cancelled' ? 'Cancelled' : 'Completed',
      value: ticket.cancelled_at ?? ticket.completed_at,
      active: Boolean(ticket.cancelled_at ?? ticket.completed_at) || ticket.lifecycle?.is_terminal === true,
    },
  ];
}

export function stationWorkloadLabel(station: KitchenStation): string {
  const counts = station.ticket_counts;

  return `${counts.queued} queued / ${counts.fired} in prep / ${counts.ready} ready`;
}

export function summarizeKitchenRealtime(state: StaffOperationalRealtimeState | null | undefined): {
  label: string;
  tone: 'success' | 'warning' | 'default';
  eventCount: number;
  pollHintSeconds: number | null;
} {
  if (!state) {
    return {
      label: 'Realtime cursor pending',
      tone: 'default',
      eventCount: 0,
      pollHintSeconds: null,
    };
  }

  return {
    label: state.stale_cursor ? 'Realtime cursor stale' : `Realtime v${state.current_version}`,
    tone: state.stale_cursor ? 'warning' : 'success',
    eventCount: state.events.length,
    pollHintSeconds: state.poll_hint_ms > 0 ? Math.round(state.poll_hint_ms / 1000) : null,
  };
}

function invalidStationContext(
  assignedStationIds: Array<number>,
  selectableStations: Array<KitchenStation>,
  stationId: number,
): KitchenStationContext {
  return {
    assignedStationIds,
    selectableStations,
    selectedStation: null,
    selectedStationId: null,
    guard: {
      kind: 'invalid-station',
      title: 'Station does not match this kitchen context',
      description: 'The station in the URL or saved context is not assigned to this staff session or is not available for the selected branch.',
      meta: `Station #${stationId}`,
    },
  };
}

function sortKitchenTickets(tickets: Array<KitchenOrderItemTicket>): Array<KitchenOrderItemTicket> {
  return [...tickets].sort((left, right) => readTicketUpdatedAt(left) - readTicketUpdatedAt(right));
}

function readTicketUpdatedAt(ticket: KitchenOrderItemTicket): number {
  const parsed = Date.parse(ticket.updated_at ?? '');

  return Number.isNaN(parsed) ? 0 : parsed;
}

function isKitchenTicketAction(value: string): value is KitchenTicketAction {
  return value === 'fire' || value === 'bump' || value === 'recall';
}
