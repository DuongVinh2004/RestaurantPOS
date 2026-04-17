import { Card, Col, Descriptions, Input, Row, Space, Statistic, Switch, Typography } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  adminSettingsSurfaces,
  branchReservationLeadLabel,
  branchSameDayCutoffLabel,
  branchWaitingListLabel,
  buildAdminBranchesQuery,
  dayOfWeekLabel,
  formatBusinessPeriods,
  pickAdminBranchId,
  summarizeAdminBranches,
  type AdminBranchFilterState,
} from '../../../../domains/admin/admin-settings';
import { listAdminBranches } from '../../../../shared/api/staff-api';
import { formatDateTime } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';

type BranchRow = Awaited<ReturnType<typeof listAdminBranches>>['data'][number];

export function AdminSettingsPage() {
  const startupBranchId = useFlowStore((state) => state.branchId);
  const [filters, setFilters] = useState<AdminBranchFilterState>({
    query: '',
    activeOnly: true,
  });
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(startupBranchId);

  const branchesQuery = useQuery({
    queryKey: ['admin-settings-branches', filters],
    queryFn: () => listAdminBranches(buildAdminBranchesQuery(filters)),
  });

  const branches = useMemo(() => branchesQuery.data?.data ?? [], [branchesQuery.data?.data]);
  const summary = useMemo(() => summarizeAdminBranches(branches), [branches]);
  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.branch_id === selectedBranchId) ?? null,
    [branches, selectedBranchId],
  );

  useEffect(() => {
    setSelectedBranchId((currentSelectedBranchId) => pickAdminBranchId(branches, currentSelectedBranchId, startupBranchId));
  }, [branches, startupBranchId]);

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
