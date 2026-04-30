import { Button, Card, Col, Row, Space, Statistic, Typography } from 'antd';
import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  groupAdminWorkspaceCards,
  resolveAdminQuickLinks,
  resolveAdminWorkspaceCards,
  summarizeAdminWorkspace,
} from '../../../../domains/admin/admin-workspace';
import { listAdminBranches, listAdminPurchaseOrders } from '../../../../shared/api/staff-api';
import { can } from '../../../../shared/auth/capabilities';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { InlineState } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';

export function AdminLandingPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);

  const cards = useMemo(() => resolveAdminWorkspaceCards(session), [session]);
  const groupedCards = useMemo(() => groupAdminWorkspaceCards(cards), [cards]);
  const quickLinks = useMemo(() => resolveAdminQuickLinks(cards), [cards]);
  const summary = useMemo(() => summarizeAdminWorkspace(cards), [cards]);
  const canManageSettings = can(session, 'settings.manage');
  const canManageInventory = can(session, 'inventory.manage');

  const branchesQuery = useQuery({
    queryKey: ['admin-landing-branches'],
    queryFn: () => listAdminBranches({}),
    enabled: canManageSettings,
    staleTime: 60_000,
  });

  const purchaseOrdersQuery = useQuery({
    queryKey: ['admin-landing-purchase-orders', branchId],
    queryFn: () => listAdminPurchaseOrders({ branch_id: branchId ?? undefined, per_page: 6, sort: '-created_at' }),
    enabled: canManageInventory,
    staleTime: 60_000,
  });

  return (
    <div className="staff-admin-workspace">
      <Space orientation="vertical" size={16} style={{ width: '100%' }}>
        <PageHeader
          eyebrow="Quản trị"
          title="Trung tâm quản trị"
          description="Quản lý cấu hình nhà hàng, danh mục, kho, báo cáo và dữ liệu kiểm soát mà không lẫn với màn vận hành trực tiếp."
          context={(
            <>
              <StatusChip label={`${summary.enabledCount}/${summary.domainCount} phân hệ có quyền`} tone="processing" />
              <StatusChip label={`${summary.liveCount} màn đang dùng`} tone="success" />
              <StatusChip label={`${summary.importExportCount} luồng nhập/xuất`} tone="default" />
              <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Chưa chọn chi nhánh'} tone={branchId ? 'processing' : 'warning'} />
            </>
          )}
          extra={(
            <Space wrap>
              {quickLinks.slice(0, 4).map((card) => (
                <Button key={card.key} type={card.key === quickLinks[0]?.key ? 'primary' : 'default'} onClick={() => navigate(card.actionPath ?? '/admin')}>
                  {card.title}
                </Button>
              ))}
            </Space>
          )}
        />

        <Row gutter={[16, 16]}>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Phân hệ có quyền" value={summary.enabledCount} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Màn đang dùng" value={summary.liveCount} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Chi nhánh đã cấu hình" value={branchesQuery.data?.data.length ?? 0} loading={branchesQuery.isLoading} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Đơn mua đang mở" value={purchaseOrdersQuery.data?.data.length ?? 0} loading={purchaseOrdersQuery.isLoading} />
            </Card>
          </Col>
        </Row>

        <AdminOverviewCharts
          summary={summary}
          branchCount={branchesQuery.data?.data.length ?? 0}
          purchaseOrderCount={purchaseOrdersQuery.data?.data.length ?? 0}
          loading={branchesQuery.isLoading || purchaseOrdersQuery.isLoading}
        />

        <InlineState
          tone={branchId ? 'info' : 'warning'}
          eyebrow="Phạm vi quản trị"
          title={branchId ? `Các màn quản trị có thể dùng chi nhánh #${branchId} khi dữ liệu phụ thuộc chi nhánh.` : 'Một số phân hệ cần chi nhánh nhưng hiện chưa chọn chi nhánh.'}
          description="Thiết lập, kho, báo cáo và nhật ký nằm trong cùng cây quản trị. Phân hệ chưa có màn riêng vẫn hiển thị rõ để không lẫn vào khu vực vận hành."
        />

        {groupedCards.map((group) => (
          <section key={group.key} className="staff-admin-domain-section">
            <div className="staff-admin-section-head">
              <div>
                <Typography.Title level={4}>{group.label}</Typography.Title>
                <Typography.Paragraph type="secondary">
                  {groupDescription(group.key)}
                </Typography.Paragraph>
              </div>
            </div>

            <div className="staff-admin-domain-grid">
              {group.cards.map((card) => (
                <Card key={card.key} className={`staff-admin-domain-card staff-admin-domain-card-${card.status}`}>
                  <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                    <div className="staff-admin-domain-card-head">
                      <div>
                        <Typography.Title level={5}>{card.title}</Typography.Title>
                        <Typography.Paragraph type="secondary">{card.description}</Typography.Paragraph>
                      </div>
                      <Space wrap>
                        <StatusChip label={card.statusLabel} tone={card.status === 'live' ? 'success' : card.status === 'contract-ready' ? 'processing' : 'warning'} />
                        <StatusChip label={card.workflowLabel} tone="default" />
                        {card.branchSensitive ? <StatusChip label="Branch-sensitive" tone="warning" /> : null}
                      </Space>
                    </div>

                    <Typography.Text type="secondary">
                      API liên quan: {card.backendSurface}
                    </Typography.Text>

                    {card.capability ? (
                      <Typography.Text type="secondary">
                        Quyền cần có: {card.capability}
                      </Typography.Text>
                    ) : null}

                    <div className="staff-admin-domain-card-actions">
                      {card.actionPath ? (
                        <Button type="primary" onClick={() => navigate(card.actionPath as string)}>
                          Mở {card.title}
                        </Button>
                      ) : (
                        <Typography.Text type="secondary">
                          Chưa có màn riêng. Phân hệ này vẫn nằm trong lộ trình quản trị, không trộn vào khu vực vận hành.
                        </Typography.Text>
                      )}
                    </div>
                  </Space>
                </Card>
              ))}
            </div>
          </section>
        ))}
      </Space>
    </div>
  );
}

function AdminOverviewCharts({
  summary,
  branchCount,
  purchaseOrderCount,
  loading,
}: {
  summary: ReturnType<typeof summarizeAdminWorkspace>;
  branchCount: number;
  purchaseOrderCount: number;
  loading: boolean;
}) {
  const restrictedCount = Math.max(summary.domainCount - summary.enabledCount, 0);

  return (
    <Card className="staff-admin-overview-card" title="Sơ đồ tổng quan">
      <div className="staff-admin-overview-grid">
        <section aria-label="Biểu đồ phân hệ quản trị" className="staff-admin-chart-panel">
          <div className="staff-admin-chart-head">
            <Typography.Title level={5}>Phân hệ quản trị</Typography.Title>
            <Typography.Text type="secondary">Tỷ lệ quyền, màn đã có và luồng cần rà soát.</Typography.Text>
          </div>
          <div className="staff-admin-chart-bars">
            <ChartBar label="Có quyền thao tác" value={summary.enabledCount} max={summary.domainCount} tone="processing" />
            <ChartBar label="Màn đang dùng" value={summary.liveCount} max={summary.domainCount} tone="success" />
            <ChartBar label="Nhập / xuất dữ liệu" value={summary.importExportCount} max={summary.domainCount} tone="default" />
            <ChartBar label="Cần quyền bổ sung" value={restrictedCount} max={summary.domainCount} tone="warning" />
          </div>
        </section>

        <section aria-label="Sơ đồ vận hành quản trị" className="staff-admin-flow-panel">
          <div className="staff-admin-chart-head">
            <Typography.Title level={5}>Tín hiệu vận hành</Typography.Title>
            <Typography.Text type="secondary">Dữ liệu thật từ các API quản trị đang có quyền đọc.</Typography.Text>
          </div>
          <div className="staff-admin-flow-map" aria-busy={loading}>
            <AdminFlowStep label="Chi nhánh" value={branchCount} description="Đã cấu hình" tone="processing" />
            <AdminFlowStep label="Đơn mua" value={purchaseOrderCount} description="Đang mở" tone="warning" />
            <AdminFlowStep label="Báo cáo" value={summary.liveCount} description="Màn sẵn sàng" tone="success" />
          </div>
        </section>
      </div>
    </Card>
  );
}

function ChartBar({
  label,
  value,
  max,
  tone,
}: {
  label: string;
  value: number;
  max: number;
  tone: 'success' | 'warning' | 'processing' | 'default';
}) {
  const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

  return (
    <div className="staff-admin-chart-row">
      <div className="staff-admin-chart-label">
        <span>{label}</span>
        <strong>{value}</strong>
      </div>
      <div className="staff-admin-chart-track" aria-label={`${label}: ${value}`}>
        <span className={`staff-admin-chart-fill staff-admin-chart-fill-${tone}`} style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

function AdminFlowStep({
  label,
  value,
  description,
  tone,
}: {
  label: string;
  value: number;
  description: string;
  tone: 'success' | 'warning' | 'processing';
}) {
  return (
    <div className={`staff-admin-flow-step staff-admin-flow-step-${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      <small>{description}</small>
    </div>
  );
}

function groupDescription(groupKey: ReturnType<typeof groupAdminWorkspaceCards>[number]['key']): string {
  switch (groupKey) {
    case 'control':
      return 'Chi nhánh, bàn và tuyến bếp được cấu hình tại đây.';
    case 'catalog':
      return 'Danh mục món, giá bán và ưu đãi tách khỏi màn vận hành trực tiếp.';
    case 'supply':
      return 'Kho, nhà cung cấp và nhận hàng thuộc quyền quản trị.';
    default:
      return 'Báo cáo, nhật ký và dữ liệu kiểm soát nằm ở back office.';
  }
}
