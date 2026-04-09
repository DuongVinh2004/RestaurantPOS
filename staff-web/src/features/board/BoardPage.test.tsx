import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BoardPage } from './BoardPage';
import { resolveOperationalPollDelay, shouldRefetchOperationalSlice } from './boardPolling';
import { renderWithSession } from '../../test/render';
import { buildStaffSession } from '../../test/fixtures';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  boardWindow: vi.fn(() => ({ from: '2026-04-07T09:00:00Z', to: '2026-04-07T13:00:00Z' })),
  checkInReservation: vi.fn(),
  loadTableBoard: vi.fn(),
  loadTableBoardChanges: vi.fn(),
  loadWaitingList: vi.fn(),
  loadWaitingListChanges: vi.fn(),
  notifyWaitingListEntry: vi.fn(),
  seatWaitingListEntry: vi.fn(),
  isUnauthorized: vi.fn(() => false),
}));

vi.mock('../../api/client', () => apiMocks);

let visibilityState: DocumentVisibilityState = 'visible';

describe('BoardPage polling helpers', () => {
  it('derives visible and hidden polling cadence from poll hints', () => {
    expect(resolveOperationalPollDelay([null, undefined], true)).toBe(30000);
    expect(resolveOperationalPollDelay([15000, null], true)).toBe(15000);
    expect(resolveOperationalPollDelay([15000, null], false)).toBe(60000);
    expect(resolveOperationalPollDelay([45000, 30000], false)).toBe(60000);
    expect(resolveOperationalPollDelay([90000, 30000], false)).toBe(90000);
  });

  it('treats has_changes or stale_cursor as a refetch trigger', () => {
    expect(shouldRefetchOperationalSlice(null)).toBe(false);
    expect(shouldRefetchOperationalSlice({ data: { has_changes: false, stale_cursor: false } } as never)).toBe(false);
    expect(shouldRefetchOperationalSlice({ data: { has_changes: true, stale_cursor: false } } as never)).toBe(true);
    expect(shouldRefetchOperationalSlice({ data: { has_changes: false, stale_cursor: true } } as never)).toBe(true);
  });
});

describe('BoardPage background polling', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    visibilityState = 'visible';

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => visibilityState,
    });

    apiMocks.loadTableBoard.mockResolvedValue(createBoardEnvelope());
    apiMocks.loadWaitingList.mockResolvedValue(createWaitingEnvelope());
    apiMocks.loadTableBoardChanges.mockResolvedValue(createRealtimeEnvelope());
    apiMocks.loadWaitingListChanges.mockResolvedValue(createRealtimeEnvelope());
    apiMocks.seatWaitingListEntry.mockResolvedValue(createSeatEnvelope());
  });

  afterEach(() => {
    vi.useRealTimers();
    visibilityState = 'visible';
  });

  it('reduces polling cadence when the tab becomes hidden', async () => {
    renderWithSession(<BoardPage />, createSessionContext());

    await flushAsyncWork();
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadWaitingList).toHaveBeenCalledTimes(1);

    act(() => {
      visibilityState = 'hidden';
      document.dispatchEvent(new Event('visibilitychange'));
    });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(59000);
    });

    expect(apiMocks.loadTableBoardChanges).not.toHaveBeenCalled();
    expect(apiMocks.loadWaitingListChanges).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });

    expect(apiMocks.loadTableBoardChanges).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadWaitingListChanges).toHaveBeenCalledTimes(1);
  });

  it('refetches full slices only when change cursors report changes or stale cursors', async () => {
    apiMocks.loadTableBoardChanges
      .mockResolvedValueOnce(createRealtimeEnvelope({ hasChanges: false }))
      .mockResolvedValueOnce(createRealtimeEnvelope({ hasChanges: true }));
    apiMocks.loadWaitingListChanges
      .mockResolvedValueOnce(createRealtimeEnvelope({ hasChanges: false, staleCursor: false }))
      .mockResolvedValueOnce(createRealtimeEnvelope({ staleCursor: true }));

    renderWithSession(<BoardPage />, createSessionContext());

    await flushAsyncWork();
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadWaitingList).toHaveBeenCalledTimes(1);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(30000);
    });

    expect(apiMocks.loadTableBoardChanges).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadWaitingListChanges).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);
    expect(apiMocks.loadWaitingList).toHaveBeenCalledTimes(1);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(30000);
    });

    expect(apiMocks.loadTableBoardChanges).toHaveBeenCalledTimes(2);
    expect(apiMocks.loadWaitingListChanges).toHaveBeenCalledTimes(2);
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(2);
    expect(apiMocks.loadWaitingList).toHaveBeenCalledTimes(2);
  });

  it('cleans up the polling interval on unmount', async () => {
    const { unmount } = renderWithSession(<BoardPage />, createSessionContext());

    await flushAsyncWork();
    expect(apiMocks.loadTableBoard).toHaveBeenCalledTimes(1);

    unmount();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(60000);
    });

    expect(apiMocks.loadTableBoardChanges).not.toHaveBeenCalled();
    expect(apiMocks.loadWaitingListChanges).not.toHaveBeenCalled();
  });

  it('exposes explicit orders and settlement handoff links for the selected table', async () => {
    renderWithSession(
      <BoardPage />,
      createSessionContext({
        capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage', 'settlement.manage'],
        known_capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage', 'settlement.manage'],
      }),
    );

    await flushAsyncWork();

    const ordersLink = screen.getByRole('link', { name: 'Mo Orders voi context nay' });
    const settlementLink = screen.getByRole('link', { name: 'Mo Settlement cho order nay' });

    expect(ordersLink.getAttribute('href')).toContain('/orders?');
    expect(ordersLink.getAttribute('href')).toContain('source=board');
    expect(ordersLink.getAttribute('href')).toContain('table_id=10');
    expect(ordersLink.getAttribute('href')).toContain('reservation_id=77');
    expect(ordersLink.getAttribute('href')).toContain('order_id=9001');

    expect(settlementLink.getAttribute('href')).toContain('/settlement?');
    expect(settlementLink.getAttribute('href')).toContain('source=board');
    expect(settlementLink.getAttribute('href')).toContain('order_id=9001');
  });

  it('shows a refreshed orders handoff after check-in so the operator can continue without manual reconstruction', async () => {
    apiMocks.loadTableBoard
      .mockResolvedValueOnce(createBoardEnvelope({ reservationRowVersion: 9 }))
      .mockResolvedValueOnce(createBoardEnvelope({ reservationRowVersion: 10, activeOrderId: null }));

    renderWithSession(
      <BoardPage />,
      createSessionContext({
        capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
        known_capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
      }),
    );

    await flushAsyncWork();
    fireEvent.click(screen.getByRole('button', { name: 'Check-in' }));

    await flushAsyncWork();
    await flushAsyncWork();

    expect(apiMocks.checkInReservation).toHaveBeenCalledWith(77, { row_version: 1 });
    const ordersLink = screen.getByRole('link', { name: 'Tiep tuc sang Orders' });
    expect(ordersLink.getAttribute('href')).toContain('/orders?');
    expect(ordersLink.getAttribute('href')).toContain('table_id=10');
    expect(ordersLink.getAttribute('href')).toContain('reservation_id=77');
    expect(ordersLink.getAttribute('href')).toContain('reservation_row_version=10');
  });

  it('shows an orders handoff after seating a waiting guest using the reservation returned by the backend', async () => {
    apiMocks.loadTableBoard.mockResolvedValue(createBoardEnvelope({ activeOrderId: null }));

    renderWithSession(
      <BoardPage />,
      createSessionContext({
        capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
        known_capabilities: ['table.board.view', 'waiting_list.manage', 'order.manage'],
      }),
    );

    await flushAsyncWork();
    fireEvent.click(screen.getByRole('button', { name: 'Seat ngay' }));

    await flushAsyncWork();
    await flushAsyncWork();

    expect(apiMocks.seatWaitingListEntry).toHaveBeenCalledWith(61, {
      row_version: 4,
      service_minutes: 120,
      user_id: 5,
    });

    const ordersLink = screen.getByRole('link', { name: 'Mo Orders cho reservation nay' });
    expect(ordersLink.getAttribute('href')).toContain('/orders?');
    expect(ordersLink.getAttribute('href')).toContain('table_id=10');
    expect(ordersLink.getAttribute('href')).toContain('reservation_id=91');
    expect(ordersLink.getAttribute('href')).toContain('reservation_row_version=6');
  });
});

function createBoardEnvelope({
  activeOrderId = 9001,
  reservationRowVersion = 9,
}: {
  activeOrderId?: number | null;
  reservationRowVersion?: number;
} = {}) {
  return {
    data: [
      {
        table_id: 10,
        table_code: 'T1',
        zone: 'Main',
        board_state: 'Occupied',
        realtime_status: 'Occupied',
        capacity: { seats: 4 },
        availability: { accepts_new_assignment: true },
        operational_hints: { preferred_action: 'check_in' },
        reservation: {
          reservation_id: 77,
          reservation_code: 'RES-77',
          row_version: reservationRowVersion,
          user: {
            full_name: 'Tran Thi A',
            phone: '0909000111',
          },
          deposit: {
            outstanding_amount: 0,
            currency: 'VND',
          },
        },
        active_order: activeOrderId ? {
          order_id: activeOrderId,
          status: 'Open',
          row_version: 3,
        } : null,
        actions: {
          check_in: {
            available: true,
            preferred_payload: {
              row_version: 1,
            },
          },
        },
      },
    ],
    summary: {
      active_order_count: 1,
      unassigned_reservation_count: 0,
    },
    meta: {
      realtime: {
        current_version: 12,
      },
    },
  } as never;
}

function createWaitingEnvelope() {
  return {
    data: [
      {
        waiting_id: 61,
        guest_name: 'Waiting Guest',
        phone: '0909000222',
        guest_count: 2,
        status: 'Waiting',
        current_response_state: 'Pending',
        row_version: 4,
        requested_at: '2026-04-07T09:20:00Z',
        user_id: 5,
        notes: 'Window seat',
        invite_window: {
          is_active: true,
          seconds_remaining: 300,
        },
        invite_lifecycle: {
          seat_readiness: 'Ready',
          can_staff_seat_now: true,
          staff_next_step: 'seat',
        },
      },
    ],
    meta: {
      summary: {
        ready_to_seat_count: 1,
        awaiting_customer_follow_up_count: 0,
      },
      realtime: {
        current_version: 8,
      },
    },
  } as never;
}

function createSeatEnvelope() {
  return {
    data: {
      waiting_list: {
        waiting_id: 61,
        row_version: 5,
      },
      reservation: {
        reservation_id: 91,
        reservation_code: 'RES-91',
        row_version: 6,
        status: 'CheckedIn',
        table_ids: [10],
      },
    },
  } as never;
}

function createRealtimeEnvelope({
  hasChanges = false,
  staleCursor = false,
  pollHintMs = 30000,
}: {
  hasChanges?: boolean;
  staleCursor?: boolean;
  pollHintMs?: number;
} = {}) {
  return {
    data: {
      enabled: true,
      topic: 'board',
      channel: 'staff.board',
      after_version: 0,
      current_version: 12,
      oldest_available_version: 1,
      events: [],
      has_changes: hasChanges,
      stale_cursor: staleCursor,
      poll_hint_ms: pollHintMs,
    },
  } as never;
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['table.board.view', 'waiting_list.manage'],
      known_capabilities: ['table.board.view', 'waiting_list.manage'],
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

async function flushAsyncWork() {
  await act(async () => {
    await Promise.resolve();
    await Promise.resolve();
  });
}
