import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AdminBenefitsPage } from './AdminBenefitsPage';

const apiMocks = vi.hoisted(() => ({
  createAdminBenefitVoucher: vi.fn(),
  createAdminLoyaltyTier: vi.fn(),
  exportAdminMasterData: vi.fn(),
  getAdminBenefitSettings: vi.fn(),
  importAdminMasterData: vi.fn(),
  listAdminBenefitVouchers: vi.fn(),
  listAdminLoyaltyTiers: vi.fn(),
  updateAdminBenefitVoucher: vi.fn(),
  updateAdminLoyaltyTier: vi.fn(),
  upsertAdminBenefitSetting: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);

describe('AdminBenefitsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiMocks.listAdminBenefitVouchers.mockResolvedValue({
      data: [{ voucher_id: 11, code: 'WELCOME', discount_type: 'Fixed', discount_value: 10000, is_active: true, row_version: 2 }],
    });
    apiMocks.listAdminLoyaltyTiers.mockResolvedValue({
      data: [{ tier_id: 7, tier_code: 'GOLD', tier_name: 'Gold', min_points: 1000, is_active: true, row_version: 4 }],
    });
    apiMocks.getAdminBenefitSettings.mockResolvedValue({
      data: [{ setting_key: 'loyalty.enabled', value: 'true' }],
    });
    apiMocks.createAdminBenefitVoucher.mockResolvedValue({ data: { voucher_id: 12 } });
    apiMocks.updateAdminBenefitVoucher.mockResolvedValue({ data: { voucher_id: 11 } });
    apiMocks.upsertAdminBenefitSetting.mockResolvedValue({ data: { setting_key: 'loyalty.enabled' } });
  });

  it('creates and updates benefit master data with row_version for updates', async () => {
    renderWithProviders();

    expect(await screen.findByText('WELCOME')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Mã voucher'), { target: { value: 'SPRING' } });
    fireEvent.change(screen.getByLabelText('Giá trị giảm'), { target: { value: '15000' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tạo voucher' }));

    await waitFor(() => {
      expect(apiMocks.createAdminBenefitVoucher).toHaveBeenCalledWith(expect.objectContaining({
        code: 'SPRING',
        discount_value: 15000,
      }));
    });

    fireEvent.click(screen.getByRole('button', { name: /WELCOME/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Cập nhật voucher đang chọn' }));

    await waitFor(() => {
      expect(apiMocks.updateAdminBenefitVoucher).toHaveBeenCalledWith(11, expect.objectContaining({
        row_version: 2,
      }));
    });

    fireEvent.change(screen.getByLabelText('Giá trị cấu hình ưu đãi'), { target: { value: 'false' } });
    fireEvent.click(screen.getByRole('button', { name: 'Lưu cấu hình' }));

    await waitFor(() => {
      expect(apiMocks.upsertAdminBenefitSetting).toHaveBeenCalledWith({
        setting_key: 'loyalty.enabled',
        value: 'false',
      });
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
        <AdminBenefitsPage />
      </QueryClientProvider>
    </AntdApp>,
  );
}
