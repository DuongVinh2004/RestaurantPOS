import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { App as AntdApp } from 'antd';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlowStore } from '../../../../app/store/flow-store';
import { AdminInventoryPage } from './AdminInventoryPage';

const apiMocks = vi.hoisted(() => ({
  listAdminIngredients: vi.fn(),
  listAdminSuppliers: vi.fn(),
  listAdminPurchaseOrders: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', async () => {
  const actual = await vi.importActual<object>('../../../../shared/api/staff-api');
  return {
    ...actual,
    listAdminIngredients: apiMocks.listAdminIngredients,
    listAdminSuppliers: apiMocks.listAdminSuppliers,
    listAdminPurchaseOrders: apiMocks.listAdminPurchaseOrders,
  };
});

const initialFlowState = useFlowStore.getState();

describe('AdminInventoryPage', () => {
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
    useFlowStore.setState(initialFlowState, true);
    apiMocks.listAdminIngredients.mockReset();
    apiMocks.listAdminSuppliers.mockReset();
    apiMocks.listAdminPurchaseOrders.mockReset();
    apiMocks.listAdminIngredients.mockResolvedValue({
      data: [
        {
          ingredient_id: 4,
          code: 'BEANS',
          name: 'Hạt cà phê',
          unit_code: 'kg',
          is_active: true,
          recipe_usage_count: 3,
          stock: { on_hand: 0, unit_code: 'kg' },
        },
      ],
    });
    apiMocks.listAdminSuppliers.mockResolvedValue({
      data: [
        {
          supplier_id: 5,
          code: 'SUP-1',
          name: 'Nhà rang',
          contact_name: 'Linh',
          phone: '0909',
          email: 'roaster@example.test',
          is_active: true,
        },
      ],
    });
    apiMocks.listAdminPurchaseOrders.mockResolvedValue({
      data: [
        {
          purchase_order_id: 9,
          order_code: 'PO-9',
          purchase_order_status: 'Draft',
          supplier_id: 5,
          branch_id: 3,
          supplier: { name: 'Nhà rang' },
          branch: { branch_code: 'B3' },
          summary: { receipt_count: 1, remaining_total_quantity: 12 },
          expected_at: '2026-04-20T00:00:00Z',
          ordered_at: null,
          created_at: '2026-04-17T00:00:00Z',
        },
      ],
    });
  });

  it('uses branch context for purchase-order reads and renders supply summaries', async () => {
    useFlowStore.getState().setBranchId(3);

    renderPage();

    expect(await screen.findByText('Kho & Mua hàng')).toBeInTheDocument();
    expect(await screen.findByText(/nguyên liệu hết tồn/i)).toBeInTheDocument();
    
    fireEvent.click(screen.getByRole('tab', { name: 'Đơn mua hàng' }));
    expect(await screen.findByText('PO-9')).toBeInTheDocument();

    await waitFor(() => expect(apiMocks.listAdminPurchaseOrders).toHaveBeenCalledWith({
      q: undefined,
      branch_id: 3,
      purchase_order_status: undefined,
      per_page: 8,
      sort: '-created_at',
    }));
  });
});

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <AntdApp>
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <AdminInventoryPage />
        </MemoryRouter>
      </AntdApp>
    </QueryClientProvider>,
  );
}
