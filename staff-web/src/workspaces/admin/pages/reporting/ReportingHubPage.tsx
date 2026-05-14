import { useEffect, useMemo, useState, type Dispatch, type SetStateAction } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Card,
  Col,
  Descriptions,
  Input,
  Row,
  Space,
  Statistic,
  Table,
  Tabs,
  Typography,
} from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useQuery } from '@tanstack/react-query';
import type {
  ReportingDailyInventoryMovementSnapshot,
  ReportingDailyOperationSnapshot,
  ReportingDailySalesSnapshot,
  StaffReportingCollectionMeta,
} from '../../../../shared/api/sdk';
import {
  listDailyInventoryReporting,
  listDailyOperationsReporting,
  listDailySalesReporting,
} from '../../../../shared/api/staff-api';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatFreshnessLabel, formatMoney } from '../../../../shared/utils/format';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import {
  ApiStateBlock,
  ConflictState,
  EmptyBlock,
  InlineLoading,
  InlineState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  buildInventoryQuery,
  buildOperationsQuery,
  buildSalesQuery,
  reportingDateRangeError,
  snapshotHealthDescription,
  snapshotHealthLabel,
  snapshotHealthReferenceAgeSeconds,
  snapshotHealthScopeExamples,
  snapshotHealthScopeSummary,
  snapshotHealthTone,
  summarizeInventory,
  summarizeOperations,
  summarizeSales,
  type ReportingFilterState,
  type ReportingTabKey,
} from '../../../../domains/reporting/reporting-hub';

const pageSize = 12;

export function ReportingHubPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const [activeTab, setActiveTab] = useState<ReportingTabKey>('sales');
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<ReportingFilterState>({
    dateFrom: isoDateDaysAgo(6),
    dateTo: isoDateDaysAgo(0),
    currency: '',
    ingredientId: '',
  });
  const [selectedRowKey, setSelectedRowKey] = useState<string | null>(null);

  const defaultFilters: ReportingFilterState = {
    dateFrom: isoDateDaysAgo(6),
    dateTo: isoDateDaysAgo(0),
    currency: '',
    ingredientId: '',
  };
  const filtersActive = filters.dateFrom !== defaultFilters.dateFrom
    || filters.dateTo !== defaultFilters.dateTo
    || filters.currency !== ''
    || filters.ingredientId !== '';
  const dateRangeError = reportingDateRangeError(filters);

  function resetFilters() {
    setFilters(defaultFilters);
    setPage(1);
    setSelectedRowKey(null);
  }

  const salesQuery = useQuery({
    queryKey: ['reporting-sales', branchId, filters, page],
    queryFn: () => listDailySalesReporting(buildSalesQuery(filters, branchId, page, pageSize)),
    enabled: activeTab === 'sales' && !dateRangeError,
  });
  const operationsQuery = useQuery({
    queryKey: ['reporting-operations', branchId, filters, page],
    queryFn: () => listDailyOperationsReporting(buildOperationsQuery(filters, branchId, page, pageSize)),
    enabled: activeTab === 'operations' && !dateRangeError,
  });
  const inventoryQuery = useQuery({
    queryKey: ['reporting-inventory', branchId, filters, page],
    queryFn: () => listDailyInventoryReporting(buildInventoryQuery(filters, branchId, page, pageSize)),
    enabled: activeTab === 'inventory' && !dateRangeError,
  });

  const activeQuery = activeTab === 'sales'
    ? salesQuery
    : activeTab === 'operations'
      ? operationsQuery
      : inventoryQuery;
  const activeRows = useMemo(() => activeQuery.data?.data ?? [], [activeQuery.data?.data]);
  const activeMeta = activeQuery.data?.meta;

  useEffect(() => {
    if (activeRows.length === 0) {
      if (selectedRowKey !== null) {
        setSelectedRowKey(null);
      }
      return;
    }

    const rowKey = activeRows.some((row) => getRowKey(activeTab, row) === selectedRowKey)
      ? selectedRowKey
      : getRowKey(activeTab, activeRows[0]);
    setSelectedRowKey(rowKey);
  }, [activeRows, activeTab, selectedRowKey]);

  const selectedRow = useMemo(() => activeRows.find((row) => getRowKey(activeTab, row) === selectedRowKey) ?? null, [activeRows, activeTab, selectedRowKey]);
  const salesSummary = summarizeSales(salesQuery.data?.data ?? []);
  const operationsSummary = summarizeOperations(operationsQuery.data?.data ?? []);
  const inventorySummary = summarizeInventory(inventoryQuery.data?.data ?? []);
  const activeTabLabel = activeTab === 'sales'
    ? 'Bán hàng'
    : activeTab === 'operations'
      ? 'Vận hành'
      : 'Kho';
  const latestRefreshSeconds = snapshotHealthReferenceAgeSeconds(activeMeta);
  const latestRefreshLabel = typeof latestRefreshSeconds === 'number'
    ? formatFreshnessLabel(Date.now() - (latestRefreshSeconds * 1000))
    : 'Chưa rõ độ mới dữ liệu';

  const scopeSummary = snapshotHealthScopeSummary(activeMeta);
  const scopeExamples = snapshotHealthScopeExamples(activeMeta);

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Báo cáo Mộc Sen"
        title="Hub báo cáo vận hành"
        description="Theo dõi doanh thu, đặt bàn, bếp, voucher, Điểm Sen và tồn kho theo snapshot mỗi ngày."
        meta={latestRefreshLabel}
        context={(
          <>
            <StatusChip label={activeTabLabel} tone="processing" />
            <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Toàn bộ phạm vi được cấp'} tone="default" />
            <StatusChip label={snapshotHealthLabel(activeMeta)} tone={snapshotHealthTone(activeMeta?.snapshot_health.status)} />
          </>
        )}
      />

      {activeMeta?.snapshot_health?.status === 'degraded' ? (
        <ConflictState
          title={branchId ? `Snapshot chi nhánh #${branchId} cần kiểm tra` : 'Snapshot báo cáo trong phạm vi hiện tại cần kiểm tra'}
          description="Phạm vi chi nhánh lấy từ bộ chọn ở shell. Bộ lọc tiền tệ và nguyên liệu chỉ áp dụng cho từng nhóm báo cáo tương ứng. Hãy dùng panel chi tiết bên phải để khoanh vùng trước khi mở luồng khác."
          primaryAction={<Button onClick={() => void activeQuery.refetch()}>Tải lại tab hiện tại</Button>}
          className="staff-inline-note"
        />
      ) : (
        <InlineState
          tone="info"
          eyebrow="Phạm vi báo cáo"
          title={branchId ? `Đang dùng ngữ cảnh chi nhánh #${branchId}` : 'Đang dùng toàn bộ chi nhánh được phép'}
          description="Phạm vi chi nhánh lấy từ bộ chọn ở shell. Bộ lọc tiền tệ và nguyên liệu chỉ áp dụng cho từng nhóm báo cáo tương ứng."
          className="staff-inline-note"
        />
      )}

      <Card title="Bộ lọc" className="staff-workspace-filter-card" extra={filtersActive ? <Button size="small" onClick={resetFilters}>Đặt lại bộ lọc</Button> : null}>
        <Row gutter={[12, 12]}>
          <Col xs={24} md={6}>
            <Input aria-label="Từ ngày báo cáo" type="date" value={filters.dateFrom} onChange={(event) => updateFilters(setFilters, setPage, 'dateFrom', event.target.value)} />
          </Col>
          <Col xs={24} md={6}>
            <Input aria-label="Đến ngày báo cáo" type="date" value={filters.dateTo} onChange={(event) => updateFilters(setFilters, setPage, 'dateTo', event.target.value)} />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Loại tiền cho báo cáo bán hàng"
              autoComplete="off"
              name="reportingCurrency"
              placeholder="Ví dụ: VND…"
              spellCheck={false}
              value={filters.currency}
              disabled={activeTab !== 'sales'}
              onChange={(event) => updateFilters(setFilters, setPage, 'currency', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Mã nguyên liệu cho báo cáo kho"
              autoComplete="off"
              name="ingredientId"
              placeholder="Mã nguyên liệu…"
              value={filters.ingredientId}
              inputMode="numeric"
              disabled={activeTab !== 'inventory'}
              onChange={(event) => updateFilters(setFilters, setPage, 'ingredientId', event.target.value)}
            />
          </Col>
        </Row>
      </Card>

      {dateRangeError ? (
        <InlineState
          tone="warning"
          eyebrow="Kiểm tra bộ lọc"
          title="Khoảng ngày báo cáo chưa hợp lệ"
          description={dateRangeError}
          className="staff-inline-note"
        />
      ) : null}

      <Tabs
        className="staff-workspace-tabs"
        activeKey={activeTab}
        onChange={(key) => {
          setActiveTab(key as ReportingTabKey);
          setPage(1);
          setSelectedRowKey(null);
        }}
        items={[
          {
            key: 'sales',
            label: 'Bán hàng',
            children: (
              <ReportingTabTable
                loading={salesQuery.isLoading}
                error={salesQuery.error}
                errorFallback="Không thể tải ảnh chụp bán hàng theo ngày."
                emptyTitle="Không có dòng bán hàng"
                emptyDescription="Phạm vi hiện tại chưa có dữ liệu bán hàng theo ngày."
                rows={salesQuery.data?.data ?? []}
                meta={salesQuery.data?.meta}
                page={page}
                onPageChange={setPage}
                onRetry={() => {
                  void salesQuery.refetch();
                }}
                selectedRowKey={selectedRowKey}
                rowKeyFn={(row) => getRowKey('sales', row)}
                onSelectRow={(row) => setSelectedRowKey(getRowKey('sales', row))}
                columns={[
                  { title: 'Ngày', render: (_, row) => row.business_date ?? 'Không có' },
                  { title: 'Chi nhánh', render: (_, row) => row.branch?.branch_code ?? `#${row.branch_id}` },
                  { title: 'Tổng bill', render: (_, row) => formatMoney(row.billed.gross_bill_amount, row.currency) },
                  { title: 'Thực thu', render: (_, row) => formatMoney(row.payments.net_paid_amount, row.currency) },
                  { title: 'Số hóa đơn', render: (_, row) => row.billed.reservation_count },
                ]}
              />
            ),
          },
          {
            key: 'operations',
            label: 'Vận hành',
            children: (
              <ReportingTabTable
                loading={operationsQuery.isLoading}
                error={operationsQuery.error}
                errorFallback="Không thể tải ảnh chụp vận hành theo ngày."
                emptyTitle="Không có dòng vận hành"
                emptyDescription="Phạm vi hiện tại chưa có dữ liệu vận hành theo ngày."
                rows={operationsQuery.data?.data ?? []}
                meta={operationsQuery.data?.meta}
                page={page}
                onPageChange={setPage}
                onRetry={() => {
                  void operationsQuery.refetch();
                }}
                selectedRowKey={selectedRowKey}
                rowKeyFn={(row) => getRowKey('operations', row)}
                onSelectRow={(row) => setSelectedRowKey(getRowKey('operations', row))}
                columns={[
                  { title: 'Ngày', render: (_, row) => row.business_date ?? 'Không có' },
                  { title: 'Chi nhánh', render: (_, row) => row.branch?.branch_code ?? `#${row.branch_id}` },
                  { title: 'Hoàn tất phục vụ', render: (_, row) => row.reservations.completed_count },
                  { title: 'Khách chờ đã vào bàn', render: (_, row) => row.waiting_list.seated_count },
                  { title: 'Vòng quay bàn TB', render: (_, row) => formatMinutes(row.turn_time.avg_turn_minutes) },
                ]}
              />
            ),
          },
          {
            key: 'inventory',
            label: 'Kho',
            children: (
              <ReportingTabTable
                loading={inventoryQuery.isLoading}
                error={inventoryQuery.error}
                errorFallback="Không thể tải ảnh chụp kho theo ngày."
                emptyTitle="Không có dòng kho"
                emptyDescription="Phạm vi hiện tại chưa có dữ liệu kho theo ngày."
                rows={inventoryQuery.data?.data ?? []}
                meta={inventoryQuery.data?.meta}
                page={page}
                onPageChange={setPage}
                onRetry={() => {
                  void inventoryQuery.refetch();
                }}
                selectedRowKey={selectedRowKey}
                rowKeyFn={(row) => getRowKey('inventory', row)}
                onSelectRow={(row) => setSelectedRowKey(getRowKey('inventory', row))}
                columns={[
                  { title: 'Ngày', render: (_, row) => row.business_date ?? 'Không có' },
                  { title: 'Chi nhánh', render: (_, row) => row.branch?.branch_code ?? `#${row.branch_id}` },
                  { title: 'Nguyên liệu', render: (_, row) => row.ingredient?.code ?? `#${row.ingredient_id}` },
                  { title: 'Số lần nhập/xuất', render: (_, row) => row.movement_summary.movement_count },
                  { title: 'Biến động ròng', render: (_, row) => `${formatQuantity(row.movement_summary.net_quantity_delta)} ${row.unit_code}` },
                ]}
              />
            ),
          },
        ]}
      />
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Đi sâu tài chính" className="staff-workspace-detail-card">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          {can(session, 'settlement.manage') ? (
            <Button block onClick={() => navigate(staffRoutePaths.ops.financeReview)}>
              Rà soát tài chính
            </Button>
          ) : null}
          {can(session, 'cashier.shift.manage') ? (
            <Button block onClick={() => navigate(staffRoutePaths.ops.cashierShift)}>
              Ca thu ngân
            </Button>
          ) : null}
          {can(session, 'payment.refund') ? (
            <Button block onClick={() => navigate(staffRoutePaths.ops.refunds)}>
              Rà soát hoàn tiền
            </Button>
          ) : null}
          {can(session, 'audit.view') ? (
            <Button block onClick={() => navigate(staffRoutePaths.admin.auditTrail)}>
              Nhật ký thao tác
            </Button>
          ) : null}
          {!can(session, 'settlement.manage') && !can(session, 'cashier.shift.manage') && !can(session, 'payment.refund') && !can(session, 'audit.view') ? (
            <EmptyBlock title="Chưa có luồng tài chính khả dụng" description="Phiên hiện tại chưa có quyền xem quyết toán, ca thu ngân, hoàn tiền hoặc nhật ký thao tác." />
          ) : null}
        </Space>
      </Card>
      <Card title="Tình trạng snapshot" className="staff-workspace-detail-card">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <StatusChip label={snapshotHealthLabel(activeMeta)} tone={snapshotHealthTone(activeMeta?.snapshot_health.status)} />
          {scopeSummary ? <StatusChip label={scopeSummary} tone={snapshotHealthTone(activeMeta?.snapshot_health.status)} /> : null}
          <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
            {snapshotHealthDescription(activeMeta)}
          </Typography.Paragraph>
          {scopeExamples ? (
            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
              {`Phạm vi cần làm mới: ${scopeExamples}`}
            </Typography.Paragraph>
          ) : null}
        </Space>
      </Card>

      <Card title="Tóm tắt tab đang xem" className="staff-workspace-detail-card">
        {activeTab === 'sales' ? (
          <Row gutter={[12, 12]}>
            <Col span={24}><Statistic title="Thực thu" value={salesSummary.netPaidAmount} formatter={(value) => formatMoney(Number(value ?? 0), filters.currency || 'VND')} /></Col>
            <Col span={24}><Statistic title="Tổng bill" value={salesSummary.grossBillAmount} formatter={(value) => formatMoney(Number(value ?? 0), filters.currency || 'VND')} /></Col>
            <Col span={24}><Statistic title="Số hóa đơn" value={salesSummary.invoiceCount} /></Col>
          </Row>
        ) : activeTab === 'operations' ? (
          <Row gutter={[12, 12]}>
            <Col span={24}><Statistic title="Hoàn tất phục vụ" value={operationsSummary.completedCount} /></Col>
            <Col span={24}><Statistic title="Khách chờ đã vào bàn" value={operationsSummary.waitingSeatedCount} /></Col>
            <Col span={24}><Statistic title="Vòng quay bàn TB" value={operationsSummary.avgTurnMinutes ?? 0} formatter={(value) => formatMinutes(Number(value ?? 0))} /></Col>
          </Row>
        ) : (
          <Row gutter={[12, 12]}>
            <Col span={24}><Statistic title="Số lần nhập/xuất" value={inventorySummary.movementCount} /></Col>
            <Col span={24}><Statistic title="Biến động ròng" value={inventorySummary.netQuantityDelta} formatter={(value) => formatQuantity(Number(value ?? 0))} /></Col>
            <Col span={24}><Statistic title="Hao hụt" value={inventorySummary.wastageQuantity} formatter={(value) => formatQuantity(Number(value ?? 0))} /></Col>
          </Row>
        )}
      </Card>

      <Card title="Dòng đang được soi" className="staff-workspace-detail-card">
        {!selectedRow ? (
          <EmptyBlock title="Chưa chọn dòng dữ liệu" description="Chọn một dòng để xem chi tiết ảnh chụp báo cáo." />
        ) : activeTab === 'sales' ? (
          <SalesRowDetail row={selectedRow as ReportingDailySalesSnapshot} />
        ) : activeTab === 'operations' ? (
          <OperationsRowDetail row={selectedRow as ReportingDailyOperationSnapshot} />
        ) : (
          <InventoryRowDetail row={selectedRow as ReportingDailyInventoryMovementSnapshot} />
        )}
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}

function ReportingTabTable<T extends object>({
  loading,
  error,
  errorFallback,
  emptyTitle,
  emptyDescription,
  rows,
  meta,
  page,
  onPageChange,
  onRetry,
  selectedRowKey,
  rowKeyFn,
  onSelectRow,
  columns,
}: {
  loading: boolean;
  error: unknown;
  errorFallback: string;
  emptyTitle: string;
  emptyDescription: string;
  rows: Array<T>;
  meta?: StaffReportingCollectionMeta | null;
  page: number;
  onPageChange: (page: number) => void;
  onRetry: () => void;
  selectedRowKey: string | null;
  rowKeyFn: (row: T) => string;
  onSelectRow: (row: T) => void;
  columns: ColumnsType<T>;
}) {
  if (loading) {
    return <InlineLoading tip="Đang tải ảnh chụp báo cáo..." />;
  }

  if (error) {
    return <ApiStateBlock error={error} fallback={errorFallback} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyBlock title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <Table<T>
      rowKey={rowKeyFn}
      dataSource={rows}
      rowClassName={(row) => (rowKeyFn(row) === selectedRowKey ? 'staff-row-selected' : '')}
      onRow={(row) => ({ onClick: () => onSelectRow(row) })}
      pagination={{
        current: meta?.current_page ?? page,
        pageSize: meta?.per_page ?? pageSize,
        total: meta?.total ?? rows.length,
        showSizeChanger: false,
        onChange: onPageChange,
      }}
      columns={columns}
    />
  );
}

function SalesRowDetail({ row }: { row: ReportingDailySalesSnapshot }) {
  return (
    <Descriptions bordered size="small" column={1}>
      <Descriptions.Item label="Ngày kinh doanh">{row.business_date ?? 'Không có'}</Descriptions.Item>
      <Descriptions.Item label="Chi nhánh">{row.branch?.branch_name ?? `Chi nhánh #${row.branch_id}`}</Descriptions.Item>
      <Descriptions.Item label="Loại tiền">{row.currency}</Descriptions.Item>
      <Descriptions.Item label="Thực thu">{formatMoney(row.payments.net_paid_amount, row.currency)}</Descriptions.Item>
      <Descriptions.Item label="Đã hoàn">{formatMoney(row.payments.refunded_amount, row.currency)}</Descriptions.Item>
      <Descriptions.Item label="Thuế">{formatMoney(row.invoices.tax_amount, row.currency)}</Descriptions.Item>
      <Descriptions.Item label="Chênh lệch tiền mặt">{formatMoney(row.cashier.cash_discrepancy_amount, row.currency)}</Descriptions.Item>
      <Descriptions.Item label="Làm mới lúc">{formatDateTime(row.freshness.refreshed_at)}</Descriptions.Item>
    </Descriptions>
  );
}

function OperationsRowDetail({ row }: { row: ReportingDailyOperationSnapshot }) {
  return (
    <Descriptions bordered size="small" column={1}>
      <Descriptions.Item label="Ngày kinh doanh">{row.business_date ?? 'Không có'}</Descriptions.Item>
      <Descriptions.Item label="Chi nhánh">{row.branch?.branch_name ?? `Chi nhánh #${row.branch_id}`}</Descriptions.Item>
      <Descriptions.Item label="Lượt đặt trước">{row.reservations.scheduled_count}</Descriptions.Item>
      <Descriptions.Item label="Đã nhận bàn">{row.reservations.checked_in_count}</Descriptions.Item>
      <Descriptions.Item label="Hoàn tất phục vụ">{row.reservations.completed_count}</Descriptions.Item>
      <Descriptions.Item label="Không đến">{row.reservations.no_show_count}</Descriptions.Item>
      <Descriptions.Item label="Lượt khách chờ mới">{row.waiting_list.created_count}</Descriptions.Item>
      <Descriptions.Item label="Tỷ lệ xác nhận đến">{formatRate(row.waiting_list.arrival_confirmation_rate)}</Descriptions.Item>
      <Descriptions.Item label="Làm mới lúc">{formatDateTime(row.freshness.refreshed_at)}</Descriptions.Item>
    </Descriptions>
  );
}

function InventoryRowDetail({ row }: { row: ReportingDailyInventoryMovementSnapshot }) {
  return (
    <Descriptions bordered size="small" column={1}>
      <Descriptions.Item label="Ngày kinh doanh">{row.business_date ?? 'Không có'}</Descriptions.Item>
      <Descriptions.Item label="Chi nhánh">{row.branch?.branch_name ?? `Chi nhánh #${row.branch_id}`}</Descriptions.Item>
      <Descriptions.Item label="Nguyên liệu">{row.ingredient?.name ?? `Nguyên liệu #${row.ingredient_id}`}</Descriptions.Item>
      <Descriptions.Item label="Đơn vị">{row.unit_code}</Descriptions.Item>
      <Descriptions.Item label="Nhập kho">{formatQuantity(row.movement_summary.stock_in_quantity)}</Descriptions.Item>
      <Descriptions.Item label="Xuất kho">{formatQuantity(row.movement_summary.stock_out_quantity)}</Descriptions.Item>
      <Descriptions.Item label="Hao hụt">{formatQuantity(row.movement_summary.wastage_quantity)}</Descriptions.Item>
      <Descriptions.Item label="Biến động gần nhất">{formatDateTime(row.movement_summary.last_movement_at)}</Descriptions.Item>
      <Descriptions.Item label="Làm mới lúc">{formatDateTime(row.freshness.refreshed_at)}</Descriptions.Item>
    </Descriptions>
  );
}

function updateFilters<K extends keyof ReportingFilterState>(
  setFilters: Dispatch<SetStateAction<ReportingFilterState>>,
  setPage: Dispatch<SetStateAction<number>>,
  key: K,
  value: ReportingFilterState[K],
) {
  setFilters((current) => ({ ...current, [key]: value }));
  setPage(1);
}

function getRowKey(tab: ReportingTabKey, row: ReportingDailySalesSnapshot | ReportingDailyOperationSnapshot | ReportingDailyInventoryMovementSnapshot): string {
  if (tab === 'sales') {
    const salesRow = row as ReportingDailySalesSnapshot;
    return `${salesRow.snapshot_id}:${salesRow.branch_id}:${salesRow.business_date}:${salesRow.currency}`;
  }

  if (tab === 'operations') {
    const operationsRow = row as ReportingDailyOperationSnapshot;
    return `${operationsRow.snapshot_id}:${operationsRow.branch_id}:${operationsRow.business_date}`;
  }

  const inventoryRow = row as ReportingDailyInventoryMovementSnapshot;
  return `${inventoryRow.snapshot_id}:${inventoryRow.branch_id}:${inventoryRow.business_date}:${inventoryRow.ingredient_id}`;
}

function formatMinutes(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return 'Không có';
  }

  return `${formatQuantity(value)} phút`;
}

function formatQuantity(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return 'Không có';
  }

  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(value);
}

function formatRate(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return 'Không có';
  }

  return `${formatQuantity(value * 100)}%`;
}

function isoDateDaysAgo(daysAgo: number): string {
  const value = new Date();
  value.setHours(0, 0, 0, 0);
  value.setDate(value.getDate() - daysAgo);
  return value.toISOString().slice(0, 10);
}
