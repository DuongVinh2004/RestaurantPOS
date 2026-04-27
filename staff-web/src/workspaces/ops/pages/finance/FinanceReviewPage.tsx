import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useCallback } from 'react';
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
  FinanceInvoiceEnvelope,
  FinancialMethodBreakdownRow,
  FinancialPaymentRow,
  FinancialReconciliationRow,
} from '../../../../shared/api/staff-api';
import {
  getFinanceInvoice,
  getFinancialReconciliationDetail,
  issueFinanceInvoice,
  listFinancialReconciliation,
} from '../../../../shared/api/staff-api';
import { formatApiError, isApiStatus } from '../../../../shared/api/errors';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatMoney } from '../../../../shared/utils/format';
import { buildJourneySearch, stripJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { buildReservationContextLabel } from '../../../journey-labels';
import { paymentTone, reservationTone } from '../../../../shared/status/status';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { toast } from '../../../../shared/ui/feedback/toast';
import { EmptyBlock, InlineError, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import {
  buildFinanceReviewSearch,
  buildFinanceQuery,
  canIssueInvoiceForRow,
  financeDateRangeError,
  financeFlagLabels,
  readFinanceReviewUrlState,
  summarizeFinance,
  type FinanceFilterState,
} from '../../../../domains/finance/finance-review';

const pageSize = 15;
const initialFilters: FinanceFilterState = {
  reservationCode: '',
  status: '',
  depositStatus: '',
  paymentCurrency: '',
  cashierUserId: '',
  hasDiscrepancy: 'all',
  activityFrom: '',
  activityTo: '',
};

const reservationStatusOptions = [
  { value: '', label: 'Tất cả trạng thái đặt bàn' },
  { value: 'Reserved', label: 'Đã giữ chỗ' },
  { value: 'Confirmed', label: 'Đã xác nhận' },
  { value: 'CheckedIn', label: 'Đã nhận bàn' },
  { value: 'Completed', label: 'Hoàn tất' },
  { value: 'Cancelled', label: 'Đã hủy' },
  { value: 'NoShow', label: 'Không đến' },
];

const depositStatusOptions = [
  { value: '', label: 'Tất cả trạng thái cọc' },
  { value: 'NotRequired', label: 'Không bắt buộc' },
  { value: 'Pending', label: 'Đang chờ' },
  { value: 'Paid', label: 'Đã thu' },
  { value: 'Refunded', label: 'Đã hoàn' },
  { value: 'PartiallyRefunded', label: 'Hoàn một phần' },
  { value: 'Forfeited', label: 'Mất cọc' },
];

const discrepancyOptions = [
  { value: 'all', label: 'Tất cả dòng' },
  { value: 'yes', label: 'Chỉ dòng có chênh lệch' },
  { value: 'no', label: 'Chỉ dòng đã khớp' },
];

export function FinanceReviewPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(false);
  const urlState = useMemo(() => readFinanceReviewUrlState(searchParams), [searchParams]);
  const { page, selectedReservationId, ...filters } = urlState;
  const contextReservationId = journey.reservationId ?? null;
  const selectedReservationRowId = contextReservationId ?? selectedReservationId;
  const dateRangeError = financeDateRangeError(filters);

  const updateUrlState = useCallback((
    patch: Partial<typeof urlState>,
    options?: { replace?: boolean },
  ) => {
    const nextSearch = buildFinanceReviewSearch(searchParams, patch);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams]);

  const query = useMemo(
    () => buildFinanceQuery(filters, page, pageSize, contextReservationId, branchId),
    [branchId, contextReservationId, filters, page],
  );
  const financeListQuery = useQuery({
    queryKey: ['finance-reconciliation', query],
    queryFn: () => listFinancialReconciliation(query),
    enabled: !!session && !dateRangeError,
  });

  useEffect(() => {
    const rows = financeListQuery.data?.data ?? [];
    if (rows.length === 0) {
      if (selectedReservationRowId !== null) {
        updateUrlState({ selectedReservationId: null }, { replace: true });
      }
      return;
    }

    if (!selectedReservationRowId || !rows.some((row) => row.reservation.reservation_id === selectedReservationRowId)) {
      if (contextReservationId === null) {
        updateUrlState({ selectedReservationId: rows[0].reservation.reservation_id }, { replace: true });
      }
    }
  }, [contextReservationId, financeListQuery.data?.data, selectedReservationRowId, updateUrlState]);

  const selectedRow = useMemo(
    () => financeListQuery.data?.data.find((row) => row.reservation.reservation_id === selectedReservationRowId) ?? null,
    [financeListQuery.data?.data, selectedReservationRowId],
  );
  const financeDetailQuery = useQuery({
    queryKey: ['finance-reconciliation-detail', branchId, selectedReservationRowId],
    queryFn: () => getFinancialReconciliationDetail(selectedReservationRowId as number, {
      branch_id: branchId ?? undefined,
    }),
    enabled: !!selectedReservationRowId,
  });
  const financeInvoiceQuery = useQuery({
    queryKey: ['finance-invoice', branchId, selectedReservationRowId],
    queryFn: () => getFinanceInvoice(selectedReservationRowId as number, {
      branch_id: branchId ?? undefined,
    }),
    enabled: !!selectedReservationRowId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const currentDetail = financeDetailQuery.data?.data ?? null;
  const currentInvoice = isApiStatus(financeInvoiceQuery.error, 404)
    ? null
    : financeInvoiceQuery.data ?? null;
  const selectedReservationRowVersion = currentDetail?.reservation.row_version
    ?? selectedRow?.reservation.row_version
    ?? journey.reservationRowVersion;
  const summary = useMemo(() => summarizeFinance(financeListQuery.data?.data ?? []), [financeListQuery.data?.data]);
  const activeFilterCount = useMemo(
    () => Object.entries(filters).reduce((count, [key, value]) => {
      const initialValue = initialFilters[key as keyof FinanceFilterState];
      return value !== initialValue ? count + 1 : count;
    }, 0),
    [filters],
  );

  useEffect(() => {
    const reservation = currentDetail?.reservation ?? selectedRow?.reservation ?? null;
    if (!reservation) {
      return;
    }

    setReservationContext({
      reservationId: reservation.reservation_id,
      reservationRowVersion: reservation.row_version,
      label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
      source: journey.source ?? 'checkout',
    });
  }, [currentDetail?.reservation, journey.source, selectedRow?.reservation, setReservationContext]);

  const issueInvoiceMutation = useMutation({
    mutationFn: async () => {
      if (!selectedReservationRowId) {
        throw new Error('Chọn một dòng đối soát trước khi phát hành hóa đơn.');
      }

      return issueFinanceInvoice(selectedReservationRowId, {
        branch_id: branchId ?? undefined,
      });
    },
    onSuccess: async (envelope) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation'] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation-detail', branchId, selectedReservationRowId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-invoice', branchId, selectedReservationRowId] }),
        queryClient.invalidateQueries({ queryKey: ['reporting-sales'] }),
      ]);
      message.success(`Đã phát hành hóa đơn ${envelope.data.invoice.invoice_number}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'Không thể phát hành hóa đơn tài chính.'));
    },
  });

  function updateFilter<K extends keyof FinanceFilterState>(key: K, value: FinanceFilterState[K]) {
    updateUrlState({
      [key]: value,
      page: 1,
      selectedReservationId: null,
    } as Partial<typeof urlState>, { replace: true });
  }

  async function handleIssueInvoice() {
    if (!selectedReservationRowId || !canIssueInvoiceForRow(currentDetail?.summary ?? selectedRow)) {
      return;
    }

    const confirmed = await confirmAction({
      title: `Phát hành hóa đơn cho đặt bàn #${selectedReservationRowId}`,
      content: 'Thao tác này sẽ tạo hoặc tái sử dụng hóa đơn tài chính chính thức từ dữ liệu đặt bàn đã chốt tiền. Chỉ tiếp tục sau khi đã kiểm tra quyết toán và thông tin thuế.',
      okText: 'Phát hành hóa đơn',
    });

    if (confirmed) {
      await issueInvoiceMutation.mutateAsync();
    }
  }

  function openReservationFlow() {
    if (!selectedReservationRowId) {
      return;
    }

        navigate(`${staffRoutePaths.ops.reservations}?${buildJourneySearch({
      source: 'reservation',
      reservationId: selectedReservationRowId,
      reservationRowVersion: selectedReservationRowVersion ?? undefined,
    })}`);
  }

  function openRefundFlow() {
    if (!selectedReservationRowId) {
      return;
    }

        navigate(`${staffRoutePaths.ops.refunds}?${buildJourneySearch({
      source: 'refund',
      tableId: journey.tableId,
      tableIds: journey.tableIds,
      reservationId: selectedReservationRowId,
      reservationRowVersion: selectedReservationRowVersion ?? undefined,
      orderId: journey.orderId,
      orderRowVersion: journey.orderRowVersion,
      stationId: journey.stationId,
    })}`);
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Đối soát tài chính"
        title="Rà soát quyết toán và hóa đơn"
        description="Soát chênh lệch, hoàn tiền và phát hành hóa đơn sau khi bill đã được chốt."
        context={(
          <>
            <StatusChip label={selectedReservationRowId ? `Đặt bàn #${selectedReservationRowId}` : 'Chưa chọn dòng'} tone={selectedReservationRowId ? 'processing' : 'warning'} />
            <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Theo branch mặc định'} tone="processing" variant="severity" />
            <StatusChip label={`${summary.discrepancyCount} dòng có chênh`} tone={summary.discrepancyCount > 0 ? 'warning' : 'success'} />
          </>
        )}
        extra={(
          <>
            <Button onClick={() => financeListQuery.refetch()} loading={financeListQuery.isFetching}>
              Làm mới danh sách
            </Button>
            <Button
              onClick={() => {
                void financeDetailQuery.refetch();
                void financeInvoiceQuery.refetch();
              }}
              disabled={!selectedReservationRowId}
              loading={financeDetailQuery.isFetching || financeInvoiceQuery.isFetching}
            >
              Làm mới chi tiết
            </Button>
          </>
        )}
      />

      {contextReservationId ? (
        <Alert
          type="info"
          showIcon
          title={`Đang khóa ngữ cảnh tài chính theo đặt bàn #${contextReservationId}`}
          description={(
            <Space wrap>
              <Typography.Text>
                Màn hình này nhận ngữ cảnh đặt bàn từ luồng vận hành trước đó để thu ngân hoặc quản lý soát từng trường hợp cụ thể.
              </Typography.Text>
              <Button
                onClick={() => {
                  const nextSearch = stripJourneySearch(searchParams);
                  navigate(
                    nextSearch ? `${staffRoutePaths.ops.financeReview}?${nextSearch}` : staffRoutePaths.ops.financeReview,
                    { replace: true },
                  );
                }}
              >
                Bỏ khóa ngữ cảnh đặt bàn
              </Button>
            </Space>
          )}
        />
      ) : null}

      {selectedRow && (selectedRow.flags.has_discrepancy || selectedRow.flags.has_bill_outstanding) ? (
        <Alert
          type={selectedRow.flags.has_discrepancy ? 'warning' : 'info'}
          showIcon
          title="Dòng đang chọn cần review kỹ trước khi phát hành hóa đơn"
          description={`Còn thiếu ${formatMoney(selectedRow.reconciliation.bill_outstanding_amount, selectedRow.reservation.bill_currency ?? 'VND')}. Hãy kiểm tra chênh lệch, giao dịch và invoice state ở panel chi tiết trước khi đi tiếp.`}
        />
      ) : null}

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}>
          <Card className="staff-workspace-summary-card"><Statistic title="Số dòng trang hiện tại" value={financeListQuery.data?.data.length ?? 0} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className={`staff-workspace-summary-card ${summary.discrepancyCount > 0 ? 'staff-workspace-summary-card-active' : ''}`}><Statistic title="Ca có chênh lệch" value={summary.discrepancyCount} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className={`staff-workspace-summary-card ${summary.outstandingAmount > 0 ? 'staff-workspace-summary-card-active' : ''}`}><Statistic title="Còn thiếu" value={formatMoney(summary.outstandingAmount)} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className="staff-workspace-summary-card"><Statistic title="Đã quyết toán" value={summary.fullySettledCount} /></Card>
        </Col>
      </Row>

      <Card
        className="staff-workspace-filter-card"
        title="Bộ lọc"
        extra={(
          <Space wrap>
            <Button type={showAdvancedFilters ? 'primary' : 'default'} onClick={() => setShowAdvancedFilters((value) => !value)}>
              {showAdvancedFilters ? 'Ẩn bộ lọc nâng cao' : 'Mở bộ lọc nâng cao'}
            </Button>
            <Button
              disabled={activeFilterCount === 0}
              onClick={() => updateUrlState({ ...initialFilters, page: 1, selectedReservationId: null }, { replace: true })}
            >
              Xóa bộ lọc
            </Button>
          </Space>
        )}
      >
        <Row gutter={[12, 12]}>
          <Col xs={24} md={7}>
            <Input
              aria-label="Lọc theo mã đặt bàn"
              autoComplete="off"
              name="reservationCode"
              placeholder="Mã đặt bàn…"
              spellCheck={false}
              value={filters.reservationCode}
              onChange={(event) => updateFilter('reservationCode', event.target.value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              aria-label="Lọc theo trạng thái đặt bàn"
              style={{ width: '100%' }}
              value={filters.status}
              options={reservationStatusOptions}
              onChange={(value) => updateFilter('status', value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              aria-label="Lọc theo trạng thái cọc"
              style={{ width: '100%' }}
              value={filters.depositStatus}
              options={depositStatusOptions}
              onChange={(value) => updateFilter('depositStatus', value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              aria-label="Lọc theo trạng thái chênh lệch"
              style={{ width: '100%' }}
              value={filters.hasDiscrepancy}
              options={discrepancyOptions}
              onChange={(value) => updateFilter('hasDiscrepancy', value)}
            />
          </Col>
          {showAdvancedFilters ? (
            <>
              <Col xs={24} md={3}>
                <Input
                  aria-label="Lọc theo loại tiền thanh toán"
                  autoComplete="off"
                  name="paymentCurrency"
                  placeholder="Ví dụ: VND…"
                  spellCheck={false}
                  value={filters.paymentCurrency}
                  onChange={(event) => updateFilter('paymentCurrency', event.target.value)}
                />
              </Col>
              <Col xs={24} md={6}>
                <Input
                  aria-label="Lọc theo mã thu ngân"
                  autoComplete="off"
                  name="cashierUserId"
                  placeholder="Mã thu ngân…"
                  value={filters.cashierUserId}
                  inputMode="numeric"
                  onChange={(event) => updateFilter('cashierUserId', event.target.value)}
                />
              </Col>
              <Col xs={24} md={6}>
                <Input aria-label="Từ ngày hoạt động thanh toán" type="date" value={filters.activityFrom} onChange={(event) => updateFilter('activityFrom', event.target.value)} />
              </Col>
              <Col xs={24} md={6}>
                <Input aria-label="Đến ngày hoạt động thanh toán" type="date" value={filters.activityTo} onChange={(event) => updateFilter('activityTo', event.target.value)} />
              </Col>
            </>
          ) : null}
        </Row>
      </Card>

      {dateRangeError ? (
        <Alert
          type="warning"
          showIcon
          title="Invalid activity date range"
          description={dateRangeError}
        />
      ) : null}

      <Card title="Dòng đối soát" className="staff-workspace-table-card">
        {financeListQuery.isLoading ? <InlineLoading tip="Đang tải dữ liệu đối soát..." /> : null}
        {financeListQuery.error ? <InlineError message={formatApiError(financeListQuery.error, 'Không thể tải dữ liệu đối soát.')} /> : null}
        {!financeListQuery.isLoading && !financeListQuery.error && (financeListQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="Không có dòng đối soát" description="Bộ lọc hiện tại không trả về dữ liệu tài chính nào." />
        ) : null}
        {(financeListQuery.data?.data.length ?? 0) > 0 ? (
          <Table<FinancialReconciliationRow>
            rowKey={(row) => row.reservation.reservation_id}
            dataSource={financeListQuery.data?.data ?? []}
            rowClassName={(row) => (row.reservation.reservation_id === selectedReservationRowId ? 'staff-row-selected' : '')}
            onRow={(row) => ({
              onClick: () => updateUrlState({ selectedReservationId: row.reservation.reservation_id }),
            })}
            pagination={{
              current: financeListQuery.data?.meta?.page ?? page,
              pageSize: financeListQuery.data?.meta?.per_page ?? pageSize,
              total: financeListQuery.data?.meta?.total ?? 0,
              showSizeChanger: false,
              onChange: (nextPage) => updateUrlState({ page: nextPage }),
            }}
            columns={[
              {
                title: 'Đặt bàn',
                render: (_, row) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{row.reservation.reservation_code}</Typography.Text>
                    <Typography.Text type="secondary">
                      {row.reservation.customer.full_name ?? row.reservation.customer.phone ?? 'Khách vãng lai / chưa rõ'}
                    </Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Trạng thái',
                render: (_, row) => (
                  <Space wrap size={6}>
                    <StatusChip label={row.reservation.status} tone={reservationTone(row.reservation.status)} />
                    <StatusChip label={row.reservation.deposit_status} tone={paymentTone(row.reservation.deposit_status)} />
                  </Space>
                ),
              },
              {
                title: 'Quyết toán',
                render: (_, row) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>
                      Tổng bill: {formatMoney(row.reconciliation.final_bill_amount, row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                    <Typography.Text type="secondary">
                      Thực thu: {formatMoney(row.payment_summary.net_paid_amount, row.payment_summary.currency.currency ?? row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                    <Typography.Text type={row.flags.has_bill_outstanding ? 'warning' : undefined}>
                      Còn thiếu: {formatMoney(row.reconciliation.bill_outstanding_amount, row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Cờ cảnh báo',
                render: (_, row) => (
                  <Space wrap size={6}>
                    {financeFlagLabels(row).map((label) => (
                      <StatusChip
                        key={`${row.reservation.reservation_id}-${label}`}
                        label={label}
                        tone={flagTone(label)}
                      />
                    ))}
                  </Space>
                ),
              },
              {
                title: 'Hoạt động gần nhất',
                width: 180,
                render: (_, row) => formatDateTime(row.payment_summary.last_payment_activity_at),
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Chi tiết đặt bàn" className="staff-workspace-detail-card">
        {!selectedReservationRowId ? (
          <EmptyBlock title="Chưa chọn đặt bàn" description="Chọn một dòng để xem lịch sử thanh toán, trạng thái hóa đơn và cờ đối soát." />
        ) : financeDetailQuery.isLoading ? (
          <InlineLoading tip="Đang tải chi tiết tài chính..." />
        ) : financeDetailQuery.error ? (
          <InlineError message={formatApiError(financeDetailQuery.error, 'Không thể tải chi tiết tài chính.')} />
        ) : currentDetail ? (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Đặt bàn">
                <Space wrap size={6}>
                  <Typography.Text strong>{currentDetail.reservation.reservation_code}</Typography.Text>
                  <StatusChip label={currentDetail.reservation.status} tone={reservationTone(currentDetail.reservation.status)} />
                  <StatusChip label={currentDetail.reservation.deposit_status} tone={paymentTone(currentDetail.reservation.deposit_status)} />
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label="Khách">
                {currentDetail.reservation.customer.full_name ?? currentDetail.reservation.customer.email ?? currentDetail.reservation.customer.phone ?? 'Không rõ'}
              </Descriptions.Item>
              <Descriptions.Item label="Lệch cọc">
                {formatMoney(currentDetail.summary.reconciliation.deposit_sync_gap_amount, currentDetail.reservation.bill_currency ?? 'VND')}
              </Descriptions.Item>
              <Descriptions.Item label="Hoàn quá tiền">
                {formatMoney(currentDetail.summary.payment_summary.over_refunded_amount, currentDetail.reservation.bill_currency ?? 'VND')}
              </Descriptions.Item>
              <Descriptions.Item label="Hoạt động thanh toán gần nhất">
                {formatDateTime(currentDetail.summary.payment_summary.last_payment_activity_at)}
              </Descriptions.Item>
            </Descriptions>

            {financeFlagLabels(currentDetail.summary).length > 0 ? (
              <Card size="small" title="Cờ cảnh báo" className="staff-workspace-detail-subcard">
                <Space wrap size={6}>
                  {financeFlagLabels(currentDetail.summary).map((label) => (
                    <StatusChip key={label} label={label} tone={flagTone(label)} />
                  ))}
                </Space>
              </Card>
            ) : null}

            <Card size="small" title="Hóa đơn" className="staff-workspace-detail-subcard">
              {financeInvoiceQuery.isLoading ? (
                <InlineLoading tip="Đang tải trạng thái hóa đơn..." />
              ) : currentInvoice ? (
                <InvoiceBlock envelope={currentInvoice} />
              ) : isApiStatus(financeInvoiceQuery.error, 404) ? (
                <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                  <Typography.Text type="secondary">
                    Đặt bàn này chưa có hóa đơn tài chính được phát hành.
                  </Typography.Text>
                  <Button
                    type="primary"
                    onClick={() => void handleIssueInvoice()}
                    disabled={!canIssueInvoiceForRow(currentDetail.summary)}
                    loading={issueInvoiceMutation.isPending}
                  >
                    Phát hành hóa đơn
                  </Button>
                </Space>
              ) : financeInvoiceQuery.error ? (
                <InlineError message={formatApiError(financeInvoiceQuery.error, 'Không thể tải trạng thái hóa đơn.')} />
              ) : (
                <EmptyBlock title="Chưa đọc được trạng thái hóa đơn" description="Làm mới khung chi tiết rồi thử lại." />
              )}
            </Card>

            <Card size="small" title="Phương thức thanh toán" className="staff-workspace-detail-subcard">
              {currentDetail.method_breakdown.length === 0 ? (
                <EmptyBlock title="Chưa có phương thức thanh toán" description="Đặt bàn này chưa có giao dịch thanh toán nào được ghi nhận." />
              ) : (
                <MethodBreakdownTable rows={currentDetail.method_breakdown} />
              )}
            </Card>

            <Card size="small" title="Giao dịch thanh toán" className="staff-workspace-detail-subcard">
              {currentDetail.payments.length === 0 ? (
                <EmptyBlock title="Chưa có giao dịch" description="Đặt bàn này chưa có dòng cọc, thanh toán cuối hoặc hoàn tiền." />
              ) : (
                <PaymentsTable rows={currentDetail.payments} />
              )}
            </Card>
          </Space>
        ) : null}
      </Card>

      <Card title="Bước tiếp theo" className="staff-workspace-next-card">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            Dùng màn hình này sau thanh toán để soát chênh lệch và phát hành hóa đơn mà không cần rời khỏi không gian làm việc của nhân viên.
          </Typography.Text>
          {selectedReservationRowId && can(session, 'payment.refund') ? (
            <Button type="primary" onClick={openRefundFlow}>
              Mở bàn hoàn tiền
            </Button>
          ) : null}
          {selectedReservationRowId && can(session, 'reservation.manage') ? (
            <Button onClick={openReservationFlow}>
              Mở đặt bàn
            </Button>
          ) : null}
          <Button
            onClick={() =>
                      navigate(`${staffRoutePaths.ops.checkout}?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                tableIds: journey.tableIds,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay lại thanh toán
          </Button>
          <Button
            onClick={() =>
              navigate(`${staffRoutePaths.ops.cashierShift}?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                tableIds: journey.tableIds,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay lại ca thu ngân
          </Button>
        </Space>
      </Card>
    </Space>
  );

  return (
    <div data-testid="finance-review-page">
      <SplitWorkspace main={main} side={side} />
    </div>
  );
}

function InvoiceBlock({ envelope }: { envelope: FinanceInvoiceEnvelope }) {
  return (
    <Descriptions bordered size="small" column={1}>
      <Descriptions.Item label="Số hóa đơn">
        <Space wrap size={6}>
          <Typography.Text strong>{envelope.data.invoice.invoice_number}</Typography.Text>
          <StatusChip label={envelope.data.invoice.invoice_status} tone="success" />
        </Space>
      </Descriptions.Item>
      <Descriptions.Item label="Tổng tiền">
        {formatMoney(envelope.data.invoice.bill_amounts.total_amount, envelope.data.invoice.currency)}
      </Descriptions.Item>
      <Descriptions.Item label="Tiền thuế">
        {formatMoney(envelope.data.invoice.tax.tax_amount, envelope.data.invoice.currency)}
      </Descriptions.Item>
      <Descriptions.Item label="Phát hành lúc">
        {formatDateTime(envelope.data.invoice.issued_at)}
      </Descriptions.Item>
      <Descriptions.Item label="Người phát hành">
        {envelope.data.invoice.issued_by.full_name ?? envelope.data.invoice.issued_by.email ?? envelope.data.invoice.issued_by.user_id ?? 'Không rõ'}
      </Descriptions.Item>
    </Descriptions>
  );
}

function MethodBreakdownTable({ rows }: { rows: Array<FinancialMethodBreakdownRow> }) {
  return (
    <Table<FinancialMethodBreakdownRow>
      rowKey={(row) => `${row.payment_method}-${row.currency}`}
      pagination={false}
      size="small"
      dataSource={rows}
      columns={[
        {
          title: 'Phương thức',
          render: (_, row) => `${row.payment_method} • ${row.currency}`,
        },
        {
          title: 'Đã thu',
          render: (_, row) => formatMoney(row.captured_amount, row.currency),
        },
        {
          title: 'Đã hoàn',
          render: (_, row) => formatMoney(row.refunded_amount, row.currency),
        },
        {
          title: 'Ròng',
          render: (_, row) => formatMoney(row.net_amount, row.currency),
        },
      ]}
    />
  );
}

function PaymentsTable({ rows }: { rows: Array<FinancialPaymentRow> }) {
  return (
    <Table<FinancialPaymentRow>
      rowKey="payment_id"
      pagination={false}
      size="small"
      dataSource={rows}
      columns={[
        {
          title: 'Loại giao dịch',
          render: (_, row) => (
            <Space orientation="vertical" size={2}>
              <Typography.Text strong>{row.payment_type}</Typography.Text>
              <Typography.Text type="secondary">{row.payment_method}</Typography.Text>
            </Space>
          ),
        },
        {
          title: 'Trạng thái',
          render: (_, row) => <StatusChip label={row.status} tone={paymentTone(row.status)} />,
        },
        {
          title: 'Số tiền',
          render: (_, row) => formatMoney(row.amount, row.currency),
        },
        {
          title: 'Nguồn gốc',
          render: (_, row) => row.refund_source_payment
            ? `${row.refund_target_payment_type ?? 'Hoàn tiền'} của giao dịch #${row.refund_source_payment.payment_id}`
            : row.transaction_code ?? 'Thanh toán trực tiếp',
        },
        {
          title: 'Thời điểm',
          render: (_, row) => formatDateTime(row.paid_at ?? row.created_at),
        },
      ]}
    />
  );
}

function flagTone(label: string): 'default' | 'processing' | 'success' | 'warning' | 'error' {
  switch (label) {
    case 'Có chênh lệch':
    case 'Hoàn quá tiền':
      return 'error';
    case 'Còn thiếu':
    case 'Lệch loại tiền':
      return 'warning';
    case 'Đã quyết toán':
      return 'success';
    default:
      return 'default';
  }
}
