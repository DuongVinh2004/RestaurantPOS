import { useCallback, useEffect, useMemo, useState } from 'react';
import { Boxes, PackageCheck, RefreshCcw, Truck } from 'lucide-react';
import {
  isUnauthorized,
  loadAdminIngredients,
  loadAdminPurchaseOrders,
  loadAdminSuppliers,
} from '../../api/client';
import { useStaffSession } from '../../app/session-context';
import { formatApiError, normalizeApiError } from '../../lib/api-errors';
import { formatDateTime, humanizeCode } from '../../lib/format';
import type {
  AdminIngredientCollectionEnvelope,
  AdminPurchaseOrderCollectionEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, PageHeader, Panel, StatusPill } from '../../components/ui';

const purchaseOrderStatusOptions = ['', 'Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled'] as const;

type InventoryFilters = {
  ingredientQuery?: string;
  ingredientActiveOnly?: boolean;
  supplierQuery?: string;
  supplierActiveOnly?: boolean;
  purchaseOrderQuery?: string;
  branchId?: number;
  purchaseOrderStatus?: (typeof purchaseOrderStatusOptions)[number];
};

export function InventoryPage() {
  const { expire, session } = useStaffSession();
  const [ingredientQuery, setIngredientQuery] = useState('');
  const [ingredientActiveMode, setIngredientActiveMode] = useState<'active' | 'all'>('active');
  const [supplierQuery, setSupplierQuery] = useState('');
  const [supplierActiveMode, setSupplierActiveMode] = useState<'active' | 'all'>('active');
  const [purchaseOrderQuery, setPurchaseOrderQuery] = useState('');
  const [purchaseOrderStatus, setPurchaseOrderStatus] = useState<(typeof purchaseOrderStatusOptions)[number]>('');
  const [purchaseOrderBranchIdInput, setPurchaseOrderBranchIdInput] = useState(() => {
    const branchId = session?.startup.default_branch?.branch_id;

    return branchId ? String(branchId) : '';
  });
  const [ingredients, setIngredients] = useState<AdminIngredientCollectionEnvelope | null>(null);
  const [suppliers, setSuppliers] = useState<Array<{
    supplier_id: number;
    code: string | null;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
  }> | null>(null);
  const [purchaseOrders, setPurchaseOrders] = useState<AdminPurchaseOrderCollectionEnvelope | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const ingredientSummary = useMemo(() => summarizeIngredients(ingredients?.data ?? []), [ingredients]);
  const purchaseOrderSummary = useMemo(() => summarizePurchaseOrders(purchaseOrders?.data ?? []), [purchaseOrders]);
  const startupBranchLabel = session?.startup.default_branch
    ? `${session.startup.default_branch.branch_code} | ${session.startup.default_branch.branch_name}`
    : 'No startup branch';

  const refreshInventory = useCallback(async (filters: InventoryFilters, nextNotice: string | null) => {
    setBusyKey('refresh');
    setError(null);

    try {
      const [nextIngredients, nextSuppliers, nextPurchaseOrders] = await Promise.all([
        loadAdminIngredients({
          q: filters.ingredientQuery,
          is_active: filters.ingredientActiveOnly ? true : undefined,
          per_page: 8,
          sort: 'name',
        }),
        loadAdminSuppliers({
          q: filters.supplierQuery,
          is_active: filters.supplierActiveOnly ? true : undefined,
          per_page: 8,
          sort: 'name',
        }),
        loadAdminPurchaseOrders({
          q: filters.purchaseOrderQuery,
          branch_id: filters.branchId,
          purchase_order_status: filters.purchaseOrderStatus || undefined,
          per_page: 8,
          sort: '-created_at',
        }),
      ]);

      setIngredients(nextIngredients);
      setSuppliers(nextSuppliers.data);
      setPurchaseOrders(nextPurchaseOrders);
      setNotice(nextNotice);
    } catch (cause) {
      if (isUnauthorized(cause)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      const normalized = normalizeApiError(cause, 'Khong tai duoc inventory read models.');
      if (normalized.code === 'feature_disabled') {
        setError('Inventory uplift dang bi khoa cho branch hoac session nay. Bat rollout inventory truoc khi mo read models nay.');
        return;
      }

      setError(formatApiError(cause, 'Khong tai duoc inventory read models.'));
    } finally {
      setBusyKey(null);
    }
  }, [expire]);

  useEffect(() => {
    void refreshInventory(
      buildInventoryFilters(
        ingredientQuery,
        ingredientActiveMode,
        supplierQuery,
        supplierActiveMode,
        purchaseOrderQuery,
        purchaseOrderBranchIdInput,
        purchaseOrderStatus,
      ),
      null,
    );
  }, [refreshInventory, session?.staff_api_key_id]);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Inventory"
        title="Stock snapshot va receiving summary"
        description="Surface nay giu Batch 5 o muc read-first: ingredient stock, supplier contacts, va purchase-order receiving summary de branch lead khong can nhay sang backend cho operational reads co gia tri cao."
        actions={(
          <ActionButton
            onClick={() => refreshInventory(
              buildInventoryFilters(
                ingredientQuery,
                ingredientActiveMode,
                supplierQuery,
                supplierActiveMode,
                purchaseOrderQuery,
                purchaseOrderBranchIdInput,
                purchaseOrderStatus,
              ),
              'Da reload inventory read models.',
            )}
            busy={busyKey === 'refresh'}
            icon={<RefreshCcw className="h-4 w-4" />}
          >
            Reload inventory
          </ActionButton>
        )}
      />

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="eyebrow">Filters</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Read-only operational scope</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Ingredient va supplier reads co the loc nhanh theo active state. Receiving summary uu tien startup branch de branch lead thay ngay PO con mo ma khong can mo admin console day du.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <StatusPill value={`Startup ${startupBranchLabel}`} tone="info" />
            <StatusPill value={`Ingredients ${ingredients?.data.length ?? 0}`} tone="success" />
            <StatusPill value={`Suppliers ${suppliers?.length ?? 0}`} />
            <StatusPill value={`PO ${purchaseOrders?.data.length ?? 0}`} tone="warning" />
          </div>
        </div>

        <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Ingredient search</span>
            <input
              value={ingredientQuery}
              onChange={(event) => setIngredientQuery(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              placeholder="Beans, milk, sugar..."
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Ingredients active</span>
            <select
              value={ingredientActiveMode}
              onChange={(event) => setIngredientActiveMode(event.target.value as 'active' | 'all')}
              className="mt-3 w-full bg-transparent text-sm outline-none"
            >
              <option value="active">Active only</option>
              <option value="all">All</option>
            </select>
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Supplier search</span>
            <input
              value={supplierQuery}
              onChange={(event) => setSupplierQuery(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              placeholder="Supplier, phone, email..."
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Suppliers active</span>
            <select
              value={supplierActiveMode}
              onChange={(event) => setSupplierActiveMode(event.target.value as 'active' | 'all')}
              className="mt-3 w-full bg-transparent text-sm outline-none"
            >
              <option value="active">Active only</option>
              <option value="all">All</option>
            </select>
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Purchase order search</span>
            <input
              value={purchaseOrderQuery}
              onChange={(event) => setPurchaseOrderQuery(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              placeholder="PO code, supplier reference..."
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">PO branch ID</span>
            <input
              value={purchaseOrderBranchIdInput}
              onChange={(event) => setPurchaseOrderBranchIdInput(event.target.value)}
              className="mt-3 w-full bg-transparent text-sm outline-none"
              inputMode="numeric"
              placeholder="All"
            />
          </label>
          <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">PO status</span>
            <select
              value={purchaseOrderStatus}
              onChange={(event) => setPurchaseOrderStatus(event.target.value as (typeof purchaseOrderStatusOptions)[number])}
              className="mt-3 w-full bg-transparent text-sm outline-none"
            >
              <option value="">All statuses</option>
              {purchaseOrderStatusOptions.filter((option) => option !== '').map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end">
            <ActionButton
              onClick={() => refreshInventory(
                buildInventoryFilters(
                  ingredientQuery,
                  ingredientActiveMode,
                  supplierQuery,
                  supplierActiveMode,
                  purchaseOrderQuery,
                  purchaseOrderBranchIdInput,
                  purchaseOrderStatus,
                ),
                'Da ap dung inventory filters moi.',
              )}
              busy={busyKey === 'refresh'}
              icon={<RefreshCcw className="h-4 w-4" />}
            >
              Apply filters
            </ActionButton>
          </div>
        </div>
      </Panel>

      <div className="grid gap-4 xl:grid-cols-3">
        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Stock snapshot</p>
              <h3 className="text-xl font-semibold text-slate-950">Ingredient health</h3>
            </div>
            <Boxes className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Displayed" value={String(ingredientSummary.displayedCount)} />
            <MetricCard label="Zero stock" value={String(ingredientSummary.zeroStockCount)} />
            <MetricCard label="Recipe usage" value={String(ingredientSummary.recipeUsageCount)} />
          </div>
          <div className="mt-4 space-y-3">
            {(ingredients?.data ?? []).length === 0 ? (
              <EmptyState
                title="Chua co ingredient snapshot"
                description="Khi inventory foundation da co du lieu, page nay se hien on_hand, unit_code, va recipe usage count de branch lead scan nhanh muc can refill."
              />
            ) : (
              (ingredients?.data ?? []).map((ingredient) => (
                <div key={ingredient.ingredient_id} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{ingredient.name}</p>
                      <p className="mt-1 text-xs text-slate-500">{ingredient.code ?? `Ingredient #${ingredient.ingredient_id}`} | {ingredient.unit_code}</p>
                    </div>
                    <StatusPill value={ingredient.is_active ? 'Active' : 'Inactive'} tone={ingredient.is_active ? 'success' : 'danger'} />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="On hand" value={`${formatQuantity(ingredient.stock.on_hand)} ${ingredient.stock.unit_code}`} />
                    <MetricCard label="Recipe usage" value={String(ingredient.recipe_usage_count)} />
                    <MetricCard label="Updated" value={formatDateTime(ingredient.updated_at)} />
                  </div>
                </div>
              ))
            )}
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Suppliers</p>
              <h3 className="text-xl font-semibold text-slate-950">Contact readiness</h3>
            </div>
            <Truck className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Displayed" value={String(suppliers?.length ?? 0)} />
            <MetricCard label="Active" value={String((suppliers ?? []).filter((supplier) => supplier.is_active).length)} />
            <MetricCard label="With phone" value={String((suppliers ?? []).filter((supplier) => (supplier.phone ?? '').trim() !== '').length)} />
          </div>
          <div className="mt-4 space-y-3">
            {(suppliers ?? []).length === 0 ? (
              <EmptyState
                title="Chua co supplier read model"
                description="Danh sach supplier giup branch lead co ngay contact context khi can theo doi receiving hoac escalation."
              />
            ) : (
              (suppliers ?? []).map((supplier) => (
                <div key={supplier.supplier_id} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{supplier.name}</p>
                      <p className="mt-1 text-xs text-slate-500">{supplier.code ?? `Supplier #${supplier.supplier_id}`} | {supplier.contact_name ?? 'No contact'}</p>
                    </div>
                    <StatusPill value={supplier.is_active ? 'Active' : 'Inactive'} tone={supplier.is_active ? 'success' : 'danger'} />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Phone" value={supplier.phone ?? 'N/A'} />
                    <MetricCard label="Email" value={supplier.email ?? 'N/A'} />
                    <MetricCard label="Updated" value={formatDateTime(supplier.updated_at)} />
                  </div>
                </div>
              ))
            )}
          </div>
        </Panel>

        <Panel>
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="eyebrow">Receiving summary</p>
              <h3 className="text-xl font-semibold text-slate-950">Purchase-order status</h3>
            </div>
            <PackageCheck className="h-5 w-5 text-slate-400" />
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-1">
            <MetricCard label="Displayed" value={String(purchaseOrders?.data.length ?? 0)} />
            <MetricCard label="Open PO" value={String(purchaseOrderSummary.openCount)} />
            <MetricCard label="Receipts" value={String(purchaseOrderSummary.receiptCount)} />
          </div>
          <div className="mt-4 space-y-3">
            {(purchaseOrders?.data ?? []).length === 0 ? (
              <EmptyState
                title="Chua co purchase-order summary"
                description="Batch 5 chi mo receiving summary o muc read-first, nen page nay tap trung vao trang thai PO, receipt_count, va remaining quantity thay vi mutation paths."
              />
            ) : (
              (purchaseOrders?.data ?? []).map((purchaseOrder) => (
                <div key={purchaseOrder.purchase_order_id} className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{purchaseOrder.order_code}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {purchaseOrder.supplier?.name ?? `Supplier #${purchaseOrder.supplier_id}`} | {purchaseOrder.branch?.branch_code ?? 'No branch'}
                      </p>
                    </div>
                    <StatusPill value={humanizeCode(purchaseOrder.purchase_order_status)} tone={purchaseOrderTone(purchaseOrder.purchase_order_status)} />
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <MetricCard label="Receipts" value={String(purchaseOrder.summary.receipt_count)} />
                    <MetricCard label="Remaining" value={formatQuantity(purchaseOrder.summary.remaining_total_quantity)} />
                    <MetricCard label="Expected" value={formatDateTime(purchaseOrder.expected_at ?? purchaseOrder.ordered_at ?? purchaseOrder.created_at)} />
                  </div>
                  {purchaseOrder.supplier_reference ? (
                    <p className="mt-3 text-sm text-slate-600">Supplier reference: {purchaseOrder.supplier_reference}</p>
                  ) : null}
                </div>
              ))
            )}
          </div>
        </Panel>
      </div>
    </div>
  );
}

function buildInventoryFilters(
  ingredientQuery: string,
  ingredientActiveMode: 'active' | 'all',
  supplierQuery: string,
  supplierActiveMode: 'active' | 'all',
  purchaseOrderQuery: string,
  purchaseOrderBranchIdInput: string,
  purchaseOrderStatus: (typeof purchaseOrderStatusOptions)[number],
): InventoryFilters {
  return {
    ingredientQuery: ingredientQuery.trim() || undefined,
    ingredientActiveOnly: ingredientActiveMode === 'active',
    supplierQuery: supplierQuery.trim() || undefined,
    supplierActiveOnly: supplierActiveMode === 'active',
    purchaseOrderQuery: purchaseOrderQuery.trim() || undefined,
    branchId: parsePositiveInteger(purchaseOrderBranchIdInput),
    purchaseOrderStatus,
  };
}

function summarizeIngredients(rows: AdminIngredientCollectionEnvelope['data']) {
  return {
    displayedCount: rows.length,
    zeroStockCount: rows.filter((ingredient) => numericValue(ingredient.stock.on_hand) === 0).length,
    recipeUsageCount: rows.reduce((sum, ingredient) => sum + ingredient.recipe_usage_count, 0),
  };
}

function summarizePurchaseOrders(rows: AdminPurchaseOrderCollectionEnvelope['data']) {
  return {
    openCount: rows.filter((purchaseOrder) => !['Received', 'Cancelled'].includes(purchaseOrder.purchase_order_status)).length,
    receiptCount: rows.reduce((sum, purchaseOrder) => sum + purchaseOrder.summary.receipt_count, 0),
  };
}

function purchaseOrderTone(status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' {
  switch (status) {
    case 'Received':
      return 'success';
    case 'Cancelled':
      return 'danger';
    case 'PartiallyReceived':
      return 'info';
    default:
      return 'warning';
  }
}

function parsePositiveInteger(value: string): number | undefined {
  const parsed = Number(value);

  if (!Number.isInteger(parsed) || parsed <= 0) {
    return undefined;
  }

  return parsed;
}

function numericValue(value: string | number | null | undefined): number {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return 0;
  }

  const parsed = Number(value);
  return Number.isNaN(parsed) ? 0 : parsed;
}

function formatQuantity(value: string | number | null | undefined): string {
  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(numericValue(value));
}
