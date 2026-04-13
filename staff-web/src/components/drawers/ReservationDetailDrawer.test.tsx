import { App as AntdApp } from 'antd';
import { render, screen } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { ReservationDetailDrawer } from './ReservationDetailDrawer';

describe('ReservationDetailDrawer', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    });

    class ResizeObserverMock {
      observe() {}
      unobserve() {}
      disconnect() {}
    }

    Object.defineProperty(globalThis, 'ResizeObserver', {
      writable: true,
      value: ResizeObserverMock,
    });
  });

  it('shows a snapshot badge for snapshot-only guests', async () => {
    render(
      <AntdApp>
        <ReservationDetailDrawer
          open
          reservation={{
            reservation_id: 91,
            reservation_code: 'RSV-PHONE-002',
            row_version: 7,
            status: 'Confirmed',
            start_time: '2026-04-11T12:00:00Z',
            end_time: '2026-04-11T14:00:00Z',
            guest_count: 2,
            table_ids: [12, 14],
            user: null,
            guest: {
              full_name: 'Caller Guest',
              phone: '0905566778',
              email: 'caller.guest@example.test',
              is_snapshot_only: true,
            },
          }}
          activeOrder={null}
          onClose={() => {}}
        />
      </AntdApp>,
    );

    expect(await screen.findAllByText('Khách snapshot')).not.toHaveLength(0);
    expect(screen.getAllByText('Caller Guest')).not.toHaveLength(0);
  });
});
