import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Card,
  Col,
  Descriptions,
  Input,
  Row,
  Select,
  Space,
  Statistic,
  Table,
  Typography,
} from 'antd';
import { useQuery } from '@tanstack/react-query';
import type { AuditTrailEntry } from '../../../../shared/api/staff-api';
import { listAuditTrail } from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatRelativeAge } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  auditActorDetail,
  auditActorLabel,
  auditLinkedEntityTarget,
  auditRelatedSubjects,
  auditRequestSummary,
  auditSubjectLabel,
  auditSummaryLine,
  buildAuditTrailQuery,
  buildInitialAuditFilters,
  type AuditBranchScope,
  type AuditFilterState,
  type AuditReferenceFilter,
} from '../../../../domains/audit/audit-trail';

const pageSize = 20;
const actorTypeOptions = [
  { value: '', label: 'Tất cả loại tác nhân' },
  { value: 'staff_user', label: 'Nhân viên' },
  { value: 'staff_api_key', label: 'Khóa API nhân viên' },
  { value: 'customer_account', label: 'Tài khoản khách hàng' },
  { value: 'customer_access_session', label: 'Phiên truy cập khách hàng' },
  { value: 'customer_session', label: 'Phiên khách hàng' },
  { value: 'webhook_provider', label: 'Nhà cung cấp webhook' },
  { value: 'system', label: 'Hệ thống' },
] satisfies Array<{ value: string; label: string }>;

const referenceOptions = [
  { value: 'reservation', label: 'Mã đặt bàn' },
  { value: 'order', label: 'Mã đơn hàng' },
  { value: 'payment', label: 'Mã thanh toán' },
  { value: 'waiting', label: 'Mã khách chờ' },
  { value: 'table', label: 'Mã bàn' },
  { value: 'cashier_shift', label: 'Mã ca thu ngân' },
  { value: 'subject', label: 'Đối tượng tùy chỉnh' },
] satisfies Array<{ value: AuditReferenceFilter; label: string }>;

export function AuditTrailPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [filters, setFilters] = useState<AuditFilterState>(() => buildInitialAuditFilters(branchId));
  const [page, setPage] = useState(1);
  const [selectedAuditId, setSelectedAuditId] = useState<number | null>(null);

  useEffect(() => {
    if (!branchId && filters.branchScope === 'shell') {
      setFilters((current) => ({ ...current, branchScope: 'all' }));
    }
  }, [branchId, filters.branchScope]);

  const defaultFilters = useMemo(() => buildInitialAuditFilters(branchId), [branchId]);
  const filtersActive = JSON.stringify(filters) !== JSON.stringify(defaultFilters);

  const query = useMemo(() => buildAuditTrailQuery(filters, branchId, page, pageSize), [branchId, filters, page]);
  const auditQuery = useQuery({
    queryKey: ['audit-trail', query],
    queryFn: () => listAuditTrail(query),
    enabled: !!session,
  });

  useEffect(() => {
    const rows = auditQuery.data?.data ?? [];
    if (rows.length === 0) {
      if (selectedAuditId !== null) {
        setSelectedAuditId(null);
      }
      return;
    }

    if (!selectedAuditId || !rows.some((row) => row.audit_id === selectedAuditId)) {
      setSelectedAuditId(rows[0].audit_id);
    }
  }, [auditQuery.data?.data, selectedAuditId]);

  const selectedEntry = useMemo(
    () => auditQuery.data?.data.find((row) => row.audit_id === selectedAuditId) ?? null,
    [auditQuery.data?.data, selectedAuditId],
  );

  const subjectTags = selectedEntry ? auditRelatedSubjects(selectedEntry) : [];
  const actionStats = useMemo(() => countActions(auditQuery.data?.data ?? []), [auditQuery.data?.data]);
  const branchScopeOptions = useMemo(
    () => (
      branchId
        ? [
          { value: 'shell', label: `Chi nhánh #${branchId}` },
          { value: 'all', label: 'Toàn quyền audit' },
        ]
        : [
          { value: 'all', label: 'Toàn quyền audit' },
        ]
    ) satisfies Array<{ value: AuditBranchScope; label: string }>,
    [branchId],
  );
  const emptyDescription = filters.branchScope === 'shell' && branchId
    ? `Không có sự kiện khớp bộ lọc trong chi nhánh #${branchId}.`
    : 'Bộ lọc hiện tại không trả về sự kiện nào.';

  function updateFilter<K extends keyof AuditFilterState>(key: K, value: AuditFilterState[K]) {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  }

  function resetFilters() {
    setFilters(buildInitialAuditFilters(branchId));
    setPage(1);
    setSelectedAuditId(null);
  }

  function openLinkedEntity() {
    if (!selectedEntry) {
      return;
    }

    const target = auditLinkedEntityTarget(selectedEntry, {
      canManageReservations: can(session, 'reservation.manage'),
      canManageOrders: can(session, 'order.manage'),
    });

    if (target?.kind === 'reservation') {
      setReservationContext({
        reservationId: target.id,
        source: 'audit',
      });
      navigate(target.path);
      return;
    }

    if (target?.kind === 'order') {
      setOrderContext({
        orderId: target.id,
        source: 'audit',
      });
      navigate(target.path);
    }
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Điều tra nhật ký"
        title="Rà soát nhật ký vận hành"
        description="Dùng màn này để lần vết sự cố, kiểm tra hành động nhạy cảm và nối lại ngữ cảnh giữa audit với luồng đặt bàn, đơn hàng hoặc tài chính."
        context={(
          <>
            <StatusChip
              label={filters.branchScope === 'shell' && branchId ? `Chi nhánh #${branchId}` : 'Toàn phạm vi nhật ký'}
              tone={filters.branchScope === 'shell' ? 'processing' : 'default'}
            />
            <StatusChip label={`${auditQuery.data?.meta?.total ?? 0} dòng khớp bộ lọc`} tone="processing" />
            {selectedEntry ? <StatusChip label={`Sự kiện ${selectedEntry.audit_id}`} tone="warning" /> : null}
          </>
        )}
        extra={(
          <>
            <Button onClick={() => auditQuery.refetch()} loading={auditQuery.isFetching}>
              Làm mới nhật ký
            </Button>
          </>
        )}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card className="staff-workspace-summary-card"><Statistic title="Số dòng trang hiện tại" value={auditQuery.data?.data.length ?? 0} /></Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-workspace-summary-card"><Statistic title="Tổng số khớp bộ lọc" value={auditQuery.data?.meta?.total ?? 0} /></Card>
        </Col>
        <Col xs={24} md={8}>
          <Card className="staff-workspace-summary-card"><Statistic title="Số thao tác khác nhau" value={Object.keys(actionStats).length} /></Card>
        </Col>
      </Row>

      <Card
        className="staff-workspace-filter-card"
        title="Bộ lọc"
        extra={filtersActive ? <Button size="small" onClick={resetFilters}>{'\u0110\u1eb7t l\u1ea1i b\u1ed9 l\u1ecdc'}</Button> : null}
      >
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              aria-label="Tìm kiếm trong nhật ký audit"
              autoComplete="off"
              name="auditSearch"
              placeholder="Thao tác, mã truy vết, người thao tác hoặc đối tượng…"
              spellCheck={false}
              value={filters.searchText}
              onChange={(event) => updateFilter('searchText', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Lọc theo mã truy vết"
              autoComplete="off"
              name="requestId"
              placeholder="Mã truy vết…"
              spellCheck={false}
              value={filters.requestId}
              onChange={(event) => updateFilter('requestId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              aria-label="Lọc theo phạm vi chi nhánh"
              style={{ width: '100%' }}
              value={filters.branchScope}
              options={branchScopeOptions}
              onChange={(value) => updateFilter('branchScope', value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Lọc theo tên thao tác"
              autoComplete="off"
              name="action"
              placeholder="Ví dụ: reservation.checked_in…"
              spellCheck={false}
              value={filters.action}
              onChange={(event) => updateFilter('action', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Select
              aria-label="Lọc theo loại tác nhân"
              style={{ width: '100%' }}
              value={filters.actorType}
              options={actorTypeOptions}
              onChange={(value) => updateFilter('actorType', value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              aria-label="Lọc theo mã người thao tác"
              autoComplete="off"
              name="actorUserId"
              placeholder="Mã người thao tác…"
              value={filters.actorUserId}
              inputMode="numeric"
              onChange={(event) => updateFilter('actorUserId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              aria-label="Lọc theo loại tham chiếu"
              style={{ width: '100%' }}
              value={filters.referenceType}
              options={referenceOptions}
              onChange={(value) => updateFilter('referenceType', value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Lọc theo mã tham chiếu"
              autoComplete="off"
              name="referenceId"
              placeholder="Mã tham chiếu…"
              value={filters.referenceId}
              inputMode={filters.referenceType === 'subject' ? 'text' : 'numeric'}
              onChange={(event) => updateFilter('referenceId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Input
              aria-label="Lọc theo loại đối tượng tùy chỉnh"
              autoComplete="off"
              name="subjectType"
              placeholder="Loại đối tượng…"
              spellCheck={false}
              value={filters.subjectType}
              disabled={filters.referenceType !== 'subject'}
              onChange={(event) => updateFilter('subjectType', event.target.value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Input
              aria-label="Từ ngày audit"
              type="date"
              value={filters.dateFrom}
              onChange={(event) => updateFilter('dateFrom', event.target.value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Input
              aria-label="Đến ngày audit"
              type="date"
              value={filters.dateTo}
              onChange={(event) => updateFilter('dateTo', event.target.value)}
            />
          </Col>
        </Row>
      </Card>

      <Card title="Dòng nhật ký" className="staff-workspace-table-card">
        {auditQuery.isLoading ? <InlineLoading tip="Đang tải nhật ký thao tác..." /> : null}
        {auditQuery.error ? <InlineError message={formatApiError(auditQuery.error, 'Không thể tải nhật ký thao tác.')} /> : null}
        {!auditQuery.isLoading && !auditQuery.error && (auditQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="Không có dòng nhật ký" description={emptyDescription} />
        ) : null}
        {(auditQuery.data?.data.length ?? 0) > 0 ? (
          <Table<AuditTrailEntry>
            rowKey="audit_id"
            dataSource={auditQuery.data?.data ?? []}
            rowClassName={(entry) => (entry.audit_id === selectedAuditId ? 'staff-row-selected' : '')}
            onRow={(entry) => ({ onClick: () => setSelectedAuditId(entry.audit_id) })}
            pagination={{
              current: auditQuery.data?.meta?.page ?? page,
              pageSize: auditQuery.data?.meta?.per_page ?? pageSize,
              total: auditQuery.data?.meta?.total ?? 0,
              showSizeChanger: false,
              onChange: (nextPage) => setPage(nextPage),
            }}
            columns={[
              {
                title: 'Thời gian',
                width: 150,
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>{formatDateTime(entry.occurred_at)}</Typography.Text>
                    <Typography.Text type="secondary">{formatRelativeAge(entry.occurred_at)}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Thao tác',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{entry.action}</Typography.Text>
                    <Typography.Text type="secondary">{auditSummaryLine(entry)}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Đối tượng',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>{auditSubjectLabel(entry)}</Typography.Text>
                    <Typography.Text type="secondary">{entry.subjects.length} liên kết</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Người thao tác',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>{auditActorLabel(entry)}</Typography.Text>
                    <Typography.Text type="secondary">{auditActorDetail(entry)}</Typography.Text>
                  </Space>
                ),
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Card title="Chi tiết nhật ký" className="staff-workspace-detail-card">
      {!selectedEntry ? (
        <EmptyBlock title="Chưa chọn sự kiện" description="Chọn một dòng để xem người thao tác, đối tượng liên quan và dữ liệu trước/sau." />
      ) : (
        <Space orientation="vertical" size={16} style={{ width: '100%' }}>
          <Space orientation="vertical" size={4} style={{ width: '100%' }}>
            <Typography.Title level={4} style={{ margin: 0 }}>{selectedEntry.action}</Typography.Title>
            <Typography.Text type="secondary">{formatDateTime(selectedEntry.occurred_at)}</Typography.Text>
          </Space>

          <Space wrap size={6}>
            <StatusChip label={selectedEntry.primary_subject.type} />
            <StatusChip label={selectedEntry.actor.type ?? 'unknown'} tone="processing" />
            {selectedEntry.request?.method ? <StatusChip label={selectedEntry.request.method} tone="warning" /> : null}
            {selectedEntry.request?.branch_id ? <StatusChip label={`Chi nhánh #${selectedEntry.request.branch_id}`} tone="default" /> : null}
          </Space>

          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Đối tượng chính">{auditSubjectLabel(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Người thao tác">{auditActorLabel(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Chi tiết tác nhân">{auditActorDetail(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Yêu cầu">{auditRequestSummary(selectedEntry)}</Descriptions.Item>
          </Descriptions>

          {subjectTags.length > 0 ? (
            <Card size="small" title="Đối tượng liên quan" className="staff-workspace-detail-subcard">
              <Space wrap size={6}>
                {subjectTags.map((label) => <StatusChip key={label} label={label} tone="default" />)}
              </Space>
            </Card>
          ) : null}

          <div className="staff-action-row">
            {(selectedEntry.primary_subject.type === 'reservation' || selectedEntry.subjects.some((subject) => subject.type === 'reservation' || subject.type === 'reservation_order')) ? (
              <Button onClick={openLinkedEntity}>
                Mở luồng liên quan
              </Button>
            ) : null}
          </div>

          <Card size="small" title="Tóm tắt" className="staff-workspace-detail-subcard">
            <JsonBlock value={selectedEntry.summary} />
          </Card>

          <Card size="small" title="Trước thay đổi" className="staff-workspace-detail-subcard">
            <JsonBlock value={selectedEntry.before} />
          </Card>

          <Card size="small" title="Sau thay đổi" className="staff-workspace-detail-subcard">
            <JsonBlock value={selectedEntry.after} />
          </Card>

          <Card size="small" title="Siêu dữ liệu" className="staff-workspace-detail-subcard">
            <JsonBlock value={selectedEntry.meta} />
          </Card>
        </Space>
      )}
    </Card>
  );

  return <SplitWorkspace main={main} side={side} />;
}

function JsonBlock({ value }: { value: Record<string, unknown> | null | undefined }) {
  if (!value || Object.keys(value).length === 0) {
    return <Typography.Text type="secondary">Không có dữ liệu</Typography.Text>;
  }

  return (
    <pre className="staff-json-block">
      {JSON.stringify(value, null, 2)}
    </pre>
  );
}

function countActions(rows: Array<AuditTrailEntry>): Record<string, number> {
  return rows.reduce<Record<string, number>>((counts, row) => {
    counts[row.action] = (counts[row.action] ?? 0) + 1;
    return counts;
  }, {});
}
