import { Button, Card, Col, Descriptions, Input, InputNumber, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  adminSettingsSurfaces,
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
import { AdminMasterDataImportPanel } from '../../components/AdminMasterDataImportPanel';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

type BranchRow = Awaited<ReturnType<typeof listAdminBranches>>['data'][number];
type NewTableStatus = 'Available' | 'Blocked' | 'Maintenance';

const tableStatusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'Available', label: 'Available' },
  { value: 'Reserved', label: 'Reserved' },
  { value: 'Occupied', label: 'Occupied' },
  { value: 'Blocked', label: 'Blocked' },
  { value: 'Maintenance', label: 'Maintenance' },
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
        throw new Error('Table code and template are required.');
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
      toast.success('Restaurant table created.');
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Could not create restaurant table.'));
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
        eyebrow="Configuration"
        title="Branches and settings lane"
        description="Keep branch registry, table ownership, kitchen routing, and settings-side imports away from the live ops shell."
        context={(
          <>
            <StatusChip label={`${summary.total} branches`} tone="processing" />
            <StatusChip label={`${summary.active} active`} tone="success" />
            <StatusChip label={`${summary.withClosures} with closures`} tone={summary.withClosures > 0 ? 'warning' : 'default'} />
            <StatusChip label={`${tableSummary.total} tables`} tone="processing" />
            <StatusChip label={startupBranchId ? `Shell branch #${startupBranchId}` : 'No shell branch'} tone={startupBranchId ? 'processing' : 'warning'} />
          </>
        )}
      />

      <Card className="staff-workspace-filter-card" title="Filter branches">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={16}>
            <Input
              aria-label="Search admin branches"
              autoComplete="off"
              value={filters.query}
              placeholder="Branch code or branch name"
              onChange={(event) => setFilters((current) => ({ ...current, query: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={8}>
            <label className="staff-admin-switch-row">
              <span>Active only</span>
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
            <Statistic title="Active branches" value={summary.active} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Default branches" value={summary.defaults} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Configured hours" value={summary.withBusinessHours} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Branches with closures" value={summary.withClosures} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Branch registry">
        {branchesQuery.isLoading ? <InlineLoading tip="Loading branch registry..." /> : null}
        {branchesQuery.error ? <ApiStateBlock error={branchesQuery.error} fallback="Unable to load admin branches." onRetry={() => void branchesQuery.refetch()} /> : null}
        {!branchesQuery.isLoading && !branchesQuery.error && branches.length === 0 ? (
          <EmptyBlock title="No branches matched this filter" description="Relax the current search or active-state filter to inspect another branch." />
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
                  <span>{branch.branch_code} • {branch.timezone ?? 'No timezone'}</span>
                </div>
                <Space wrap size={6}>
                  {branch.is_default ? <StatusChip label="Default" tone="processing" /> : null}
                  <StatusChip label={branch.is_active ? 'Active' : 'Inactive'} tone={branch.is_active ? 'success' : 'warning'} />
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      <Card className="staff-workspace-filter-card" title="Filter tables">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              aria-label="Search admin tables"
              autoComplete="off"
              value={tableFilters.query}
              placeholder="Table code or description"
              onChange={(event) => setTableFilters((current) => ({ ...current, query: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Admin table zone"
              autoComplete="off"
              value={tableFilters.zone}
              placeholder="Zone"
              onChange={(event) => setTableFilters((current) => ({ ...current, zone: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Admin table branch id"
              autoComplete="off"
              value={tableFilters.branchIdInput}
              placeholder="Branch id"
              onChange={(event) => setTableFilters((current) => ({ ...current, branchIdInput: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              aria-label="Admin table status"
              style={{ width: '100%' }}
              options={tableStatusOptions}
              value={tableFilters.status}
              onChange={(value) => setTableFilters((current) => ({ ...current, status: value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <label className="staff-admin-switch-row">
              <span>Include archived</span>
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
            <Statistic title="Tables" value={tableSummary.total} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Allocatable" value={tableSummary.allocatable} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Linked live" value={tableSummary.operationallyLinked} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-admin-summary-card">
            <Statistic title="Table branches" value={tableSummary.branchScoped} />
          </Card>
        </Col>
      </Row>

      <Card className="staff-workspace-table-card" title="Table management">
        {tableQuery.isLoading ? <InlineLoading tip="Loading restaurant tables..." /> : null}
        {tableQuery.error ? <ApiStateBlock error={tableQuery.error} fallback="Unable to load restaurant tables." onRetry={() => void tableQuery.refetch()} /> : null}
        {!tableQuery.isLoading && !tableQuery.error && tables.length === 0 ? (
          <EmptyBlock title="No tables matched this filter" description="Adjust the branch, zone, or status filter to inspect another table set." />
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
                  <span>{table.branch?.branch_code ?? `Branch #${table.branch_id ?? 'n/a'}`} / {table.zone ?? 'No zone'}</span>
                </div>
                <Space wrap size={6}>
                  <StatusChip label={table.status} tone={adminTableStatusTone(table.status)} />
                  <StatusChip label={`${table.row_version ?? 'n/a'} rv`} tone="default" />
                  {table.usage?.has_active_operational_links ? <StatusChip label="Live links" tone="warning" /> : null}
                </Space>
              </button>
            ))}
          </div>
        ) : null}
      </Card>

      <Card className="staff-workspace-table-card" title="Create table">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={6}>
            <Input
              aria-label="New table code"
              autoComplete="off"
              value={tableForm.tableCode}
              placeholder="Table code"
              onChange={(event) => setTableForm((current) => ({ ...current, tableCode: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="New table branch id"
              autoComplete="off"
              value={tableForm.branchId}
              placeholder="Branch id"
              onChange={(event) => setTableForm((current) => ({ ...current, branchId: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              aria-label="New table template"
              style={{ width: '100%' }}
              placeholder="Template"
              loading={templatesQuery.isLoading}
              value={tableForm.templateId || undefined}
              options={templates.map((template) => ({
                value: String(template.template_id),
                label: `${template.template_code} / ${template.seats} seats`,
              }))}
              onChange={(value) => setTableForm((current) => ({ ...current, templateId: value }))}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="New table zone"
              autoComplete="off"
              value={tableForm.zone}
              placeholder="Zone"
              onChange={(event) => setTableForm((current) => ({ ...current, zone: event.target.value }))}
            />
          </Col>
          <Col xs={24} md={3}>
            <InputNumber
              aria-label="New table price"
              style={{ width: '100%' }}
              min={0}
              value={tableForm.seatsPrice === '' ? null : Number(tableForm.seatsPrice)}
              placeholder="Price"
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
              Create
            </Button>
          </Col>
        </Row>
        {createTableMutation.error ? (
          <Typography.Paragraph type="danger">
            {formatApiError(createTableMutation.error, 'Could not create restaurant table.')}
          </Typography.Paragraph>
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card className="staff-workspace-detail-card" title="Selected branch">
        {!selectedBranch ? (
          <EmptyBlock title="No branch selected" description="Pick a branch from the registry to inspect policy, schedule, and operational context." />
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Space wrap size={6}>
              {selectedBranch.is_default ? <StatusChip label="Default branch" tone="processing" /> : null}
              <StatusChip label={selectedBranch.is_active ? 'Active' : 'Inactive'} tone={selectedBranch.is_active ? 'success' : 'warning'} />
              <StatusChip label={`Row version ${selectedBranch.row_version ?? 'n/a'}`} tone="default" />
            </Space>

            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Code">{selectedBranch.branch_code}</Descriptions.Item>
              <Descriptions.Item label="Timezone">{selectedBranch.timezone ?? 'Not set'}</Descriptions.Item>
              <Descriptions.Item label="Currency">{selectedBranch.currency ?? 'Not set'}</Descriptions.Item>
              <Descriptions.Item label="Updated at">{formatDateTime(selectedBranch.updated_at ?? selectedBranch.created_at ?? null, selectedBranch.timezone ?? undefined)}</Descriptions.Item>
              <Descriptions.Item label="Reservation lead">{branchReservationLeadLabel(selectedBranch)}</Descriptions.Item>
              <Descriptions.Item label="Same-day cutoff">{branchSameDayCutoffLabel(selectedBranch)}</Descriptions.Item>
              <Descriptions.Item label="Waiting list">{branchWaitingListLabel(selectedBranch)}</Descriptions.Item>
            </Descriptions>

            <Card size="small" title="Business hours" className="staff-workspace-detail-subcard">
              {selectedBranch.business_hours.length === 0 ? (
                <EmptyBlock title="No business hours configured" description="This branch does not expose any opening periods in the current payload." />
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

            <Card size="small" title="Closure windows" className="staff-workspace-detail-subcard">
              {selectedBranch.closure_windows.length === 0 ? (
                <EmptyBlock title="No closure windows" description="No temporary closure overrides are active for this branch." />
              ) : (
                <div className="staff-admin-detail-list">
                  {selectedBranch.closure_windows.map((closureWindow: BranchRow['closure_windows'][number], index: number) => (
                    <div key={`${closureWindow.start_local ?? 'closure'}-${index}`} className="staff-admin-detail-item">
                      <strong>{closureWindow.reason ?? 'Closure window'}</strong>
                      <span>{formatDateTime(closureWindow.start_local ?? null, selectedBranch.timezone ?? undefined)} to {formatDateTime(closureWindow.end_local ?? null, selectedBranch.timezone ?? undefined)}</span>
                    </div>
                  ))}
                </div>
              )}
            </Card>
          </Space>
        )}
      </Card>

      <Card className="staff-workspace-detail-card" title="Selected table">
        {!selectedTable ? (
          <EmptyBlock title="No table selected" description="Pick a table from the management list to inspect row version, live guards, and audit timestamps." />
        ) : (
          <TableDetail table={selectedTable} />
        )}
      </Card>

      <AdminMasterDataImportPanel
        title="Settings import dry run"
        description="Preview branches or restaurant tables with backend validation before committing with an idempotency key."
        domains={settingsImportDomains}
        onCommitted={() => {
          void queryClient.invalidateQueries({ queryKey: ['admin-settings-branches'] });
          void queryClient.invalidateQueries({ queryKey: ['admin-settings-tables'] });
        }}
      />

      <Card className="staff-workspace-detail-card" title="Configuration ownership">
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
        <StatusChip label={table.status} tone={adminTableStatusTone(table.status)} />
        <StatusChip label={table.is_allocatable ? 'Allocatable' : 'Not allocatable'} tone={table.is_allocatable ? 'success' : 'warning'} />
        <StatusChip label={`Row version ${table.row_version ?? 'n/a'}`} tone="default" />
      </Space>

      <Descriptions bordered size="small" column={1}>
        <Descriptions.Item label="Code">{table.table_code}</Descriptions.Item>
        <Descriptions.Item label="Branch">{table.branch?.branch_name ?? `Branch #${table.branch_id ?? 'n/a'}`}</Descriptions.Item>
        <Descriptions.Item label="Template">{table.template?.template_code ?? `Template #${table.template_id ?? 'n/a'}`}</Descriptions.Item>
        <Descriptions.Item label="Seats">{table.seats ?? table.capacity ?? 'Not set'}</Descriptions.Item>
        <Descriptions.Item label="Zone">{table.zone ?? 'Not set'}</Descriptions.Item>
        <Descriptions.Item label="Price">{table.price ?? 'Not set'}</Descriptions.Item>
        <Descriptions.Item label="Updated at">{formatDateTime(table.updated_at ?? table.created_at ?? null)}</Descriptions.Item>
      </Descriptions>

      <Card size="small" title="Live guards" className="staff-workspace-detail-subcard">
        <div className="staff-admin-detail-list">
          <div className="staff-admin-detail-item">
            <strong>Operational links</strong>
            <span>{table.usage?.has_active_operational_links ? 'Active reservations, holds, or orders exist' : 'No active operational links reported'}</span>
          </div>
          {Object.entries(table.guards ?? {}).map(([key, value]) => (
            <div key={key} className="staff-admin-detail-item">
              <strong>{key}</strong>
              <span>{value ? 'Allowed' : 'Blocked'}</span>
            </div>
          ))}
        </div>
      </Card>
    </Space>
  );
}
