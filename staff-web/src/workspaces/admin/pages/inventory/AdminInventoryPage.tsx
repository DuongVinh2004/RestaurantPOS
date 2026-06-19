import { Button, Card, Col, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  adminInventoryLaneNotes,
  adminPurchaseOrderTone,
  adminPurchaseOrderStatusLabel,
  buildAdminIngredientMovementQuery,
  buildAdminIngredientQuery,
  buildAdminPurchaseOrderQuery,
  buildAdminSupplierQuery,
  formatInventoryQuantity,
  inventoryMovementTone,
  inventoryMovementTypeLabel,
  inventoryMovementTypeOptions,
  inventoryReceiptStatusLabel,
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
  showAdminPurchaseOrder,
  updateAdminIngredient,
  updateAdminSupplier,
  updateAdminPurchaseOrder,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';
import { IngredientModal } from './IngredientModal';
import { SupplierModal } from './SupplierModal';
import { PurchaseOrderModal } from './PurchaseOrderModal';
import { PurchaseReceiptModal } from './PurchaseReceiptModal';
import { RecipeModal } from './RecipeModal';
const purchaseOrderStatusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'Draft', label: 'Nháp' },
  { value: 'Ordered', label: 'Đã đặt hàng' },
  { value: 'PartiallyReceived', label: 'Nhận một phần' },
  { value: 'Received', label: 'Đã nhận đủ' },
  { value: 'Cancelled', label: 'Đã hủy' },
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

  const [isIngredientModalOpen, setIsIngredientModalOpen] = useState(false);
  const [editingIngredient, setEditingIngredient] = useState<any | null>(null);
  const [isSupplierModalOpen, setIsSupplierModalOpen] = useState(false);
  const [editingSupplier, setEditingSupplier] = useState<any | null>(null);
  const [isPOModalOpen, setIsPOModalOpen] = useState(false);
  const [isReceiptModalOpen, setIsReceiptModalOpen] = useState(false);
  const [isRecipeModalOpen, setIsRecipeModalOpen] = useState(false);
  const [recipeMenuItemId, setRecipeMenuItemId] = useState<number | null>(null);
  const [recipeMenuItemName, setRecipeMenuItemName] = useState<string>('');

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

  const purchaseOrderDetailQuery = useQuery({
    queryKey: ['admin-inventory-purchase-order-detail', selectedPurchaseOrderId],
    queryFn: () => showAdminPurchaseOrder(selectedPurchaseOrderId as number),
    enabled: selectedPurchaseOrderId !== null && isReceiptModalOpen,
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
    () => purchaseOrderRows.find((purchaseOrder: any) => purchaseOrder.purchase_order_id === selectedPurchaseOrderId) ?? null,
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
        throw new Error('Hãy chọn nguyên liệu trước khi ghi nhận xuất nhập kho.');
      }

      const quantity = Number(movementForm.quantity);
      const scopedBranchId = Number(filters.branchIdInput) || branchId;
      if (!Number.isFinite(quantity) || quantity <= 0) {
        throw new Error('Số lượng xuất nhập kho phải lớn hơn 0.');
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
      toast.success('Đã ghi nhận xuất nhập kho.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa ghi nhận được xuất nhập kho.'));
    },
  });

  const updateIngredientMutation = useMutation({
    mutationFn: (variables: { id: number, payload: any }) => updateAdminIngredient(variables.id, variables.payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredients'] });
      toast.success('Đã cập nhật trạng thái nguyên liệu.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa cập nhật được nguyên liệu.'));
    },
  });

  const updateSupplierMutation = useMutation({
    mutationFn: (variables: { id: number, payload: any }) => updateAdminSupplier(variables.id, variables.payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-inventory-suppliers'] });
      toast.success('Đã cập nhật trạng thái nhà cung cấp.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa cập nhật được nhà cung cấp.'));
    },
  });

  const updatePurchaseOrderMutation = useMutation({
    mutationFn: (variables: { id: number, payload: any }) => updateAdminPurchaseOrder(variables.id, variables.payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-inventory-purchase-orders'] });
      toast.success('Đã hủy đơn mua hàng.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa hủy được đơn mua hàng.'));
    },
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Kho"
        title="Kho và mua hàng"
        description="Theo dõi nguyên liệu, nhà cung cấp và đơn mua hàng trong khu quản trị, tách khỏi màn vận hành ca."
        context={(
          <>
            <StatusChip label={filters.branchIdInput || branchId ? `Chi nhánh #${filters.branchIdInput || branchId}` : 'Tất cả chi nhánh'} tone={filters.branchIdInput || branchId ? 'processing' : 'default'} />
            <StatusChip label={`${purchaseOrderSummary.openCount} đơn mua đang mở`} tone={purchaseOrderSummary.openCount > 0 ? 'warning' : 'success'} />
            <StatusChip label={`${ingredientSummary.zeroStockCount} nguyên liệu hết tồn`} tone={ingredientSummary.zeroStockCount > 0 ? 'warning' : 'default'} />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Lọc kho">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={12}>
            <Input
              aria-label="Tìm nguyên liệu"
              autoComplete="off"
              placeholder="Tìm nguyên liệu"
              value={filters.ingredientQuery}
              onChange={(event) => setFilters((current) => ({ ...current, ingredientQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={12}>
            <label className="staff-admin-switch-row">
              <span>Chỉ nguyên liệu đang dùng</span>
              <Switch
                checked={filters.ingredientActiveOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, ingredientActiveOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={12}>
            <Input
              aria-label="Tìm nhà cung cấp"
              autoComplete="off"
              placeholder="Tìm nhà cung cấp"
              value={filters.supplierQuery}
              onChange={(event) => setFilters((current) => ({ ...current, supplierQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={12}>
            <label className="staff-admin-switch-row">
              <span>Chỉ nhà cung cấp đang dùng</span>
              <Switch
                checked={filters.supplierActiveOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, supplierActiveOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Tìm đơn mua hàng"
              autoComplete="off"
              placeholder="Tìm đơn mua hàng"
              value={filters.purchaseOrderQuery}
              onChange={(event) => setFilters((current) => ({ ...current, purchaseOrderQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Mã chi nhánh của đơn mua"
              autoComplete="off"
              placeholder="Mã chi nhánh"
              value={filters.branchIdInput}
              onChange={(event) => setFilters((current) => ({ ...current, branchIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <Select
              aria-label="Trạng thái đơn mua"
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
            <Statistic title="Nguyên liệu" value={ingredientSummary.displayedCount} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Nhà cung cấp" value={supplierSummary.displayedCount} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Đơn mua hàng" value={purchaseOrderSummary.displayedCount} />
          </Card>
        </Col>
      </Row>

      <Card
        className="staff-workspace-table-card"
        title="Nguyên liệu"
        extra={<Button type="primary" size="small" data-testid="inventory-ingredient-create-button" onClick={() => { setEditingIngredient(null); setIsIngredientModalOpen(true); }}>Tạo NL</Button>}
      >
        <QuerySurface
          loading={ingredientsQuery.isLoading}
          error={ingredientsQuery.error}
          fallback="Chưa tải được danh sách nguyên liệu."
          emptyTitle="Không có nguyên liệu phù hợp"
          emptyDescription="Hãy đổi từ khóa hoặc bộ lọc hoạt động để xem nguyên liệu khác."
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
                      {ingredient.code ?? `Nguyên liệu #${ingredient.ingredient_id}`} / {ingredient.unit_code}
                    </Typography.Paragraph>
                    <Button type="link" size="small" style={{ padding: 0 }} onClick={(e) => { e.stopPropagation(); setEditingIngredient(ingredient); setIsIngredientModalOpen(true); }}>Sửa</Button>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={ingredient.is_active ? 'Đang dùng' : 'Tạm tắt'} tone={ingredient.is_active ? 'success' : 'warning'} />
                    <StatusChip label={`${ingredient.recipe_usage_count} công thức`} tone="default" />
                    <Button
                      size="small"
                      danger
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm(`Bạn có chắc muốn ${ingredient.is_active ? 'tạm tắt' : 'mở lại'} nguyên liệu này?`)) {
                          updateIngredientMutation.mutate({
                            id: ingredient.ingredient_id,
                            payload: { row_version: ingredient.row_version, is_active: !ingredient.is_active }
                          });
                        }
                      }}
                    >
                      {ingredient.is_active ? 'Tạm tắt' : 'Mở lại'}
                    </Button>
                  </Space>
                  <Typography.Text type="secondary">
                    Tồn kho {formatInventoryQuantity(ingredient.stock.on_hand)} {ingredient.stock.unit_code}
                  </Typography.Text>
                </button>
              ))}
            </div>
          )}
          onRetry={() => void ingredientsQuery.refetch()}
        />
      </Card>

      <Card
        className="staff-workspace-table-card"
        title="Nhà cung cấp"
        extra={<Button type="primary" size="small" data-testid="inventory-supplier-create-button" onClick={() => { setEditingSupplier(null); setIsSupplierModalOpen(true); }}>Tạo NCC</Button>}
      >
        <QuerySurface
          loading={suppliersQuery.isLoading}
          error={suppliersQuery.error}
          fallback="Chưa tải được danh sách nhà cung cấp."
          emptyTitle="Không có nhà cung cấp phù hợp"
          emptyDescription="Hãy đổi bộ lọc để xem nhóm nhà cung cấp khác."
          rows={supplierRows}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {supplierRows.map((supplier) => (
                <div key={supplier.supplier_id} className="staff-admin-surface-item">
                  <div>
                    <strong>{supplier.name}</strong>
                    <Typography.Paragraph type="secondary">
                      {supplier.code ?? `Nhà cung cấp #${supplier.supplier_id}`} / {supplier.contact_name ?? 'Chưa có người liên hệ'}
                    </Typography.Paragraph>
                    <Button type="link" size="small" style={{ padding: 0 }} onClick={(e) => { e.stopPropagation(); setEditingSupplier(supplier); setIsSupplierModalOpen(true); }}>Sửa</Button>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={supplier.is_active ? 'Đang dùng' : 'Tạm tắt'} tone={supplier.is_active ? 'success' : 'warning'} />
                    <Button
                      size="small"
                      danger
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm(`Bạn có chắc muốn ${supplier.is_active ? 'tạm tắt' : 'mở lại'} nhà cung cấp này?`)) {
                          updateSupplierMutation.mutate({
                            id: supplier.supplier_id,
                            payload: { row_version: supplier.row_version, is_active: !supplier.is_active }
                          });
                        }
                      }}
                    >
                      {supplier.is_active ? 'Tạm tắt' : 'Mở lại'}
                    </Button>
                  </Space>
                  <Typography.Text type="secondary">
                    {supplier.phone ?? 'Chưa có số điện thoại'} / {supplier.email ?? 'Chưa có email'}
                  </Typography.Text>
                </div>
              ))}
            </div>
          )}
          onRetry={() => void suppliersQuery.refetch()}
        />
      </Card>

      <Card
        className="staff-workspace-table-card"
        title="Đơn mua hàng"
        extra={<Button type="primary" size="small" data-testid="inventory-po-create-button" onClick={() => setIsPOModalOpen(true)}>Tạo Đơn</Button>}
      >
        <QuerySurface
          loading={purchaseOrdersQuery.isLoading}
          error={purchaseOrdersQuery.error}
          fallback="Chưa tải được danh sách đơn mua hàng."
          emptyTitle="Không có đơn mua phù hợp"
          emptyDescription="Hãy đổi chi nhánh hoặc trạng thái để xem lịch nhận hàng khác."
          rows={purchaseOrderRows}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {purchaseOrderRows.map((purchaseOrder: any) => (
                <button
                  key={purchaseOrder.purchase_order_id}
                  type="button"
                  className={`staff-admin-branch-row ${purchaseOrder.purchase_order_id === selectedPurchaseOrderId ? 'staff-admin-branch-row-selected' : ''}`}
                  onClick={() => setSelectedPurchaseOrderId(purchaseOrder.purchase_order_id)}
                >
                  <div>
                    <strong>{purchaseOrder.order_code}</strong>
                    <Typography.Paragraph type="secondary">
                      {purchaseOrder.supplier?.name ?? `Nhà cung cấp #${purchaseOrder.supplier_id}`} / {purchaseOrder.branch?.branch_code ?? `Chi nhánh #${purchaseOrder.branch_id}`}
                    </Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={adminPurchaseOrderStatusLabel(purchaseOrder.purchase_order_status)} tone={adminPurchaseOrderTone(purchaseOrder.purchase_order_status)} />
                    <StatusChip label={`${purchaseOrder.summary.receipt_count} phiếu nhận`} tone="default" />
                    {purchaseOrder.purchase_order_status !== 'Received' && purchaseOrder.purchase_order_status !== 'Cancelled' && (
                      <Button
                        size="small"
                        danger
                        onClick={(e) => {
                          e.stopPropagation();
                          if (confirm('Bạn có chắc muốn hủy đơn mua hàng này?')) {
                            updatePurchaseOrderMutation.mutate({
                              id: purchaseOrder.purchase_order_id,
                              payload: { row_version: purchaseOrder.row_version, purchase_order_status: 'Cancelled' }
                            });
                          }
                        }}
                      >
                        Hủy đơn
                      </Button>
                    )}
                  </Space>
                  <Typography.Text type="secondary">
                    Còn lại {formatInventoryQuantity(purchaseOrder.summary.remaining_total_quantity)} / dự kiến {formatDateTime(purchaseOrder.expected_at ?? purchaseOrder.ordered_at ?? purchaseOrder.created_at)}
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
      <Card className="staff-workspace-detail-card" title="Tổng quan kho">
        <Row gutter={[12, 12]}>
          <Col span={24}>
            <Statistic title="Nguyên liệu hết tồn" value={ingredientSummary.zeroStockCount} />
          </Col>
          <Col span={24}>
            <Statistic title="Liên kết công thức" value={ingredientSummary.recipeUsageCount} />
          </Col>
          <Col span={24}>
            <Statistic title="Nhà cung cấp có số ĐT" value={supplierSummary.withPhoneCount} suffix={`/ ${supplierSummary.displayedCount}`} />
          </Col>
          <Col span={24}>
            <Statistic title="Số lượng mua còn lại" value={purchaseOrderSummary.remainingQuantity} formatter={(value) => formatInventoryQuantity(Number(value ?? 0))} />
          </Col>
        </Row>
      </Card>

      <Card className="staff-workspace-detail-card" title="Lịch sử xuất nhập kho">
        {!selectedIngredient ? (
          <EmptyBlock title="Chưa chọn nguyên liệu" description="Chọn một nguyên liệu để xem lịch sử xuất nhập, điều chỉnh thủ công hoặc hao hụt." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              <StatusChip label={selectedIngredient.name} tone="processing" />
              <StatusChip label={`${movementSummary.displayedCount} lần ghi nhận`} tone="default" />
              <StatusChip label={`${movementSummary.auditedCount} có người thao tác`} tone="success" />
            </Space>
            {ingredientMovementsQuery.isLoading ? <InlineLoading tip="Đang tải lịch sử xuất nhập kho..." /> : null}
            {ingredientMovementsQuery.error ? (
              <ApiStateBlock
                error={ingredientMovementsQuery.error}
                fallback="Chưa tải được lịch sử xuất nhập kho."
                onRetry={() => void ingredientMovementsQuery.refetch()}
              />
            ) : null}
            {movementRows.length > 0 ? (
              <div className="staff-admin-detail-list">
                {movementRows.map((movement) => (
                  <div key={movement.movement_id} className="staff-admin-detail-item">
                    <strong>{inventoryMovementTypeLabel(movement.movement_type)}</strong>
                    <span>
                      {formatInventoryQuantity(movement.quantity_delta)} {movement.unit_code} / {formatDateTime(movement.created_at)}
                    </span>
                    <StatusChip label={movement.created_by ? `Người thao tác #${movement.created_by}` : 'Chưa rõ người thao tác'} tone={movement.created_by ? 'success' : 'warning'} />
                  </div>
                ))}
              </div>
            ) : !ingredientMovementsQuery.isLoading && !ingredientMovementsQuery.error ? (
              <EmptyBlock title="Chưa có lịch sử xuất nhập" description="Không có ghi nhận nào trong phạm vi chi nhánh hiện tại." />
            ) : null}

            <Select
              aria-label="Loại xuất nhập kho"
              style={{ width: '100%' }}
              options={[...inventoryMovementTypeOptions]}
              value={movementForm.movementType}
              onChange={(value) => setMovementForm((current) => ({ ...current, movementType: value }))}
            />
            <InputNumber
              aria-label="Số lượng xuất nhập kho"
              style={{ width: '100%' }}
              min={0}
              value={movementForm.quantity === '' ? null : Number(movementForm.quantity)}
              placeholder={`Số lượng (${selectedIngredient.unit_code})`}
              onChange={(value) => setMovementForm((current) => ({ ...current, quantity: value === null ? '' : String(value) }))}
            />
            <Input
              aria-label="Ghi chú xuất nhập kho"
              autoComplete="off"
              value={movementForm.notes}
              placeholder="Ghi chú điều chỉnh"
              onChange={(event) => setMovementForm((current) => ({ ...current, notes: event.target.value }))}
            />
            <Space wrap>
              <Button
                type="primary"
                onClick={() => createMovementMutation.mutate()}
                loading={createMovementMutation.isPending}
                disabled={createMovementMutation.isPending}
              >
                Ghi nhận xuất nhập
              </Button>
              <StatusChip label={inventoryMovementTypeLabel(movementForm.movementType)} tone={inventoryMovementTone(movementForm.movementType)} />
            </Space>
            {createMovementMutation.error ? (
              <Typography.Text type="danger">
                {formatApiError(createMovementMutation.error, 'Chưa ghi nhận được xuất nhập kho.')}
              </Typography.Text>
            ) : null}
          </Space>
        )}
      </Card>

      <Card
        className="staff-workspace-detail-card"
        title="Phiếu nhận hàng"
        extra={
          selectedPurchaseOrder &&
          !['Received', 'Cancelled'].includes(selectedPurchaseOrder.purchase_order_status) ? (
            <Button
              type="primary"
              size="small"
              data-testid="inventory-receipt-create-button"
              onClick={() => setIsReceiptModalOpen(true)}
            >
              Tạo phiếu nhận
            </Button>
          ) : null
        }
      >
        {!selectedPurchaseOrder ? (
          <EmptyBlock title="Chưa chọn đơn mua" description="Chọn một đơn mua hàng để xem lịch sử nhận hàng từ backend." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              <StatusChip label={selectedPurchaseOrder.order_code} tone="processing" />
              <StatusChip label={adminPurchaseOrderStatusLabel(selectedPurchaseOrder.purchase_order_status)} tone={adminPurchaseOrderTone(selectedPurchaseOrder.purchase_order_status)} />
              <StatusChip label={`${receiptSummary.displayedCount} phiếu nhận`} tone="default" />
              <StatusChip
                label={`Tồn: ${formatInventoryQuantity(receiptSummary.receivedQuantity)}`}
                tone="success"
                data-testid="inventory-stock-on-hand-value"
              />
            </Space>
            {purchaseOrderReceiptsQuery.isLoading ? <InlineLoading tip="Đang tải lịch sử nhận hàng..." /> : null}
            {purchaseOrderReceiptsQuery.error ? (
              <ApiStateBlock
                error={purchaseOrderReceiptsQuery.error}
                fallback="Chưa tải được phiếu nhận hàng."
                onRetry={() => void purchaseOrderReceiptsQuery.refetch()}
              />
            ) : null}
            {receiptRows.length > 0 ? (
              <div className="staff-admin-detail-list">
                {receiptRows.map((receipt) => (
                  <div key={receipt.receipt_id} className="staff-admin-detail-item" data-testid="inventory-stock-movement-row">
                    <strong>{receipt.receipt_code}</strong>
                    <span>
                      {inventoryReceiptStatusLabel(receipt.receipt_status)} / {formatInventoryQuantity(receipt.summary.received_total_quantity)} đã nhận / {formatDateTime(receipt.received_at ?? receipt.created_at)}
                    </span>
                  </div>
                ))}
              </div>
            ) : !purchaseOrderReceiptsQuery.isLoading && !purchaseOrderReceiptsQuery.error ? (
              <EmptyBlock title="Chưa có phiếu nhận" description="Nhấn 'Tạo phiếu nhận' để ghi nhận hàng vào kho." />
            ) : null}
          </Space>
        )}
      </Card>

      <Card className="staff-workspace-detail-card" title="Ghi chú khu vực kho">
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

  const poDetailLines = (purchaseOrderDetailQuery.data as any)?.data?.lines ?? [];

  return (
    <>
      <SplitWorkspace main={main} side={side} />
      <IngredientModal open={isIngredientModalOpen} onClose={() => setIsIngredientModalOpen(false)} editingIngredient={editingIngredient} />
      <SupplierModal open={isSupplierModalOpen} onClose={() => setIsSupplierModalOpen(false)} editingSupplier={editingSupplier} />
      <PurchaseOrderModal open={isPOModalOpen} onClose={() => setIsPOModalOpen(false)} suppliers={supplierRows as any} ingredients={ingredientRows as any} />
      <PurchaseReceiptModal
        open={isReceiptModalOpen}
        onClose={() => setIsReceiptModalOpen(false)}
        purchaseOrderId={selectedPurchaseOrderId}
        purchaseOrderCode={selectedPurchaseOrder?.order_code ?? ''}
        lines={poDetailLines}
      />
      <RecipeModal
        open={isRecipeModalOpen}
        onClose={() => setIsRecipeModalOpen(false)}
        menuItemId={recipeMenuItemId}
        menuItemName={recipeMenuItemName}
        ingredients={ingredientRows as any}
      />
    </>
  );
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
    return <InlineLoading tip="Đang tải dữ liệu quản trị..." />;
  }

  if (error) {
    return <ApiStateBlock error={error} fallback={fallback} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyBlock title={emptyTitle} description={emptyDescription} />;
  }

  return renderRows();
}
