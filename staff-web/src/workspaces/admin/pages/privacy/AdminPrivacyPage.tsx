import { Button, Card, Col, Input, Row, Select, Space, Statistic, Typography, Pagination } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  exportAdminCustomerData,
  listAdminPrivacyRequests,
  reviewAdminPrivacyRequest,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime, humanizeCode } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

type RecordRow = Record<string, unknown>;
type PrivacyDecision = 'approve' | 'reject';
type PrivacyReviewMode = 'dry_run' | 'commit';

const requestStatusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'requested', label: 'Đang chờ' },
  { value: 'rejected', label: 'Đã từ chối' },
  { value: 'completed', label: 'Hoàn tất' },
  { value: 'failed', label: 'Thất bại' },
];

export function AdminPrivacyPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState({ status: '', userId: '', page: 1, perPage: 25 });
  const [reviewForm, setReviewForm] = useState({
    requestId: '',
    decision: 'approve' as PrivacyDecision,
    mode: 'dry_run' as PrivacyReviewMode,
    notes: '',
  });
  const [exportUserId, setExportUserId] = useState('');
  const [lastReviewResult, setLastReviewResult] = useState<RecordRow | null>(null);
  const [lastExport, setLastExport] = useState<RecordRow | Array<RecordRow> | null>(null);

  const requestsQuery = useQuery({
    queryKey: ['admin-privacy-requests', filters.status, filters.userId, filters.page, filters.perPage],
    queryFn: () => listAdminPrivacyRequests({
      status: (filters.status || undefined) as 'requested' | 'rejected' | 'completed' | 'failed' | undefined,
      user_id: positiveInteger(filters.userId) ?? undefined,
      page: filters.page,
      per_page: filters.perPage,
    }),
  });

  const requests = useMemo(() => recordsFromPayload(requestsQuery.data?.data), [requestsQuery.data?.data]);
  const pendingCount = requests.filter((request) => (rowString(request, 'status') ?? '').toLowerCase() === 'pending').length;

  const reviewMutation = useMutation({
    mutationFn: () => {
      const requestId = positiveInteger(reviewForm.requestId);
      if (!requestId) {
        throw new Error('Hãy nhập privacy request id hợp lệ.');
      }

      return reviewAdminPrivacyRequest(requestId, {
        decision: reviewForm.decision,
        mode: reviewForm.mode,
        notes: emptyToNull(reviewForm.notes),
      });
    },
    onSuccess: async (envelope) => {
      setLastReviewResult(envelope.data);
      await queryClient.invalidateQueries({ queryKey: ['admin-privacy-requests'] });
      toast.success(reviewForm.mode === 'dry_run' ? 'Đã chạy thử quyết định privacy.' : 'Đã ghi nhận quyết định privacy.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa xử lý được yêu cầu privacy.')),
  });

  const exportMutation = useMutation({
    mutationFn: () => {
      const userId = positiveInteger(exportUserId);
      if (!userId) {
        throw new Error('Hãy nhập customer user id hợp lệ.');
      }

      return exportAdminCustomerData(userId);
    },
    onSuccess: (envelope) => {
      setLastExport(envelope.data);
      toast.success('Đã tải data export khách hàng.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa export được dữ liệu khách hàng.')),
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Admin privacy"
        title="Rà soát dữ liệu khách"
        description="Xử lý yêu cầu privacy theo dry-run/commit và xuất dữ liệu khách bằng route admin hiện có."
        extra={<Button onClick={() => requestsQuery.refetch()} loading={requestsQuery.isFetching}>Làm mới yêu cầu</Button>}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}><Card><Statistic title="Yêu cầu" value={requests.length} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Đang chờ" value={pendingCount} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Bộ lọc user" value={positiveInteger(filters.userId) ?? 0} /></Card></Col>
      </Row>

      <Card title="Danh sách yêu cầu privacy">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Space wrap>
            <Select
              aria-label="Lọc trạng thái privacy"
              value={filters.status}
              options={requestStatusOptions}
              onChange={(value) => setFilters((current) => ({ ...current, status: value }))}
              style={{ width: 180 }}
            />
            <Input.Search
              aria-label="Lọc theo customer user id"
              inputMode="numeric"
              allowClear
              value={filters.userId}
              placeholder="Customer user id"
              onChange={(event) => setFilters((current) => ({ ...current, userId: event.target.value }))}
              onSearch={(value) => setFilters((current) => ({ ...current, userId: value.trim() }))}
              style={{ width: 220 }}
            />
          </Space>

          {requestsQuery.isLoading ? <InlineLoading tip="Đang tải yêu cầu privacy..." /> : null}
          {requestsQuery.error ? <ApiStateBlock error={requestsQuery.error} fallback="Không thể tải yêu cầu privacy." onRetry={() => requestsQuery.refetch()} /> : null}
          {!requestsQuery.isLoading && !requestsQuery.error && requests.length === 0 ? <EmptyBlock title="Không có yêu cầu privacy" description="Backend không trả về yêu cầu nào theo bộ lọc hiện tại." /> : null}

          <div className="staff-admin-surface-list">
            {requests.map((request, index) => {
              const requestId = rowNumber(request, 'request_id') ?? rowNumber(request, 'privacy_request_id');
              const userId = rowNumber(request, 'user_id') ?? rowNumber(request, 'customer_user_id');

              return (
                <button
                  key={requestId ?? index}
                  type="button"
                  className="staff-admin-surface-item staff-clickable-surface"
                  onClick={() => {
                    setReviewForm((current) => ({ ...current, requestId: requestId ? String(requestId) : current.requestId }));
                    setExportUserId(userId ? String(userId) : exportUserId);
                  }}
                >
                  <strong>{rowString(request, 'request_type') ?? `Yêu cầu #${requestId ?? index + 1}`}</strong>
                  <Typography.Text type="secondary">
                    {userId ? `Customer #${userId}` : 'Không có user id'} / {rowString(request, 'created_at') ? formatDateTime(rowString(request, 'created_at')) : 'Không có thời điểm tạo'}
                  </Typography.Text>
                  <Space wrap>
                    <StatusChip label={humanizeCode(rowString(request, 'status') ?? 'unknown')} tone={privacyStatusTone(rowString(request, 'status'))} />
                    {requestId ? <StatusChip label={`Request #${requestId}`} tone="default" /> : null}
                  </Space>
                </button>
              );
            })}
          </div>
          {requests.length > 0 && (
            <Pagination
              current={filters.page}
              pageSize={filters.perPage}
              total={(requestsQuery.data as any)?.meta?.total ?? requests.length}
              onChange={(page, pageSize) => setFilters((current) => ({ ...current, page, perPage: pageSize }))}
              showSizeChanger
              pageSizeOptions={['10', '25', '50', '100']}
              style={{ marginTop: 16, textAlign: 'right' }}
            />
          )}
        </Space>
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Review yêu cầu">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input aria-label="Privacy request id" inputMode="numeric" placeholder="Privacy request id" value={reviewForm.requestId} onChange={(event) => setReviewForm((current) => ({ ...current, requestId: event.target.value }))} />
          <Select<PrivacyDecision> aria-label="Quyết định privacy" value={reviewForm.decision} onChange={(value) => setReviewForm((current) => ({ ...current, decision: value }))} options={[{ value: 'approve', label: 'Duyệt' }, { value: 'reject', label: 'Từ chối' }]} />
          <Select<PrivacyReviewMode> aria-label="Chế độ review privacy" value={reviewForm.mode} onChange={(value) => setReviewForm((current) => ({ ...current, mode: value }))} options={[{ value: 'dry_run', label: 'Dry-run' }, { value: 'commit', label: 'Commit' }]} />
          <Input.TextArea aria-label="Ghi chú review privacy" rows={4} value={reviewForm.notes} placeholder="Lý do hoặc ghi chú kiểm soát" onChange={(event) => setReviewForm((current) => ({ ...current, notes: event.target.value }))} />
          <Button type="primary" onClick={() => reviewMutation.mutate()} loading={reviewMutation.isPending}>
            {reviewForm.mode === 'dry_run' ? 'Chạy thử review' : 'Ghi nhận review'}
          </Button>
          {lastReviewResult ? <pre className="staff-json-preview">{JSON.stringify(lastReviewResult, null, 2)}</pre> : <Typography.Text type="secondary">Chưa có kết quả review trong phiên này.</Typography.Text>}
        </Space>
      </Card>

      <Card title="Xuất dữ liệu khách">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input aria-label="Customer user id export" inputMode="numeric" placeholder="Customer user id" value={exportUserId} onChange={(event) => setExportUserId(event.target.value)} />
          <Button onClick={() => exportMutation.mutate()} loading={exportMutation.isPending}>Export dữ liệu khách</Button>
          {lastExport ? <pre className="staff-json-preview">{JSON.stringify(lastExport, null, 2)}</pre> : <Typography.Text type="secondary">Chưa có payload export trong phiên này.</Typography.Text>}
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} variant="balanced" />;
}

function recordsFromPayload(payload: unknown): Array<RecordRow> {
  if (Array.isArray(payload)) {
    return payload.filter(isRecord);
  }

  if (!isRecord(payload)) {
    return [];
  }

  for (const key of ['data', 'items', 'rows', 'requests', 'privacy_requests']) {
    const value = payload[key];
    if (Array.isArray(value)) {
      return value.filter(isRecord);
    }
  }

  return [];
}

function positiveInteger(value: string): number | null {
  const parsed = Number(value.trim());
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function rowNumber(row: RecordRow, key: string): number | null {
  const value = row[key];
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function rowString(row: RecordRow, key: string): string | null {
  const value = row[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function emptyToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function isRecord(value: unknown): value is RecordRow {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function privacyStatusTone(status: string | null): 'success' | 'warning' | 'error' | 'processing' | 'default' {
  switch ((status ?? '').toLowerCase()) {
    case 'approved':
    case 'completed':
      return 'success';
    case 'rejected':
      return 'error';
    case 'pending':
      return 'warning';
    default:
      return 'processing';
  }
}
