import {
  Button,
  Card,
  Col,
  Descriptions,
  Form,
  Input,
  InputNumber,
  Row,
  Select,
  Space,
  Statistic,
  Switch,
  Typography,
} from 'antd';
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
  listAdminMenuCategories,
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
import { BranchModal } from './BranchModal';
import { ZoneRenameModal } from './ZoneRenameModal';
import { KitchenStationModal } from './KitchenStationModal';
import {
  listAdminZones,
  listAdminKitchenStations,
  syncAdminCategoryRoutes,
  listAdminCategoryRoutes,
  getAdminTaxProfile,
  upsertAdminTaxProfile,
  type AdminBranch,
  type AdminKitchenStation,
} from './settings-crud-api';

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

  // ─── Filter states ───────────────────────────────────────────────────────
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

  // ─── Selection states ────────────────────────────────────────────────────
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(startupBranchId);
  const [selectedTableId, setSelectedTableId] = useState<number | null>(null);
  const [selectedStationId, setSelectedStationId] = useState<number | null>(null);

  // ─── Table create form ───────────────────────────────────────────────────
  const [tableForm, setTableForm] = useState({
    tableCode: '',
    branchId: startupBranchId ? String(startupBranchId) : '',
    templateId: '',
    zone: '',
    status: 'Available' as NewTableStatus,
    seatsPrice: '',
  });

  // ─── Modal open states ───────────────────────────────────────────────────
  const [isBranchModalOpen, setIsBranchModalOpen] = useState(false);
  const [editingBranch, setEditingBranch] = useState<AdminBranch | null>(null);
  const [isZoneRenameModalOpen, setIsZoneRenameModalOpen] = useState(false);
  const [renamingZone, setRenamingZone] = useState('');
  const [isKitchenStationModalOpen, setIsKitchenStationModalOpen] = useState(false);
  const [editingStation, setEditingStation] = useState<AdminKitchenStation | null>(null);

  // ─── Tax profile form state ──────────────────────────────────────────────
  const [taxForm] = Form.useForm();

  // ─── Category route state ────────────────────────────────────────────────
  const [categoryRouteForm] = Form.useForm();

  // ─── Queries ─────────────────────────────────────────────────────────────
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

  const zonesQuery = useQuery({
    queryKey: ['admin-settings-zones'],
    queryFn: () => listAdminZones(),
  });

  const kitchenStationsQuery = useQuery({
    queryKey: ['admin-kitchen-stations'],
    queryFn: () => listAdminKitchenStations(),
  });

  const categoryRoutesQuery = useQuery({
    queryKey: ['admin-category-routes', selectedStationId],
    queryFn: () => listAdminCategoryRoutes(selectedStationId as number),
    enabled: selectedStationId !== null,
  });

  const taxProfileQuery = useQuery({
    queryKey: ['admin-tax-profile'],
    queryFn: () => getAdminTaxProfile(),
  });

  const menuCategoriesQuery = useQuery({
    queryKey: ['admin-menu-categories-list'],
    queryFn: () => listAdminMenuCategories({ per_page: 50, sort: 'sort_order' }),
  });

  // ─── Derived data ────────────────────────────────────────────────────────
  const branches = useMemo(() => branchesQuery.data?.data ?? [], [branchesQuery.data?.data]);
  const tables = useMemo(() => tableQuery.data?.data ?? [], [tableQuery.data?.data]);
  const templates = useMemo(() => templatesQuery.data?.data ?? [], [templatesQuery.data?.data]);
  const zones = useMemo(() => zonesQuery.data?.data ?? [], [zonesQuery.data?.data]);
  const stations = useMemo(() => kitchenStationsQuery.data?.data ?? [], [kitchenStationsQuery.data?.data]);
  const categoryRoutes = useMemo(() => categoryRoutesQuery.data?.data ?? [], [categoryRoutesQuery.data?.data]);
  const taxProfile = useMemo(() => taxProfileQuery.data?.data ?? null, [taxProfileQuery.data?.data]);
  const menuCategories = useMemo(() => menuCategoriesQuery.data?.data ?? [], [menuCategoriesQuery.data?.data]);

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
  const selectedStation = useMemo(
    () => stations.find((station) => station.station_id === selectedStationId) ?? null,
    [stations, selectedStationId],
  );

  // ─── Mutations ────────────────────────────────────────────────────────────
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

  const syncCategoryRoutesMutation = useMutation({
    mutationFn: async (categoryIds: Array<number>) => {
      if (!selectedStationId) throw new Error('Chưa chọn trạm bếp');
      return syncAdminCategoryRoutes(selectedStationId, {
        routes: categoryIds.map((id) => ({ category_id: id })),
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-category-routes', selectedStationId] });
      toast.success('Cập nhật tuyến danh mục thành công.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa cập nhật được tuyến danh mục.'));
    },
  });

  const upsertTaxProfileMutation = useMutation({
    mutationFn: async (values: { tax_rate: number; service_charge_rate: number }) => {
      return upsertAdminTaxProfile({
        tax_rate: values.tax_rate,
        service_charge_rate: values.service_charge_rate,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-tax-profile'] });
      toast.success('Cập nhật hồ sơ thuế thành công.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Chưa cập nhật được hồ sơ thuế.'));
    },
  });

  // ─── Effects ───────────────────────────────────────────────────────────────
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

  // Populate tax form when data loads
  useEffect(() => {
    if (taxProfile) {
      taxForm.setFieldsValue({
        tax_rate: taxProfile.tax_rate,
        service_charge_rate: taxProfile.service_charge_rate,
      });
    }
  }, [taxProfile, taxForm]);

  // Populate category route form when routes load
  useEffect(() => {
    if (categoryRoutes.length > 0) {
      categoryRouteForm.setFieldsValue({
        category_ids: categoryRoutes.map((route) => route.category_id),
      });
    } else {
      categoryRouteForm.setFieldsValue({ category_ids: [] });
    }
  }, [categoryRoutes, categoryRouteForm]);

  // ─── Main content ──────────────────────────────────────────────────────────
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

      {/* ─── Branch List ─────────────────────────────────────────────────── */}
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

      <Card
        className="staff-workspace-table-card"
        title="Danh bạ chi nhánh"
        data-testid="admin-branches-page"
        extra={(
          <Button
            type="primary"
            size="small"
            data-testid="admin-branch-create-button"
            onClick={() => { setEditingBranch(null); setIsBranchModalOpen(true); }}
          >
            Tạo chi nhánh
          </Button>
        )}
      >
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
                data-testid="admin-branch-row"
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
                  <Button
                    type="link"
                    size="small"
                    style={{ padding: 0 }}
                    onClick={(e) => {
                      e.stopPropagation();
                      setEditingBranch(branch as unknown as AdminBranch);
                      setIsBranchModalOpen(true);
                    }}
                  >
                    Sửa
                  </Button>
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      {/* ─── Zone List ───────────────────────────────────────────────────── */}
      <Card
        className="staff-workspace-table-card"
        title="Khu vực (Zones)"
        data-testid="admin-zones-page"
      >
        {zonesQuery.isLoading ? <InlineLoading tip="Đang tải khu vực..." /> : null}
        {zonesQuery.error ? <ApiStateBlock error={zonesQuery.error} fallback="Chưa tải được khu vực." onRetry={() => void zonesQuery.refetch()} /> : null}
        {!zonesQuery.isLoading && !zonesQuery.error && zones.length === 0 ? (
          <EmptyBlock title="Chưa có khu vực" description="Khu vực được tạo khi tạo bàn với tên khu vực mới." />
        ) : null}
        {zones.length > 0 ? (
          <div className="staff-admin-surface-list">
            {zones.map((zone) => (
              <div key={zone.zone} className="staff-admin-surface-item" data-testid="admin-zone-row">
                <div>
                  <strong>{zone.zone}</strong>
                  <Typography.Paragraph type="secondary">{zone.table_count} bàn</Typography.Paragraph>
                </div>
                <Button
                  type="link"
                  size="small"
                  onClick={() => { setRenamingZone(zone.zone); setIsZoneRenameModalOpen(true); }}
                >
                  Đổi tên
                </Button>
              </div>
            ))}
          </div>
        ) : null}
      </Card>

      {/* ─── Table Filter ────────────────────────────────────────────────── */}
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

      {/* ─── Table List ──────────────────────────────────────────────────── */}
      <Card
        className="staff-workspace-table-card"
        title="Quản lý bàn"
        data-testid="admin-tables-page"
        extra={(
          <Button
            type="primary"
            size="small"
            data-testid="admin-table-create-button"
            onClick={() => {
              setTableForm((current) => ({ ...current, tableCode: '', seatsPrice: '' }));
            }}
          >
            Tạo bàn mới
          </Button>
        )}
      >
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
                data-testid="admin-table-row"
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

      {/* ─── Table Create Form ───────────────────────────────────────────── */}
      <Card className="staff-workspace-table-card" title="Tạo bàn" data-testid="admin-table-form">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={6}>
            <Input
              aria-label="Mã bàn mới"
              autoComplete="off"
              data-testid="admin-table-name-input"
              value={tableForm.tableCode}
              placeholder="Mã bàn"
              onChange={(event) => setTableForm((current) => ({ ...current, tableCode: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Mã chi nhánh của bàn mới"
              autoComplete="off"
              data-testid="admin-table-branch-select"
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
              data-testid="admin-table-zone-select"
              value={tableForm.zone}
              placeholder="Khu vực"
              onChange={(event) => setTableForm((current) => ({ ...current, zone: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={3}>
            <InputNumber
              aria-label="Giá bàn mới"
              data-testid="admin-table-capacity-input"
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
              data-testid="admin-table-save-button"
              onClick={() => createTableMutation.mutate()}
              loading={createTableMutation.isPending}
              disabled={createTableMutation.isPending}
            >
              Tạo
            </Button>
          </Col>
        </Row>
        {createTableMutation.error ? (
          <Typography.Paragraph type="danger" data-testid="admin-error-alert">
            {formatApiError(createTableMutation.error, 'Chưa tạo được bàn nhà hàng.')}
          </Typography.Paragraph>
        ) : null}
      </Card>

      {/* ─── Kitchen Stations ────────────────────────────────────────────── */}
      <Card
        className="staff-workspace-table-card"
        title="Trạm bếp (Kitchen Stations)"
        data-testid="admin-kitchen-stations-page"
        extra={(
          <Button
            type="primary"
            size="small"
            data-testid="admin-kitchen-station-create-button"
            onClick={() => { setEditingStation(null); setIsKitchenStationModalOpen(true); }}
          >
            Tạo trạm bếp
          </Button>
        )}
      >
        {kitchenStationsQuery.isLoading ? <InlineLoading tip="Đang tải trạm bếp..." /> : null}
        {kitchenStationsQuery.error ? <ApiStateBlock error={kitchenStationsQuery.error} fallback="Chưa tải được trạm bếp." onRetry={() => void kitchenStationsQuery.refetch()} /> : null}
        {!kitchenStationsQuery.isLoading && !kitchenStationsQuery.error && stations.length === 0 ? (
          <EmptyBlock title="Chưa có trạm bếp" description="Tạo trạm bếp để cấu hình tuyến món. Mỗi trạm bếp nhận các loại món được chỉ định." />
        ) : null}
        {stations.length > 0 ? (
          <div className="staff-admin-surface-list">
            {stations.map((station) => (
              <button
                key={station.station_id}
                type="button"
                data-testid="admin-kitchen-station-row"
                className={`staff-admin-branch-row ${station.station_id === selectedStationId ? 'staff-admin-branch-row-selected' : ''}`}
                onClick={() => setSelectedStationId(station.station_id)}
              >
                <div className="staff-admin-branch-row-main">
                  <strong>{station.name}</strong>
                  <span>{station.description ?? 'Chưa có mô tả'}</span>
                </div>
                <Space wrap size={6}>
                  <StatusChip label={station.is_active ? 'Đang hoạt động' : 'Tạm tắt'} tone={station.is_active ? 'success' : 'warning'} />
                  <Button
                    type="link"
                    size="small"
                    style={{ padding: 0 }}
                    onClick={(e) => {
                      e.stopPropagation();
                      setEditingStation(station);
                      setIsKitchenStationModalOpen(true);
                    }}
                  >
                    Sửa
                  </Button>
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      {/* ─── Category Routes ─────────────────────────────────────────────── */}
      <Card
        className="staff-workspace-table-card"
        title="Tuyến danh mục → Trạm bếp"
        data-testid="admin-category-routes-page"
      >
        {!selectedStation ? (
          <EmptyBlock
            title="Chưa chọn trạm bếp"
            description="Chọn một trạm bếp ở trên để xem và cập nhật tuyến danh mục món."
          />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <StatusChip label={`Trạm bếp: ${selectedStation.name}`} tone="processing" />
            {categoryRoutesQuery.isLoading ? <InlineLoading tip="Đang tải tuyến danh mục..." /> : null}
            {categoryRoutesQuery.error ? <ApiStateBlock error={categoryRoutesQuery.error} fallback="Chưa tải được tuyến danh mục." onRetry={() => void categoryRoutesQuery.refetch()} /> : null}
            <Form
              form={categoryRouteForm}
              layout="vertical"
              onFinish={(values: { category_ids: Array<number> }) => {
                syncCategoryRoutesMutation.mutate(values.category_ids ?? []);
              }}
            >
              <Form.Item
                label="Danh mục món được route tới trạm này"
                name="category_ids"
              >
                <Select
                  mode="multiple"
                  data-testid="admin-category-route-category-select"
                  style={{ width: '100%' }}
                  placeholder="Chọn danh mục món"
                  loading={menuCategoriesQuery.isLoading}
                  options={menuCategories.map((cat) => ({
                    value: cat.category_id,
                    label: cat.name,
                  }))}
                />
              </Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                data-testid="admin-category-route-save-button"
                loading={syncCategoryRoutesMutation.isPending}
              >
                Cập nhật tuyến danh mục
              </Button>
            </Form>
          </Space>
        )}
      </Card>

      {/* ─── Tax Profile ─────────────────────────────────────────────────── */}
      <Card
        className="staff-workspace-table-card"
        title="Hồ sơ thuế và phí dịch vụ"
        data-testid="admin-tax-profile-page"
      >
        {taxProfileQuery.isLoading ? <InlineLoading tip="Đang tải hồ sơ thuế..." /> : null}
        {taxProfileQuery.error ? <ApiStateBlock error={taxProfileQuery.error} fallback="Chưa tải được hồ sơ thuế." onRetry={() => void taxProfileQuery.refetch()} /> : null}
        {!taxProfileQuery.isLoading && !taxProfileQuery.error ? (
          <Form
            form={taxForm}
            layout="vertical"
            onFinish={(values) => upsertTaxProfileMutation.mutate(values as { tax_rate: number; service_charge_rate: number })}
          >
            <Row gutter={[12, 12]}>
              <Col xs={24} md={12}>
                <Form.Item
                  label="Thuế suất (%)"
                  name="tax_rate"
                  rules={[
                    { required: true, message: 'Vui lòng nhập thuế suất' },
                    {
                      validator: (_, value) => {
                        if (value === null || value === undefined) return Promise.resolve();
                        if (value < 0) return Promise.reject(new Error('Thuế suất không được âm'));
                        if (value > 100) return Promise.reject(new Error('Thuế suất không được vượt 100%'));
                        return Promise.resolve();
                      },
                    },
                  ]}
                >
                  <InputNumber
                    data-testid="admin-tax-rate-input"
                    style={{ width: '100%' }}
                    min={0}
                    max={100}
                    precision={2}
                    addonAfter="%"
                    placeholder="VD: 10"
                  />
                </Form.Item>
              </Col>
              <Col xs={24} md={12}>
                <Form.Item
                  label="Phí dịch vụ (%)"
                  name="service_charge_rate"
                  rules={[
                    {
                      validator: (_, value) => {
                        if (value === null || value === undefined) return Promise.resolve();
                        if (value < 0) return Promise.reject(new Error('Phí dịch vụ không được âm'));
                        if (value > 100) return Promise.reject(new Error('Phí dịch vụ không được vượt 100%'));
                        return Promise.resolve();
                      },
                    },
                  ]}
                >
                  <InputNumber
                    data-testid="admin-service-charge-input"
                    style={{ width: '100%' }}
                    min={0}
                    max={100}
                    precision={2}
                    addonAfter="%"
                    placeholder="VD: 5"
                  />
                </Form.Item>
              </Col>
            </Row>
            <Space>
              <Button
                type="primary"
                htmlType="submit"
                data-testid="admin-tax-profile-save-button"
                loading={upsertTaxProfileMutation.isPending}
              >
                Lưu hồ sơ thuế
              </Button>
              {upsertTaxProfileMutation.isSuccess ? (
                <StatusChip label="Đã lưu thành công" tone="success" data-testid="admin-tax-profile-success" />
              ) : null}
            </Space>
            {upsertTaxProfileMutation.error ? (
              <Typography.Paragraph type="danger" style={{ marginTop: 8 }}>
                {formatApiError(upsertTaxProfileMutation.error, 'Chưa lưu được hồ sơ thuế.')}
              </Typography.Paragraph>
            ) : null}
          </Form>
        ) : null}
      </Card>
    </Space>
  );

  // ─── Side panel ────────────────────────────────────────────────────────────
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

      <Card className="staff-workspace-detail-card" title="Trạm bếp đang chọn">
        {!selectedStation ? (
          <EmptyBlock title="Chưa chọn trạm bếp" description="Chọn một trạm bếp để xem tuyến danh mục." />
        ) : (
          <Space orientation="vertical" size={8} style={{ width: '100%' }}>
            <StatusChip label={selectedStation.is_active ? 'Đang hoạt động' : 'Tạm tắt'} tone={selectedStation.is_active ? 'success' : 'warning'} />
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Tên trạm bếp">{selectedStation.name}</Descriptions.Item>
              <Descriptions.Item label="Mô tả">{selectedStation.description ?? 'Chưa có mô tả'}</Descriptions.Item>
              <Descriptions.Item label="Cập nhật lúc">{formatDateTime(selectedStation.updated_at ?? selectedStation.created_at ?? null)}</Descriptions.Item>
            </Descriptions>
            {categoryRoutes.length > 0 ? (
              <div className="staff-admin-detail-list">
                {categoryRoutes.map((route) => (
                  <div key={route.route_id} className="staff-admin-detail-item" data-testid="admin-category-route-row">
                    <strong>{route.category_name ?? `Danh mục #${route.category_id}`}</strong>
                    <span>→ {selectedStation.name}</span>
                  </div>
                ))}
              </div>
            ) : (
              !categoryRoutesQuery.isLoading && <EmptyBlock title="Chưa có tuyến danh mục" description="Dùng form bên trái để thêm danh mục món vào trạm bếp này." />
            )}
          </Space>
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

  return (
    <>
      <SplitWorkspace main={main} side={side} />
      <BranchModal
        open={isBranchModalOpen}
        onClose={() => setIsBranchModalOpen(false)}
        editingBranch={editingBranch}
      />
      <ZoneRenameModal
        open={isZoneRenameModalOpen}
        onClose={() => setIsZoneRenameModalOpen(false)}
        currentZoneName={renamingZone}
      />
      <KitchenStationModal
        open={isKitchenStationModalOpen}
        onClose={() => setIsKitchenStationModalOpen(false)}
        editingStation={editingStation}
      />
    </>
  );
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
