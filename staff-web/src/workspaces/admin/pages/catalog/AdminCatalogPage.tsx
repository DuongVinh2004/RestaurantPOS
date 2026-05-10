import { Button, Card, Col, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  buildAdminMenuCategoryQuery,
  buildAdminMenuItemPriceQuery,
  buildAdminMenuItemQuery,
  formatCatalogPrice,
  readSelectedCatalogItemId,
  resolveSelectedCatalogItem,
  summarizeAdminCatalog,
  type AdminCatalogFilterState,
} from '../../../../domains/admin/admin-catalog';
import { catalogImportDomains } from '../../../../domains/admin/admin-master-data';
import {
  createAdminMenuCategory,
  createAdminMenuItem,
  createAdminMenuItemPrice,
  listAdminMenuCategories,
  listAdminMenuItemPrices,
  listAdminMenuItems,
} from '../../../../shared/api/staff-api';
import { formatApiError, isKnownApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { AdminMasterDataImportPanel } from '../components/AdminMasterDataImportPanel';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

export function AdminCatalogPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<AdminCatalogFilterState>({
    categoryQuery: '',
    includeDeletedCategories: false,
    itemQuery: '',
    availableOnly: true,
    categoryIdInput: '',
    selectedItemIdInput: '',
  });
  const [categoryForm, setCategoryForm] = useState({ name: '', description: '', sortOrder: '0' });
  const [itemForm, setItemForm] = useState({
    code: '',
    name: '',
    categoryId: '',
    description: '',
    available: true,
  });
  const [priceForm, setPriceForm] = useState({
    price: '',
    currency: 'VND',
    effectiveFrom: new Date().toISOString().slice(0, 16),
  });

  const selectedItemId = readSelectedCatalogItemId(filters);

  const categoriesQuery = useQuery({
    queryKey: ['admin-catalog-categories', filters.categoryQuery, filters.includeDeletedCategories],
    queryFn: () => listAdminMenuCategories(buildAdminMenuCategoryQuery(filters)),
  });
  const itemsQuery = useQuery({
    queryKey: ['admin-catalog-items', filters.itemQuery, filters.availableOnly, filters.categoryIdInput],
    queryFn: () => listAdminMenuItems(buildAdminMenuItemQuery(filters)),
  });
  const pricesQuery = useQuery({
    queryKey: ['admin-catalog-prices', selectedItemId],
    queryFn: () => listAdminMenuItemPrices(selectedItemId as number, buildAdminMenuItemPriceQuery()),
    enabled: selectedItemId !== null,
  });

  const categories = useMemo(() => categoriesQuery.data?.data ?? [], [categoriesQuery.data?.data]);
  const items = useMemo(() => itemsQuery.data?.data ?? [], [itemsQuery.data?.data]);
  const prices = useMemo(() => pricesQuery.data?.data ?? [], [pricesQuery.data?.data]);
  const selectedCatalogItem = useMemo(() => resolveSelectedCatalogItem(selectedItemId, items), [items, selectedItemId]);
  const selectedItemLabel = selectedCatalogItem.displayLabel ?? 'Món đã chọn';
  const pricePanelReady = selectedItemId !== null && !pricesQuery.isLoading && !pricesQuery.error;
  const summary = useMemo(() => summarizeAdminCatalog(categories, items, prices), [categories, items, prices]);

  const createCategoryMutation = useMutation({
    mutationFn: () => {
      const sortOrder = Number(categoryForm.sortOrder);
      if (categoryForm.name.trim() === '') {
        throw new Error('Hãy nhập tên loại món.');
      }

      return createAdminMenuCategory({
        name: categoryForm.name.trim(),
        description: categoryForm.description.trim() || null,
        sort_order: Number.isInteger(sortOrder) ? sortOrder : 0,
      });
    },
    onSuccess: async () => {
      setCategoryForm({ name: '', description: '', sortOrder: '0' });
      await queryClient.invalidateQueries({ queryKey: ['admin-catalog-categories'] });
      toast.success('Đã tạo loại món.');
    },
  });

  const createItemMutation = useMutation({
    mutationFn: () => {
      const categoryId = Number(itemForm.categoryId);
      if (itemForm.name.trim() === '') {
        throw new Error('Hãy nhập tên món.');
      }

      return createAdminMenuItem({
        code: itemForm.code.trim() || null,
        name: itemForm.name.trim(),
        category_id: Number.isInteger(categoryId) && categoryId > 0 ? categoryId : null,
        description: itemForm.description.trim() || null,
        is_available: itemForm.available,
      });
    },
    onSuccess: async () => {
      setItemForm((current) => ({ ...current, code: '', name: '', description: '' }));
      await queryClient.invalidateQueries({ queryKey: ['admin-catalog-items'] });
      toast.success('Đã tạo món ăn.');
    },
  });

  const createPriceMutation = useMutation({
    mutationFn: () => {
      const itemId = selectedItemId;
      const price = Number(priceForm.price);
      if (!itemId || !Number.isFinite(price) || price < 0 || priceForm.effectiveFrom.trim() === '') {
        throw new Error('Hãy chọn món, nhập giá và thời điểm áp dụng trước.');
      }

      return createAdminMenuItemPrice(itemId, {
        price,
        currency: priceForm.currency.trim() || 'VND',
        effective_from: new Date(priceForm.effectiveFrom).toISOString(),
      });
    },
    onSuccess: async () => {
      setPriceForm((current) => ({ ...current, price: '' }));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin-catalog-items'] }),
        queryClient.invalidateQueries({ queryKey: ['admin-catalog-prices'] }),
      ]);
      toast.success('Đã thêm dòng giá món.');
    },
  });

  const updateCategoryForm = (patch: Partial<typeof categoryForm>) => {
    createCategoryMutation.reset();
    setCategoryForm((current) => ({ ...current, ...patch }));
  };

  const updateItemForm = (patch: Partial<typeof itemForm>) => {
    createItemMutation.reset();
    setItemForm((current) => ({ ...current, ...patch }));
  };

  const updatePriceForm = (patch: Partial<typeof priceForm>) => {
    createPriceMutation.reset();
    setPriceForm((current) => ({ ...current, ...patch }));
  };

  const updateSelectedItemIdInput = (nextItemId: string) => {
    createPriceMutation.reset();
    setFilters((current) => ({ ...current, selectedItemIdInput: nextItemId }));
  };

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Thực đơn"
        title="Thực đơn và giá bán"
        description="Quản lý loại món, món ăn, dòng giá và dữ liệu nhập/xuất thực đơn qua quyền menu.manage."
        context={(
          <>
            <StatusChip label={`${summary.categories} loại món`} tone="processing" />
            <StatusChip label={`${summary.items} món`} tone="processing" />
            <StatusChip label={`${summary.pricedItems} món có giá`} tone="success" />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Lọc thực đơn">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              aria-label="Tìm loại món"
              autoComplete="off"
              placeholder="Tìm loại món"
              value={filters.categoryQuery}
              onChange={(event) => setFilters((current) => ({ ...current, categoryQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Gồm mục đã xóa</span>
              <Switch
                checked={filters.includeDeletedCategories}
                onChange={(checked) => setFilters((current) => ({ ...current, includeDeletedCategories: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Tìm món ăn"
              autoComplete="off"
              placeholder="Tìm món ăn"
              value={filters.itemQuery}
              onChange={(event) => setFilters((current) => ({ ...current, itemQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Chỉ món đang bán</span>
              <Switch
                checked={filters.availableOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, availableOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Mã loại món"
              autoComplete="off"
              placeholder="Mã loại món"
              value={filters.categoryIdInput}
              onChange={(event) => setFilters((current) => ({ ...current, categoryIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Mã món đang chọn"
              autoComplete="off"
              placeholder="Mã món để xem giá"
              value={filters.selectedItemIdInput}
              onChange={(event) => updateSelectedItemIdInput(event.target.value)}
            />
          </Col>
        </Row>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Loại món" value={summary.categories} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Món đang bán" value={summary.availableItems} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Dòng giá" value={summary.priceRows} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Loại món">
        <CatalogSurface
          loading={categoriesQuery.isLoading}
          error={categoriesQuery.error}
          fallback="Chưa tải được loại món."
          rows={categories}
          emptyTitle="Không có loại món phù hợp"
          onRetry={() => void categoriesQuery.refetch()}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {categories.map((category) => (
                <div key={category.category_id} className="staff-admin-surface-item">
                  <div>
                    <strong>{category.name}</strong>
                    <Typography.Paragraph type="secondary">Loại #{category.category_id} / thứ tự {category.sort_order}</Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={category.is_deleted ? 'Đã xóa' : 'Đang dùng'} tone={category.is_deleted ? 'warning' : 'success'} />
                  </Space>
                  <Typography.Text type="secondary">{category.description ?? 'Chưa có mô tả'}</Typography.Text>
                </div>
              ))}
            </div>
          )}
        />
      </Card>

      <Card className="staff-workspace-table-card" title="Món ăn">
        <CatalogSurface
          loading={itemsQuery.isLoading}
          error={itemsQuery.error}
          fallback="Chưa tải được món ăn."
          rows={items}
          emptyTitle="Không có món ăn phù hợp"
          onRetry={() => void itemsQuery.refetch()}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {items.map((item) => (
                <button
                  key={item.item_id}
                  type="button"
                  className={`staff-admin-branch-row ${item.item_id === selectedItemId ? 'staff-admin-branch-row-selected' : ''}`}
                  onClick={() => updateSelectedItemIdInput(String(item.item_id))}
                >
                  <div className="staff-admin-branch-row-main">
                    <strong>{item.name}</strong>
                    <span>{item.code ?? `Món #${item.item_id}`} / {item.category?.name ?? `Loại #${item.category_id ?? 'không rõ'}`}</span>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={item.is_available ? 'Đang bán' : 'Tạm ngưng'} tone={item.is_available ? 'success' : 'warning'} />
                    <StatusChip label={item.current_price ? formatCatalogPrice(item.current_price.price, item.current_price.currency) : 'Chưa có giá hiện tại'} tone={item.current_price ? 'success' : 'warning'} />
                  </Space>
                </button>
              ))}
            </div>
          )}
        />
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card className="staff-workspace-detail-card" title="Tạo loại món">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Input
              aria-label="Tên loại món mới"
              autoComplete="off"
              placeholder="Tên loại món"
              value={categoryForm.name}
              onChange={(event) => updateCategoryForm({ name: event.target.value })}
            />
            <Input
              aria-label="Mô tả loại món mới"
              autoComplete="off"
              placeholder="Mô tả"
              value={categoryForm.description}
              onChange={(event) => updateCategoryForm({ description: event.target.value })}
            />
            <InputNumber
              aria-label="Thứ tự loại món mới"
              style={{ width: '100%' }}
              value={Number(categoryForm.sortOrder)}
              onChange={(value) => updateCategoryForm({ sortOrder: value === null ? '0' : String(value) })}
            />
          <Button type="primary" loading={createCategoryMutation.isPending} disabled={createCategoryMutation.isPending} onClick={() => createCategoryMutation.mutate()}>
            Tạo loại món
          </Button>
          <CatalogMutationErrorBlock
            error={createCategoryMutation.error}
            fallback="Chưa tạo được loại món."
            onRetry={() => createCategoryMutation.mutate()}
            validationTitle="Thông tin loại món chưa hợp lệ"
          />
        </Space>
      </Card>

      <Card className="staff-workspace-detail-card" title="Tạo món ăn">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Input
              aria-label="Mã món mới"
              autoComplete="off"
              placeholder="Mã món"
              value={itemForm.code}
              onChange={(event) => updateItemForm({ code: event.target.value })}
            />
            <Input
              aria-label="Tên món mới"
              autoComplete="off"
              placeholder="Tên món"
              value={itemForm.name}
              onChange={(event) => updateItemForm({ name: event.target.value })}
            />
          <Select
            aria-label="Loại món của món mới"
            style={{ width: '100%' }}
            placeholder="Loại món"
            allowClear
            value={itemForm.categoryId || undefined}
            options={categories.map((category) => ({ value: String(category.category_id), label: category.name }))}
            onChange={(value) => updateItemForm({ categoryId: value ?? '' })}
          />
            <Input
              aria-label="Mô tả món mới"
              autoComplete="off"
              placeholder="Mô tả"
              value={itemForm.description}
              onChange={(event) => updateItemForm({ description: event.target.value })}
            />
          <label className="staff-admin-switch-row">
            <span>Đang bán</span>
            <Switch
              checked={itemForm.available}
              onChange={(checked) => updateItemForm({ available: checked })}
            />
          </label>
          <Button type="primary" loading={createItemMutation.isPending} disabled={createItemMutation.isPending} onClick={() => createItemMutation.mutate()}>
            Tạo món
          </Button>
          <CatalogMutationErrorBlock
            error={createItemMutation.error}
            fallback="Chưa tạo được món ăn."
            onRetry={() => createItemMutation.mutate()}
            notFoundTitle="Không còn thấy loại món đã chọn"
            notFoundDescription="Loại món đang gắn cho món mới có thể vừa bị xóa hoặc nằm ngoài phạm vi hiện tại."
            validationTitle="Thông tin món ăn chưa hợp lệ"
          />
        </Space>
      </Card>

      <Card className="staff-workspace-detail-card" title="Quản lý giá">
        <CatalogPricePanel
          selectedItemId={selectedItemId}
          selectedItemLabel={selectedItemLabel}
          selectedItemOutsideCurrentResults={selectedCatalogItem.outsideCurrentResults}
          prices={prices}
          pricesQueryError={pricesQuery.error}
          pricesQueryLoading={pricesQuery.isLoading}
          pricePanelReady={pricePanelReady}
          onRetryPrices={() => void pricesQuery.refetch()}
          priceForm={priceForm}
          setPriceForm={updatePriceForm}
          createPriceError={createPriceMutation.error}
          createPricePending={createPriceMutation.isPending}
          onCreatePrice={() => createPriceMutation.mutate()}
        />
      </Card>

      <AdminMasterDataImportPanel
        title="Chạy thử nhập thực đơn"
        description="Kiểm tra loại món, món ăn hoặc dòng giá trước khi ghi nhận với Idempotency-Key."
        domains={catalogImportDomains}
        onCommitted={() => {
          void queryClient.invalidateQueries({ queryKey: ['admin-catalog-categories'] });
          void queryClient.invalidateQueries({ queryKey: ['admin-catalog-items'] });
          void queryClient.invalidateQueries({ queryKey: ['admin-catalog-prices'] });
        }}
      />
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}

type CatalogPriceRow = {
  price_id: number;
  price: string | number | null;
  currency: string | null;
  effective_from: string | null;
  effective_to: string | null;
};

type CatalogPriceFormState = {
  price: string;
  currency: string;
  effectiveFrom: string;
};

type CatalogPriceFormPatch = Partial<CatalogPriceFormState>;

function CatalogMutationErrorBlock({
  error,
  fallback,
  onRetry,
  notFoundTitle,
  notFoundDescription,
  validationTitle,
}: {
  error: unknown;
  fallback: string;
  onRetry: () => void;
  notFoundTitle?: string;
  notFoundDescription?: string;
  validationTitle?: string;
}) {
  if (!error) {
    return null;
  }

  if (!isKnownApiError(error)) {
    return <Typography.Text type="danger">{formatApiError(error, fallback)}</Typography.Text>;
  }

  return (
    <ApiStateBlock
      error={error}
      fallback={fallback}
      onRetry={onRetry}
      retryLabel="Thử gửi lại"
      notFoundTitle={notFoundTitle}
      notFoundDescription={notFoundDescription}
      validationTitle={validationTitle}
    />
  );
}

function CatalogPricePanel({
  selectedItemId,
  selectedItemLabel,
  selectedItemOutsideCurrentResults,
  prices,
  pricesQueryError,
  pricesQueryLoading,
  pricePanelReady,
  onRetryPrices,
  priceForm,
  setPriceForm,
  createPriceError,
  createPricePending,
  onCreatePrice,
}: {
  selectedItemId: number | null;
  selectedItemLabel: string;
  selectedItemOutsideCurrentResults: boolean;
  prices: Array<CatalogPriceRow>;
  pricesQueryError: unknown;
  pricesQueryLoading: boolean;
  pricePanelReady: boolean;
  onRetryPrices: () => void;
  priceForm: CatalogPriceFormState;
  setPriceForm: (patch: CatalogPriceFormPatch) => void;
  createPriceError: unknown;
  createPricePending: boolean;
  onCreatePrice: () => void;
}) {
  if (selectedItemId === null) {
    return <EmptyBlock title="Chưa chọn món" description="Chọn một món hoặc nhập mã món để xem và thêm dòng giá." />;
  }

  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      <Typography.Text>{selectedItemLabel}</Typography.Text>
      {selectedItemOutsideCurrentResults ? (
        <Typography.Text type="secondary">
          Đang xem theo mã món trực tiếp. Món này có thể đang nằm ngoài bộ lọc hiện tại hoặc vừa thay đổi ở nơi khác.
        </Typography.Text>
      ) : null}
      {pricesQueryLoading ? (
        <InlineLoading
          tip="Đang tải dòng giá..."
          description={selectedItemOutsideCurrentResults ? 'Hệ thống đang kiểm tra mã món bạn vừa nhập trong contract hiện tại.' : undefined}
        />
      ) : null}
      {pricesQueryError ? (
        <ApiStateBlock
          error={pricesQueryError}
          fallback="Chưa tải được dòng giá."
          onRetry={onRetryPrices}
          notFoundTitle={`Không còn thấy ${selectedItemLabel.toLowerCase()}`}
          notFoundDescription="Mã món này có thể đã bị xóa, nằm ngoài phạm vi hiện tại hoặc bạn không còn quyền xem."
        />
      ) : null}
      {pricePanelReady ? (
        <>
          {prices.length > 0 ? (
            <div className="staff-admin-detail-list">
              {prices.map((price) => (
                <div key={price.price_id} className="staff-admin-detail-item">
                  <strong>{formatCatalogPrice(price.price, price.currency)}</strong>
                  <span>{formatDateTime(price.effective_from)} đến {price.effective_to ? formatDateTime(price.effective_to) : 'chưa có ngày kết thúc'}</span>
                </div>
              ))}
            </div>
          ) : (
            <EmptyBlock
              title="Chưa có dòng giá"
              description={`Hãy thêm giá trước khi ${selectedItemLabel.toLowerCase()} có thể bán với giá hiện tại.`}
            />
          )}
          <InputNumber
            aria-label="Giá món mới"
            style={{ width: '100%' }}
            min={0}
            value={priceForm.price === '' ? null : Number(priceForm.price)}
            placeholder="Giá"
            onChange={(value) => setPriceForm({ price: value === null ? '' : String(value) })}
          />
          <Input
            aria-label="Tiền tệ của giá mới"
            autoComplete="off"
            value={priceForm.currency}
            onChange={(event) => setPriceForm({ currency: event.target.value })}
          />
          <Input
            aria-label="Thời điểm áp dụng giá mới"
            type="datetime-local"
            value={priceForm.effectiveFrom}
            onChange={(event) => setPriceForm({ effectiveFrom: event.target.value })}
          />
          <Button type="primary" loading={createPricePending} disabled={createPricePending} onClick={onCreatePrice}>
            Thêm dòng giá
          </Button>
          <CatalogMutationErrorBlock
            error={createPriceError}
            fallback="Chưa tạo được giá món."
            onRetry={onCreatePrice}
            notFoundTitle={`Không còn thêm giá cho ${selectedItemLabel.toLowerCase()}`}
            notFoundDescription="Mã món này có thể đã bị xóa, đổi phạm vi hoặc bạn không còn quyền cập nhật."
            validationTitle="Dòng giá chưa hợp lệ"
          />
        </>
      ) : null}
    </Space>
  );
}

function CatalogSurface({
  loading,
  error,
  fallback,
  rows,
  emptyTitle,
  renderRows,
  onRetry,
}: {
  loading: boolean;
  error: unknown;
  fallback: string;
  rows: Array<unknown>;
  emptyTitle: string;
  renderRows: () => JSX.Element;
  onRetry: () => void;
}) {
  if (loading) {
    return <InlineLoading tip="Đang tải dữ liệu thực đơn..." />;
  }

  if (error) {
    return <ApiStateBlock error={error} fallback={fallback} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyBlock title={emptyTitle} description="Hãy đổi bộ lọc hoặc tạo/nhập thêm dữ liệu cho khu vực này." />;
  }

  return renderRows();
}
