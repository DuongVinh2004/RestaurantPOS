import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
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
  Tabs,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { StaffConversationCollectionEnvelope } from '../../core/api/sdk';
import {
  addConversationInternalNote,
  getConversationDetail,
  listConversations,
  sendConversationOutboundReply,
  takeOverConversation,
  unassignConversation,
} from '../../core/api/staff-api';
import { formatApiError, isApiStatus } from '../../core/api/errors';
import { can } from '../../core/permissions/capabilities';
import { formatDateTime, humanizeCode } from '../../core/utils/format';
import { buildJourneySearch } from '../../core/utils/journey';
import { conversationTone } from '../../core/utils/status';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { EmptyBlock, InlineError, InlineLoading } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import {
  assignmentAgentLabel,
  buildConversationInboxSearch,
  type ConversationAssignmentFilter,
  type ConversationChannelFilter,
  type ConversationInboxTab,
  type ConversationInboxUrlState,
  type ConversationStatusFilter,
  conversationBranchLabel,
  conversationCustomerLabel,
  conversationReservationCode,
  conversationReservationId,
  conversationSummaryStats,
  conversationTitle,
  conversationWaitingLabel,
  conversationWaitingListId,
  outboundReplyState,
  readConversationInboxUrlState,
} from './conversation-inbox';
import { AiAssistPanel, HistoryPanel, MessageThread } from './ConversationInboxPanels';

const statusOptions = [
  { value: 'all', label: 'Tất cả trạng thái' },
  { value: 'Open', label: 'Đang mở' },
  { value: 'Pending', label: 'Đang chờ' },
  { value: 'Closed', label: 'Đã đóng' },
  { value: 'Spam', label: 'Tin rác' },
] satisfies Array<{ value: ConversationStatusFilter; label: string }>;

const assignmentOptions = [
  { value: 'all', label: 'Tất cả phân công' },
  { value: 'assigned', label: 'Đã phân công' },
  { value: 'unassigned', label: 'Chưa phân công' },
  { value: 'mine', label: 'Của tôi' },
] satisfies Array<{ value: ConversationAssignmentFilter; label: string }>;

const channelOptions = [
  { value: 'all', label: 'Tất cả kênh' },
  { value: 'WebChat', label: 'Web chat' },
  { value: 'Facebook', label: 'Facebook' },
  { value: 'Zalo', label: 'Zalo' },
  { value: 'Whatsapp', label: 'WhatsApp' },
  { value: 'Instagram', label: 'Instagram' },
  { value: 'Line', label: 'LINE' },
  { value: 'Other', label: 'Khác' },
] satisfies Array<{ value: ConversationChannelFilter; label: string }>;

const pageSize = 15;
const detailQueryDefaults = {
  message_limit: 20,
  event_limit: 12,
  include_closed_assignments: true,
} satisfies Parameters<typeof getConversationDetail>[1];

export function ConversationInboxPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const confirmAction = useConfirmAction();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const [searchDraft, setSearchDraft] = useState('');
  const [noteDraft, setNoteDraft] = useState('');
  const [replyDraft, setReplyDraft] = useState('');
  const currentStaffUserId = session?.user?.user_id ?? null;

  const urlState = useMemo(() => readConversationInboxUrlState(searchParams), [searchParams]);
  const statusFilter = urlState.status;
  const assignmentFilter = urlState.assignment;
  const channelFilter = urlState.channel;
  const searchTerm = urlState.q;
  const page = urlState.page;
  const selectedConversationId = urlState.conversationId;
  const activeTab = urlState.tab;

  useEffect(() => {
    setSearchDraft(searchTerm);
  }, [searchTerm]);

  useEffect(() => {
    setNoteDraft('');
    setReplyDraft('');
  }, [selectedConversationId]);

  const updateUrlState = useCallback((
    patch: Partial<ConversationInboxUrlState>,
    options?: { replace?: boolean },
  ) => {
    const nextSearch = buildConversationInboxSearch(searchParams, patch);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams]);

  const inboxQuery = useQuery({
    queryKey: ['conversations', branchId, statusFilter, assignmentFilter, channelFilter, searchTerm, page],
    queryFn: () =>
      listConversations({
        branch_id: branchId ?? undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        assignment_state: assignmentFilter === 'all' ? undefined : assignmentFilter,
        channel: channelFilter === 'all' ? undefined : channelFilter,
        q: searchTerm || undefined,
        sort_by: 'latest_activity',
        sort_dir: 'desc',
        page,
        per_page: pageSize,
      }),
    enabled: !!session,
  });

  useEffect(() => {
    const currentRows = inboxQuery.data?.data ?? [];

    if (currentRows.length === 0) {
      if (selectedConversationId !== null) {
        updateUrlState({ conversationId: null }, { replace: true });
      }
      return;
    }

    if (!selectedConversationId || !currentRows.some((item) => item.conversation_id === selectedConversationId)) {
      updateUrlState({ conversationId: currentRows[0].conversation_id }, { replace: true });
    }
  }, [inboxQuery.data?.data, selectedConversationId, updateUrlState]);

  const selectedConversation = useMemo(
    () => inboxQuery.data?.data.find((item) => item.conversation_id === selectedConversationId) ?? null,
    [inboxQuery.data?.data, selectedConversationId],
  );
  const activeConversationId = selectedConversation?.conversation_id ?? null;

  const detailQuery = useQuery({
    queryKey: ['conversation-detail', activeConversationId],
    queryFn: () => getConversationDetail(activeConversationId as string, detailQueryDefaults),
    enabled: !!activeConversationId,
  });

  const detailConversation = detailQuery.data?.data.conversation ?? selectedConversation;
  const replyState = outboundReplyState(detailQuery.data?.data);
  const summaryStats = conversationSummaryStats(inboxQuery.data?.meta?.summary);
  const linkedReservationId = detailConversation ? conversationReservationId(detailConversation) : null;
  const linkedReservationCode = detailConversation ? conversationReservationCode(detailConversation) : null;
  const linkedWaitingListId = detailConversation ? conversationWaitingListId(detailConversation) : null;
  const linkedWaitingListLabel = detailConversation ? conversationWaitingLabel(detailConversation) : null;
  const currentAssignment = detailConversation?.active_assignment ?? null;
  const canTakeOver = !!activeConversationId && currentAssignment?.agent_user_id !== currentStaffUserId;
  const canUnassign = !!activeConversationId && currentAssignment?.agent_user_id === currentStaffUserId;

  const takeOverMutation = useMutation({
    mutationFn: () => takeOverConversation(activeConversationId as string),
  });
  const unassignMutation = useMutation({
    mutationFn: () => unassignConversation(activeConversationId as string),
  });
  const addNoteMutation = useMutation({
    mutationFn: (draft: string) =>
      addConversationInternalNote(activeConversationId as string, {
        message_text: draft,
        related_reservation_id: linkedReservationId ?? undefined,
      }),
  });
  const replyMutation = useMutation({
    mutationFn: (draft: string) =>
      sendConversationOutboundReply(activeConversationId as string, {
        message_text: draft,
        related_reservation_id: linkedReservationId ?? undefined,
      }),
  });

  async function refreshInbox(conversationId = activeConversationId) {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['conversations'] }),
      conversationId
        ? queryClient.invalidateQueries({ queryKey: ['conversation-detail', conversationId] })
        : Promise.resolve(),
    ]);
  }

  async function handleTakeOver() {
    if (!activeConversationId) {
      return;
    }

    try {
      await takeOverMutation.mutateAsync();
      await refreshInbox(activeConversationId);
      message.success(`Đã nhận xử lý hội thoại ${activeConversationId}.`);
    } catch (error) {
      message.error(formatApiError(error, 'Không thể nhận xử lý hội thoại.'));
      if (isApiStatus(error, 409)) {
        await refreshInbox(activeConversationId);
      }
    }
  }

  async function handleUnassign() {
    if (!activeConversationId) {
      return;
    }

    const confirmed = await confirmAction({
      title: `Nhả hội thoại ${activeConversationId}?`,
      content: 'Chỉ dùng khi hội thoại này cần được trả về hàng chờ chung cho nhân viên khác tiếp nhận.',
      okText: 'Nhả hội thoại',
      danger: true,
    });

    if (!confirmed) {
      return;
    }

    try {
      await unassignMutation.mutateAsync();
      await refreshInbox(activeConversationId);
      message.success(`Đã trả hội thoại ${activeConversationId} về hàng chờ.`);
    } catch (error) {
      message.error(formatApiError(error, 'Không thể bỏ phân công hội thoại.'));
      if (isApiStatus(error, 409)) {
        await refreshInbox(activeConversationId);
      }
    }
  }

  async function handleAddNote() {
    const draft = noteDraft.trim();
    if (!activeConversationId || draft === '') {
      return;
    }

    try {
      await addNoteMutation.mutateAsync(draft);
      setNoteDraft('');
      await refreshInbox(activeConversationId);
      message.success('Đã thêm ghi chú nội bộ.');
    } catch (error) {
      message.error(formatApiError(error, 'Không thể thêm ghi chú nội bộ.'));
      if (isApiStatus(error, 409)) {
        await refreshInbox(activeConversationId);
      }
    }
  }

  async function handleReply() {
    const draft = replyDraft.trim();
    if (!activeConversationId || draft === '' || !replyState.canSend) {
      return;
    }

    const confirmed = await confirmAction({
      title: 'Đưa phản hồi ra ngoài vào hàng chờ?',
      content: 'Thao tác này chỉ tạo phản hồi gửi ra ngoài khi capability ở phần chi tiết cho biết việc gửi là được hỗ trợ.',
      okText: 'Xếp hàng phản hồi',
    });

    if (!confirmed) {
      return;
    }

    try {
      await replyMutation.mutateAsync(draft);
      setReplyDraft('');
      await refreshInbox(activeConversationId);
      message.success('Đã xếp hàng phản hồi ra ngoài.');
    } catch (error) {
      message.error(formatApiError(error, 'Không thể xếp hàng phản hồi ra ngoài.'));
      if (isApiStatus(error, 409)) {
        await refreshInbox(activeConversationId);
      }
    }
  }

  function openLinkedReservation() {
    if (!linkedReservationId) {
      return;
    }

    setReservationContext({
      reservationId: linkedReservationId,
      source: 'reservation',
    });
    navigate(`/reservations?${buildJourneySearch({
      source: 'reservation',
      reservationId: linkedReservationId,
    })}`);
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Hộp thư hội thoại"
        title="Hàng chờ hội thoại vận hành"
        description="Bộ lọc, phân trang, hội thoại đang mở và tab chi tiết đều nằm trên URL của màn hình này. Nhân viên có thể làm mới, quay lại hoặc chia sẻ liên kết nội bộ mà không làm mất ngữ cảnh triage."
        extra={(
          <>
            <Select
              aria-label="Lọc theo trạng thái hội thoại"
              style={{ width: 150 }}
              value={statusFilter}
              options={statusOptions}
              onChange={(value) => updateUrlState({ status: value, page: 1, conversationId: null })}
            />
            <Select
              aria-label="Lọc theo trạng thái phân công"
              style={{ width: 160 }}
              value={assignmentFilter}
              options={assignmentOptions}
              onChange={(value) => updateUrlState({ assignment: value, page: 1, conversationId: null })}
            />
            <Select
              aria-label="Lọc theo kênh hội thoại"
              style={{ width: 150 }}
              value={channelFilter}
              options={channelOptions}
              onChange={(value) => updateUrlState({ channel: value, page: 1, conversationId: null })}
            />
            <Input.Search
              aria-label="Tìm kiếm trong hộp thư hội thoại"
              allowClear
              value={searchDraft}
              placeholder="Khách, đặt bàn, số điện thoại…"
              style={{ width: 260 }}
              onChange={(event) => {
                const nextValue = event.target.value;
                setSearchDraft(nextValue);
                if (nextValue === '') {
                  updateUrlState({ q: '', page: 1, conversationId: null });
                }
              }}
              onSearch={(value) => {
                const nextValue = value.trim();
                setSearchDraft(value);
                updateUrlState({ q: nextValue, page: 1, conversationId: null });
              }}
            />
            <Button onClick={() => inboxQuery.refetch()} loading={inboxQuery.isFetching}>Làm mới hộp thư</Button>
          </>
        )}
      />

      <Alert
        type={branchId ? 'info' : 'warning'}
        showIcon
        title={branchId ? `Đang triage theo chi nhánh #${branchId}` : 'Đang xem tất cả chi nhánh được phép'}
        description={branchId
          ? 'Danh sách hiện đang lấy theo branch context của shell. Nếu cần đổi phạm vi, hãy chuyển chi nhánh ở shell để URL và dữ liệu tiếp tục khớp nhau.'
          : 'Shell chưa giữ một branch context rõ ràng, nên danh sách có thể trải qua nhiều chi nhánh được backend cho phép.'}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}><Card><Statistic title="Tổng số" value={summaryStats.total} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Đã phân công" value={summaryStats.assigned} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Chưa phân công" value={summaryStats.unassigned} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Của tôi" value={summaryStats.mine} /></Card></Col>
      </Row>

      <Card title="Hàng chờ hội thoại" extra={activeConversationId ? `Đang mở ${activeConversationId}` : 'Chưa khóa hội thoại'}>
        {inboxQuery.isLoading ? <InlineLoading tip="Đang tải hộp thư hội thoại..." /> : null}
        {inboxQuery.error ? <InlineError message={formatApiError(inboxQuery.error, 'Không thể tải hộp thư hội thoại.')} /> : null}
        {!inboxQuery.isLoading && !inboxQuery.error && (inboxQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="Không có hội thoại" description="Bộ lọc hiện tại không trả về dòng hộp thư nào." />
        ) : null}
        {(inboxQuery.data?.data.length ?? 0) > 0 ? (
          <Table<StaffConversationCollectionEnvelope['data'][number]>
            rowKey="conversation_id"
            dataSource={inboxQuery.data?.data ?? []}
            rowClassName={(entry) => (entry.conversation_id === selectedConversationId ? 'staff-row-selected' : '')}
            pagination={{
              current: inboxQuery.data?.meta?.current_page ?? page,
              pageSize: inboxQuery.data?.meta?.per_page ?? pageSize,
              total: inboxQuery.data?.meta?.total ?? 0,
              showSizeChanger: false,
              onChange: (nextPage) => updateUrlState({ page: nextPage, conversationId: null }),
            }}
            columns={[
              {
                title: 'Hội thoại',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Button
                      type="link"
                      className="staff-link-button"
                      aria-pressed={entry.conversation_id === selectedConversationId}
                      onClick={() => updateUrlState({ conversationId: entry.conversation_id })}
                    >
                      {conversationTitle(entry)}
                    </Button>
                    <Typography.Text type="secondary">{conversationCustomerLabel(entry)}</Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Triage',
                render: (_, entry) => (
                  <Space orientation="vertical" size={6}>
                    <Space wrap size={4}>
                      <StatusChip label={entry.status} tone={conversationTone(entry.status)} />
                      <StatusChip label={entry.channel} tone="processing" />
                      {entry.assignment_state.is_mine ? <StatusChip label="Của tôi" tone="success" /> : null}
                      {entry.assignment_state.is_unassigned ? <StatusChip label="Chưa phân công" tone="warning" /> : null}
                    </Space>
                    <Typography.Text type="secondary">
                      {assignmentAgentLabel(entry.active_assignment)}
                    </Typography.Text>
                  </Space>
                ),
              },
              {
                title: 'Hoạt động gần nhất',
                render: (_, entry) => (
                  <Space orientation="vertical" size={2}>
                    <Typography.Text>{formatDateTime(entry.latest_activity_at ?? entry.created_at)}</Typography.Text>
                    <Typography.Paragraph type="secondary" style={{ marginBottom: 0, maxWidth: 320 }} ellipsis={{ rows: 2 }}>
                      {entry.latest_message?.message_text ?? 'Chưa có tin nhắn nào.'}
                    </Typography.Paragraph>
                  </Space>
                ),
              },
              {
                title: 'Tác vụ',
                render: (_, entry) => (
                  <Button
                    type={entry.conversation_id === selectedConversationId ? 'primary' : 'default'}
                    onClick={() => updateUrlState({ conversationId: entry.conversation_id })}
                  >
                    Tập trung
                  </Button>
                ),
              },
            ]}
          />
        ) : null}
      </Card>
    </Space>
  );

  const side = (
    <Card title="Chi tiết hội thoại">
      {!activeConversationId ? (
        <EmptyBlock title="Chưa chọn hội thoại" description="Chọn một hội thoại trong danh sách để khóa ngữ cảnh chi tiết, lịch sử phân công và các thao tác nội bộ." />
      ) : detailQuery.isLoading ? (
        <InlineLoading tip="Đang tải chi tiết hội thoại..." />
      ) : detailQuery.error ? (
        <InlineError message={formatApiError(detailQuery.error, 'Không thể tải chi tiết hội thoại.')} extra={<Button onClick={() => detailQuery.refetch()}>Thử lại</Button>} />
      ) : detailConversation ? (
        <Space orientation="vertical" size={16} style={{ width: '100%' }}>
          <Space orientation="vertical" size={4} style={{ width: '100%' }}>
            <Typography.Title level={4} style={{ margin: 0 }}>{conversationTitle(detailConversation)}</Typography.Title>
            <Typography.Text type="secondary">{conversationBranchLabel(detailConversation)}</Typography.Text>
          </Space>

          <Space wrap size={6}>
            <StatusChip label={detailConversation.status} tone={conversationTone(detailConversation.status)} />
            <StatusChip label={detailConversation.channel} tone="processing" />
            {detailConversation.intent_detected ? <StatusChip label={humanizeCode(detailConversation.intent_detected)} tone="default" /> : null}
            {currentAssignment?.agent_user_id === currentStaffUserId ? (
              <StatusChip label="Đang giao cho tôi" tone="success" />
            ) : currentAssignment ? (
              <StatusChip label="Đang giao cho nhân viên khác" tone="warning" />
            ) : (
              <StatusChip label="Hàng chờ chung" tone="default" />
            )}
          </Space>

          <Alert
            type="info"
            showIcon
            title="Ownership, liên kết nghiệp vụ và phản hồi ra ngoài được tách riêng"
            description="Nhận xử lý và trả hàng chờ chỉ thay đổi ownership. Mở đặt bàn hoặc khách chờ chỉ điều hướng sang luồng liên quan. Phản hồi ra ngoài luôn phụ thuộc vào capability trong detail envelope của chính hội thoại này."
          />

          <div className="staff-action-row">
            {canTakeOver ? <Button type="primary" onClick={() => void handleTakeOver()} loading={takeOverMutation.isPending}>Nhận xử lý</Button> : null}
            {canUnassign ? <Button danger onClick={() => void handleUnassign()} loading={unassignMutation.isPending}>Trả về hàng chờ</Button> : null}
            {linkedReservationId && can(session, 'reservation.manage') ? <Button onClick={openLinkedReservation}>Mở đặt bàn</Button> : null}
            {linkedWaitingListId && can(session, 'waiting_list.manage') ? <Button onClick={() => navigate('/waiting-list')}>Mở danh sách chờ</Button> : null}
          </div>

          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Khách">{conversationCustomerLabel(detailConversation)}</Descriptions.Item>
            <Descriptions.Item label="Người đang phụ trách">{assignmentAgentLabel(currentAssignment)}</Descriptions.Item>
            <Descriptions.Item label="Liên kết đặt bàn">{linkedReservationCode ?? (linkedReservationId ? `Đặt bàn #${linkedReservationId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Liên kết khách chờ">{linkedWaitingListLabel ?? (linkedWaitingListId ? `Khách chờ #${linkedWaitingListId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Hoạt động gần nhất">{formatDateTime(detailConversation.latest_activity_at ?? detailConversation.created_at)}</Descriptions.Item>
            <Descriptions.Item label="Số lượng">
              <Space wrap size={8}>
                <StatusChip label={`${detailConversation.counts.messages} tin nhắn`} />
                <StatusChip label={`${detailConversation.counts.internal_notes} ghi chú`} tone="warning" />
                <StatusChip label={`${detailConversation.counts.events} sự kiện`} tone="processing" />
                <StatusChip label={`${detailConversation.counts.analyses} phân tích`} tone="success" />
              </Space>
            </Descriptions.Item>
          </Descriptions>

          <Alert
            type={replyState.canSend ? 'success' : 'warning'}
            showIcon
            title={replyState.canSend ? 'Luồng phản hồi ra ngoài đang sẵn sàng cho hội thoại này.' : 'Phản hồi ra ngoài đang bị khóa.'}
            description={describeOutboundReplyState(replyState)}
          />

          <Tabs
            activeKey={activeTab}
            onChange={(key) => updateUrlState({ tab: key as ConversationInboxTab }, { replace: true })}
            items={[
              { key: 'messages', label: `Tin nhắn (${detailQuery.data?.data.messages.length ?? 0})`, children: <MessageThread messages={detailQuery.data?.data.messages ?? []} /> },
              { key: 'ai', label: 'AI hỗ trợ', children: <AiAssistPanel aiAssist={detailQuery.data?.data.ai_assist} /> },
              { key: 'history', label: 'Lịch sử', children: <HistoryPanel assignments={detailQuery.data?.data.assignment_history ?? []} events={detailQuery.data?.data.events ?? []} /> },
            ]}
          />

          <Card size="small" title="Thêm ghi chú nội bộ">
            <Space orientation="vertical" size={12} style={{ width: '100%' }}>
              <Input.TextArea value={noteDraft} rows={4} placeholder="Ghi chú cho bàn giao, rủi ro thời gian hoặc theo dõi đặt bàn." onChange={(event) => setNoteDraft(event.target.value)} />
              <Button type="primary" onClick={() => void handleAddNote()} disabled={noteDraft.trim() === ''} loading={addNoteMutation.isPending}>Thêm ghi chú</Button>
            </Space>
          </Card>

          <Card size="small" title="Xếp hàng phản hồi ra ngoài">
            <Space orientation="vertical" size={12} style={{ width: '100%' }}>
              <Typography.Text type="secondary">Thao tác này tuân theo quyền trong chi tiết hội thoại, không chỉ dựa vào quyền của phiên đăng nhập.</Typography.Text>
              <Input.TextArea value={replyDraft} rows={4} placeholder="Nội dung phản hồi cần gửi cho khách khi kênh hiện tại cho phép." onChange={(event) => setReplyDraft(event.target.value)} disabled={!replyState.canSend} />
              <Button type="primary" onClick={() => void handleReply()} disabled={!replyState.canSend || replyDraft.trim() === ''} loading={replyMutation.isPending}>Xếp hàng phản hồi</Button>
            </Space>
          </Card>
        </Space>
      ) : null}
    </Card>
  );

  return <SplitWorkspace main={main} side={side} />;
}

function describeOutboundReplyState(state: ReturnType<typeof outboundReplyState>): string {
  if (state.canSend) {
    const parts = ['Backend hiện cho phép phiên nhân viên này xếp hàng phản hồi ra ngoài.'];
    if (state.channel) {
      parts.push(`Kênh: ${state.channel}.`);
    }
    if (state.deliveryMode) {
      parts.push(`Chế độ gửi: ${state.deliveryMode}.`);
    }
    if (state.recipientMasked) {
      parts.push(`Người nhận: ${state.recipientMasked}.`);
    }
    return parts.join(' ');
  }

  if (state.reasonCode) {
    return `Phản hồi ra ngoài đang bị khóa vì backend trả về trạng thái ${humanizeCode(state.reasonCode)}.`;
  }

  return 'Phản hồi ra ngoài đang bị khóa vì chi tiết hội thoại chưa cho phép thao tác này.';
}
