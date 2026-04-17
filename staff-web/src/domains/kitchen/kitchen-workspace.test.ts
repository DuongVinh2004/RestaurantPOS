import { describe, expect, it } from 'vitest';
import type { KitchenOrderItemTicket, KitchenStation } from '../../shared/api/sdk';
import { buildStaffSession } from '../../test/fixtures';
import {
  groupKitchenTicketsByLane,
  resolveKitchenBranchGuard,
  resolveKitchenStationContext,
  resolveKitchenWorkspaceGuard,
  ticketAllowedActions,
} from './kitchen-workspace';

describe('kitchen workspace domain rules', () => {
  it('guards sessions without kitchen workspace access', () => {
    const session = buildStaffSession({
      capabilities: ['reservation.manage'],
      known_capabilities: ['reservation.manage', 'kitchen.manage'],
    });

    expect(resolveKitchenWorkspaceGuard(session)).toMatchObject({
      kind: 'missing-workspace-access',
    });
  });

  it('guards branch ids outside the startup branch contract', () => {
    const session = buildStaffSession({
      capabilities: ['kitchen.manage'],
      startup: {
        allowed_branch_ids: [1, 2],
        assigned_station_ids: [11],
      },
    });

    expect(resolveKitchenBranchGuard(session, 9)).toMatchObject({
      kind: 'invalid-branch',
      meta: 'Branch #9',
    });
  });

  it('requires an assigned station before a kitchen session can load tickets', () => {
    const session = buildStaffSession({
      capabilities: ['kitchen.manage'],
      startup: {
        assigned_station_ids: [],
      },
    });

    expect(resolveKitchenStationContext({
      session,
      stations: [makeStation(11)],
      requestedStationId: null,
    }).guard).toMatchObject({
      kind: 'missing-assigned-station',
    });
  });

  it('rejects a station that is not assigned to the kitchen session', () => {
    const session = buildStaffSession({
      capabilities: ['kitchen.manage'],
      startup: {
        assigned_station_ids: [11],
      },
    });

    expect(resolveKitchenStationContext({
      session,
      stations: [makeStation(11), makeStation(22)],
      requestedStationId: 22,
    }).guard).toMatchObject({
      kind: 'invalid-station',
      meta: 'Station #22',
    });
  });

  it('groups tickets into operational status lanes and maps safe fast actions', () => {
    const tickets = [
      makeTicket(1, 'Ready'),
      makeTicket(2, 'Queued'),
      makeTicket(3, 'Fired'),
    ];

    expect(groupKitchenTicketsByLane(tickets).map((lane) => [
      lane.status,
      lane.tickets.map((ticket) => ticket.ticket_id),
    ])).toEqual([
      ['Queued', [2]],
      ['Fired', [3]],
      ['Ready', [1]],
    ]);
    expect(ticketAllowedActions(makeTicket(4, 'Queued'))).toEqual(['fire']);
    expect(ticketAllowedActions(makeTicket(5, 'Fired'))).toEqual(['bump']);
    expect(ticketAllowedActions(makeTicket(6, 'Ready'))).toEqual(['recall']);
  });
});

function makeStation(stationId: number): KitchenStation {
  return {
    station_id: stationId,
    code: stationId === 11 ? 'HOT' : 'COLD',
    name: stationId === 11 ? 'Hot Pass' : 'Cold Pass',
    description: null,
    output_mode: 'Both',
    printer_target: null,
    is_active: true,
    route_count: 1,
    ticket_counts: {
      queued: 1,
      fired: 0,
      ready: 0,
    },
    created_at: null,
    updated_at: null,
  };
}

function makeTicket(ticketId: number, status: string): KitchenOrderItemTicket {
  return {
    ticket_id: ticketId,
    ticket_status: status,
    route_source: 'category',
    dispatch_count: 1,
    recall_count: 0,
    output_mode: 'Both',
    printer_target: null,
    ticket_notes: null,
    order: {
      order_id: 50 + ticketId,
      reservation_id: 70 + ticketId,
    },
    station: {
      station_id: 11,
      code: 'HOT',
      name: 'Hot Pass',
    },
    route: null,
    routing: {
      route_present: true,
      route_active: true,
      station_matches_route: true,
    },
    order_item: {
      order_item_id: 100 + ticketId,
      item_id: 200 + ticketId,
      quantity: 1,
      status: 'Ordered',
      notes: null,
      item_name_snapshot: `Item ${ticketId}`,
    },
    item: {
      item_id: 200 + ticketId,
      name: `Item ${ticketId}`,
      category_id: null,
      category_name: null,
    },
    lifecycle: {
      status,
      state_reason: 'test',
      is_terminal: status === 'Completed' || status === 'Cancelled',
      allowed_actions: [],
    },
    reconciliation: {
      sync_status: 'synced',
      routing_status: 'ok',
      order_item_expected_status: null,
      order_item_matches_ticket: true,
      station_active: true,
      drift_reasons: [],
      next_actions: [],
    },
    first_dispatched_at: '2026-04-11T09:00:00Z',
    fired_at: status === 'Fired' || status === 'Ready' ? '2026-04-11T09:01:00Z' : null,
    ready_at: status === 'Ready' ? '2026-04-11T09:03:00Z' : null,
    completed_at: null,
    cancelled_at: null,
    last_recalled_at: null,
    created_at: '2026-04-11T09:00:00Z',
    updated_at: `2026-04-11T09:0${ticketId}:00Z`,
  };
}
