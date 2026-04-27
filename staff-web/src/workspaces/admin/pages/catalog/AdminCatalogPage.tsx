import { Button, Card, Col, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  buildAdminMenuCategoryQuery,
  buildAdminMenuItemPriceQuery,
  buildAdminMenuItemQuery,
  formatCatalogPrice,
  readSelectedCatalogItemId,
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
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { AdminMasterDataImportPanel } from '../../components/AdminMasterDataImportPanel';
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
  const selectedItem = useMemo(
    () => items.find((item) => item.item_id === selectedItemId) ?? null,
    [items, selectedItemId],
  );
  const summary = useMemo(() => summarizeAdminCatalog(categories, items, prices), [categories, items, prices]);

  const createCategoryMutation = useMutation({
    mutationFn: () => {
      const sortOrder = Number(categoryForm.sortOrder);
      if (categoryForm.name.trim() === '') {
        throw new Error('Category name is required.');
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
      toast.success('Menu category created.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Could not create menu category.')),
  });

  const createItemMutation = useMutation({
    mutationFn: () => {
      const categoryId = Number(itemForm.categoryId);
      if (itemForm.name.trim() === '') {
        throw new Error('Menu item name is required.');
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
      toast.success('Menu item created.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Could not create menu item.')),
  });

  const createPriceMutation = useMutation({
    mutationFn: () => {
      const itemId = selectedItemId;
      const price = Number(priceForm.price);
      if (!itemId || !Number.isFinite(price) || price < 0 || priceForm.effectiveFrom.trim() === '') {
        throw new Error('Select a menu item, price, and effective date first.');
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
      toast.success('Menu price row created.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Could not create menu price.')),
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Catalog"
        title="Menu and pricing lane"
        description="Manage menu categories, items, price rows, and catalog imports through menu.manage routes."
        context={(
          <>
            <StatusChip label={`${summary.categories} categories`} tone="processing" />
            <StatusChip label={`${summary.items} items`} tone="processing" />
            <StatusChip label={`${summary.pricedItems} priced items`} tone="success" />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Catalog filters">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              aria-label="Search menu categories"
              autoComplete="off"
              placeholder="Category search"
              value={filters.categoryQuery}
              onChange={(event) => setFilters((current) => ({ ...current, categoryQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Include deleted</span>
              <Switch
                checked={filters.includeDeletedCategories}
                onChange={(checked) => setFilters((current) => ({ ...current, includeDeletedCategories: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={8}>
            <Input
              aria-label="Search menu items"
              autoComplete="off"
              placeholder="Item search"
              value={filters.itemQuery}
              onChange={(event) => setFilters((current) => ({ ...current, itemQuery: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Available only</span>
              <Switch
                checked={filters.availableOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, availableOnly: checked }))}
              />
            </label>
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Catalog item category id"
              autoComplete="off"
              placeholder="Category id"
              value={filters.categoryIdInput}
              onChange={(event) => setFilters((current) => ({ ...current, categoryIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Selected catalog item id"
              autoComplete="off"
              placeholder="Selected item id for prices"
              value={filters.selectedItemIdInput}
              onChange={(event) => setFilters((current) => ({ ...current, selectedItemIdInput: event.target.value }))}
            />
          </Col>
        </Row>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Categories" value={summary.categories} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Available items" value={summary.availableItems} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Price rows" value={summary.priceRows} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Menu categories">
        <CatalogSurface
          loading={categoriesQuery.isLoading}
          error={categoriesQuery.error}
          fallback="Unable to load menu categories."
          rows={categories}
          emptyTitle="No categories matched this filter"
          onRetry={() => void categoriesQuery.refetch()}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {categories.map((category) => (
                <div key={category.category_id} className="staff-admin-surface-item">
                  <div>
                    <strong>{category.name}</strong>
                    <Typography.Paragraph type="secondary">Category #{category.category_id} / sort {category.sort_order}</Typography.Paragraph>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={category.is_deleted ? 'Deleted' : 'Active'} tone={category.is_deleted ? 'warning' : 'success'} />
                  </Space>
                  <Typography.Text type="secondary">{category.description ?? 'No description'}</Typography.Text>
                </div>
              ))}
            </div>
          )}
        />
      </Card>

      <Card className="staff-workspace-table-card" title="Menu items">
        <CatalogSurface
          loading={itemsQuery.isLoading}
          error={itemsQuery.error}
          fallback="Unable to load menu items."
          rows={items}
          emptyTitle="No menu items matched this filter"
          onRetry={() => void itemsQuery.refetch()}
          renderRows={() => (
            <div className="staff-admin-surface-list">
              {items.map((item) => (
                <button
                  key={item.item_id}
                  type="button"
                  className={`staff-admin-branch-row ${item.item_id === selectedItemId ? 'staff-admin-branch-row-selected' : ''}`}
                  onClick={() => setFilters((current) => ({ ...current, selectedItemIdInput: String(item.item_id) }))}
                >
                  <div className="staff-admin-branch-row-main">
                    <strong>{item.name}</strong>
                    <span>{item.code ?? `Item #${item.item_id}`} / {item.category?.name ?? `Category #${item.category_id ?? 'n/a'}`}</span>
                  </div>
                  <Space wrap size={6}>
                    <StatusChip label={item.is_available ? 'Available' : 'Unavailable'} tone={item.is_available ? 'success' : 'warning'} />
                    <StatusChip label={item.current_price ? formatCatalogPrice(item.current_price.price, item.current_price.currency) : 'No current price'} tone={item.current_price ? 'success' : 'warning'} />
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
      <Card className="staff-workspace-detail-card" title="Create category">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input
            aria-label="New category name"
            autoComplete="off"
            placeholder="Name"
            value={categoryForm.name}
            onChange={(event) => setCategoryForm((current) => ({ ...current, name: event.target.value }))}
          />
          <Input
            aria-label="New category description"
            autoComplete="off"
            placeholder="Description"
            value={categoryForm.description}
            onChange={(event) => setCategoryForm((current) => ({ ...current, description: event.target.value }))}
          />
          <InputNumber
            aria-label="New category sort order"
            style={{ width: '100%' }}
            value={Number(categoryForm.sortOrder)}
            onChange={(value) => setCategoryForm((current) => ({ ...current, sortOrder: value === null ? '0' : String(value) }))}
          />
          <Button type="primary" loading={createCategoryMutation.isPending} disabled={createCategoryMutation.isPending} onClick={() => createCategoryMutation.mutate()}>
            Create category
          </Button>
          {createCategoryMutation.error ? <Typography.Text type="danger">{formatApiError(createCategoryMutation.error, 'Could not create menu category.')}</Typography.Text> : null}
        </Space>
      </Card>

      <Card className="staff-workspace-detail-card" title="Create item">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input
            aria-label="New item code"
            autoComplete="off"
            placeholder="Code"
            value={itemForm.code}
            onChange={(event) => setItemForm((current) => ({ ...current, code: event.target.value }))}
          />
          <Input
            aria-label="New item name"
            autoComplete="off"
            placeholder="Name"
            value={itemForm.name}
            onChange={(event) => setItemForm((current) => ({ ...current, name: event.target.value }))}
          />
          <Select
            aria-label="New item category"
            style={{ width: '100%' }}
            placeholder="Category"
            allowClear
            value={itemForm.categoryId || undefined}
            options={categories.map((category) => ({ value: String(category.category_id), label: category.name }))}
            onChange={(value) => setItemForm((current) => ({ ...current, categoryId: value ?? '' }))}
          />
          <Input
            aria-label="New item description"
            autoComplete="off"
            placeholder="Description"
            value={itemForm.description}
            onChange={(event) => setItemForm((current) => ({ ...current, description: event.target.value }))}
          />
          <label className="staff-admin-switch-row">
            <span>Available</span>
            <Switch
              checked={itemForm.available}
              onChange={(checked) => setItemForm((current) => ({ ...current, available: checked }))}
            />
          </label>
          <Button type="primary" loading={createItemMutation.isPending} disabled={createItemMutation.isPending} onClick={() => createItemMutation.mutate()}>
            Create item
          </Button>
          {createItemMutation.error ? <Typography.Text type="danger">{formatApiError(createItemMutation.error, 'Could not create menu item.')}</Typography.Text> : null}
        </Space>
      </Card>

      <Card className="staff-workspace-detail-card" title="Price management">
        {!selectedItem ? (
          <EmptyBlock title="No item selected" description="Select an item row or enter an item id to inspect and add price rows." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Typography.Text>{selectedItem.name}</Typography.Text>
            {pricesQuery.isLoading ? <InlineLoading tip="Loading price rows..." /> : null}
            {pricesQuery.error ? <ApiStateBlock error={pricesQuery.error} fallback="Unable to load price rows." onRetry={() => void pricesQuery.refetch()} /> : null}
            {prices.length > 0 ? (
              <div className="staff-admin-detail-list">
                {prices.map((price) => (
                  <div key={price.price_id} className="staff-admin-detail-item">
                    <strong>{formatCatalogPrice(price.price, price.currency)}</strong>
                    <span>{formatDateTime(price.effective_from)} to {price.effective_to ? formatDateTime(price.effective_to) : 'open ended'}</span>
                  </div>
                ))}
              </div>
            ) : !pricesQuery.isLoading && !pricesQuery.error ? (
              <EmptyBlock title="No price rows" description="Add a price row before this item can be sold with a current price." />
            ) : null}
            <InputNumber
              aria-label="New item price"
              style={{ width: '100%' }}
              min={0}
              value={priceForm.price === '' ? null : Number(priceForm.price)}
              placeholder="Price"
              onChange={(value) => setPriceForm((current) => ({ ...current, price: value === null ? '' : String(value) }))}
            />
            <Input
              aria-label="New price currency"
              autoComplete="off"
              value={priceForm.currency}
              onChange={(event) => setPriceForm((current) => ({ ...current, currency: event.target.value }))}
            />
            <Input
              aria-label="New price effective from"
              type="datetime-local"
              value={priceForm.effectiveFrom}
              onChange={(event) => setPriceForm((current) => ({ ...current, effectiveFrom: event.target.value }))}
            />
            <Button type="primary" loading={createPriceMutation.isPending} disabled={createPriceMutation.isPending} onClick={() => createPriceMutation.mutate()}>
              Add price row
            </Button>
          </Space>
        )}
      </Card>

      <AdminMasterDataImportPanel
        title="Catalog import dry run"
        description="Preview menu category, item, or price rows before committing with an idempotency key."
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
    return <InlineLoading tip="Loading catalog reads..." />;
  }

  if (error) {
    return <ApiStateBlock error={error} fallback={fallback} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyBlock title={emptyTitle} description="Adjust filters or create/import records for this catalog surface." />;
  }

  return renderRows();
}
