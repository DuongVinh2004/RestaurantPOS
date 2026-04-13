import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AccessPage } from './AccessPage';
import { StaffSessionContext, type StaffSessionContextValue } from '../../app/session-context';
import { buildStaffSession } from '../../test/fixtures';

describe('AccessPage', () => {
  it('surfaces backend startup blockers when the operator shell is not ready', () => {
    const baseSession = buildStaffSession();
    const context = createSessionContext({
      session: buildStaffSession({
        startup: {
          ...baseSession.startup,
          readiness: {
            ...baseSession.startup.readiness,
            branch: 'missing',
            operator_ready: false,
          },
        },
      }),
    });

    render(
      <StaffSessionContext.Provider value={context}>
        <AccessPage />
      </StaffSessionContext.Provider>,
    );

    expect(screen.getByText('Thiếu thông tin để bắt đầu ca')).toBeInTheDocument();
    expect(screen.getByText('Chi nhánh còn thiếu')).toBeInTheDocument();
    expect(screen.getByText('Chưa xác định được chi nhánh mặc định.')).toBeInTheDocument();
  });

  it('explains that finance flows stay locked until the startup shift is ready', () => {
    const baseSession = buildStaffSession();
    const context = createSessionContext({
      session: buildStaffSession({
        startup: {
          ...baseSession.startup,
          active_cashier_shift: null,
          readiness: {
            ...baseSession.startup.readiness,
            cashier_shift: 'action_required',
            operator_ready: true,
          },
        },
      }),
    });

    render(
      <StaffSessionContext.Provider value={context}>
        <AccessPage />
      </StaffSessionContext.Provider>,
    );

    expect(screen.getByText('Đã vào ca, nhưng chưa mở ca thu ngân')).toBeInTheDocument();
    expect(screen.getByText(/Bạn vẫn có thể xem các mục khác/i)).toBeInTheDocument();
    expect(screen.getByText(/Hãy mở ca thu ngân để dùng thanh toán, hoàn tiền và màn hình thu ngân/i)).toBeInTheDocument();
  });
});

function createSessionContext(overrides: Partial<StaffSessionContextValue> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession(),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn().mockResolvedValue(undefined),
    logout: vi.fn().mockResolvedValue(undefined),
    expire: vi.fn(),
    ...overrides,
  };
}
