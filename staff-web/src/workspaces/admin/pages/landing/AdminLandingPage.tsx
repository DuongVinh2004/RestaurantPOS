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
          eyebrow="Back office"
          title="Admin control center"
          description="Use the admin workspace for configuration, catalog ownership, and read models without mixing with live floor execution."
          context={(
            <>
              <StatusChip label={`${summary.enabledCount}/${summary.domainCount} domains granted`} tone="processing" />
              <StatusChip label={`${summary.liveCount} live pages`} tone="success" />
              <StatusChip label={`${summary.importExportCount} import/export surfaces`} tone="default" />
              <StatusChip label={branchId ? `Branch #${branchId}` : 'No branch locked'} tone={branchId ? 'processing' : 'warning'} />
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
              <Statistic title="Granted domains" value={summary.enabledCount} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Live routes" value={summary.liveCount} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Branches in settings" value={branchesQuery.data?.data.length ?? 0} loading={branchesQuery.isLoading} />
            </Card>
          </Col>
          <Col xs={24} md={6}>
            <Card className="staff-admin-summary-card">
              <Statistic title="Open purchase orders" value={purchaseOrdersQuery.data?.data.length ?? 0} loading={purchaseOrdersQuery.isLoading} />
            </Card>
          </Col>
        </Row>

        <InlineState
          tone={branchId ? 'info' : 'warning'}
          eyebrow="Workspace boundary"
          title={branchId ? `Back-office pages can reuse branch #${branchId} when a domain is branch-scoped.` : 'Some admin domains are branch-sensitive and no branch is locked yet.'}
          description="Settings, inventory, reporting, and audit stay inside one admin route tree. Domains without a dedicated page remain visible here as contract-ready ownership lanes."
        />

        {groupedCards.map((group) => (
          <section key={group.key} className="staff-admin-domain-section">
            <div className="staff-admin-section-head">
              <div>
                <Typography.Title level={4}>{group.label}</Typography.Title>
                <Typography.Paragraph type="secondary">
                  {group.key === 'control'
                    ? 'Branch, table, and kitchen configuration lives here.'
                    : group.key === 'catalog'
                      ? 'Catalog and review domains stay outside live ops.'
                      : group.key === 'supply'
                        ? 'Supply control and receiving stay under admin ownership.'
                        : 'Read models and investigations stay in the back office.'}
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
                      Backend surface: {card.backendSurface}
                    </Typography.Text>

                    {card.capability ? (
                      <Typography.Text type="secondary">
                        Capability: {card.capability}
                      </Typography.Text>
                    ) : null}

                    <div className="staff-admin-domain-card-actions">
                      {card.actionPath ? (
                        <Button type="primary" onClick={() => navigate(card.actionPath as string)}>
                          Open {card.title}
                        </Button>
                      ) : (
                        <Typography.Text type="secondary">
                          No dedicated page yet. Keep this domain on the admin roadmap instead of merging it into ops.
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
