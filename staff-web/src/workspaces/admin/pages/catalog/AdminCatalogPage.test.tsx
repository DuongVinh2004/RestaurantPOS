import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App as AntdApp } from 'antd';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AdminCatalogPage } from './AdminCatalogPage';

const apiMocks = vi.hoisted(() => ({
  createAdminMenuCategory: vi.fn(),
  createAdminMenuItem: vi.fn(),
  createAdminMenuItemPrice: vi.fn(),
  importAdminMasterData: vi.fn(),
  listAdminMenuCategories: vi.fn(),
  listAdminMenuItemPrices: vi.fn(),
  listAdminMenuItems: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', async () => {
  const actual = await vi.importActual<object>('../../../../shared/api/staff-api');
  return {
    ...actual,
    createAdminMenuCategory: apiMocks.createAdminMenuCategory,
    createAdminMenuItem: apiMocks.createAdminMenuItem,
    createAdminMenuItemPrice: apiMocks.createAdminMenuItemPrice,
    importAdminMasterData: apiMocks.importAdminMasterData,
    listAdminMenuCategories: apiMocks.listAdminMenuCategories,
    listAdminMenuItemPrices: apiMocks.listAdminMenuItemPrices,
    listAdminMenuItems: apiMocks.listAdminMenuItems,
  };
});

describe('AdminCatalogPage', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: false,
        media: '',
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
      }),
    });

    vi.clearAllMocks();
    apiMocks.listAdminMenuCategories.mockResolvedValue({
      data: [
        { category_id: 3, name: 'Món sáng', description: null, sort_order: 1, is_deleted: false },
      ],
    });
    apiMocks.listAdminMenuItems.mockResolvedValue({
      data: [
        {
          item_id: 12,
          category_id: 3,
          code: 'COF',
          name: 'Cà phê',
          description: null,
          img_url: null,
          is_available: true,
          is_preorder_enabled: false,
          preorder_quota_per_day: null,
          preorder_cutoff_minutes: null,
          category: { category_id: 3, name: 'Món sáng', description: null, sort_order: 1, is_deleted: false },
          current_price: { price_id: 4, item_id: 12, price: '45000.00', currency: 'VND', effective_from: '2026-04-17T00:00:00Z', effective_to: null },
        },
      ],
    });
    apiMocks.listAdminMenuItemPrices.mockResolvedValue({
      data: [
        { price_id: 4, item_id: 12, price: '45000.00', currency: 'VND', effective_from: '2026-04-17T00:00:00Z', effective_to: null },
      ],
    });
    apiMocks.createAdminMenuCategory.mockResolvedValue({ data: {} });
    apiMocks.createAdminMenuItem.mockResolvedValue({ data: {} });
    apiMocks.createAdminMenuItemPrice.mockResolvedValue({ data: {} });
    apiMocks.importAdminMasterData.mockResolvedValue({
      data: {
        domain: 'menu-categories',
        label: 'Loại món',
        format: 'json',
        mode: 'dry_run',
        can_commit: true,
        schema: { columns: ['name'], required_columns: ['name'], errors: [] },
        summary: { total_rows: 1, valid_rows: 1, invalid_rows: 0 },
        rows: [{ row_number: 1, status: 'valid', operation: 'create', errors: [], before: null, after: { name: 'Món trưa' } }],
        commit: null,
      },
    });
  });

  it('renders catalog reads and loads selected item price rows', async () => {
    renderPage();

    expect(await screen.findByText('Thực đơn và giá bán')).toBeInTheDocument();
    expect(await screen.findByText('Món sáng')).toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: /Cà phê/i }));

    await waitFor(() => expect(apiMocks.listAdminMenuItemPrices).toHaveBeenCalledWith(12, {
      per_page: 8,
      sort: '-effective_from',
    }));
    expect(await screen.findByText('45.000 ₫')).toBeInTheDocument();
  });

  it('surfaces price route not-found states when an operator enters a direct item id outside the current list', async () => {
    apiMocks.listAdminMenuItemPrices.mockImplementation((itemId: number) => {
      if (itemId === 77) {
        return Promise.reject({
          status: 404,
          payload: {
            error_code: 'not_found',
            category_code: 'not_found',
            message: 'Resource not found.',
            request_id: 'req-catalog-404',
          },
        });
      }

      return Promise.resolve({
        data: [
          { price_id: 4, item_id: 12, price: '45000.00', currency: 'VND', effective_from: '2026-04-17T00:00:00Z', effective_to: null },
        ],
      });
    });

    renderPage();

    fireEvent.change(await screen.findByLabelText('Mã món đang chọn'), {
      target: { value: '77' },
    });

    await waitFor(() => expect(apiMocks.listAdminMenuItemPrices).toHaveBeenCalledWith(77, {
      per_page: 8,
      sort: '-effective_from',
    }));
    expect(await screen.findByText('Món #77')).toBeInTheDocument();
    expect(await screen.findByText('Không còn thấy món #77')).toBeInTheDocument();
    expect(screen.queryByText('Chưa chọn món')).not.toBeInTheDocument();
  });

  it('shows inline mutation feedback when creating a price fails for a stale item selection', async () => {
    apiMocks.createAdminMenuItemPrice.mockRejectedValue({
      status: 404,
      payload: {
        error_code: 'not_found',
        category_code: 'not_found',
        message: 'Resource not found.',
        request_id: 'req-catalog-price-create-404',
      },
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /Cà phê/i }));
    fireEvent.change(await screen.findByLabelText('Giá món mới'), {
      target: { value: '56000' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Thêm dòng giá' }));

    await waitFor(() => expect(apiMocks.createAdminMenuItemPrice).toHaveBeenCalledWith(12, {
      price: 56000,
      currency: 'VND',
      effective_from: expect.any(String),
    }));
    expect(await screen.findByText('Không còn thêm giá cho cà phê')).toBeInTheDocument();
    expect(await screen.findByText('Mã truy vết: req-catalog-price-create-404')).toBeInTheDocument();
  });

  it('previews imports before commit can be sent with an idempotency key', async () => {
    renderPage();

    fireEvent.change(await screen.findByLabelText('Dòng JSON nhập liệu quản trị'), {
      target: { value: '[{"name":"Món trưa"}]' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chạy thử' }));

    await waitFor(() => expect(apiMocks.importAdminMasterData).toHaveBeenCalledWith('menu-categories', {
      mode: 'dry_run',
      format: 'json',
      rows: [{ name: 'Món trưa' }],
    }));
    expect(await screen.findByText(/Khi ghi nhận, hệ thống sẽ gửi Idempotency-Key/)).toBeInTheDocument();
  });
});

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <AntdApp>
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <AdminCatalogPage />
        </MemoryRouter>
      </AntdApp>
    </QueryClientProvider>,
  );
}
