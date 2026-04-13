import { screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { InventoryPage } from './InventoryPage';
import { renderWithSession } from '../../test/render';
import { buildStaffSession } from '../../test/fixtures';
import { RestaurantPosApiError } from '../../core/api/sdk';
import type { StaffSessionContextValue } from '../../app/session-context';

const apiMocks = vi.hoisted(() => ({
  listAdminIngredients: vi.fn(),
  listAdminSuppliers: vi.fn(),
  listAdminPurchaseOrders: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);

describe('InventoryPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('loads inventory read models with the startup branch and renders stock plus receiving summaries', async () => {
    arrangeInventoryFixtures();

    renderWithSession(<InventoryPage />, createSessionContext());

    await waitFor(() =>
      expect(apiMocks.listAdminIngredients).toHaveBeenCalledWith({
        q: undefined,
        is_active: true,
        per_page: 8,
        sort: 'name',
      }),
    );
    expect(apiMocks.listAdminSuppliers).toHaveBeenCalledWith({
      q: undefined,
      is_active: true,
      per_page: 8,
      sort: 'name',
    });
    expect(apiMocks.listAdminPurchaseOrders).toHaveBeenCalledWith({
      q: undefined,
      branch_id: 1,
      purchase_order_status: undefined,
      per_page: 8,
      sort: '-created_at',
    });

    expect(screen.getByText('Arabica Beans')).toBeInTheDocument();
    expect(screen.getByText('Fresh Farm Co')).toBeInTheDocument();
    expect(screen.getByText('PO-0001')).toBeInTheDocument();
    expect(screen.getByText(/Supplier reference: QUOTE-77/i)).toBeInTheDocument();
  });

  it('surfaces a branch rollout block when inventory uplift is disabled', async () => {
    arrangeInventoryFixtures();
    apiMocks.listAdminPurchaseOrders.mockRejectedValueOnce(buildCoreApiError(422, {
      error_code: 'feature_disabled',
      message: 'Validation error.',
      errors: {
        feature_flag: ['inventory.read_models is disabled for this branch.'],
      },
    }));

    renderWithSession(<InventoryPage />, createSessionContext());

    await waitFor(() => expect(apiMocks.listAdminPurchaseOrders).toHaveBeenCalled());
    expect(await screen.findByText(/Inventory uplift dang bi khoa cho branch hoac session nay/i)).toBeInTheDocument();
  });

  it('expires the staff session when inventory bootstrap returns 401', async () => {
    arrangeInventoryFixtures();
    const session = createSessionContext();
    apiMocks.listAdminIngredients.mockRejectedValueOnce(buildCoreApiError(401, {
      error_code: 'unauthorized',
      message: 'Unauthorized.',
    }));

    renderWithSession(<InventoryPage />, session);

    await waitFor(() =>
      expect(session.expire).toHaveBeenCalledWith('Phien staff da het han. Dang nhap lai de tiep tuc.'),
    );
  });
});

function arrangeInventoryFixtures() {
  apiMocks.listAdminIngredients.mockResolvedValue({
    data: [
      {
        ingredient_id: 501,
        code: 'BEANS',
        name: 'Arabica Beans',
        unit_code: 'kg',
        description: 'House roast',
        is_active: true,
        stock: {
          on_hand: '0.000',
          unit_code: 'kg',
        },
        recipe_usage_count: 2,
        created_at: '2026-04-08T09:00:00Z',
        updated_at: '2026-04-08T09:10:00Z',
      },
      {
        ingredient_id: 502,
        code: 'MILK',
        name: 'Fresh Milk',
        unit_code: 'L',
        description: 'Dairy',
        is_active: true,
        stock: {
          on_hand: '12.500',
          unit_code: 'L',
        },
        recipe_usage_count: 1,
        created_at: '2026-04-08T09:00:00Z',
        updated_at: '2026-04-08T09:11:00Z',
      },
    ],
  });
  apiMocks.listAdminSuppliers.mockResolvedValue({
    data: [
      {
        supplier_id: 41,
        code: 'SUP-41',
        name: 'Fresh Farm Co',
        contact_name: 'Tran Supplier',
        phone: '0909000222',
        email: 'supplier@example.test',
        notes: null,
        is_active: true,
        created_at: '2026-04-08T09:00:00Z',
        updated_at: '2026-04-08T09:12:00Z',
      },
    ],
  });
  apiMocks.listAdminPurchaseOrders.mockResolvedValue({
    data: [
      {
        purchase_order_id: 71,
        branch_id: 1,
        branch: {
          branch_id: 1,
          branch_code: 'MAIN',
          branch_name: 'Chi nhanh chinh',
          is_default: true,
        },
        order_code: 'PO-0001',
        purchase_order_status: 'Ordered',
        supplier_id: 41,
        supplier: {
          supplier_id: 41,
          code: 'SUP-41',
          name: 'Fresh Farm Co',
          is_active: true,
        },
        ordered_at: '2026-04-08T08:00:00Z',
        expected_at: '2026-04-09T08:00:00Z',
        received_at: null,
        supplier_reference: 'QUOTE-77',
        notes: null,
        summary: {
          line_count: 2,
          receipt_count: 1,
          ordered_total_quantity: '20.000',
          received_total_quantity: '8.000',
          remaining_total_quantity: '12.000',
        },
        created_by: 5,
        updated_by: 5,
        created_at: '2026-04-08T08:00:00Z',
        updated_at: '2026-04-08T08:30:00Z',
      },
    ],
  });
}

function buildCoreApiError<T>(status: number, payload: T, message = 'API request failed') {
  return new RestaurantPosApiError(message, status, payload);
}

function createSessionContext(overrides: Partial<StaffSessionContextValue['session']> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession({
      capabilities: ['inventory.manage'],
      known_capabilities: ['inventory.manage'],
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
