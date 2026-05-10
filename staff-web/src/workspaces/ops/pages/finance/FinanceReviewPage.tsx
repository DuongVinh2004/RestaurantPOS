import { useEffect, useMemo } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { Button, Card, Descriptions, Input, Select, Space, Statistic, Table, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { buildJourneySearch, type JourneyContext } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useJourneyContext } from '../../../../app/router/useJourneyContext';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import {
  canIssueInvoiceForRow,
  buildFinanceQuery,
  buildFinanceReviewSearch,
  financeDateRangeError,
  financeFlagLabels,
  readFinanceMetric,
  readFinanceReviewUrlState,
  summarizeFinance,
  type FinanceReviewUrlState,
} from '../../../../domains/finance/finance-review';
import type { StaffSession } from '../../../../shared/auth/storage';
import type {
  FinanceInvoiceEnvelope,
  FinancialReconciliationDetailEnvelope,
  FinancialReconciliationRow,
} from '../../../../shared/api/staff-api';
import {
  getFinanceInvoice,
  getFinancialReconciliationDetail,
  issueFinanceInvoice,
  listFinancialReconciliation,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatMoney } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { toast } from '../../../../shared/ui/feedback/toast';
import { ApiStateBlock, ConflictState, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { buildReservationContextLabel } from '../../../journey-labels';

const perPage = 12;

const blockedBenefitsRoutes = [
  'GET /api/v1/staff/reservations/{reservation_id}/vouchers',
  'GET /api/v1/staff/reservations/{reservation_id}/loyalty',
  'GET /api/v1/staff/users/{user_id}/loyalty',
  'POST /api/v1/staff/reservations/{reservation_id}/voucher/apply',
  'POST /api/v1/staff/reservations/{reservation_id}/voucher/remove',
  'POST /api/v1/staff/reservations/{reservation_id}/loyalty/redeem',
  'POST /api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release',
  'POST /api/v1/staff/users/{user_id}/loyalty/adjust',
] as const;

export function FinanceReviewPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const urlState = useMemo(() => readFinanceReviewUrlState(searchParams), [searchParams]);
  const dateRangeError = financeDateRangeError(urlState);
  const scopedReservationId = journey.reservationId ?? null;

  const reconciliationQuery = useQuery({
    queryKey: ['finance-reconciliation', branchId, scopedReservationId, urlState],
    queryFn: () => listFinancialReconciliation(buildFinanceQuery(urlState, urlState.page, perPage, scopedReservationId, branchId)),
    enabled: !!session && !dateRangeError,
  });

  const rows = reconciliationQuery.data?.data ?? [];
  const selectedReservationId = urlState.selectedReservationId
    ?? scopedReservationId
    ?? rows[0]?.reservation.reservation_id
    ?? null;
  const selectedRow = rows.find((row) => row.reservation.reservation_id === selectedReservationId) ?? null;

  const detailQuery = useQuery({
    queryKey: ['finance-reconciliation-detail', branchId, selectedReservationId],
    queryFn: () => getFinancialReconciliationDetail(selectedReservationId as number, { branch_id: branchId ?? undefined }),
    enabled: !!session && !!selectedReservationId,
  });

  const invoiceQuery = useQuery({
    queryKey: ['finance-invoice', branchId, selectedReservationId],
    queryFn: () => getFinanceInvoice(selectedReservationId as number, { branch_id: branchId ?? undefined }),
    enabled: !!session && !!selectedReservationId,
  });

  const issueInvoiceMutation = useMutation({
    mutationFn: () => issueFinanceInvoice(selectedReservationId as number, { branch_id: branchId ?? undefined }),
    onSuccess: async () => {
      toast.success('Invoice issued.');
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['finance-invoice', branchId, selectedReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation-detail', branchId, selectedReservationId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation'] }),
      ]);
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Unable to issue invoice.'));
    },
  });

  useEffect(() => {
    setReservationContext({
      reservationId: selectedReservationId,
      reservationRowVersion: selectedRow?.reservation.row_version ?? journey.reservationRowVersion ?? null,
      label: buildReservationContextLabel(null, selectedReservationId),
      source: 'audit',
    });
  }, [
    journey.reservationRowVersion,
    selectedReservationId,
    selectedRow?.reservation.row_version,
    setReservationContext,
  ]);

  const summary = useMemo(() => summarizeFinance(rows), [rows]);
  const canOpenRefund = Boolean(session && can(session, 'payment.refund') && selectedReservationId);
  const canOpenCashierShift = Boolean(session && can(session, 'cashier.shift.manage'));
  const canOpenCheckout = Boolean(session && can(session, 'settlement.manage') && journey.orderId);
  const canIssueInvoice = Boolean(session && can(session, 'settlement.manage') && canIssueInvoiceForRow(detailQuery.data?.data.summary ?? selectedRow));

  const selectReservation = (reservationId: number) => {
    setSearchParams(
      buildFinanceReviewSearch(searchParams, {
        ...urlState,
        selectedReservationId: reservationId,
      }),
      { replace: false },
    );
  };

  const updateFilters = (patch: Partial<FinanceReviewUrlState>) => {
    setSearchParams(
      buildFinanceReviewSearch(searchParams, {
        ...urlState,
        ...patch,
        page: 1,
        selectedReservationId: null,
      }),
      { replace: true },
    );
  };

  const main = (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Finance review"
        title="Reconciliation and invoices"
        description="Review reservation settlement state, drill into payment evidence, and issue finance invoices through the canonical staff SDK contract."
        context={(
          <>
            <StatusChip label={branchId ? `Branch #${branchId}` : 'Default branch'} tone="processing" variant="severity" />
            <StatusChip label={selectedReservationId ? `Reservation #${selectedReservationId}` : 'No reservation selected'} tone={selectedReservationId ? 'processing' : 'warning'} />
            <StatusChip label={journey.orderId ? `Order #${journey.orderId}` : 'No order context'} tone={journey.orderId ? 'processing' : 'warning'} />
          </>
        )}
      />

      <Card size="small">
        <Space wrap>
          <Input.Search
            aria-label="Reservation code"
            allowClear
            placeholder="Reservation code"
            defaultValue={urlState.reservationCode}
            onSearch={(value) => updateFilters({ reservationCode: value })}
            style={{ width: 220 }}
          />
          <Select
            aria-label="Discrepancy filter"
            value={urlState.hasDiscrepancy}
            onChange={(value) => updateFilters({ hasDiscrepancy: value })}
            options={[
              { value: 'all', label: 'All rows' },
              { value: 'yes', label: 'Discrepancy only' },
              { value: 'no', label: 'No discrepancy' },
            ]}
            style={{ width: 180 }}
          />
          <Button onClick={() => void reconciliationQuery.refetch()}>Refresh</Button>
        </Space>
      </Card>

      {dateRangeError ? (
        <ConflictState
          title="Invalid finance date range"
          description={dateRangeError}
        />
      ) : null}

      <Space wrap size={12}>
        <Statistic title="Rows" value={rows.length} />
        <Statistic title="Discrepancies" value={summary.discrepancyCount} />
        <Statistic title="Outstanding" value={formatMoney(summary.outstandingAmount, currencyForRow(selectedRow))} />
        <Statistic title="Over-refund" value={formatMoney(summary.overRefundAmount, currencyForRow(selectedRow))} />
        <Statistic title="Fully settled" value={summary.fullySettledCount} />
      </Space>

      {reconciliationQuery.isLoading ? <InlineLoading tip="Loading finance reconciliation..." /> : null}
      {reconciliationQuery.error ? (
        <ApiStateBlock
          error={reconciliationQuery.error}
          fallback="Unable to load finance reconciliation."
          onRetry={() => void reconciliationQuery.refetch()}
        />
      ) : null}

      {!reconciliationQuery.isLoading && !reconciliationQuery.error ? (
        <Table
          rowKey={(row) => row.reservation.reservation_id}
          columns={financeColumns(selectReservation)}
          dataSource={rows}
          pagination={{
            current: urlState.page,
            pageSize: perPage,
            total: reconciliationQuery.data?.meta?.total ?? rows.length,
            onChange: (page) => setSearchParams(buildFinanceReviewSearch(searchParams, { ...urlState, page }), { replace: false }),
          }}
          onRow={(row) => ({
            onClick: () => selectReservation(row.reservation.reservation_id),
          })}
          rowClassName={(row) => (row.reservation.reservation_id === selectedReservationId ? 'staff-table-row-selected' : '')}
          locale={{ emptyText: <EmptyBlock title="No finance rows" description="No reservation matched the current finance filters." /> }}
        />
      ) : null}

      <Space wrap>
        <Button
          disabled={!selectedReservationId}
          onClick={() => navigate(`${staffRoutePaths.ops.reservations}?${buildOperatorJourneySearch(journey, {
            source: 'reservation',
            reservationId: selectedReservationId ?? undefined,
            reservationRowVersion: selectedRow?.reservation.row_version ?? journey.reservationRowVersion ?? undefined,
          })}`)}
        >
          Open reservation
        </Button>
        <Button
          disabled={!canOpenCheckout}
          onClick={() => navigate(`${staffRoutePaths.ops.checkout}?${buildOperatorJourneySearch(journey, {
            source: 'checkout',
          })}`)}
        >
          Open checkout
        </Button>
        <Button
          disabled={!canOpenRefund}
          onClick={() => navigate(`${staffRoutePaths.ops.refunds}?${buildOperatorJourneySearch(journey, {
            source: 'refund',
            reservationId: selectedReservationId ?? undefined,
          })}`)}
        >
          Open refund
        </Button>
        <Button
          disabled={!canOpenCashierShift}
          onClick={() => navigate(`${staffRoutePaths.ops.cashierShift}?${buildOperatorJourneySearch(journey, {
            source: journey.source ?? 'audit',
          })}`)}
        >
          Open cashier shift
        </Button>
      </Space>
    </Space>
  );

  const side = (
    <FinanceDetailPanel
      selectedReservationId={selectedReservationId}
      selectedRow={selectedRow}
      detailQuery={detailQuery}
      invoiceQuery={invoiceQuery}
      canIssueInvoice={canIssueInvoice}
      isIssuing={issueInvoiceMutation.isPending}
      onIssueInvoice={() => issueInvoiceMutation.mutate()}
    />
  );

  return (
    <div data-testid="finance-review-page">
      <SplitWorkspace main={main} side={side} />
    </div>
  );
}

export function StaffBenefitsOpsPanel({
  reservationId,
  reservationRowVersion,
  customerUserId,
  session,
  onMutationSettled,
}: {
  reservationId: number;
  reservationRowVersion: number | null;
  customerUserId: number | null;
  session: StaffSession | null;
  onMutationSettled: () => void;
}) {
  void reservationId;
  void reservationRowVersion;
  void customerUserId;
  void onMutationSettled;

  const canManageVoucher = Boolean(session && can(session, 'voucher.manage'));
  const canViewLoyalty = Boolean(session && can(session, 'loyalty.view'));
  const canRedeemLoyalty = Boolean(session && can(session, 'loyalty.redeem'));
  const canAdjustLoyalty = Boolean(session && can(session, 'loyalty.adjust'));

  if (!canManageVoucher && !canViewLoyalty && !canRedeemLoyalty && !canAdjustLoyalty) {
    return (
      <Card size="small" title="Voucher / loyalty" className="staff-workspace-detail-subcard">
        <EmptyBlock title="No benefits capability" description="This staff session does not have voucher or loyalty capabilities." />
      </Card>
    );
  }

  return (
    <Card size="small" title="Voucher / loyalty" className="staff-workspace-detail-subcard">
      <ConflictState
        title="Staff voucher and loyalty remain outside the operator contract lane"
        description="Staff-web does not execute these staff-only voucher or loyalty routes because they are still fallback-only and are not part of the frozen operator SDK promise."
        meta="Exact blocker: no official staff benefits read/write lane in frozen OpenAPI + generated SDK for these staff-prefixed routes."
        body={(
          <div className="staff-mini-list">
            {blockedBenefitsRoutes.map((route) => (
              <div key={route} className="staff-mini-list-item">
                <Typography.Text code>{route}</Typography.Text>
              </div>
            ))}
          </div>
        )}
        className="staff-inline-note"
      />
    </Card>
  );
}

function FinanceDetailPanel({
  selectedReservationId,
  selectedRow,
  detailQuery,
  invoiceQuery,
  canIssueInvoice,
  isIssuing,
  onIssueInvoice,
}: {
  selectedReservationId: number | null;
  selectedRow: FinancialReconciliationRow | null;
  detailQuery: UseQueryResult<FinancialReconciliationDetailEnvelope, Error>;
  invoiceQuery: UseQueryResult<FinanceInvoiceEnvelope, Error>;
  canIssueInvoice: boolean;
  isIssuing: boolean;
  onIssueInvoice: () => void;
}) {
  if (!selectedReservationId) {
    return (
      <EmptyBlock
        title="Select a reservation"
        description="Choose a finance row to load reconciliation detail and invoice state."
      />
    );
  }

  const detail = detailQuery.data?.data;
  const invoice = invoiceQuery.data?.data.invoice;
  const row = detail?.summary ?? selectedRow;
  const currency = currencyForRow(row);

  return (
    <Space direction="vertical" size={12} style={{ width: '100%' }}>
      <Card size="small" title={`Reservation #${selectedReservationId}`}>
        {detailQuery.isLoading ? <InlineLoading tip="Loading reconciliation detail..." /> : null}
        {detailQuery.error ? (
          <ApiStateBlock
            error={detailQuery.error}
            fallback="Unable to load reconciliation detail."
            onRetry={() => void detailQuery.refetch()}
          />
        ) : null}
        {row ? (
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Reservation code">{row.reservation.reservation_code}</Descriptions.Item>
            <Descriptions.Item label="Status">{row.reservation.status}</Descriptions.Item>
            <Descriptions.Item label="Deposit status">{row.reservation.deposit_status}</Descriptions.Item>
            <Descriptions.Item label="Net paid">{formatMoney(readFinanceMetric(row.payment_summary, 'net_paid_amount'), currency)}</Descriptions.Item>
            <Descriptions.Item label="Final bill">{formatMoney(readFinanceMetric(row.reconciliation, 'final_bill_amount'), currency)}</Descriptions.Item>
            <Descriptions.Item label="Outstanding">{formatMoney(readFinanceMetric(row.reconciliation, 'bill_outstanding_amount'), currency)}</Descriptions.Item>
            <Descriptions.Item label="Flags">
              <Space wrap>{financeFlagLabels(row).map((flag) => <Tag key={flag}>{flag}</Tag>)}</Space>
            </Descriptions.Item>
          </Descriptions>
        ) : null}
      </Card>

      <Card
        size="small"
        title="Invoice"
        extra={(
          <Button
            type="primary"
            disabled={!canIssueInvoice}
            loading={isIssuing}
            onClick={onIssueInvoice}
          >
            Issue invoice
          </Button>
        )}
      >
        {invoiceQuery.isLoading ? <InlineLoading tip="Loading invoice..." /> : null}
        {invoiceQuery.error ? (
          <ApiStateBlock
            error={invoiceQuery.error}
            fallback="Unable to load invoice."
            onRetry={() => void invoiceQuery.refetch()}
          />
        ) : null}
        {invoice ? (
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Invoice number">{readString(invoice, 'invoice_number') || 'Not issued'}</Descriptions.Item>
            <Descriptions.Item label="Status">{readString(invoice, 'invoice_status') || 'draft'}</Descriptions.Item>
            <Descriptions.Item label="Currency">{readString(invoice, 'currency') || currency}</Descriptions.Item>
            <Descriptions.Item label="Total">{formatMoney(readFinanceMetric(readRecord(invoice, 'bill_amounts'), 'total_amount'), readString(invoice, 'currency') || currency)}</Descriptions.Item>
            <Descriptions.Item label="Issued at">{formatDateTime(readNullableString(invoice, 'issued_at'))}</Descriptions.Item>
          </Descriptions>
        ) : null}
        {!invoiceQuery.isLoading && !invoiceQuery.error && !invoice ? (
          <EmptyBlock title="No invoice payload" description="The backend returned no invoice object for this reservation." />
        ) : null}
      </Card>
    </Space>
  );
}

function financeColumns(onSelect: (reservationId: number) => void): ColumnsType<FinancialReconciliationRow> {
  return [
    {
      title: 'Reservation',
      dataIndex: ['reservation', 'reservation_code'],
      render: (_value, row) => (
        <Button type="link" onClick={(event) => {
          event.stopPropagation();
          onSelect(row.reservation.reservation_id);
        }}>
          {row.reservation.reservation_code}
        </Button>
      ),
    },
    {
      title: 'Customer',
      render: (_value, row) => readString(row.reservation.customer, 'full_name') || readString(row.reservation.customer, 'phone') || 'Walk-in',
    },
    {
      title: 'Status',
      render: (_value, row) => (
        <Space wrap>
          <Tag>{row.reservation.status}</Tag>
          <Tag>{row.reservation.deposit_status}</Tag>
        </Space>
      ),
    },
    {
      title: 'Net paid',
      render: (_value, row) => formatMoney(readFinanceMetric(row.payment_summary, 'net_paid_amount'), currencyForRow(row)),
    },
    {
      title: 'Outstanding',
      render: (_value, row) => formatMoney(readFinanceMetric(row.reconciliation, 'bill_outstanding_amount'), currencyForRow(row)),
    },
    {
      title: 'Flags',
      render: (_value, row) => (
        <Space wrap>
          {financeFlagLabels(row).map((flag) => <Tag key={flag}>{flag}</Tag>)}
        </Space>
      ),
    },
  ];
}

function currencyForRow(row: FinancialReconciliationRow | null | undefined): string {
  const currencyRecord = readRecord(row?.payment_summary, 'currency');
  return readString(currencyRecord, 'currency') || row?.reservation.bill_currency || 'VND';
}

function readRecord(value: unknown, key: string): Record<string, unknown> | undefined {
  if (!isRecord(value)) {
    return undefined;
  }

  const child = value[key];
  return isRecord(child) ? child : undefined;
}

function readString(value: unknown, key: string): string | null {
  if (!isRecord(value)) {
    return null;
  }

  const raw = value[key];
  return typeof raw === 'string' && raw.trim() !== '' ? raw : null;
}

function readNullableString(value: unknown, key: string): string | null {
  if (!isRecord(value)) {
    return null;
  }

  const raw = value[key];
  return typeof raw === 'string' ? raw : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function buildOperatorJourneySearch(journey: JourneyContext, overrides: Partial<JourneyContext> = {}): string {
  return buildJourneySearch({
    source: overrides.source ?? journey.source ?? 'audit',
    tableId: overrides.tableId ?? journey.tableId ?? undefined,
    tableIds: overrides.tableIds ?? journey.tableIds ?? undefined,
    reservationId: overrides.reservationId ?? journey.reservationId ?? undefined,
    reservationRowVersion: overrides.reservationRowVersion ?? journey.reservationRowVersion ?? undefined,
    orderId: overrides.orderId ?? journey.orderId ?? undefined,
    orderRowVersion: overrides.orderRowVersion ?? journey.orderRowVersion ?? undefined,
    stationId: overrides.stationId ?? journey.stationId ?? undefined,
  });
}
