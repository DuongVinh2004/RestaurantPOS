import { Button, Card, Col, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  adminInventoryLaneNotes,
  adminPurchaseOrderTone,
  buildAdminIngredientMovementQuery,
  buildAdminIngredientQuery,
  buildAdminPurchaseOrderQuery,
  buildAdminSupplierQuery,
  formatInventoryQuantity,
  inventoryMovementTone,
  inventoryMovementTypeOptions,
  summarizeAdminIngredientMovements,
  summarizeAdminIngredients,
  summarizeAdminPurchaseOrders,
  summarizeAdminPurchaseReceipts,
  summarizeAdminSuppliers,
  type AdminInventoryFilterState,
} from '../../../../domains/inventory/admin-inventory';
import {
  createAdminIngredientMovement,
  listAdminIngredients,
  listAdminIngredientMovements,
  listAdminPurchaseOrders,
  listAdminPurchaseOrderReceipts,
  listAdminSuppliers,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

const purchaseOrderStatusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'Draft', label: 'Draft' },
  { value: 'Ordered', label: 'Ordered' },
  { value: 'PartiallyReceived', label: 'Partially received' },
  { value: 'Received', label: 'Received' },
  { value: 'Cancelled', label: 'Cancelled' },
];

export function AdminInventoryPage() {
  const queryClient = useQueryClient();
  const branchId = useFlowStore((state) => state.branchId);
  const [filters, setFilters] = useState<AdminInventoryFilterState>({
    ingredientQuery: '',
    ingredientActiveOnly: true,
    supplierQuery: '',
    supplierActiveOnly: true,
    purchaseOrderQuery: '',
    purchaseOrderStatus: '',
    branchIdInput: branchId ? String(branchId) : '',
  });
  const [selectedIngredientId, setSelectedIngredientId] = useState<number | null>(null);
  const [selectedPurchaseOrderId, setSelectedPurchaseOrderId] = useState<number | null>(null);
  const [movementForm, setMovementForm] = useState({
    movementType: 'AdjustmentDecrease',
    quantity: '',
    notes: '',
  });

  const ingredientsQuery = useQuery({
    queryKey: ['admin-inventory-ingredients', filters.ingredientQuery, filters.ingredientActiveOnly],
    queryFn: () => listAdminIngredients(buildAdminIngredientQuery(filters)),
  });

  const suppliersQuery = useQuery({
    queryKey: ['admin-inventory-suppliers', filters.supplierQuery, filters.supplierActiveOnly],
    queryFn: () => listAdminSuppliers(buildAdminSupplierQuery(filters)),
  });

  const purchaseOrdersQuery = useQuery({
    queryKey: ['admin-inventory-purchase-orders', filters.purchaseOrderQuery, filters.purchaseOrderStatus, filters.branchIdInput, branchId],
    queryFn: () => listAdminPurchaseOrders(buildAdminPurchaseOrderQuery(filters, branchId)),
  });

  const ingredientMovementsQuery = useQuery({
    queryKey: ['admin-inventory-ingredient-movements', selectedIngredientId, filters.branchIdInput, branchId],
    queryFn: () => listAdminIngredientMovements(
      selectedIngredientId as number,
      buildAdminIngredientMovementQuery(Number(filters.branchIdInput) || branchId),
    ),
    enabled: selectedIngredientId !== null,
  });

  const purchaseOrderReceiptsQuery = useQuery({
    queryKey: ['admin-inventory-purchase-order-receipts', selectedPurchaseOrderId],
    queryFn: () => listAdminPurchaseOrderReceipts(selectedPurchaseOrderId as number),
    enabled: selectedPurchaseOrderId !== null,
  });

  const ingredientRows = useMemo(() => ingredientsQuery.data?.data ?? [], [ingredientsQuery.data?.data]);
  const supplierRows = useMemo(() => suppliersQuery.data?.data ?? [], [suppliersQuery.data?.data]);
  const purchaseOrderRows = useMemo(() => purchaseOrdersQuery.data?.data ?? [], [purchaseOrdersQuery.data?.data]);
  const movementRows = useMemo(() => ingredientMovementsQuery.data?.data ?? [], [ingredientMovementsQuery.data?.data]);
  const receiptRows = useMemo(() => purchaseOrderReceiptsQuery.data?.data ?? [], [purchaseOrderReceiptsQuery.data?.data]);
  const selectedIngredient = useMemo(
    () => ingredientRows.find((ingredient) => ingredient.ingredient_id === selectedIngredientId) ?? null,
    [ingredientRows, selectedIngredientId],
  );
  const selectedPurchaseOrder = useMemo(
    () => purchaseOrderRows.find((purchaseOrder) => purchaseOrder.purchase_order_id === selectedPurchaseOrderId) ?? null,
    [purchaseOrderRows, selectedPurchaseOrderId],
  );

  const ingredientSummary = useMemo(() => summarizeAdminIngredients(ingredientRows), [ingredientRows]);
  const supplierSummary = useMemo(() => summarizeAdminSuppliers(supplierRows), [supplierRows]);
  const purchaseOrderSummary = useMemo(() => summarizeAdminPurchaseOrders(purchaseOrderRows), [purchaseOrderRows]);
  const movementSummary = useMemo(() => summarizeAdminIngredientMovements(movementRows), [movementRows]);
  const receiptSummary = useMemo(() => summarizeAdminPurchaseReceipts(receiptRows), [receiptRows]);

  const createMovementMutation = useMutation({
    mutationFn: async () => {
      if (!selectedIngredient) {
        throw new Error('Select an ingredient before creating a movement.');
      }

      const quantity = Number(movementForm.quantity);
      const scopedBranchId = Number(filters.branchIdInput) || branchId;
      if (!Number.isFinite(quantity) || quantity <= 0) {
        throw new Error('Movement quantity must be greater than zero.');
      }

      return createAdminIngredientMovement(selectedIngredient.ingredient_id, {
        movement_type: movementForm.movementType as 'StockIn' | 'StockOut' | 'AdjustmentIncrease' | 'AdjustmentDecrease' | 'Wastage',
        branch_id: scopedBranchId ?? null,
        quantity,
        unit_code: selectedIngredient.unit_code,
        notes: movementForm.notes.trim() || null,
      });
    },
    onSuccess: async () => {
      setMovementForm((current) => ({ ...current, quantity: '', notes: '' }));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredients'] }),
        queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredient-movements'] }),
      ]);
      toast.success('Inventory movement created.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Could not create inventory movement.'));
    },
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Supply control"
        title="Inventory and purchasing lane"
        description="Use the admin workspace for ingredient, supplier, and purchase-order oversight without mixing it back into ops."
        context={(
          <>
            <StatusChip label={`Branch ${filters.branchIdInput || branchId || 'all'}`} tone={filters.branchIdInput || branchId ? 'processing' : 'default'} />
            <StatusChip label={`${purchaseOrderSummary.openCount} open PO`} tone={purchaseOrderSummary.openCount > 0 ? 'warning' : 'success'} />
            <StatusChip label={`${ingredientSummary.zeroStockCount} zero stock`} tone={ingredientSummary.zeroStockCount > 0 ? 'warning' : 'default'} />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Inventory filters">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={12}>
            <Input
              aria-label="Search ingredients"
              autoComplete="off"
              placeholder="Ingredient search"
              value={filters.ingredientQuery}
              onChange={(event) => setFilters((current) => ({ ...current, ingredientQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={12}>
            <label className="staff-admin-switch-row">
              <span>Active ingredients only</span>
              <Switch
                checked={filters.ingredientActiveOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, ingredientActiveOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={12}>
            <Input
              aria-label="Search suppliers"
              autoComplete="off"
              placeholder="Supplier search"
              value={filters.supplierQuery}
              onChange={(event) => setFilters((current) => ({ ...current, supplierQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={12}>
            <label className="staff-admin-switch-row">
              <span>Active suppliers only</span>
              <Switch
                checked={filters.supplierActiveOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, supplierActiveOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Search purchase orders"
              autoComplete="off"
              placeholder="Purchase order search"
              value={filters.purchaseOrderQuery}
              onChange={(event) => setFilters((current) => ({ ...current, purchaseOrderQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Purchase order branch id"
              autoComplete="off"
              placeholder="Branch id"
              value={filters.branchIdInput}
              onChange={(event) => setFilters((current) => ({ ...current, branchIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <Select
              aria-label="Purchase order status"
              style={{ width: '100%' }}
              options={purchaseOrderStatusOptions}
              value={filters.purchaseOrderStatus}
              onChange={(value) => setFilters((current) => ({ ...current, purchaseOrderStatus: value }))}
            />
          </Col>
        </Row>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Ingredients" value={ingredientSummary.displayedCount} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Suppliers" value={supplierSummary.displayedCount} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Purchase orders" value={purchaseOrderSummary.displayedCount} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Ingredients">
        <QuerySurface
          loading={ingredientsQuery.isLoading}
          error={ingredientsQuery.error}
          fallback="Unable to load ingredient snapshots."
          emptyTitle="No ingredients matched this filter"
          emptyDescription="Adjust the search or active-state filter to inspect another ingredient."
          rows={ingredientRows}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {ingredientRows.map((ingredient) => (
                <button
                  key={ingredient.ingredient_id}
                  type="button"
                  className={`staff-admin-branch-row ${ingredient.ingredient_id === selectedIngredientId ? 'staff-admin-branch-row-selected' : ''}`}
                  onClick={() => setSelectedIngredientId(ingredient.ingredient_id)}
                >
                  <div>
                    <strong>{ingredient.name}</strong>
                    <Typography.Paragraph type="secondary">
                      {ingredient.code ?? `Ingredient #${ingredient.ingredient_id}`} • {ingredient.unit_code}
                    </Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={ingredient.is_active ? 'Active' : 'Inactive'} tone={ingredient.is_active ? 'success' : 'warning'} />
                    <StatusChip label={`${ingredient.recipe_usage_count} recipe refs`} tone="default" />
                  </Space>
                  <Typography.Text type="secondary">
                    On hand {formatInventoryQuantity(ingredient.stock.on_hand)} {ingredient.stock.unit_code}
                  </Typography.Text>
                </button>
              ))}
            </div>
          )}
          onRetry={() => void ingredientsQuery.refetch()}
        />
      </Card>

      <Card className="staff-workspace-table-card" title="Suppliers">
        <QuerySurface
          loading={suppliersQuery.isLoading}
          error={suppliersQuery.error}
          fallback="Unable to load supplier reads."
          emptyTitle="No suppliers matched this filter"
          emptyDescription="Adjust the current filter to inspect supplier coverage."
          rows={supplierRows}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {supplierRows.map((supplier) => (
                <div key={supplier.supplier_id} className="staff-admin-surface-item">
                  <div>
                    <strong>{supplier.name}</strong>
                    <Typography.Paragraph type="secondary">
                      {supplier.code ?? `Supplier #${supplier.supplier_id}`} • {supplier.contact_name ?? 'No contact'}
                    </Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={supplier.is_active ? 'Active' : 'Inactive'} tone={supplier.is_active ? 'success' : 'warning'} />
                  </Space>
                  <Typography.Text type="secondary">
                    {supplier.phone ?? 'No phone'} • {supplier.email ?? 'No email'}
                  </Typography.Text>
                </div>
              ))}
            </div>
          )}
          onRetry={() => void suppliersQuery.refetch()}
        />
      </Card>

      <Card className="staff-workspace-table-card" title="Purchase orders">
        <QuerySurface
          loading={purchaseOrdersQuery.isLoading}
          error={purchaseOrdersQuery.error}
          fallback="Unable to load purchase-order reads."
          emptyTitle="No purchase orders matched this filter"
          emptyDescription="Try another branch or status filter to inspect receiving activity."
          rows={purchaseOrderRows}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {purchaseOrderRows.map((purchaseOrder) => (
                <button
                  key={purchaseOrder.purchase_order_id}
                  type="button"
                  className={`staff-admin-branch-row ${purchaseOrder.purchase_order_id === selectedPurchaseOrderId ? 'staff-admin-branch-row-selected' : ''}`}
                  onClick={() => setSelectedPurchaseOrderId(purchaseOrder.purchase_order_id)}
                >
                  <div>
                    <strong>{purchaseOrder.order_code}</strong>
                    <Typography.Paragraph type="secondary">
                      {purchaseOrder.supplier?.name ?? `Supplier #${purchaseOrder.supplier_id}`} • {purchaseOrder.branch?.branch_code ?? `Branch #${purchaseOrder.branch_id}`}
                    </Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={purchaseOrder.purchase_order_status} tone={adminPurchaseOrderTone(purchaseOrder.purchase_order_status)} />
                    <StatusChip label={`${purchaseOrder.summary.receipt_count} receipts`} tone="default" />
                  </Space>
                  <Typography.Text type="secondary">
                    Remaining {formatInventoryQuantity(purchaseOrder.summary.remaining_total_quantity)} • expected {formatDateTime(purchaseOrder.expected_at ?? purchaseOrder.ordered_at ?? purchaseOrder.created_at)}
                  </Typography.Text>
                </button>
              ))}
            </div>
          )}
          onRetry={() => void purchaseOrdersQuery.refetch()}
        />
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card className="staff-workspace-detail-card" title="Supply overview">
        <Row gutter={[12, 12]}>
          <Col span={24}>
            <Statistic title="Zero-stock ingredients" value={ingredientSummary.zeroStockCount} />
          </Col>
          <Col span={24}>
            <Statistic title="Recipe references" value={ingredientSummary.recipeUsageCount} />
          </Col>
          <Col span={24}>
            <Statistic title="Supplier coverage" value={supplierSummary.withPhoneCount} suffix={`/ ${supplierSummary.displayedCount}`} />
          </Col>
          <Col span={24}>
            <Statistic title="Remaining PO quantity" value={purchaseOrderSummary.remainingQuantity} formatter={(value) => formatInventoryQuantity(Number(value ?? 0))} />
          </Col>
        </Row>
      </Card>

      <Card className="staff-workspace-detail-card" title="Stock movement history">
        {!selectedIngredient ? (
          <EmptyBlock title="No ingredient selected" description="Select an ingredient to inspect movement history and create manual adjustments or wastage." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              <StatusChip label={selectedIngredient.name} tone="processing" />
              <StatusChip label={`${movementSummary.displayedCount} movements`} tone="default" />
              <StatusChip label={`${movementSummary.auditedCount} audited`} tone="success" />
            </Space>
            {ingredientMovementsQuery.isLoading ? <InlineLoading tip="Loading movement history..." /> : null}
            {ingredientMovementsQuery.error ? (
              <ApiStateBlock
                error={ingredientMovementsQuery.error}
                fallback="Unable to load ingredient movements."
                onRetry={() => void ingredientMovementsQuery.refetch()}
              />
            ) : null}
            {movementRows.length > 0 ? (
              <div className="staff-admin-detail-list">
                {movementRows.map((movement) => (
                  <div key={movement.movement_id} className="staff-admin-detail-item">
                    <strong>{movement.movement_type}</strong>
                    <span>
                      {formatInventoryQuantity(movement.quantity_delta)} {movement.unit_code} / {formatDateTime(movement.created_at)}
                    </span>
                    <StatusChip label={movement.created_by ? `Actor #${movement.created_by}` : 'No actor'} tone={movement.created_by ? 'success' : 'warning'} />
                  </div>
                ))}
              </div>
            ) : !ingredientMovementsQuery.isLoading && !ingredientMovementsQuery.error ? (
              <EmptyBlock title="No movement history" description="No movements matched the current branch scope." />
            ) : null}

            <Select
              aria-label="Inventory movement type"
              style={{ width: '100%' }}
              options={[...inventoryMovementTypeOptions]}
              value={movementForm.movementType}
              onChange={(value) => setMovementForm((current) => ({ ...current, movementType: value }))}
            />
            <InputNumber
              aria-label="Inventory movement quantity"
              style={{ width: '100%' }}
              min={0}
              value={movementForm.quantity === '' ? null : Number(movementForm.quantity)}
              placeholder={`Quantity (${selectedIngredient.unit_code})`}
              onChange={(value) => setMovementForm((current) => ({ ...current, quantity: value === null ? '' : String(value) }))}
            />
            <Input
              aria-label="Inventory movement notes"
              autoComplete="off"
              value={movementForm.notes}
              placeholder="Adjustment notes"
              onChange={(event) => setMovementForm((current) => ({ ...current, notes: event.target.value }))}
            />
            <Space wrap>
              <Button
                type="primary"
                onClick={() => createMovementMutation.mutate()}
                loading={createMovementMutation.isPending}
                disabled={createMovementMutation.isPending}
              >
                Create movement
              </Button>
              <StatusChip label={inventoryMovementTypeOptions.find((option) => option.value === movementForm.movementType)?.label ?? movementForm.movementType} tone={inventoryMovementTone(movementForm.movementType)} />
            </Space>
            {createMovementMutation.error ? (
              <Typography.Text type="danger">
                {formatApiError(createMovementMutation.error, 'Could not create inventory movement.')}
              </Typography.Text>
            ) : null}
          </Space>
        )}
      </Card>

      <Card className="staff-workspace-detail-card" title="Receiving receipts">
        {!selectedPurchaseOrder ? (
          <EmptyBlock title="No purchase order selected" description="Select a purchase order to inspect backend receipt history." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              <StatusChip label={selectedPurchaseOrder.order_code} tone="processing" />
              <StatusChip label={`${receiptSummary.displayedCount} receipts`} tone="default" />
              <StatusChip label={`${formatInventoryQuantity(receiptSummary.receivedQuantity)} received`} tone="success" />
            </Space>
            {purchaseOrderReceiptsQuery.isLoading ? <InlineLoading tip="Loading receipt history..." /> : null}
            {purchaseOrderReceiptsQuery.error ? (
              <ApiStateBlock
                error={purchaseOrderReceiptsQuery.error}
                fallback="Unable to load purchase-order receipts."
                onRetry={() => void purchaseOrderReceiptsQuery.refetch()}
              />
            ) : null}
            {receiptRows.length > 0 ? (
              <div className="staff-admin-detail-list">
                {receiptRows.map((receipt) => (
                  <div key={receipt.receipt_id} className="staff-admin-detail-item">
                    <strong>{receipt.receipt_code}</strong>
                    <span>
                      {receipt.receipt_status} / {formatInventoryQuantity(receipt.summary.received_total_quantity)} received / {formatDateTime(receipt.received_at ?? receipt.created_at)}
                    </span>
                  </div>
                ))}
              </div>
            ) : !purchaseOrderReceiptsQuery.isLoading && !purchaseOrderReceiptsQuery.error ? (
              <EmptyBlock title="No receipts yet" description="Receiving commit remains hidden until the line-detail create contract is promoted into the staff-web facade." />
            ) : null}
          </Space>
        )}
      </Card>

      <Card className="staff-workspace-detail-card" title="Lane notes">
        <div className="staff-admin-note-list">
          {adminInventoryLaneNotes.map((note) => (
            <div key={note} className="staff-admin-note-item">
              <span />
              <Typography.Text>{note}</Typography.Text>
            </div>
          ))}
        </div>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}

function QuerySurface({
  loading,
  error,
  fallback,
  emptyTitle,
  emptyDescription,
  rows,
  renderRows,
  onRetry,
}: {
  loading: boolean;
  error: unknown;
  fallback: string;
  emptyTitle: string;
  emptyDescription: string;
  rows: Array<unknown>;
  renderRows: () => JSX.Element;
  onRetry: () => void;
}) {
  if (loading) {
    return <InlineLoading tip="Loading admin reads..." />;
  }

  if (error) {
    return <ApiStateBlock error={error} fallback={fallback} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyBlock title={emptyTitle} description={emptyDescription} />;
  }

  return renderRows();
}
