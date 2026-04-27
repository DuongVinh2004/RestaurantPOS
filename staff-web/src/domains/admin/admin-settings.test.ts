import { describe, expect, it } from 'vitest';
import {
  branchReservationLeadLabel,
  branchSameDayCutoffLabel,
  branchWaitingListLabel,
  buildAdminBranchesQuery,
  buildAdminRestaurantTableQuery,
  formatBusinessPeriods,
  pickAdminBranchId,
  summarizeAdminTables,
  summarizeAdminBranches,
} from './admin-settings';

const branchFixture = {
  branch_id: 2,
  is_default: false,
  is_active: true,
  business_hours: [
    {
      periods: [{ start_time: '08:00', end_time: '17:00' }],
    },
  ],
  closure_windows: [{ start_local: '2026-04-17T00:00:00Z' }],
  booking_policy: {
    reservation: {
      min_lead_time_minutes: 45,
      same_day_cutoff_time: '18:00',
    },
    waiting_list: {
      enabled: true,
    },
  },
};

describe('admin settings domain helpers', () => {
  it('builds branch queries without leaking empty filters', () => {
    expect(buildAdminBranchesQuery({ query: '  ', activeOnly: true })).toEqual({
      is_active: true,
      q: undefined,
    });
    expect(buildAdminRestaurantTableQuery({
      query: '  patio ',
      status: 'Available',
      zone: '',
      includeDeleted: false,
      branchIdInput: '',
    }, 3)).toEqual({
      q: 'patio',
      status: 'Available',
      zone: undefined,
      include_deleted: undefined,
      branch_id: 3,
    });
  });

  it('prefers preferred then current branch selection', () => {
    expect(pickAdminBranchId([branchFixture], null, 2)).toBe(2);
    expect(pickAdminBranchId([branchFixture], 2, null)).toBe(2);
  });

  it('summarizes configured branches and formats policy labels', () => {
    expect(summarizeAdminBranches([branchFixture, { ...branchFixture, branch_id: 3, is_active: false, is_default: true, closure_windows: [] }])).toEqual({
      total: 2,
      active: 1,
      defaults: 1,
      withClosures: 1,
      withBusinessHours: 2,
    });
    expect(formatBusinessPeriods(branchFixture.business_hours[0].periods)).toBe('08:00 - 17:00');
    expect(branchReservationLeadLabel(branchFixture)).toBe('45 min');
    expect(branchSameDayCutoffLabel(branchFixture)).toBe('18:00');
    expect(branchWaitingListLabel(branchFixture)).toBe('Enabled');
  });

  it('summarizes table management reads', () => {
    expect(summarizeAdminTables([
      {
        status: 'Available',
        is_active: true,
        is_allocatable: true,
        branch_id: 1,
        usage: { has_active_operational_links: false },
      },
      {
        status: 'Occupied',
        is_active: true,
        is_allocatable: true,
        branch_id: 2,
        usage: { has_active_operational_links: true },
      },
    ])).toEqual({
      total: 2,
      active: 2,
      allocatable: 2,
      operationallyLinked: 1,
      branchScoped: 2,
    });
  });
});
