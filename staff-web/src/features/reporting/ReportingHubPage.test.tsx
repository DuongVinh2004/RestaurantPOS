import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App as AntdApp } from 'antd';
import { MemoryRouter } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../app/store/flow-store';
import type { StaffReportingCollectionMeta } from '../../core/api/sdk';
import { ReportingHubPage } from './ReportingHubPage';

const apiMocks = vi.hoisted(() => ({
  listDailyInventoryReporting: vi.fn(),
  listDailyOperationsReporting: vi.fn(),
  listDailySalesReporting: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => ({
  listDailyInventoryReporting: apiMocks.listDailyInventoryReporting,
  listDailyOperationsReporting: apiMocks.listDailyOperationsReporting,
  listDailySalesReporting: apiMocks.listDailySalesReporting,
}));

const initialFlowState = useFlowStore.getState();

describe('ReportingHubPage', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
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

  beforeEach(() => {
    apiMocks.listDailyInventoryReporting.mockReset();
    apiMocks.listDailyOperationsReporting.mockReset();
    apiMocks.listDailySalesReporting.mockReset();

    apiMocks.listDailySalesReporting.mockResolvedValue({
      data: [],
      meta: createReportingMeta(),
    });
    apiMocks.listDailyOperationsReporting.mockResolvedValue({
      data: [],
      meta: createReportingMeta(),
    });
    apiMocks.listDailyInventoryReporting.mockResolvedValue({
      data: [],
      meta: createReportingMeta(),
    });

    useFlowStore.setState(initialFlowState, true);
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 3,
    });
  });

  it('keeps reporting filters accessible and resets ad-hoc tab filters', async () => {
    renderReportingHubPage();

    expect(await screen.findByRole('tab', { name: 'Bán hàng' })).toBeInTheDocument();

    const currencyInput = screen.getByLabelText('Loại tiền cho báo cáo bán hàng');
    const ingredientInput = screen.getByLabelText('Mã nguyên liệu cho báo cáo kho');

    expect(screen.getByLabelText('Từ ngày báo cáo')).toBeInTheDocument();
    expect(screen.getByLabelText('Đến ngày báo cáo')).toBeInTheDocument();
    expect(ingredientInput).toBeDisabled();

    fireEvent.change(currencyInput, { target: { value: 'USD' } });
    await waitFor(() => expect(currencyInput).toHaveValue('USD'));

    const resetButton = await screen.findByRole('button', { name: 'Đặt lại bộ lọc' });
    fireEvent.click(resetButton);

    await waitFor(() => expect(currencyInput).toHaveValue(''));
  });
});

function createReportingMeta() {
  return {
    current_page: 1,
    page: 1,
    per_page: 12,
    total: 0,
    last_page: 1,
    snapshot_health: {
      status: 'healthy',
      is_empty: true,
      reasons: [],
      row_count: 0,
      latest_business_date: null,
      latest_refresh_age_seconds: 120,
      scope_count: 1,
      healthy_scope_count: 1,
      stale_scope_count: 0,
      stale_scope_examples: [],
      health_reference_refresh_age_seconds: 120,
    },
  } as unknown as StaffReportingCollectionMeta;
}

function renderReportingHubPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <ReportingHubPage />
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}
