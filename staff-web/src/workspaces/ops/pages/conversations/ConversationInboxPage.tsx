import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
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
  Tabs,
  Typography,
} from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { StaffConversationCollectionEnvelope } from '../../../../shared/api/sdk';
import {
  addConversationInternalNote,
  assignConversation,
  linkConversation,
  getConversationDetail,
  listConversations,
  sendConversationOutboundReply,
  takeOverConversation,
  unassignConversation,
  unlinkConversationReservation,
  unlinkConversationWaitingList,
  updateConversationWorkflowState,
  type ConversationWorkflowState,
} from '../../../../shared/api/staff-api';
import { formatApiError, isApiStatus } from '../../../../shared/api/errors';
import { can } from '../../../../shared/auth/capabilities';
import { formatDateTime, formatRelativeAge, humanizeCode } from '../../../../shared/utils/format';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { conversationTone } from '../../../../shared/status/status';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { toast } from '../../../../shared/ui/feedback/toast';
import {
  ApiStateBlock,
  EmptyBlock,
  InlineLoading,
  InlineState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useConfirmAction } from '../../../../shared/hooks/useConfirmAction';
import {
  assignmentAgentLabel,
  buildConversationInboxSearch,
  buildConversationWaitingListPath,
  type ConversationItem,
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
} from '../../../../domains/conversations/conversation-inbox';
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
const workflowWriteOptions = [
  { value: 'Open', label: 'Đang mở' },
  { value: 'Triaged', label: 'Đã triage' },
  { value: 'PendingCustomer', label: 'Chờ khách' },
  { value: 'Resolved', label: 'Đã xử lý' },
  { value: 'Closed', label: 'Đã đóng' },
] satisfies Array<{ value: WorkflowWriteState; label: string }>;

type WorkflowWriteState = Exclude<ConversationWorkflowState, 'Assigned'>;

export function ConversationInboxPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const message = toast;
  const confirmAction = useConfirmAction();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const [searchDraft, setSearchDraft] = useState('');
  const [noteDraft, setNoteDraft] = useState('');
  const [replyDraft, setReplyDraft] = useState('');
  const [assignAgentIdDraft, setAssignAgentIdDraft] = useState('');
  const [assignNotesDraft, setAssignNotesDraft] = useState('');
  const [workflowStateDraft, setWorkflowStateDraft] = useState<WorkflowWriteState>('Open');
  const [workflowReasonDraft, setWorkflowReasonDraft] = useState('');
  const [linkReservationIdDraft, setLinkReservationIdDraft] = useState('');
  const [linkWaitingListIdDraft, setLinkWaitingListIdDraft] = useState('');
  const [linkCustomerUserIdDraft, setLinkCustomerUserIdDraft] = useState('');
  const [linkNotesDraft, setLinkNotesDraft] = useState('');
  const [selectedConversationIds, setSelectedConversationIds] = useState<Array<string>>([]);
  const currentStaffUserId = session?.user?.user_id ?? null;
  const canManageConversation = can(session, 'conversation.manage');

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
    setAssignAgentIdDraft('');
    setAssignNotesDraft('');
    setWorkflowReasonDraft('');
    setLinkReservationIdDraft('');
    setLinkWaitingListIdDraft('');
    setLinkCustomerUserIdDraft('');
    setLinkNotesDraft('');
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

  useEffect(() => {
    const currentIds = new Set((inboxQuery.data?.data ?? []).map((item) => item.conversation_id));
    setSelectedConversationIds((existing) => existing.filter((id) => currentIds.has(id)));
  }, [inboxQuery.data?.data]);

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
  const selectedConversationRows = useMemo(
    () => (inboxQuery.data?.data ?? []).filter((item) => selectedConversationIds.includes(item.conversation_id)),
    [inboxQuery.data?.data, selectedConversationIds],
  );
  const linkedReservationId = detailConversation ? conversationReservationId(detailConversation) : null;
  const linkedReservationCode = detailConversation ? conversationReservationCode(detailConversation) : null;
  const linkedReservationRowVersion = detailConversation ? conversationReservationRowVersion(detailConversation) : null;
  const linkedWaitingListId = detailConversation ? conversationWaitingListId(detailConversation) : null;
  const linkedWaitingListLabel = detailConversation ? conversationWaitingLabel(detailConversation) : null;
  const currentWorkflowState = detailConversation ? conversationWorkflowState(detailConversation.workflow.state) : null;
  const currentAssignment = detailConversation?.active_assignment ?? null;
  const canTakeOver = !!activeConversationId && currentAssignment?.agent_user_id !== currentStaffUserId;
  const canUnassign = !!activeConversationId && currentAssignment?.agent_user_id === currentStaffUserId;
  const bulkTakeOverIds = selectedConversationRows
    .filter((item) => !item.assignment_state.is_mine)
    .map((item) => item.conversation_id);
  const bulkUnassignIds = selectedConversationRows
    .filter((item) => item.assignment_state.is_mine)
    .map((item) => item.conversation_id);

  useEffect(() => {
    const editableWorkflowState = toWorkflowWriteState(currentWorkflowState);
    if (editableWorkflowState) {
      setWorkflowStateDraft(editableWorkflowState);
    }
  }, [currentWorkflowState]);

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
  const assignMutation = useMutation({
    mutationFn: ({ agentUserId, notes }: { agentUserId: number; notes?: string | null }) =>
      assignConversation(activeConversationId as string, {
        agent_user_id: agentUserId,
        notes,
      }),
  });
  const workflowMutation = useMutation({
    mutationFn: ({ workflowState, reason }: { workflowState: WorkflowWriteState; reason?: string | null }) =>
      updateConversationWorkflowState(activeConversationId as string, {
        workflow_state: workflowState,
        expected_workflow_state: currentWorkflowState,
        reason,
      }),
  });
  const linkMutation = useMutation({
    mutationFn: (payload: {
      reservationId?: number;
      waitingListId?: number;
      customerUserId?: number;
      notes?: string | null;
    }) =>
      linkConversation(activeConversationId as string, {
        reservation_id: payload.reservationId ?? null,
        waiting_list_id: payload.waitingListId ?? null,
        customer_user_id: payload.customerUserId ?? null,
        notes: payload.notes ?? null,
      }),
  });
  const unlinkReservationMutation = useMutation({
    mutationFn: () => unlinkConversationReservation(activeConversationId as string),
  });
  const unlinkWaitingListMutation = useMutation({
    mutationFn: () => unlinkConversationWaitingList(activeConversationId as string),
  });
  const bulkOwnershipMutation = useMutation({
    mutationFn: async ({ mode, ids }: { mode: 'takeover' | 'unassign'; ids: Array<string> }) => {
      const results = await Promise.allSettled(
        ids.map((id) => (mode === 'takeover' ? takeOverConversation(id) : unassignConversation(id))),
      );

      return {
        mode,
        total: ids.length,
        successCount: results.filter((result) => result.status === 'fulfilled').length,
      };
    },
  });

  async function refreshInbox(conversationId = activeConversationId) {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['conversations'] }),
      conversationId
        ? queryClient.invalidateQueries({ queryKey: ['conversation-detail', conversationId] })
        : Promise.resolve(),
    ]);
  }

  async function refreshAfterGuardedError(error: unknown, conversationId = activeConversationId) {
    if (conversationId && (isApiStatus(error, 409) || isApiStatus(error, 422))) {
      await refreshInbox(conversationId);
    }
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
      await refreshAfterGuardedError(error, activeConversationId);
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
      await refreshAfterGuardedError(error, activeConversationId);
    }
  }

  async function handleAssign() {
    if (!activeConversationId) {
      return;
    }

    const agentUserId = parsePositiveInteger(assignAgentIdDraft);
    if (!agentUserId) {
      message.error('Nhập staff user id hợp lệ trước khi phân công hội thoại.');
      return;
    }

    try {
      await assignMutation.mutateAsync({
        agentUserId,
        notes: emptyToNull(assignNotesDraft),
      });
      setAssignAgentIdDraft('');
      setAssignNotesDraft('');
      await refreshInbox(activeConversationId);
      message.success(`Đã phân công hội thoại ${activeConversationId} cho nhân viên #${agentUserId}.`);
    } catch (error) {
      message.error(formatApiError(error, 'Không thể phân công hội thoại.'));
      await refreshAfterGuardedError(error, activeConversationId);
    }
  }

  async function handleWorkflowStateUpdate() {
    if (!activeConversationId) {
      return;
    }

    try {
      await workflowMutation.mutateAsync({
        workflowState: workflowStateDraft,
        reason: emptyToNull(workflowReasonDraft),
      });
      setWorkflowReasonDraft('');
      await refreshInbox(activeConversationId);
      message.success('Đã cập nhật trạng thái workflow của hội thoại.');
    } catch (error) {
      message.error(formatApiError(error, 'Không thể cập nhật workflow của hội thoại.'));
      await refreshAfterGuardedError(error, activeConversationId);
    }
  }

  async function handleLinkConversation() {
    if (!activeConversationId) {
      return;
    }

    const reservationId = parsePositiveInteger(linkReservationIdDraft);
    const waitingListId = parsePositiveInteger(linkWaitingListIdDraft);
    const customerUserId = parsePositiveInteger(linkCustomerUserIdDraft);

    if (!reservationId && !waitingListId && !customerUserId) {
      message.error('Nhập ít nhất một reservation, waiting-list hoặc customer user id để liên kết.');
      return;
    }

    try {
      await linkMutation.mutateAsync({
        reservationId: reservationId ?? undefined,
        waitingListId: waitingListId ?? undefined,
        customerUserId: customerUserId ?? undefined,
        notes: emptyToNull(linkNotesDraft),
      });
      setLinkReservationIdDraft('');
      setLinkWaitingListIdDraft('');
      setLinkCustomerUserIdDraft('');
      setLinkNotesDraft('');
      await refreshInbox(activeConversationId);
      message.success('Đã liên kết hội thoại với ngữ cảnh nghiệp vụ.');
    } catch (error) {
      message.error(formatApiError(error, 'Không thể liên kết hội thoại.'));
      await refreshAfterGuardedError(error, activeConversationId);
    }
  }

  async function handleUnlinkConversationLink(type: 'reservation' | 'waiting-list') {
    if (!activeConversationId) {
      return;
    }

    const confirmed = await confirmAction({
      title: type === 'reservation' ? 'Gỡ liên kết đặt bàn?' : 'Gỡ liên kết khách chờ?',
      content: 'Thao tác này chỉ gỡ liên kết trong inbox hội thoại, không xóa dữ liệu nghiệp vụ gốc.',
      okText: 'Gỡ liên kết',
      danger: true,
    });

    if (!confirmed) {
      return;
    }

    try {
      if (type === 'reservation') {
        await unlinkReservationMutation.mutateAsync();
      } else {
        await unlinkWaitingListMutation.mutateAsync();
      }
      await refreshInbox(activeConversationId);
      message.success('Đã gỡ liên kết hội thoại.');
    } catch (error) {
      message.error(formatApiError(error, 'Không thể gỡ liên kết hội thoại.'));
      await refreshAfterGuardedError(error, activeConversationId);
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
      await refreshAfterGuardedError(error, activeConversationId);
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
      await refreshAfterGuardedError(error, activeConversationId);
    }
  }

  async function handleBulkOwnership(mode: 'takeover' | 'unassign') {
    const ids = mode === 'takeover' ? bulkTakeOverIds : bulkUnassignIds;
    if (ids.length === 0) {
      return;
    }

    const confirmed = await confirmAction({
      title: mode === 'takeover' ? `Nhận xử lý ${ids.length} hội thoại đã chọn?` : `Trả ${ids.length} hội thoại đã chọn về hàng chờ?`,
      content: mode === 'takeover'
        ? 'Chỉ dùng khi ca bận cần gom nhanh các hội thoại chưa có owner rõ ràng.'
        : 'Chỉ dùng khi bạn thực sự cần bàn giao lại hàng chờ cho người khác.',
      okText: mode === 'takeover' ? 'Nhận xử lý hàng loạt' : 'Trả về hàng chờ',
      danger: mode === 'unassign',
    });

    if (!confirmed) {
      return;
    }

    try {
      const result = await bulkOwnershipMutation.mutateAsync({ mode, ids });
      await refreshInbox();
      setSelectedConversationIds([]);
      message.success(
        mode === 'takeover'
          ? `Đã nhận xử lý ${result.successCount}/${result.total} hội thoại đã chọn.`
          : `Đã trả ${result.successCount}/${result.total} hội thoại về hàng chờ.`,
      );
      if (result.successCount < result.total) {
        message.warning('Một số hội thoại không cập nhật được. Hãy làm mới và kiểm tra lại ownership.');
      }
    } catch (error) {
      message.error(formatApiError(error, 'Không thể cập nhật ownership cho các hội thoại đã chọn.'));
    }
  }

  function openLinkedReservation() {
    if (!linkedReservationId) {
      return;
    }

    setReservationContext({
      reservationId: linkedReservationId,
      reservationRowVersion: linkedReservationRowVersion,
      label: linkedReservationCode,
      source: 'reservation',
    });
    navigate(`${staffRoutePaths.ops.reservations}?${buildJourneySearch({
      source: 'reservation',
      reservationId: linkedReservationId,
      reservationRowVersion: linkedReservationRowVersion ?? undefined,
    })}`);
  }

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }} className="staff-conversation-page">
      <PageHeader
        className="staff-conversation-page-header"
        eyebrow="Hộp thư hội thoại"
        title="Inbox xử lý hội thoại"
        description="Giữ rõ ownership, liên kết nghiệp vụ và bước trả lời kế tiếp để không bỏ sót khách đang chờ."
        context={(
          <>
            <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Toàn bộ phạm vi được cấp'} tone="default" variant="freshness" />
            <StatusChip label={`${summaryStats.unassigned} chưa phân công`} tone={summaryStats.unassigned > 0 ? 'warning' : 'success'} variant="severity" />
            <StatusChip label={selectedConversationId ? `Đang mở ${selectedConversationId}` : 'Chưa khóa hội thoại'} tone={selectedConversationId ? 'processing' : 'warning'} variant="entity" />
          </>
        )}
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

      <Card size="small" className="staff-conversation-preset-card">
        <Space wrap>
          <Button type={assignmentFilter === 'unassigned' ? 'primary' : 'default'} onClick={() => updateUrlState({ assignment: 'unassigned', status: 'Open', page: 1, conversationId: null })}>
            Chưa phân công
          </Button>
          <Button type={assignmentFilter === 'mine' ? 'primary' : 'default'} onClick={() => updateUrlState({ assignment: 'mine', status: 'Open', page: 1, conversationId: null })}>
            Của tôi
          </Button>
          <Button type={channelFilter === 'WebChat' ? 'primary' : 'default'} onClick={() => updateUrlState({ channel: 'WebChat', page: 1, conversationId: null })}>
            Web chat
          </Button>
          <Button type={statusFilter === 'Pending' ? 'primary' : 'default'} onClick={() => updateUrlState({ status: 'Pending', page: 1, conversationId: null })}>
            Đang chờ
          </Button>
          <Button onClick={() => updateUrlState({ status: 'all', assignment: 'all', channel: 'all', q: '', page: 1, conversationId: null })}>
            Xóa preset
          </Button>
        </Space>
      </Card>

      <InlineState
        tone={branchId ? 'info' : 'warning'}
        eyebrow="Phạm vi triage"
        className="staff-conversation-scope-alert"
        title={branchId ? `Đang triage theo chi nhánh #${branchId}` : 'Đang xem tất cả chi nhánh được phép'}
        description={branchId
          ? 'Danh sách hiện đang lấy theo branch context của shell. Nếu cần đổi phạm vi, hãy chuyển chi nhánh ở shell để URL và dữ liệu tiếp tục khớp nhau.'
          : 'Shell chưa giữ một branch context rõ ràng, nên danh sách có thể trải qua nhiều chi nhánh mà phiên nhân viên được phép xem.'}
      />

      {selectedConversationIds.length > 0 ? (
        <Alert
          className="staff-conversation-bulk-alert"
          type="info"
          showIcon
          title={`Đã chọn ${selectedConversationIds.length} hội thoại để triage nhanh`}
          description={(
            <Space wrap>
              <Button
                type="primary"
                disabled={bulkTakeOverIds.length === 0}
                loading={bulkOwnershipMutation.isPending}
                onClick={() => void handleBulkOwnership('takeover')}
              >
                Nhận xử lý đã chọn
              </Button>
              <Button
                danger
                disabled={bulkUnassignIds.length === 0}
                loading={bulkOwnershipMutation.isPending}
                onClick={() => void handleBulkOwnership('unassign')}
              >
                Trả về hàng chờ
              </Button>
            </Space>
          )}
        />
      ) : null}

      <Row gutter={[16, 16]} className="staff-conversation-summary-grid">
        <Col xs={24} md={6}>
          <Card className="staff-conversation-summary-card">
            <Statistic title="Tổng số" value={summaryStats.total} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className={`staff-conversation-summary-card ${assignmentFilter === 'assigned' ? 'staff-conversation-summary-card-active' : ''}`}>
            <Statistic title="Đã phân công" value={summaryStats.assigned} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className={`staff-conversation-summary-card ${assignmentFilter === 'unassigned' ? 'staff-conversation-summary-card-active' : ''}`}>
            <Statistic title="Chưa phân công" value={summaryStats.unassigned} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card className={`staff-conversation-summary-card ${assignmentFilter === 'mine' ? 'staff-conversation-summary-card-active' : ''}`}>
            <Statistic title="Của tôi" value={summaryStats.mine} />
          </Card>
        </Col>
      </Row>

      <Card
        title="Hàng chờ hội thoại"
        extra={activeConversationId ? `Đang mở ${activeConversationId}` : 'Chưa khóa hội thoại'}
        className="staff-conversation-inbox-card"
      >
        {inboxQuery.isLoading ? <InlineLoading tip="Đang tải hộp thư hội thoại..." /> : null}
        {inboxQuery.error ? (
          <ApiStateBlock
            error={inboxQuery.error}
            fallback="Không thể tải hộp thư hội thoại."
            onRetry={() => {
              void inboxQuery.refetch();
            }}
          />
        ) : null}
        {!inboxQuery.isLoading && !inboxQuery.error && (inboxQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyBlock title="Không có hội thoại" description="Bộ lọc hiện tại không trả về dòng hộp thư nào." />
        ) : null}
        {(inboxQuery.data?.data.length ?? 0) > 0 ? (
          <Table<StaffConversationCollectionEnvelope['data'][number]>
            className="staff-conversation-table"
            rowKey="conversation_id"
            dataSource={inboxQuery.data?.data ?? []}
            rowSelection={{
              selectedRowKeys: selectedConversationIds,
              onChange: (keys) => setSelectedConversationIds(keys.map((key) => String(key))),
            }}
            rowClassName={(entry) => (entry.conversation_id === selectedConversationId ? 'staff-row-selected staff-conversation-row-selected' : '')}
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
                  <Space orientation="vertical" size={2} className="staff-conversation-table-title">
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
                  <Space orientation="vertical" size={6} className="staff-conversation-triage-cell">
                    <Space wrap size={4}>
                      <StatusChip label={entry.status} tone={conversationTone(entry.status)} variant="severity" />
                      <StatusChip label={entry.channel} tone="processing" variant="freshness" />
                      {entry.assignment_state.is_mine ? <StatusChip label="Của tôi" tone="success" variant="severity" /> : null}
                      {entry.assignment_state.is_unassigned ? <StatusChip label="Chưa phân công" tone="warning" variant="severity" /> : null}
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
                  <Space orientation="vertical" size={2} className="staff-conversation-activity-cell">
                    <Typography.Text>{formatDateTime(entry.latest_activity_at ?? entry.created_at)}</Typography.Text>
                    <Typography.Text type="secondary">{formatRelativeAge(entry.latest_activity_at ?? entry.created_at)}</Typography.Text>
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
                    Mở thread
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
    <Card title="Chi tiết hội thoại" className="staff-conversation-detail-card">
      {!activeConversationId ? (
        <EmptyBlock title="Chưa chọn hội thoại" description="Chọn một hội thoại trong danh sách để khóa ngữ cảnh chi tiết, lịch sử phân công và các thao tác nội bộ." />
      ) : detailQuery.isLoading ? (
        <InlineLoading tip="Đang tải chi tiết hội thoại..." />
      ) : detailQuery.error ? (
        <ApiStateBlock
          error={detailQuery.error}
          fallback="Không thể tải chi tiết hội thoại."
          onRetry={() => {
            void detailQuery.refetch();
          }}
          retryLabel="Thử lại"
        />
      ) : detailConversation ? (
        <Space orientation="vertical" size={16} style={{ width: '100%' }} className="staff-conversation-detail-shell">
          <Space orientation="vertical" size={4} style={{ width: '100%' }} className="staff-conversation-detail-header">
            <Typography.Title level={4} style={{ margin: 0 }}>{conversationTitle(detailConversation)}</Typography.Title>
            <Typography.Text type="secondary">{conversationBranchLabel(detailConversation)}</Typography.Text>
          </Space>

          <Space wrap size={6} className="staff-conversation-detail-ownership">
            <StatusChip label={detailConversation.status} tone={conversationTone(detailConversation.status)} variant="severity" />
            <StatusChip label={detailConversation.channel} tone="processing" variant="freshness" />
            {detailConversation.intent_detected ? <StatusChip label={humanizeCode(detailConversation.intent_detected)} tone="default" variant="entity" /> : null}
            {currentAssignment?.agent_user_id === currentStaffUserId ? (
              <StatusChip label="Đang giao cho tôi" tone="success" variant="severity" />
            ) : currentAssignment ? (
              <StatusChip label="Đang giao cho nhân viên khác" tone="warning" variant="severity" />
            ) : (
              <StatusChip label="Hàng chờ chung" tone="default" variant="severity" />
            )}
          </Space>

          <InlineState
            tone="info"
            eyebrow="Cách dùng luồng hội thoại"
            className="staff-conversation-linkage-alert"
            title="Ownership, liên kết nghiệp vụ và phản hồi ra ngoài được tách riêng"
            description="Nhận xử lý và trả hàng chờ chỉ thay đổi ownership. Mở đặt bàn hoặc khách chờ chỉ điều hướng sang luồng liên quan. Phản hồi ra ngoài luôn phụ thuộc vào capability trong detail envelope của chính hội thoại này."
          />

          <div className="staff-action-row staff-conversation-detail-actions">
            {canTakeOver ? <Button type="primary" onClick={() => void handleTakeOver()} loading={takeOverMutation.isPending}>Nhận xử lý</Button> : null}
            {canUnassign ? <Button danger onClick={() => void handleUnassign()} loading={unassignMutation.isPending}>Trả về hàng chờ</Button> : null}
            {linkedReservationId && can(session, 'reservation.manage') ? <Button onClick={openLinkedReservation}>Mở đặt bàn</Button> : null}
            {linkedWaitingListId && can(session, 'waiting_list.manage') ? <Button onClick={() => navigate(buildConversationWaitingListPath(linkedWaitingListId))}>Mở danh sách chờ</Button> : null}
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Phân công rõ nhân viên</Typography.Text>
            <Typography.Text type="secondary">
              Dùng route assign khi cần giao hội thoại cho một staff cụ thể thay vì chỉ nhận xử lý bằng tài khoản hiện tại.
            </Typography.Text>
            <Space wrap align="start">
              <Input
                aria-label="Staff user id nhận hội thoại"
                inputMode="numeric"
                placeholder="Staff user id"
                value={assignAgentIdDraft}
                onChange={(event) => setAssignAgentIdDraft(event.target.value)}
                style={{ width: 160 }}
                disabled={!canManageConversation}
              />
              <Input
                aria-label="Ghi chú phân công hội thoại"
                placeholder="Ghi chú phân công"
                value={assignNotesDraft}
                onChange={(event) => setAssignNotesDraft(event.target.value)}
                style={{ width: 260 }}
                disabled={!canManageConversation}
              />
              <Button
                type="primary"
                onClick={() => void handleAssign()}
                loading={assignMutation.isPending}
                disabled={!canManageConversation || parsePositiveInteger(assignAgentIdDraft) === null}
              >
                Phân công
              </Button>
            </Space>
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Workflow hội thoại</Typography.Text>
            <Typography.Text type="secondary">
              Gửi kèm expected_workflow_state hiện tại để backend phát hiện stale state trước khi đổi trạng thái.
            </Typography.Text>
            <Space wrap align="start">
              <Select<WorkflowWriteState>
                aria-label="Trạng thái workflow hội thoại"
                value={workflowStateDraft}
                options={workflowWriteOptions}
                onChange={(value) => setWorkflowStateDraft(value)}
                style={{ width: 180 }}
                disabled={!canManageConversation}
              />
              <Input
                aria-label="Lý do cập nhật workflow"
                placeholder="Lý do nếu có"
                value={workflowReasonDraft}
                onChange={(event) => setWorkflowReasonDraft(event.target.value)}
                style={{ width: 280 }}
                disabled={!canManageConversation}
              />
              <Button
                type="primary"
                onClick={() => void handleWorkflowStateUpdate()}
                loading={workflowMutation.isPending}
                disabled={!canManageConversation || !activeConversationId}
              >
                Cập nhật workflow
              </Button>
              <StatusChip
                label={`Hiện tại: ${humanizeCode(currentWorkflowState ?? detailConversation.workflow.state)}`}
                tone="processing"
                variant="freshness"
              />
            </Space>
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Liên kết nghiệp vụ</Typography.Text>
            <Typography.Text type="secondary">
              Link hoặc unlink theo route backend riêng, sau đó refetch lại detail để dùng liên kết canonical.
            </Typography.Text>
            <Space wrap align="start">
              <Input
                aria-label="Reservation id cần liên kết"
                inputMode="numeric"
                placeholder="Reservation id"
                value={linkReservationIdDraft}
                onChange={(event) => setLinkReservationIdDraft(event.target.value)}
                style={{ width: 150 }}
                disabled={!canManageConversation}
              />
              <Input
                aria-label="Waiting-list id cần liên kết"
                inputMode="numeric"
                placeholder="Waiting-list id"
                value={linkWaitingListIdDraft}
                onChange={(event) => setLinkWaitingListIdDraft(event.target.value)}
                style={{ width: 150 }}
                disabled={!canManageConversation}
              />
              <Input
                aria-label="Customer user id cần liên kết"
                inputMode="numeric"
                placeholder="Customer user id"
                value={linkCustomerUserIdDraft}
                onChange={(event) => setLinkCustomerUserIdDraft(event.target.value)}
                style={{ width: 160 }}
                disabled={!canManageConversation}
              />
              <Input
                aria-label="Ghi chú liên kết hội thoại"
                placeholder="Ghi chú liên kết"
                value={linkNotesDraft}
                onChange={(event) => setLinkNotesDraft(event.target.value)}
                style={{ width: 240 }}
                disabled={!canManageConversation}
              />
              <Button
                type="primary"
                onClick={() => void handleLinkConversation()}
                loading={linkMutation.isPending}
                disabled={
                  !canManageConversation
                  || (
                    parsePositiveInteger(linkReservationIdDraft) === null
                    && parsePositiveInteger(linkWaitingListIdDraft) === null
                    && parsePositiveInteger(linkCustomerUserIdDraft) === null
                  )
                }
              >
                Liên kết
              </Button>
              {linkedReservationId ? (
                <Button
                  danger
                  onClick={() => void handleUnlinkConversationLink('reservation')}
                  loading={unlinkReservationMutation.isPending}
                  disabled={!canManageConversation}
                >
                  Gỡ đặt bàn
                </Button>
              ) : null}
              {linkedWaitingListId ? (
                <Button
                  danger
                  onClick={() => void handleUnlinkConversationLink('waiting-list')}
                  loading={unlinkWaitingListMutation.isPending}
                  disabled={!canManageConversation}
                >
                  Gỡ khách chờ
                </Button>
              ) : null}
            </Space>
          </div>

          <Descriptions bordered size="small" column={1} className="staff-conversation-linkage-grid">
            <Descriptions.Item label="Khách">{conversationCustomerLabel(detailConversation)}</Descriptions.Item>
            <Descriptions.Item label="Người đang phụ trách">{assignmentAgentLabel(currentAssignment)}</Descriptions.Item>
            <Descriptions.Item label="Liên kết đặt bàn">{linkedReservationCode ?? (linkedReservationId ? `Đặt bàn #${linkedReservationId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Liên kết khách chờ">{linkedWaitingListLabel ?? (linkedWaitingListId ? `Khách chờ #${linkedWaitingListId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Hoạt động gần nhất">{formatDateTime(detailConversation.latest_activity_at ?? detailConversation.created_at)}</Descriptions.Item>
            <Descriptions.Item label="Số lượng">
              <Space wrap size={8}>
                <StatusChip label={`${detailConversation.counts.messages} tin nhắn`} variant="count" />
                <StatusChip label={`${detailConversation.counts.internal_notes} ghi chú`} tone="warning" variant="count" />
                <StatusChip label={`${detailConversation.counts.events} sự kiện`} tone="processing" variant="count" />
                <StatusChip label={`${detailConversation.counts.analyses} phân tích`} tone="success" variant="count" />
              </Space>
            </Descriptions.Item>
          </Descriptions>

          <InlineState
            tone={replyState.canSend ? 'success' : 'warning'}
            eyebrow="Phản hồi ra ngoài"
            className="staff-conversation-outbound-alert"
            title={replyState.canSend ? 'Luồng phản hồi ra ngoài đang sẵn sàng cho hội thoại này.' : 'Phản hồi ra ngoài đang bị khóa.'}
            description={describeOutboundReplyState(replyState)}
          />

          <Tabs
            className="staff-conversation-detail-tabs"
            activeKey={activeTab}
            onChange={(key) => updateUrlState({ tab: key as ConversationInboxTab }, { replace: true })}
            items={[
              { key: 'messages', label: `Tin nhắn (${detailQuery.data?.data.messages.length ?? 0})`, children: <MessageThread messages={detailQuery.data?.data.messages ?? []} /> },
              { key: 'ai', label: 'AI hỗ trợ', children: <AiAssistPanel aiAssist={detailQuery.data?.data.ai_assist} /> },
              { key: 'history', label: 'Lịch sử', children: <HistoryPanel assignments={detailQuery.data?.data.assignment_history ?? []} events={detailQuery.data?.data.events ?? []} /> },
            ]}
          />

          <div className="staff-conversation-composer staff-conversation-composer-note">
            <Typography.Text strong>Thêm ghi chú nội bộ</Typography.Text>
            <Typography.Text type="secondary">Giữ lại bối cảnh bàn giao, rủi ro thời gian hoặc các điểm cần staff ca sau tiếp tục.</Typography.Text>
            <Input.TextArea value={noteDraft} rows={4} placeholder="Ghi chú cho bàn giao, rủi ro thời gian hoặc theo dõi đặt bàn." onChange={(event) => setNoteDraft(event.target.value)} />
            <Button type="primary" onClick={() => void handleAddNote()} disabled={noteDraft.trim() === ''} loading={addNoteMutation.isPending}>Thêm ghi chú</Button>
          </div>

          <div className="staff-conversation-composer staff-conversation-composer-reply">
            <Typography.Text strong>Xếp hàng phản hồi ra ngoài</Typography.Text>
            <Typography.Text type="secondary">Thao tác này tuân theo capability của chính hội thoại đang mở, không chỉ dựa vào quyền phiên đăng nhập.</Typography.Text>
            <Input.TextArea value={replyDraft} rows={4} placeholder="Nội dung phản hồi cần gửi cho khách khi kênh hiện tại cho phép." onChange={(event) => setReplyDraft(event.target.value)} disabled={!replyState.canSend} />
            <Button type="primary" onClick={() => void handleReply()} disabled={!replyState.canSend || replyDraft.trim() === ''} loading={replyMutation.isPending}>Xếp hàng phản hồi</Button>
          </div>
        </Space>
      ) : null}
    </Card>
  );

  return <SplitWorkspace main={main} side={side} variant="balanced" className="staff-conversation-workspace" />;
}

function parsePositiveInteger(value: string): number | null {
  const parsed = Number(value.trim());
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function emptyToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function conversationWorkflowState(value: string): ConversationWorkflowState | null {
  return value === 'Open'
    || value === 'Triaged'
    || value === 'Assigned'
    || value === 'PendingCustomer'
    || value === 'Resolved'
    || value === 'Closed'
    ? value
    : null;
}

function toWorkflowWriteState(value: ConversationWorkflowState | null): WorkflowWriteState | null {
  if (!value || value === 'Assigned') {
    return null;
  }

  return value;
}

function conversationReservationRowVersion(item: ConversationItem): number | null {
  return readNumberFromRecord(item.linked_reservation, 'row_version')
    ?? readNumberFromRecord(item.linked_reservation, 'reservation_row_version');
}

function readNumberFromRecord(source: unknown, key: string): number | null {
  const value = source && typeof source === 'object' && !Array.isArray(source)
    ? (source as Record<string, unknown>)[key]
    : null;

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function describeOutboundReplyState(state: ReturnType<typeof outboundReplyState>): string {
  if (state.canSend) {
    const parts = ['Phiên nhân viên này hiện có thể xếp hàng phản hồi ra ngoài.'];
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
    return `Phản hồi ra ngoài đang bị khóa vì trạng thái hiện tại là ${humanizeCode(state.reasonCode)}.`;
  }

  return 'Phản hồi ra ngoài đang bị khóa vì chi tiết hội thoại chưa cho phép thao tác này.';
}
