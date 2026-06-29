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
    
    // Open create voucher drawer
    const createBtns = screen.getAllByRole('button', { name: 'Tạo voucher' });
    fireEvent.click(createBtns[0]);
    await screen.findByLabelText('Mã voucher');

    fireEvent.change(screen.getByLabelText('Mã voucher'), { target: { value: 'SPRING' } });
    fireEvent.change(screen.getByLabelText('Giá trị giảm'), { target: { value: '15000' } });
    
    // Submit voucher
    const submitBtns = screen.getAllByRole('button', { name: 'Tạo voucher' });
    fireEvent.click(submitBtns[submitBtns.length - 1]);

    await waitFor(() => {
      expect(apiMocks.createAdminBenefitVoucher).toHaveBeenCalledWith(expect.objectContaining({
        code: 'SPRING',
        discount_value: 15000,
      }));
    });

    // Update voucher
    fireEvent.click(screen.getByRole('button', { name: /WELCOME/ }));
    await screen.findByLabelText('Mã voucher');
    fireEvent.click(screen.getByRole('button', { name: 'Cập nhật voucher' }));

    await waitFor(() => {
      expect(apiMocks.updateAdminBenefitVoucher).toHaveBeenCalledWith(11, expect.objectContaining({
        row_version: 2,
      }));
    });

    // Update settings
    fireEvent.click(screen.getByRole('button', { name: 'Cấu hình & Export' }));
    await screen.findByLabelText('Giá trị cấu hình ưu đãi');
    fireEvent.change(screen.getByLabelText('Giá trị cấu hình ưu đãi'), { target: { value: 'false' } });
    fireEvent.click(screen.getByRole('button', { name: 'Lưu cấu hình' }));

    await waitFor(() => {
      expect(apiMocks.upsertAdminBenefitSetting).toHaveBeenCalledWith({
        setting_key: 'loyalty.enabled',
        value: 'false',
      });
    });
  }, 30000);
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
