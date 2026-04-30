import { Button, Card, Col, Descriptions, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  adminSettingsSurfaces,
  adminTableStatusLabel,
  adminTableStatusTone,
  branchReservationLeadLabel,
  branchSameDayCutoffLabel,
  branchWaitingListLabel,
  buildAdminBranchesQuery,
  buildAdminRestaurantTableQuery,
  dayOfWeekLabel,
  formatBusinessPeriods,
  pickAdminBranchId,
  summarizeAdminBranches,
  summarizeAdminTables,
  type AdminBranchFilterState,
  type AdminTableFilterState,
} from '../../../../domains/admin/admin-settings';
import { settingsImportDomains } from '../../../../domains/admin/admin-master-data';
import {
  createAdminRestaurantTable,
  listAdminBranches,
  listAdminRestaurantTables,
  listAdminRestaurantTableTemplates,
  type AdminRestaurantTable,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime } from '../../../../shared/utils/format';
import { AdminMasterDataImportPanel } from '../components/AdminMasterDataImportPanel';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

type BranchRow = Awaited<ReturnType<typeof listAdminBranches>>['data'][number];
type NewTableStatus = 'Available' | 'Blocked' | 'Maintenance';

const tableStatusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'Available', label: 'Bàn trống' },
  { value: 'Reserved', label: 'Đã đặt' },
  { value: 'Occupied', label: 'Đang phục vụ' },
  { value: 'Blocked', label: 'Đang khóa' },
  { value: 'Maintenance', label: 'Bảo trì' },
];

export function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const startupBranchId = useFlowStore((state) => state.branchId);
  const [filters, setFilters] = useState<AdminBranchFilterState>({
    query: '',
    activeOnly: true,
  });
  const [tableFilters, setTableFilters] = useState<AdminTableFilterState>({
    query: '',
    status: '',
    zone: '',
    includeDeleted: false,
    branchIdInput: startupBranchId ? String(startupBranchId) : '',
  });
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(startupBranchId);
  const [selectedTableId, setSelectedTableId] = useState<number | null>(null);
  const [tableForm, setTableForm] = useState({
    tableCode: '',
    branchId: startupBranchId ? String(startupBranchId) : '',
    templateId: '',
    zone: '',
    status: 'Available' as NewTableStatus,
    seatsPrice: '',
  });

  const branchesQuery = useQuery({
    queryKey: ['admin-settings-branches', filters],
    queryFn: () => listAdminBranches(buildAdminBranchesQuery(filters)),
  });

  const tableQuery = useQuery({
    queryKey: ['admin-settings-tables', tableFilters, startupBranchId],
    queryFn: () => listAdminRestaurantTables(buildAdminRestaurantTableQuery(tableFilters, startupBranchId)),
  });

  const templatesQuery = useQuery({
    queryKey: ['admin-settings-table-templates'],
    queryFn: () => listAdminRestaurantTableTemplates(),
  });

  const branches = useMemo(() => branchesQuery.data?.data ?? [], [branchesQuery.data?.data]);
  const tables = useMemo(() => tableQuery.data?.data ?? [], [tableQuery.data?.data]);
  const templates = useMemo(() => templatesQuery.data?.data ?? [], [templatesQuery.data?.data]);
  const summary = useMemo(() => summarizeAdminBranches(branches), [branches]);
  const tableSummary = useMemo(() => summarizeAdminTables(tables), [tables]);
  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.branch_id === selectedBranchId) ?? null,
    [branches, selectedBranchId],
  );
  const selectedTable = useMemo(
    () => tables.find((table) => table.table_id === selectedTableId) ?? null,
    [selectedTableId, tables],
  );

  const createTableMutation = useMutation({
    mutationFn: async () => {
      const branchId = Number(tableForm.branchId);
      const templateId = Number(tableForm.templateId);
      const price = tableForm.seatsPrice.trim() === '' ? null : Number(tableForm.seatsPrice);

      if (tableForm.tableCode.trim() === '' || !Number.isInteger(templateId) || templateId <= 0) {
        throw new Error('Hãy nhập mã bàn và chọn mẫu bàn trước khi tạo.');
      }

      return createAdminRestaurantTable({
        table_code: tableForm.tableCode.trim(),
        branch_id: Number.isInteger(branchId) && branchId > 0 ? branchId : startupBranchId ?? null,
        template_id: templateId,
        zone: tableForm.zone.trim() || null,
        status: tableForm.status,
        price: price !== null && Number.isFinite(price) ? price : null,
      });
    },
    onSuccess: async () => {
      setTableForm((current) => ({ ...current, tableCode: '', seatsPrice: '' }));
      await queryClient.invalidateQueries({ queryKey: ['admin-settings-tables'] });
      toast.success('Đã tạo bàn nhà hàng.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa tạo được bàn nhà hàng.'));
    },
  });

  useEffect(() => {
    setSelectedBranchId((currentSelectedBranchId) => pickAdminBranchId(branches, currentSelectedBranchId, startupBranchId));
  }, [branches, startupBranchId]);

  useEffect(() => {
    setSelectedTableId((currentSelectedTableId) => {
      if (currentSelectedTableId !== null && tables.some((table) => table.table_id === currentSelectedTableId)) {
        return currentSelectedTableId;
      }

      return tables[0]?.table_id ?? null;
    });
  }, [tables]);

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Cấu hình"
        title="Chi nhánh và thiết lập"
        description="Quản lý chi nhánh, bàn, khu vực, tuyến bếp và dữ liệu nhập/xuất tách khỏi màn vận hành đang chạy."
        context={(
          <>
            <StatusChip label={`${summary.total} chi nhánh`} tone="processing" />
            <StatusChip label={`${summary.active} đang hoạt động`} tone="success" />
            <StatusChip label={`${summary.withClosures} có lịch đóng`} tone={summary.withClosures > 0 ? 'warning' : 'default'} />
            <StatusChip label={`${tableSummary.total} bàn`} tone="processing" />
            <StatusChip label={startupBranchId ? `Chi nhánh #${startupBranchId}` : 'Chưa chọn chi nhánh'} tone={startupBranchId ? 'processing' : 'warning'} />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Lọc chi nhánh">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={16}>
            <Input
              aria-label="Tìm chi nhánh quản trị"
              autoComplete="off"
              value={filters.query}
              placeholder="Mã hoặc tên chi nhánh"
              onChange={(event) => setFilters((current) => ({ ...current, query: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <label className="staff-admin-switch-row">
              <span>Chỉ chi nhánh hoạt động</span>
              <Switch
                checked={filters.activeOnly}
                onChange={(checked) => setFilters((current) => ({ ...current, activeOnly: checked }))}
              />
            </label>
          </Col>
        </Row>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Chi nhánh hoạt động" value={summary.active} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Chi nhánh mặc định" value={summary.defaults} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Đã có giờ mở cửa" value={summary.withBusinessHours} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Có lịch đóng" value={summary.withClosures} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Danh bạ chi nhánh">
        {branchesQuery.isLoading ? <InlineLoading tip="Đang tải danh bạ chi nhánh..." /> : null}
        {branchesQuery.error ? <ApiStateBlock error={branchesQuery.error} fallback="Chưa tải được danh sách chi nhánh." onRetry={() => void branchesQuery.refetch()} /> : null}
        {!branchesQuery.isLoading && !branchesQuery.error && branches.length === 0 ? (
          <EmptyBlock title="Không có chi nhánh phù hợp" description="Hãy nới điều kiện tìm kiếm hoặc tắt bộ lọc hoạt động để xem thêm chi nhánh." />
        ) : null}
        {branches.length > 0 ? (
          <div className="staff-admin-branch-list">
            {branches.map((branch) => (
              <button
                key={branch.branch_id}
                type="button"
                className={`staff-admin-branch-row ${branch.branch_id === selectedBranchId ? 'staff-admin-branch-row-selected' : ''}`}
                onClick={() => setSelectedBranchId(branch.branch_id)}
              >
                <div className="staff-admin-branch-row-main">
                  <strong>{branch.branch_name}</strong>
                  <span>{branch.branch_code} / {branch.timezone ?? 'Chưa có múi giờ'}</span>
                </div>
                <Space wrap size={6}>
                  {branch.is_default ? <StatusChip label="Mặc định" tone="processing" /> : null}
                  <StatusChip label={branch.is_active ? 'Đang hoạt động' : 'Tạm tắt'} tone={branch.is_active ? 'success' : 'warning'} />
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      <Card className="staff-workspace-filter-card" title="Lọc bàn">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              aria-label="Tìm bàn quản trị"
              autoComplete="off"
              value={tableFilters.query}
              placeholder="Mã bàn hoặc mô tả"
              onChange={(event) => setTableFilters((current) => ({ ...current, query: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Khu vực bàn"
              autoComplete="off"
              value={tableFilters.zone}
              placeholder="Khu vực"
              onChange={(event) => setTableFilters((current) => ({ ...current, zone: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Mã chi nhánh của bàn"
              autoComplete="off"
              value={tableFilters.branchIdInput}
              placeholder="Mã chi nhánh"
              onChange={(event) => setTableFilters((current) => ({ ...current, branchIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              aria-label="Trạng thái bàn"
              style={{ width: '100%' }}
              options={tableStatusOptions}
              value={tableFilters.status}
              onChange={(value) => setTableFilters((current) => ({ ...current, status: value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Gồm bàn đã lưu trữ</span>
              <Switch
                checked={tableFilters.includeDeleted}
                onChange={(checked) => setTableFilters((current) => ({ ...current, includeDeleted: checked }))}
              />
            </label>
          </Col>
        </Row>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Bàn" value={tableSummary.total} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Có thể xếp khách" value={tableSummary.allocatable} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Đang liên kết live" value={tableSummary.operationallyLinked} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Chi nhánh có bàn" value={tableSummary.branchScoped} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Quản lý bàn">
        {tableQuery.isLoading ? <InlineLoading tip="Đang tải danh sách bàn..." /> : null}
        {tableQuery.error ? <ApiStateBlock error={tableQuery.error} fallback="Chưa tải được danh sách bàn." onRetry={() => void tableQuery.refetch()} /> : null}
        {!tableQuery.isLoading && !tableQuery.error && tables.length === 0 ? (
          <EmptyBlock title="Không có bàn phù hợp" description="Hãy đổi chi nhánh, khu vực hoặc trạng thái để xem nhóm bàn khác." />
        ) : null}
        {tables.length > 0 ? (
          <div className="staff-admin-surface-list">
            {tables.map((table) => (
              <button
                key={table.table_id}
                type="button"
                className={`staff-admin-branch-row ${table.table_id === selectedTableId ? 'staff-admin-branch-row-selected' : ''}`}
                onClick={() => setSelectedTableId(table.table_id)}
              >
                <div className="staff-admin-branch-row-main">
                  <strong>{table.table_code}</strong>
                  <span>{table.branch?.branch_code ?? `Chi nhánh #${table.branch_id ?? 'không rõ'}`} / {table.zone ?? 'Chưa có khu vực'}</span>
                </div>
                <Space wrap size={6}>
                  <StatusChip label={adminTableStatusLabel(table.status)} tone={adminTableStatusTone(table.status)} />
                  <StatusChip label={`v${table.row_version ?? 'không rõ'}`} tone="default" />
                  {table.usage?.has_active_operational_links ? <StatusChip label="Đang liên kết" tone="warning" /> : null}
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      <Card className="staff-workspace-table-card" title="Tạo bàn">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={6}>
            <Input
              aria-label="Mã bàn mới"
              autoComplete="off"
              value={tableForm.tableCode}
              placeholder="Mã bàn"
              onChange={(event) => setTableForm((current) => ({ ...current, tableCode: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Mã chi nhánh của bàn mới"
              autoComplete="off"
              value={tableForm.branchId}
              placeholder="Mã chi nhánh"
              onChange={(event) => setTableForm((current) => ({ ...current, branchId: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              aria-label="Mẫu bàn mới"
              style={{ width: '100%' }}
              placeholder="Mẫu bàn"
              loading={templatesQuery.isLoading}
              value={tableForm.templateId || undefined}
              options={templates.map((template) => ({
                value: String(template.template_id),
                label: `${template.template_code} / ${template.seats} ghế`,
              }))}
              onChange={(value) => setTableForm((current) => ({ ...current, templateId: value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Khu vực bàn mới"
              autoComplete="off"
              value={tableForm.zone}
              placeholder="Khu vực"
              onChange={(event) => setTableForm((current) => ({ ...current, zone: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={3}>
            <InputNumber
              aria-label="Giá bàn mới"
              style={{ width: '100%' }}
              min={0}
              value={tableForm.seatsPrice === '' ? null : Number(tableForm.seatsPrice)}
              placeholder="Giá"
              onChange={(value) => setTableForm((current) => ({ ...current, seatsPrice: value === null ? '' : String(value) }))}
            />
          </Col>
          <Col xs={24} md={2}>
            <Button
              type="primary"
              onClick={() => createTableMutation.mutate()}
              loading={createTableMutation.isPending}
              disabled={createTableMutation.isPending}
            >
              Tạo
            </Button>
          </Col>
        </Row>
        {createTableMutation.error ? (
          <Typography.Paragraph type="danger">
            {formatApiError(createTableMutation.error, 'Chưa tạo được bàn nhà hàng.')}
          </Typography.Paragraph>
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card className="staff-workspace-detail-card" title="Chi nhánh đang chọn">
        {!selectedBranch ? (
          <EmptyBlock title="Chưa chọn chi nhánh" description="Chọn một chi nhánh để xem chính sách đặt bàn, lịch hoạt động và ngữ cảnh vận hành." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              {selectedBranch.is_default ? <StatusChip label="Chi nhánh mặc định" tone="processing" /> : null}
              <StatusChip label={selectedBranch.is_active ? 'Đang hoạt động' : 'Tạm tắt'} tone={selectedBranch.is_active ? 'success' : 'warning'} />
              <StatusChip label={`Phiên bản ${selectedBranch.row_version ?? 'không rõ'}`} tone="default" />
            </Space>

            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Mã chi nhánh">{selectedBranch.branch_code}</Descriptions.Item>
              <Descriptions.Item label="Múi giờ">{selectedBranch.timezone ?? 'Chưa thiết lập'}</Descriptions.Item>
              <Descriptions.Item label="Tiền tệ">{selectedBranch.currency ?? 'Chưa thiết lập'}</Descriptions.Item>
              <Descriptions.Item label="Cập nhật lúc">{formatDateTime(selectedBranch.updated_at ?? selectedBranch.created_at ?? null, selectedBranch.timezone ?? undefined)}</Descriptions.Item>
              <Descriptions.Item label="Thời gian đặt trước">{branchReservationLeadLabel(selectedBranch)}</Descriptions.Item>
              <Descriptions.Item label="Chốt đặt trong ngày">{branchSameDayCutoffLabel(selectedBranch)}</Descriptions.Item>
              <Descriptions.Item label="Danh sách chờ">{branchWaitingListLabel(selectedBranch)}</Descriptions.Item>
            </Descriptions>

            <Card size="small" title="Giờ mở cửa" className="staff-workspace-detail-subcard">
              {selectedBranch.business_hours.length === 0 ? (
                <EmptyBlock title="Chưa cấu hình giờ mở cửa" description="Chi nhánh này chưa có khung giờ hoạt động trong dữ liệu hiện tại." />
              ) : (
                <div className="staff-admin-detail-list">
                  {selectedBranch.business_hours.map((businessHour: BranchRow['business_hours'][number]) => (
                    <div key={businessHour.day_of_week} className="staff-admin-detail-item">
                      <strong>{dayOfWeekLabel(businessHour.day_of_week)}</strong>
                      <span>{formatBusinessPeriods(businessHour.periods)}</span>
                    </div>
                  ))}
                </div>
              )}
            </Card>

            <Card size="small" title="Lịch đóng tạm thời" className="staff-workspace-detail-subcard">
              {selectedBranch.closure_windows.length === 0 ? (
                <EmptyBlock title="Không có lịch đóng" description="Chi nhánh không có khung đóng tạm thời đang áp dụng." />
              ) : (
                <div className="staff-admin-detail-list">
                  {selectedBranch.closure_windows.map((closureWindow: BranchRow['closure_windows'][number], index: number) => (
                    <div key={`${closureWindow.start_local ?? 'closure'}-${index}`} className="staff-admin-detail-item">
                      <strong>{closureWindow.reason ?? 'Lịch đóng tạm thời'}</strong>
                      <span>{formatDateTime(closureWindow.start_local ?? null, selectedBranch.timezone ?? undefined)} đến {formatDateTime(closureWindow.end_local ?? null, selectedBranch.timezone ?? undefined)}</span>
                    </div>
                  ))}
                </div>
              )}
            </Card>
          </Space>
        )}
      </Card>

      <Card className="staff-workspace-detail-card" title="Bàn đang chọn">
        {!selectedTable ? (
          <EmptyBlock title="Chưa chọn bàn" description="Chọn một bàn để xem phiên bản dữ liệu, ràng buộc live và thời điểm cập nhật." />
        ) : (
          <TableDetail table={selectedTable} />
        )}
      </Card>

      <AdminMasterDataImportPanel
        title="Chạy thử nhập cấu hình"
        description="Kiểm tra chi nhánh hoặc bàn bằng validate backend trước khi ghi nhận với Idempotency-Key."
        domains={settingsImportDomains}
        onCommitted={() => {
          void queryClient.invalidateQueries({ queryKey: ['admin-settings-branches'] });
          void queryClient.invalidateQueries({ queryKey: ['admin-settings-tables'] });
        }}
      />

      <Card className="staff-workspace-detail-card" title="Phạm vi cấu hình">
        <div className="staff-admin-surface-list">
          {adminSettingsSurfaces.map((surface) => (
            <div key={surface.key} className="staff-admin-surface-item">
              <div>
                <strong>{surface.title}</strong>
                <Typography.Paragraph type="secondary">{surface.description}</Typography.Paragraph>
              </div>
              <Space wrap size={6}>
                <StatusChip label={surface.workflowLabel} tone="default" />
              </Space>
              <Typography.Text type="secondary">{surface.backendSurface}</Typography.Text>
            </div>
          ))}
        </div>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} />;
}

function TableDetail({ table }: { table: AdminRestaurantTable }) {
  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      <Space wrap size={6}>
        <StatusChip label={adminTableStatusLabel(table.status)} tone={adminTableStatusTone(table.status)} />
        <StatusChip label={table.is_allocatable ? 'Có thể xếp khách' : 'Không xếp khách'} tone={table.is_allocatable ? 'success' : 'warning'} />
        <StatusChip label={`Phiên bản ${table.row_version ?? 'không rõ'}`} tone="default" />
      </Space>

      <Descriptions bordered size="small" column={1}>
        <Descriptions.Item label="Mã bàn">{table.table_code}</Descriptions.Item>
        <Descriptions.Item label="Chi nhánh">{table.branch?.branch_name ?? `Chi nhánh #${table.branch_id ?? 'không rõ'}`}</Descriptions.Item>
        <Descriptions.Item label="Mẫu bàn">{table.template?.template_code ?? `Mẫu #${table.template_id ?? 'không rõ'}`}</Descriptions.Item>
        <Descriptions.Item label="Số ghế">{table.seats ?? table.capacity ?? 'Chưa thiết lập'}</Descriptions.Item>
        <Descriptions.Item label="Khu vực">{table.zone ?? 'Chưa thiết lập'}</Descriptions.Item>
        <Descriptions.Item label="Giá">{table.price ?? 'Chưa thiết lập'}</Descriptions.Item>
        <Descriptions.Item label="Cập nhật lúc">{formatDateTime(table.updated_at ?? table.created_at ?? null)}</Descriptions.Item>
      </Descriptions>

      <Card size="small" title="Ràng buộc live" className="staff-workspace-detail-subcard">
        <div className="staff-admin-detail-list">
          <div className="staff-admin-detail-item">
            <strong>Liên kết vận hành</strong>
            <span>{table.usage?.has_active_operational_links ? 'Đang có đặt bàn, giữ bàn hoặc đơn hàng liên quan' : 'Không có liên kết vận hành đang mở'}</span>
          </div>
          {Object.entries(table.guards ?? {}).map(([key, value]) => (
            <div key={key} className="staff-admin-detail-item">
              <strong>{key}</strong>
              <span>{value ? 'Cho phép' : 'Đang chặn'}</span>
            </div>
          ))}
        </div>
      </Card>
    </Space>
  );
}
