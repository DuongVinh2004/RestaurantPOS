import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useCallback } from 'react';
import {
  Alert,
  App,
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
} from '../../core/api/staff-api';
import {
  getFinanceInvoice,
  getFinancialReconciliationDetail,
  issueFinanceInvoice,
  listFinancialReconciliation,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatDateTime, formatMoney } from '../../core/utils/format';
import { buildJourneySearch, stripJourneySearch } from '../../core/utils/journey';
import { paymentTone, reservationTone } from '../../core/utils/status';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import {
  buildFinanceReviewSearch,
  buildFinanceQuery,
  canIssueInvoiceForRow,
  financeFlagLabels,
  readFinanceReviewUrlState,
  summarizeFinance,
  type FinanceFilterState,
} from './finance-review';

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
  { value: '', label: 'Táº¥t cáº£ tráº¡ng thÃ¡i Ä‘áº·t bÃ n' },
  { value: 'Reserved', label: 'ÄÃ£ giá»¯ chá»—' },
  { value: 'Confirmed', label: 'ÄÃ£ xÃ¡c nháº­n' },
  { value: 'CheckedIn', label: 'ÄÃ£ nháº­n bÃ n' },
  { value: 'Completed', label: 'HoÃ n táº¥t' },
  { value: 'Cancelled', label: 'ÄÃ£ há»§y' },
  { value: 'NoShow', label: 'KhÃ´ng Ä‘áº¿n' },
];

const depositStatusOptions = [
  { value: '', label: 'Táº¥t cáº£ tráº¡ng thÃ¡i cá»c' },
  { value: 'NotRequired', label: 'KhÃ´ng báº¯t buá»™c' },
  { value: 'Pending', label: 'Äang chá»' },
  { value: 'Paid', label: 'ÄÃ£ thu' },
  { value: 'Refunded', label: 'ÄÃ£ hoÃ n' },
  { value: 'PartiallyRefunded', label: 'HoÃ n má»™t pháº§n' },
  { value: 'Forfeited', label: 'Máº¥t cá»c' },
];

const discrepancyOptions = [
  { value: 'all', label: 'Táº¥t cáº£ dÃ²ng' },
  { value: 'yes', label: 'Chá»‰ dÃ²ng cÃ³ chÃªnh lá»‡ch' },
  { value: 'no', label: 'Chá»‰ dÃ²ng Ä‘Ã£ khá»›p' },
];

export function FinanceReviewPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const urlState = useMemo(() => readFinanceReviewUrlState(searchParams), [searchParams]);
  const { page, selectedReservationId, ...filters } = urlState;
  const contextReservationId = journey.reservationId ?? null;
  const selectedReservationRowId = contextReservationId ?? selectedReservationId;

  const updateUrlState = useCallback((
    patch: Partial<typeof urlState>,
    options?: { replace?: boolean },
  ) => {
    const nextSearch = buildFinanceReviewSearch(searchParams, patch);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams, urlState]);

  const query = useMemo(
    () => buildFinanceQuery(filters, page, pageSize, contextReservationId),
    [contextReservationId, filters, page],
  );
  const financeListQuery = useQuery({
    queryKey: ['finance-reconciliation', query],
    queryFn: () => listFinancialReconciliation(query),
    enabled: !!session,
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
    queryKey: ['finance-reconciliation-detail', selectedReservationRowId],
    queryFn: () => getFinancialReconciliationDetail(selectedReservationRowId as number),
    enabled: !!selectedReservationRowId,
  });
  const financeInvoiceQuery = useQuery({
    queryKey: ['finance-invoice', selectedReservationRowId],
    queryFn: () => getFinanceInvoice(selectedReservationRowId as number),
    enabled: !!selectedReservationRowId,
    retry: (failureCount, error) => !isApiStatus(error, 404) && failureCount < 1,
  });

  const currentDetail = financeDetailQuery.data?.data ?? null;
  const currentInvoice = isApiStatus(financeInvoiceQuery.error, 404)
    ? null
    : financeInvoiceQuery.data ?? null;
  const summary = useMemo(() => summarizeFinance(financeListQuery.data?.data ?? []), [financeListQuery.data?.data]);

  const issueInvoiceMutation = useMutation({
    mutationFn: async () => {
      if (!selectedReservationRowId) {
        throw new Error('Chá»n má»™t dÃ²ng Ä‘á»‘i soÃ¡t trÆ°á»›c khi phÃ¡t hÃ nh hÃ³a Ä‘Æ¡n.');
      }

      return issueFinanceInvoice(selectedReservationRowId);
    },
    onSuccess: async (envelope) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation'] }),
        queryClient.invalidateQueries({ queryKey: ['finance-reconciliation-detail', selectedReservationRowId] }),
        queryClient.invalidateQueries({ queryKey: ['finance-invoice', selectedReservationRowId] }),
        queryClient.invalidateQueries({ queryKey: ['reporting-sales'] }),
      ]);
      message.success(`ÄÃ£ phÃ¡t hÃ nh hÃ³a Ä‘Æ¡n ${envelope.data.invoice.invoice_number}.`);
    },
    onError: (error) => {
      message.error(formatApiError(error, 'KhÃ´ng thá»ƒ phÃ¡t hÃ nh hÃ³a Ä‘Æ¡n tÃ i chÃ­nh.'));
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
      title: `PhÃ¡t hÃ nh hÃ³a Ä‘Æ¡n cho Ä‘áº·t bÃ n #${selectedReservationRowId}`,
      content: 'Thao tÃ¡c nÃ y sáº½ táº¡o hoáº·c tÃ¡i sá»­ dá»¥ng hÃ³a Ä‘Æ¡n tÃ i chÃ­nh chÃ­nh thá»©c tá»« dá»¯ liá»‡u Ä‘áº·t bÃ n Ä‘Ã£ chá»‘t tiá»n. Chá»‰ tiáº¿p tá»¥c sau khi Ä‘Ã£ kiá»ƒm tra quyáº¿t toÃ¡n vÃ  thÃ´ng tin thuáº¿.',
      okText: 'PhÃ¡t hÃ nh hÃ³a Ä‘Æ¡n',
    });

    if (confirmed) {
      await issueInvoiceMutation.mutateAsync();
    }
  }

  function openReservationFlow() {
    if (!selectedReservationRowId) {
      return;
    }

    navigate(`/reservations?${buildJourneySearch({
      source: 'reservation',
      reservationId: selectedReservationRowId,
    })}`);
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Äá»‘i soÃ¡t tÃ i chÃ­nh"
        title="RÃ  soÃ¡t quyáº¿t toÃ¡n vÃ  hÃ³a Ä‘Æ¡n"
        description="DÃ¹ng mÃ n hÃ¬nh nÃ y sau khi thanh toÃ¡n hoáº·c xá»­ lÃ½ ca thu ngÃ¢n Ä‘á»ƒ soÃ¡t chÃªnh lá»‡ch, láº§n váº¿t hoÃ n tiá»n vÃ  phÃ¡t hÃ nh hÃ³a Ä‘Æ¡n tá»« dá»¯ liá»‡u Ä‘Ã£ chá»‘t bill."
        extra={(
          <>
            <Button onClick={() => financeListQuery.refetch()} loading={financeListQuery.isFetching}>
              LÃ m má»›i danh sÃ¡ch
            </Button>
            <Button
              onClick={() => {
                void financeDetailQuery.refetch();
                void financeInvoiceQuery.refetch();
              }}
              disabled={!selectedReservationRowId}
              loading={financeDetailQuery.isFetching || financeInvoiceQuery.isFetching}
            >
              LÃ m má»›i chi tiáº¿t
            </Button>
          </>
        )}
      />

      {contextReservationId ? (
        <Alert
          type="info"
          showIcon
          title={`Äang khÃ³a ngá»¯ cáº£nh tÃ i chÃ­nh theo Ä‘áº·t bÃ n #${contextReservationId}`}
          description={(
            <Space wrap>
              <Typography.Text>
                MÃ n hÃ¬nh nÃ y nháº­n ngá»¯ cáº£nh Ä‘áº·t bÃ n tá»« luá»“ng váº­n hÃ nh trÆ°á»›c Ä‘Ã³ Ä‘á»ƒ thu ngÃ¢n hoáº·c quáº£n lÃ½ soÃ¡t tá»«ng trÆ°á»ng há»£p cá»¥ thá»ƒ.
              </Typography.Text>
              <Button
                onClick={() => {
                  const nextSearch = stripJourneySearch(searchParams);
                  navigate(
                    nextSearch ? `/finance-review?${nextSearch}` : '/finance-review',
                    { replace: true },
                  );
                }}
              >
                Bá» khÃ³a ngá»¯ cáº£nh Ä‘áº·t bÃ n
              </Button>
            </Space>
          )}
        />
      ) : null}

      {branchId ? (
        <Alert
          type="warning"
          showIcon
          title="API Ä‘á»‘i soÃ¡t hiá»‡n chÆ°a lá»c theo chi nhÃ¡nh"
          description="Shell váº«n hiá»ƒn thá»‹ chi nhÃ¡nh hiá»‡n táº¡i Ä‘á»ƒ giá»¯ ngá»¯ cáº£nh thao tÃ¡c, nhÆ°ng API Ä‘á»‘i soÃ¡t bÃ¢y giá» váº«n lá»c theo thuá»™c tÃ­nh Ä‘áº·t bÃ n hoáº·c thanh toÃ¡n thay vÃ¬ `branch_id`."
        />
      ) : null}

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}>
          <Card><Statistic title="Sá»‘ dÃ²ng trang hiá»‡n táº¡i" value={financeListQuery.data?.data.length ?? 0} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card><Statistic title="Ca cÃ³ chÃªnh lá»‡ch" value={summary.discrepancyCount} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card><Statistic title="CÃ²n thiáº¿u" value={formatMoney(summary.outstandingAmount)} /></Card>
        </Col>
        <Col xs={24} md={6}>
          <Card><Statistic title="ÄÃ£ quyáº¿t toÃ¡n" value={summary.fullySettledCount} /></Card>
        </Col>
      </Row>

      <Card title="Bá»™ lá»c">
        <Row gutter={[12, 12]}>
          <Col xs={24} md={7}>
            <Input
              value={filters.reservationCode}
              placeholder="MÃ£ Ä‘áº·t bÃ n"
              onChange={(event) => updateFilter('reservationCode', event.target.value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              style={{ width: '100%' }}
              value={filters.status}
              options={reservationStatusOptions}
              onChange={(value) => updateFilter('status', value)}
            />
          </Col>
          <Col xs={24} md={5}>
            <Select
              style={{ width: '100%' }}
              value={filters.depositStatus}
              options={depositStatusOptions}
              onChange={(value) => updateFilter('depositStatus', value)}
            />
          </Col>
          <Col xs={24} md={4}>
            <Select
              style={{ width: '100%' }}
              value={filters.hasDiscrepancy}
              options={discrepancyOptions}
              onChange={(value) => updateFilter('hasDiscrepancy', value)}
            />
          </Col>
          <Col xs={24} md={3}>
            <Input
              value={filters.paymentCurrency}
              placeholder="Loáº¡i tiá»n"
              onChange={(event) => updateFilter('paymentCurrency', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input
              value={filters.cashierUserId}
              placeholder="MÃ£ thu ngÃ¢n"
              inputMode="numeric"
              onChange={(event) => updateFilter('cashierUserId', event.target.value)}
            />
          </Col>
          <Col xs={24} md={6}>
            <Input type="date" value={filters.activityFrom} onChange={(event) => updateFilter('activityFrom', event.target.value)} />
          </Col>
          <Col xs={24} md={6}>
            <Input type="date" value={filters.activityTo} onChange={(event) => updateFilter('activityTo', event.target.value)} />
          </Col>
        </Row>
      </Card>

      <Card title="DÃ²ng Ä‘á»‘i soÃ¡t">
        {financeListQuery.isLoading ? <InlineLoading tip="Äang táº£i dá»¯ liá»‡u Ä‘á»‘i soÃ¡t..." /> : null}
        {financeListQuery.error ? <InlineError message={formatApiError(financeListQuery.error, 'KhÃ´ng thá»ƒ táº£i dá»¯ liá»‡u Ä‘á»‘i soÃ¡t.')} /> : null}
        {!financeListQuery.isLoading && !financeListQuery.error && (financeListQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="KhÃ´ng cÃ³ dÃ²ng Ä‘á»‘i soÃ¡t" description="Bá»™ lá»c hiá»‡n táº¡i khÃ´ng tráº£ vá» dá»¯ liá»‡u tÃ i chÃ­nh nÃ o." />
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
                title: 'Äáº·t bÃ n',
                render: (_, row) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text strong>{row.reservation.reservation_code}</Typography.Text>
                    <Typography.Text type="secondary">
                      {row.reservation.customer.full_name ?? row.reservation.customer.phone ?? 'KhÃ¡ch vÃ£ng lai / chÆ°a rÃµ'}
                    </Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Tráº¡ng thÃ¡i',
                render: (_, row) => (
                  <Space wrap size={6}>
                    <StatusChip label={row.reservation.status} tone={reservationTone(row.reservation.status)} />
                    <StatusChip label={row.reservation.deposit_status} tone={paymentTone(row.reservation.deposit_status)} />
                  </Space>
                ),
              },
              {
                title: 'Quyáº¿t toÃ¡n',
                render: (_, row) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>
                      Tá»•ng bill: {formatMoney(row.reconciliation.final_bill_amount, row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                    <Typography.Text type="secondary">
                      Thá»±c thu: {formatMoney(row.payment_summary.net_paid_amount, row.payment_summary.currency.currency ?? row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                    <Typography.Text type={row.flags.has_bill_outstanding ? 'warning' : undefined}>
                      CÃ²n thiáº¿u: {formatMoney(row.reconciliation.bill_outstanding_amount, row.reservation.bill_currency ?? 'VND')}
                    </Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Cá» cáº£nh bÃ¡o',
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
                title: 'Hoáº¡t Ä‘á»™ng gáº§n nháº¥t',
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
      <Card title="Chi tiáº¿t Ä‘áº·t bÃ n">
        {!selectedReservationRowId ? (
          <EmptyBlock title="ChÆ°a chá»n Ä‘áº·t bÃ n" description="Chá»n má»™t dÃ²ng Ä‘á»ƒ xem lá»‹ch sá»­ thanh toÃ¡n, tráº¡ng thÃ¡i hÃ³a Ä‘Æ¡n vÃ  cá» Ä‘á»‘i soÃ¡t." />
        ) : financeDetailQuery.isLoading ? (
          <InlineLoading tip="Äang táº£i chi tiáº¿t tÃ i chÃ­nh..." />
        ) : financeDetailQuery.error ? (
          <InlineError message={formatApiError(financeDetailQuery.error, 'KhÃ´ng thá»ƒ táº£i chi tiáº¿t tÃ i chÃ­nh.')} />
        ) : currentDetail ? (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Äáº·t bÃ n">
                <Space wrap size={6}>
                  <Typography.Text strong>{currentDetail.reservation.reservation_code}</Typography.Text>
                  <StatusChip label={currentDetail.reservation.status} tone={reservationTone(currentDetail.reservation.status)} />
                  <StatusChip label={currentDetail.reservation.deposit_status} tone={paymentTone(currentDetail.reservation.deposit_status)} />
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label="KhÃ¡ch">
                {currentDetail.reservation.customer.full_name ?? currentDetail.reservation.customer.email ?? currentDetail.reservation.customer.phone ?? 'KhÃ´ng rÃµ'}
              </Descriptions.Item>
              <Descriptions.Item label="Lá»‡ch cá»c">
                {formatMoney(currentDetail.summary.reconciliation.deposit_sync_gap_amount, currentDetail.reservation.bill_currency ?? 'VND')}
              </Descriptions.Item>
              <Descriptions.Item label="HoÃ n quÃ¡ tiá»n">
                {formatMoney(currentDetail.summary.payment_summary.over_refunded_amount, currentDetail.reservation.bill_currency ?? 'VND')}
              </Descriptions.Item>
              <Descriptions.Item label="Hoáº¡t Ä‘á»™ng thanh toÃ¡n gáº§n nháº¥t">
                {formatDateTime(currentDetail.summary.payment_summary.last_payment_activity_at)}
              </Descriptions.Item>
            </Descriptions>

            {financeFlagLabels(currentDetail.summary).length > 0 ? (
              <Card size="small" title="Cá» cáº£nh bÃ¡o">
                <Space wrap size={6}>
                  {financeFlagLabels(currentDetail.summary).map((label) => (
                    <StatusChip key={label} label={label} tone={flagTone(label)} />
                  ))}
                </Space>
              </Card>
            ) : null}

            <Card size="small" title="HÃ³a Ä‘Æ¡n">
              {financeInvoiceQuery.isLoading ? (
                <InlineLoading tip="Äang táº£i tráº¡ng thÃ¡i hÃ³a Ä‘Æ¡n..." />
              ) : currentInvoice ? (
                <InvoiceBlock envelope={currentInvoice} />
              ) : isApiStatus(financeInvoiceQuery.error, 404) ? (
                <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                  <Typography.Text type="secondary">
                    Äáº·t bÃ n nÃ y chÆ°a cÃ³ hÃ³a Ä‘Æ¡n tÃ i chÃ­nh Ä‘Æ°á»£c phÃ¡t hÃ nh.
                  </Typography.Text>
                  <Button
                    type="primary"
                    onClick={() => void handleIssueInvoice()}
                    disabled={!canIssueInvoiceForRow(currentDetail.summary)}
                    loading={issueInvoiceMutation.isPending}
                  >
                    PhÃ¡t hÃ nh hÃ³a Ä‘Æ¡n
                  </Button>
                </Space>
              ) : financeInvoiceQuery.error ? (
                <InlineError message={formatApiError(financeInvoiceQuery.error, 'KhÃ´ng thá»ƒ táº£i tráº¡ng thÃ¡i hÃ³a Ä‘Æ¡n.')} />
              ) : (
                <EmptyBlock title="ChÆ°a Ä‘á»c Ä‘Æ°á»£c tráº¡ng thÃ¡i hÃ³a Ä‘Æ¡n" description="LÃ m má»›i khung chi tiáº¿t rá»“i thá»­ láº¡i." />
              )}
            </Card>

            <Card size="small" title="PhÆ°Æ¡ng thá»©c thanh toÃ¡n">
              {currentDetail.method_breakdown.length === 0 ? (
                <EmptyBlock title="ChÆ°a cÃ³ phÆ°Æ¡ng thá»©c thanh toÃ¡n" description="Äáº·t bÃ n nÃ y chÆ°a cÃ³ giao dá»‹ch thanh toÃ¡n nÃ o Ä‘Æ°á»£c ghi nháº­n." />
              ) : (
                <MethodBreakdownTable rows={currentDetail.method_breakdown} />
              )}
            </Card>

            <Card size="small" title="Giao dá»‹ch thanh toÃ¡n">
              {currentDetail.payments.length === 0 ? (
                <EmptyBlock title="ChÆ°a cÃ³ giao dá»‹ch" description="Äáº·t bÃ n nÃ y chÆ°a cÃ³ dÃ²ng cá»c, thanh toÃ¡n cuá»‘i hoáº·c hoÃ n tiá»n." />
              ) : (
                <PaymentsTable rows={currentDetail.payments} />
              )}
            </Card>
          </Space>
        ) : null}
      </Card>

      <Card title="BÆ°á»›c tiáº¿p theo">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Typography.Text type="secondary">
            DÃ¹ng mÃ n hÃ¬nh nÃ y sau thanh toÃ¡n Ä‘á»ƒ soÃ¡t chÃªnh lá»‡ch vÃ  phÃ¡t hÃ nh hÃ³a Ä‘Æ¡n mÃ  khÃ´ng cáº§n rá»i khá»i khÃ´ng gian lÃ m viá»‡c cá»§a nhÃ¢n viÃªn.
          </Typography.Text>
          {selectedReservationRowId && can(session, 'reservation.manage') ? (
            <Button onClick={openReservationFlow}>
              Má»Ÿ Ä‘áº·t bÃ n
            </Button>
          ) : null}
          <Button
            onClick={() =>
              navigate(`/checkout?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay láº¡i thanh toÃ¡n
          </Button>
          <Button
            onClick={() =>
              navigate(`/cashier-shift?${buildJourneySearch({
                source: journey.source ?? 'checkout',
                tableId: journey.tableId,
                reservationId: journey.reservationId,
                reservationRowVersion: journey.reservationRowVersion,
                orderId: journey.orderId,
                orderRowVersion: journey.orderRowVersion,
                stationId: journey.stationId,
              })}`)
            }
          >
            Quay láº¡i ca thu ngÃ¢n
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
      <Descriptions.Item label="Sá»‘ hÃ³a Ä‘Æ¡n">
        <Space wrap size={6}>
          <Typography.Text strong>{envelope.data.invoice.invoice_number}</Typography.Text>
          <StatusChip label={envelope.data.invoice.invoice_status} tone="success" />
        </Space>
      </Descriptions.Item>
      <Descriptions.Item label="Tá»•ng tiá»n">
        {formatMoney(envelope.data.invoice.bill_amounts.total_amount, envelope.data.invoice.currency)}
      </Descriptions.Item>
      <Descriptions.Item label="Tiá»n thuáº¿">
        {formatMoney(envelope.data.invoice.tax.tax_amount, envelope.data.invoice.currency)}
      </Descriptions.Item>
      <Descriptions.Item label="PhÃ¡t hÃ nh lÃºc">
        {formatDateTime(envelope.data.invoice.issued_at)}
      </Descriptions.Item>
      <Descriptions.Item label="NgÆ°á»i phÃ¡t hÃ nh">
        {envelope.data.invoice.issued_by.full_name ?? envelope.data.invoice.issued_by.email ?? envelope.data.invoice.issued_by.user_id ?? 'KhÃ´ng rÃµ'}
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
          title: 'PhÆ°Æ¡ng thá»©c',
          render: (_, row) => `${row.payment_method} â€¢ ${row.currency}`,
        },
        {
          title: 'ÄÃ£ thu',
          render: (_, row) => formatMoney(row.captured_amount, row.currency),
        },
        {
          title: 'ÄÃ£ hoÃ n',
          render: (_, row) => formatMoney(row.refunded_amount, row.currency),
        },
        {
          title: 'RÃ²ng',
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
          title: 'Loáº¡i giao dá»‹ch',
          render: (_, row) => (
            <Space orientation="vertical" size={2}>
              <Typography.Text strong>{row.payment_type}</Typography.Text>
              <Typography.Text type="secondary">{row.payment_method}</Typography.Text>
            </Space>
          ),
        },
        {
          title: 'Tráº¡ng thÃ¡i',
          render: (_, row) => <StatusChip label={row.status} tone={paymentTone(row.status)} />,
        },
        {
          title: 'Sá»‘ tiá»n',
          render: (_, row) => formatMoney(row.amount, row.currency),
        },
        {
          title: 'Nguá»“n gá»‘c',
          render: (_, row) => row.refund_source_payment
            ? `${row.refund_target_payment_type ?? 'HoÃ n tiá»n'} cá»§a giao dá»‹ch #${row.refund_source_payment.payment_id}`
            : row.transaction_code ?? 'Thanh toÃ¡n trá»±c tiáº¿p',
        },
        {
          title: 'Thá»i Ä‘iá»ƒm',
          render: (_, row) => formatDateTime(row.paid_at ?? row.created_at),
        },
      ]}
    />
  );
}

function flagTone(label: string): 'default' | 'processing' | 'success' | 'warning' | 'error' {
  switch (label) {
    case 'CÃ³ chÃªnh lá»‡ch':
    case 'HoÃ n quÃ¡ tiá»n':
      return 'error';
    case 'CÃ²n thiáº¿u':
    case 'Lá»‡ch loáº¡i tiá»n':
      return 'warning';
    case 'ÄÃ£ quyáº¿t toÃ¡n':
      return 'success';
    default:
      return 'default';
  }
}

