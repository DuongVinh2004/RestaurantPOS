import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Alert,
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
import type { AuditTrailEntry } from '../../core/api/staff-api';
import { listAuditTrail } from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatDateTime } from '../../core/utils/format';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import {
  auditActorDetail,
  auditActorLabel,
  auditRelatedSubjects,
  auditSubjectLabel,
  auditSummaryLine,
  buildAuditTrailQuery,
  type AuditFilterState,
  type AuditReferenceFilter,
} from './audit-trail';

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

const initialFilters: AuditFilterState = {
  action: '',
  actorType: '',
  actorUserId: '',
  referenceType: 'reservation',
  referenceId: '',
  subjectType: '',
  dateFrom: '',
  dateTo: '',
};

export function AuditTrailPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const [filters, setFilters] = useState<AuditFilterState>(initialFilters);
  const [page, setPage] = useState(1);
  const [selectedAuditId, setSelectedAuditId] = useState<number | null>(null);

  const query = useMemo(() => buildAuditTrailQuery(filters, page, pageSize), [filters, page]);
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

  function updateFilter<K extends keyof AuditFilterState>(key: K, value: AuditFilterState[K]) {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  }

  function openLinkedEntity() {
    if (!selectedEntry) {
      return;
    }

    const reservationSubject = selectedEntry.subjects.find((subject) => subject.type === 'reservation') ?? (
      selectedEntry.primary_subject.type === 'reservation' ? selectedEntry.primary_subject : null
    );
    const orderSubject = selectedEntry.subjects.find((subject) => subject.type === 'reservation_order') ?? (
      selectedEntry.primary_subject.type === 'reservation_order' ? selectedEntry.primary_subject : null
    );

    const reservationId = reservationSubject ? Number(reservationSubject.id) : null;
    const orderId = orderSubject ? Number(orderSubject.id) : null;

    if (reservationId && can(session, 'reservation.manage')) {
      setReservationContext({
        reservationId,
        source: 'reservation',
      });
      navigate('/reservations');
      return;
    }

    if (orderId && can(session, 'order.manage')) {
      setOrderContext({
        orderId,
        source: 'order',
      });
      navigate('/orders');
    }
  }

  const main = (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Nhật ký thao tác"
        title="Rà soát nhật ký vận hành"
        description="Màn hình này dùng để lần vết sự cố và soát luồng tài chính. Giao diện ưu tiên lọc và đọc chi tiết, không phải bảng điều khiển realtime."
        extra={(
          <>
            <Button onClick={() => auditQuery.refetch()} loading={auditQuery.isFetching}>
              Làm mới nhật ký
            </Button>
          </>
        )}
      />

      {branchId ? (
        <Alert
          type="info"
          showIcon
          message="Nhật ký thao tác chưa lọc theo chi nhánh từ shell"
          description="API audit hiện tại chưa nhận `branch_id`. Hãy dùng bộ lọc đặt bàn, đơn hàng, thanh toán hoặc bàn để thu hẹp phạm vi vận hành."
        />
      ) : null}

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card><Statistic title="Số dòng trang hiện tại" value={auditQuery.data?.data.length ?? 0} /></Card>
        </Col>
        <Col xs={24} md={8}>
          <Card><Statistic title="Tổng số khớp bộ lọc" value={auditQuery.data?.meta?.total ?? 0} /></Card>
        </Col>
        <Col xs={24} md={8}>
          <Card><Statistic title="Số thao tác khác nhau" value={Object.keys(actionStats).length} /></Card>
        </Col>
      </Row>

      <Card title="Bộ lọc">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={8}>
            <Input
              value={filters.action}
              placeholder="Thao tác, ví dụ: reservation.checked_in"
              onChange={(event) => updateFilter('action', event.target.value)}
            />
          </Col>
          <Col xs={24} md={8}>
            <Select
              style={{ width: '100%' }}
              value={filters.actorType}
              options={actorTypeOptions}
              onChange={(value) => updateFilter('actorType', value)}
            />
          </Col>
          <Col xs={24} md={8}>
            <Input
              value={filters.actorUserId}
              placeholder="Mã người thao tác"
              inputMode="numeric"
              onChange={(event) => updateFilter('actorUserId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Select
              style={{ width: '100%' }}
              value={filters.referenceType}
              options={referenceOptions}
              onChange={(value) => updateFilter('referenceType', value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              value={filters.referenceId}
              placeholder="Mã tham chiếu"
              inputMode={filters.referenceType === 'subject' ? 'text' : 'numeric'}
              onChange={(event) => updateFilter('referenceId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              value={filters.subjectType}
              placeholder="Loại đối tượng tùy chỉnh"
              disabled={filters.referenceType !== 'subject'}
              onChange={(event) => updateFilter('subjectType', event.target.value)}
            />
          </Col>
          <Col xs={24} md={3}>
            <Input
              type="date"
              value={filters.dateFrom}
              onChange={(event) => updateFilter('dateFrom', event.target.value)}
            />
          </Col>
          <Col xs={24} md={3}>
            <Input
              type="date"
              value={filters.dateTo}
              onChange={(event) => updateFilter('dateTo', event.target.value)}
            />
          </Col>
        </Row>
      </Card>

      <Card title="Dòng nhật ký">
        {auditQuery.isLoading ? <InlineLoading tip="Đang tải nhật ký thao tác..." /> : null}
        {auditQuery.error ? <InlineError message={formatApiError(auditQuery.error, 'Không thể tải nhật ký thao tác.')} /> : null}
        {!auditQuery.isLoading && !auditQuery.error && (auditQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="Không có dòng nhật ký" description="Bộ lọc hiện tại không trả về sự kiện nào." />
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
                render: (_, entry) => formatDateTime(entry.occurred_at),
              },
              {
                title: 'Thao tác',
                render: (_, entry) => (
                  <Space direction="vertical" size={2}>
                    <Typography.Text strong>{entry.action}</Typography.Text>
                    <Typography.Text type="secondary">{auditSummaryLine(entry)}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Đối tượng',
                render: (_, entry) => (
                  <Space direction="vertical" size={2}>
                    <Typography.Text>{auditSubjectLabel(entry)}</Typography.Text>
                    <Typography.Text type="secondary">{entry.subjects.length} liên kết</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Người thao tác',
                render: (_, entry) => (
                  <Space direction="vertical" size={2}>
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
    <Card title="Chi tiết nhật ký">
      {!selectedEntry ? (
        <EmptyBlock title="Chưa chọn sự kiện" description="Chọn một dòng để xem người thao tác, đối tượng liên quan và dữ liệu trước/sau." />
      ) : (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Space direction="vertical" size={4} style={{ width: '100%' }}>
            <Typography.Title level={4} style={{ margin: 0 }}>{selectedEntry.action}</Typography.Title>
            <Typography.Text type="secondary">{formatDateTime(selectedEntry.occurred_at)}</Typography.Text>
          </Space>

          <Space wrap size={6}>
            <StatusChip label={selectedEntry.primary_subject.type} />
            <StatusChip label={selectedEntry.actor.type ?? 'unknown'} tone="processing" />
            {selectedEntry.request?.method ? <StatusChip label={selectedEntry.request.method} tone="warning" /> : null}
          </Space>

          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Đối tượng chính">{auditSubjectLabel(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Người thao tác">{auditActorLabel(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Chi tiết tác nhân">{auditActorDetail(selectedEntry)}</Descriptions.Item>
            <Descriptions.Item label="Yêu cầu">
              {[
                selectedEntry.request?.request_id,
                selectedEntry.request?.method,
                selectedEntry.request?.path,
              ].filter(Boolean).join(' | ') || 'Chưa có dữ liệu request'}
            </Descriptions.Item>
          </Descriptions>

          {subjectTags.length > 0 ? (
            <Card size="small" title="Đối tượng liên quan">
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

          <Card size="small" title="Tóm tắt">
            <JsonBlock value={selectedEntry.summary} />
          </Card>

          <Card size="small" title="Trước thay đổi">
            <JsonBlock value={selectedEntry.before} />
          </Card>

          <Card size="small" title="Sau thay đổi">
            <JsonBlock value={selectedEntry.after} />
          </Card>

          <Card size="small" title="Siêu dữ liệu">
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
