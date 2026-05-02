import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AdminPrivacyPage } from './AdminPrivacyPage';

const apiMocks = vi.hoisted(() => ({
  exportAdminCustomerData: vi.fn(),
  listAdminPrivacyRequests: vi.fn(),
  reviewAdminPrivacyRequest: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);

describe('AdminPrivacyPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiMocks.listAdminPrivacyRequests.mockResolvedValue({
      data: [{
        request_id: 31,
        user_id: 22,
        request_type: 'data_export',
        status: 'Pending',
        created_at: '2026-04-30T08:00:00Z',
      }],
    });
    apiMocks.reviewAdminPrivacyRequest.mockResolvedValue({ data: { request_id: 31, mode: 'dry_run' } });
    apiMocks.exportAdminCustomerData.mockResolvedValue({ data: { user_id: 22, reservations: [] } });
  });

  it('reviews requests with dry-run and exports customer data', async () => {
    renderWithProviders();

    expect(await screen.findByText('data_export')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /data_export/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Chạy thử review' }));

    await waitFor(() => {
      expect(apiMocks.reviewAdminPrivacyRequest).toHaveBeenCalledWith(31, {
        decision: 'approve',
        mode: 'dry_run',
        notes: null,
      });
    });

    fireEvent.click(screen.getByRole('button', { name: 'Export dữ liệu khách' }));

    await waitFor(() => {
      expect(apiMocks.exportAdminCustomerData).toHaveBeenCalledWith(22);
    });
  });
});

function renderWithProviders() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <AdminPrivacyPage />
      </QueryClientProvider>
    </AntdApp>,
  );
}
