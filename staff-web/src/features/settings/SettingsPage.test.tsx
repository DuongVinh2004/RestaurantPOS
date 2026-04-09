import { screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SettingsPage } from './SettingsPage';
import { renderWithSession } from '../../test/render';
import { buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  loadAdminBranches: vi.fn(),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    arrangeBranchFixtures();
  });

  it('loads branch settings and auto-selects the startup default branch detail', async () => {
    renderWithSession(
      <SettingsPage />,
      createSessionContext({
        startup: {
          ...buildStaffSession().startup,
          default_branch: {
            branch_id: 2,
            branch_code: 'HCM01',
            branch_name: 'Ho Chi Minh 01',
            timezone: 'Asia/Ho_Chi_Minh',
            currency: 'VND',
            is_default: false,
            is_active: true,
          },
        },
      }),
    );

    await waitFor(() =>
      expect(apiMocks.loadAdminBranches).toHaveBeenCalledWith({
        q: undefined,
        is_active: true,
      }),
    );

    expect(screen.getByText('Selected HCM01')).toBeInTheDocument();
    expect(screen.getByText('Asia/Ho_Chi_Minh')).toBeInTheDocument();
    expect(screen.getByText('Thu hai')).toBeInTheDocument();
    expect(screen.getByText('09:00 - 21:00')).toBeInTheDocument();
    expect(screen.getByText('18:00 31/12/2026 -> 23:00 31/12/2026')).toBeInTheDocument();
    expect(screen.getByText('15:30 08/04/2026')).toBeInTheDocument();
    expect(screen.getByText('90 phut')).toBeInTheDocument();
    expect(screen.getByText('Tat')).toBeInTheDocument();
  });
});

function arrangeBranchFixtures() {
  apiMocks.loadAdminBranches.mockResolvedValue({
    data: [
      {
        branch_id: 1,
        branch_code: 'MAIN',
        branch_name: 'Chi nhanh chinh',
        description: 'Primary branch',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        business_hours: [
          {
            day_of_week: 1,
            periods: [
              {
                start_time: '08:00',
                end_time: '20:00',
              },
            ],
          },
        ],
        closure_windows: [],
        booking_policy: {
          reservation: {
            min_lead_time_minutes: 30,
            same_day_cutoff_time: '19:00',
          },
          waiting_list: {
            enabled: true,
          },
        },
        is_active: true,
        is_default: true,
        row_version: 5,
        created_at: '2026-04-08T08:00:00Z',
        updated_at: '2026-04-08T08:30:00Z',
      },
      {
        branch_id: 2,
        branch_code: 'HCM01',
        branch_name: 'Ho Chi Minh 01',
        description: 'Secondary operating branch',
        timezone: 'Asia/Ho_Chi_Minh',
        currency: 'VND',
        business_hours: [
          {
            day_of_week: 1,
            periods: [
              {
                start_time: '09:00',
                end_time: '21:00',
              },
            ],
          },
        ],
        closure_windows: [
          {
            start_local: '2026-12-31 18:00:00',
            end_local: '2026-12-31 23:00:00',
            type: 'holiday',
            reason: 'Su kien cuoi nam',
          },
        ],
        booking_policy: {
          reservation: {
            min_lead_time_minutes: 90,
            same_day_cutoff_time: '18:00',
          },
          waiting_list: {
            enabled: false,
          },
        },
        is_active: true,
        is_default: false,
        row_version: 7,
        created_at: '2026-04-08T08:00:00Z',
        updated_at: '2026-04-08T08:30:00Z',
      },
    ],
    meta: {
      action: 'admin_branches_index',
      count: 2,
    },
  });
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['settings.manage'],
      known_capabilities: ['settings.manage'],
      ...overrides,
    }),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn(),
    logout: vi.fn(),
    expire: vi.fn(),
  };
}
