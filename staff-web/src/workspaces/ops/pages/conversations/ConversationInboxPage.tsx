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

import { Drawer } from 'antd';
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
  conversationCanTakeOver,
  conversationCanUnassignMine,
  type ConversationItem,
  type ConversationAssignmentFilter,
  type ConversationChannelFilter,
  type ConversationInboxViewFilter,
  type ConversationInboxTab,
  type ConversationInboxUrlState,
  type ConversationStatusFilter,
  type ConversationWorkflowFilter,
  type ConversationWorkflowWriteState,
  conversationBranchLabel,
  conversationCustomerLabel,
  conversationInboxViewStats,
  conversationMutationCapabilities,
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

const workflowOptions = [
  { value: 'all', label: 'Tất cả workflow' },
  { value: 'Open', label: 'Đang mở' },
  { value: 'Triaged', label: 'Đã triage' },
  { value: 'Assigned', label: 'Đã giao người xử lý' },
  { value: 'PendingCustomer', label: 'Chờ khách' },
  { value: 'Resolved', label: 'Đã xử lý' },
  { value: 'Closed', label: 'Đã đóng' },
] satisfies Array<{ value: ConversationWorkflowFilter; label: string }>;

const inboxViewOptions = [
  { value: 'all', label: 'Tất cả hàng chờ' },
  { value: 'unassigned', label: 'Chưa phân công' },
  { value: 'overdue', label: 'Quá hạn' },
  { value: 'waiting_on_customer', label: 'Chờ khách phản hồi' },
  { value: 'resolved_today', label: 'Đã xử lý hôm nay' },
] satisfies Array<{ value: ConversationInboxViewFilter; label: string }>;

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

type WorkflowWriteState = ConversationWorkflowWriteState;

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
  const [detailDrawerOpen, setDetailDrawerOpen] = useState(false);
  const currentStaffUserId = session?.user?.user_id ?? null;
  const canManageConversation = can(session, 'conversation.manage');

  const urlState = useMemo(() => readConversationInboxUrlState(searchParams), [searchParams]);
  const statusFilter = urlState.status;
  const workflowStateFilter = urlState.workflowState;
  const inboxViewFilter = urlState.inboxView;
  const assignmentFilter = urlState.assignment;
  const channelFilter = urlState.channel;
  const searchTerm = urlState.q;
  const page = urlState.page;
  const selectedConversationId = urlState.conversationId;
  const activeTab = urlState.tab;
  const assignCapabilityHelpText = () => (
    canAssign
      ? 'D\u00f9ng route assign khi c\u1ea7n giao h\u1ed9i tho\u1ea1i cho m\u1ed9t staff c\u1ee5 th\u1ec3 thay v\u00ec ch\u1ec9 nh\u1eadn x\u1eed l\u00fd b\u1eb1ng t\u00e0i kho\u1ea3n hi\u1ec7n t\u1ea1i.'
      : 'H\u1ed9i tho\u1ea1i \u0111\u00e3 x\u1eed l\u00fd ho\u1eb7c \u0111\u00e3 \u0111\u00f3ng. H\u00e3y m\u1edf l\u1ea1i v\u1ec1 triaged tr\u01b0\u1edbc khi ph\u00e2n c\u00f4ng.'
  );
  const linkCapabilityHelpText = () => (
    canLink
      ? 'Link ho\u1eb7c unlink theo route backend ri\u00eang, sau \u0111\u00f3 refetch l\u1ea1i detail \u0111\u1ec3 d\u00f9ng li\u00ean k\u1ebft canonical.'
      : 'H\u1ed9i tho\u1ea1i \u0111\u00e3 \u0111\u00f3ng n\u00ean backend kh\u00f3a thao t\u00e1c link/unlink. H\u00e3y m\u1edf l\u1ea1i thread tr\u01b0\u1edbc khi \u0111\u1ed5i li\u00ean k\u1ebft.'
  );
  const noteCapabilityHelpText = () => (
    canAddInternalNote
      ? 'Gi\u1eef l\u1ea1i b\u1ed1i c\u1ea3nh b\u00e0n giao, r\u1ee7i ro th\u1eddi gian ho\u1eb7c c\u00e1c \u0111i\u1ec3m c\u1ea7n staff ca sau ti\u1ebfp t\u1ee5c.'
      : 'H\u1ed9i tho\u1ea1i \u0111\u00e3 \u0111\u00f3ng n\u00ean kh\u00f4ng th\u1ec3 th\u00eam ghi ch\u00fa n\u1ed9i b\u1ed9 cho \u0111\u1ee3t triage n\u00e0y.'
  );
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

  useEffect(() => {
    if (selectedConversationId) {
      setDetailDrawerOpen(true);
    } else {
      setDetailDrawerOpen(false);
    }
  }, [selectedConversationId]);

  const updateUrlState = useCallback((
    patch: Partial<ConversationInboxUrlState>,
    options?: { replace?: boolean },
  ) => {
    const nextSearch = buildConversationInboxSearch(searchParams, patch);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams]);

  const inboxQuery = useQuery({
    queryKey: ['conversations', branchId, statusFilter, workflowStateFilter, inboxViewFilter, assignmentFilter, channelFilter, searchTerm, page],
    queryFn: () =>
      listConversations({
        branch_id: branchId ?? undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        workflow_state: workflowStateFilter === 'all' ? undefined : workflowStateFilter,
        inbox_view: inboxViewFilter === 'all' ? undefined : inboxViewFilter,
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
  const detailCapabilities = conversationMutationCapabilities(detailQuery.data?.data);
  const replyState = outboundReplyState(detailQuery.data?.data);
  const summaryStats = conversationSummaryStats(inboxQuery.data?.meta?.summary);
  const inboxViewStats = conversationInboxViewStats(inboxQuery.data?.meta?.summary);
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
  const workflowTargetOptions = useMemo(
    () => workflowWriteOptions.filter((option) => detailCapabilities.workflowStateTargets.includes(option.value)),
    [detailCapabilities.workflowStateTargets],
  );
  const canTakeOver = !!activeConversationId && detailCapabilities.canTakeOver && currentAssignment?.agent_user_id !== currentStaffUserId;
  const canUnassign = !!activeConversationId && detailCapabilities.canUnassign && currentAssignment?.agent_user_id === currentStaffUserId;
  const canAssign = !!activeConversationId && detailCapabilities.canAssign;
  const canLink = !!activeConversationId && detailCapabilities.canLink;
  const canAddInternalNote = !!activeConversationId && detailCapabilities.canAddInternalNote;
  const canUpdateWorkflowState = !!activeConversationId && detailCapabilities.canUpdateWorkflowState && workflowTargetOptions.length > 0;
  const bulkTakeOverIds = selectedConversationRows
    .filter((item) => conversationCanTakeOver(item))
    .map((item) => item.conversation_id);
  const bulkUnassignIds = selectedConversationRows
    .filter((item) => conversationCanUnassignMine(item))
    .map((item) => item.conversation_id);
  const assignHelpText = canAssign
    ? 'Dùng route assign khi cần giao hội thoại cho một staff cụ thể thay vì chỉ nhận xử lý bằng tài khoản hiện tại.'
    : 'Hội thoại đã xử lý hoặc đã đóng. Hãy mở lại về triaged trước khi phân công.';
  const linkHelpText = canLink
    ? 'Link hoặc unlink theo route backend riêng, sau đó refetch lại detail để dùng liên kết canonical.'
    : 'Hội thoại đã đóng nên backend khóa thao tác link/unlink. Hãy mở lại thread trước khi đổi liên kết.';
  const noteHelpText = canAddInternalNote
    ? 'Giữ lại bối cảnh bàn giao, rủi ro thời gian hoặc các điểm cần staff ca sau tiếp tục.'
    : 'Hội thoại đã đóng nên không thể thêm ghi chú nội bộ cho đợt triage này.';

  void [assignHelpText, linkHelpText, noteHelpText];

  useEffect(() => {
    if (workflowTargetOptions.length === 0) {
      return;
    }

    if (!workflowTargetOptions.some((option) => option.value === workflowStateDraft)) {
      const editableWorkflowState = toWorkflowWriteState(currentWorkflowState);
      const nextWorkflowState = editableWorkflowState && workflowTargetOptions.some((option) => option.value === editableWorkflowState)
        ? editableWorkflowState
        : workflowTargetOptions[0].value;
      setWorkflowStateDraft(nextWorkflowState);
    }
  }, [currentWorkflowState, workflowStateDraft, workflowTargetOptions]);

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
    if (!activeConversationId || !canTakeOver) {
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
    if (!activeConversationId || !canUnassign) {
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
    if (!activeConversationId || !canAssign) {
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
    if (!activeConversationId || !canUpdateWorkflowState) {
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
    if (!activeConversationId || !canLink) {
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
    if (!activeConversationId || !canLink) {
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
    if (!activeConversationId || draft === '' || !canAddInternalNote) {
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
    <div className="staff-workspace-fluid staff-workspace-flex-column" style={{ display: 'flex', flexDirection: 'column', gap: '16px', width: '100%' }}>
      {/* Top Toolbar */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '12px', background: '#fff', padding: '12px 16px', borderRadius: '8px', border: '1px solid #f0f0f0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
          <Typography.Title level={4} style={{ margin: 0 }}>Hộp thư hội thoại</Typography.Title>
          <StatusChip label={branchId ? `Chi nhánh #${branchId}` : 'Toàn bộ phạm vi được cấp'} tone="default" variant="freshness" />
          <StatusChip label={`${summaryStats.unassigned} chưa phân công`} tone={summaryStats.unassigned > 0 ? 'warning' : 'success'} variant="severity" />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
          <Select
            aria-label="Lọc theo trạng thái hội thoại"
            style={{ width: 140 }}
            value={statusFilter}
            options={statusOptions}
            onChange={(value) => updateUrlState({ status: value, page: 1, conversationId: null })}
          />
          <Select
            aria-label="Lọc theo trạng thái phân công"
            style={{ width: 150 }}
            value={assignmentFilter}
            options={assignmentOptions}
            onChange={(value) => updateUrlState({ assignment: value, page: 1, conversationId: null })}
          />
          <Select
            aria-label="Lọc theo workflow hội thoại"
            style={{ width: 150 }}
            value={workflowStateFilter}
            options={workflowOptions}
            onChange={(value) => updateUrlState({ workflowState: value, page: 1, conversationId: null })}
          />
          <Select
            aria-label="Lọc theo kênh hội thoại"
            style={{ width: 130 }}
            value={channelFilter}
            options={channelOptions}
            onChange={(value) => updateUrlState({ channel: value, page: 1, conversationId: null })}
          />
          <Input.Search
            aria-label="Tìm kiếm trong hộp thư hội thoại"
            allowClear
            value={searchDraft}
            placeholder="Khách, đặt bàn, SĐT..."
            style={{ width: 220 }}
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
          <Button onClick={() => inboxQuery.refetch()} loading={inboxQuery.isFetching}>Làm mới</Button>
        </div>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
        <Space wrap>
          <Button
            type={inboxViewFilter === 'unassigned' ? 'primary' : 'default'}
            onClick={() => updateUrlState({ inboxView: 'unassigned', workflowState: 'all', status: 'all', page: 1, conversationId: null })}
          >
            Chưa phân công ({inboxViewStats.unassigned})
          </Button>
          <Button
            type={inboxViewFilter === 'overdue' ? 'primary' : 'default'}
            onClick={() => updateUrlState({ inboxView: 'overdue', workflowState: 'all', status: 'all', page: 1, conversationId: null })}
          >
            Quá hạn ({inboxViewStats.overdue})
          </Button>
          <Button
            type={inboxViewFilter === 'waiting_on_customer' ? 'primary' : 'default'}
            onClick={() => updateUrlState({ inboxView: 'waiting_on_customer', workflowState: 'PendingCustomer', status: 'all', page: 1, conversationId: null })}
          >
            Chờ khách ({inboxViewStats.waitingOnCustomer})
          </Button>
          <Button
            type={inboxViewFilter === 'resolved_today' ? 'primary' : 'default'}
            onClick={() => updateUrlState({ inboxView: 'resolved_today', workflowState: 'Resolved', status: 'all', page: 1, conversationId: null })}
          >
            Đã xử lý hôm nay ({inboxViewStats.resolvedToday})
          </Button>
          <Button onClick={() => updateUrlState({ status: 'all', workflowState: 'all', inboxView: 'all', assignment: 'all', channel: 'all', q: '', page: 1, conversationId: null })}>
            Xóa preset
          </Button>
        </Space>
        
        <Space wrap size={16}>
          <Statistic title="Tổng số" value={summaryStats.total} valueStyle={{ fontSize: 16 }} />
          <Statistic title="Đã phân công" value={summaryStats.assigned} valueStyle={{ fontSize: 16 }} />
          <Statistic title="Chưa phân công" value={summaryStats.unassigned} valueStyle={{ fontSize: 16 }} />
          <Statistic title="Của tôi" value={summaryStats.mine} valueStyle={{ fontSize: 16 }} />
        </Space>
      </div>

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

      <Card bodyStyle={{ padding: 0 }} bordered={false} style={{ overflow: 'hidden' }}>
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
            onRow={(entry) => ({ onClick: () => { updateUrlState({ conversationId: entry.conversation_id }); setDetailDrawerOpen(true); } })}
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
                    <Typography.Text strong>
                      {conversationTitle(entry)}
                    </Typography.Text>
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
            ]}
          />
        ) : null}
      </Card>
    </div>
  );

  const side = (
    <div className="staff-conversation-detail-wrapper" style={{ height: '100%', overflowY: 'auto', padding: '0 16px' }}>
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

          <div className="staff-action-row staff-conversation-detail-actions">
            {canTakeOver ? <Button type="primary" onClick={() => void handleTakeOver()} loading={takeOverMutation.isPending}>Nhận xử lý</Button> : null}
            {canUnassign ? <Button danger onClick={() => void handleUnassign()} loading={unassignMutation.isPending}>Trả về hàng chờ</Button> : null}
            {linkedReservationId && can(session, 'reservation.manage') ? <Button onClick={openLinkedReservation}>Mở đặt bàn</Button> : null}
            {linkedWaitingListId && can(session, 'waiting_list.manage') ? <Button onClick={() => navigate(buildConversationWaitingListPath(linkedWaitingListId))}>Mở danh chờ</Button> : null}
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Phân công rõ nhân viên</Typography.Text>
            <Typography.Text type="secondary">
              {assignCapabilityHelpText()}
            </Typography.Text>
            <Space wrap align="start">
              <Input
                aria-label="Staff user id nhận hội thoại"
                inputMode="numeric"
                placeholder="Staff id"
                value={assignAgentIdDraft}
                onChange={(event) => setAssignAgentIdDraft(event.target.value)}
                style={{ width: 100 }}
                disabled={!canManageConversation || !canAssign}
              />
              <Input
                aria-label="Ghi chú phân công hội thoại"
                placeholder="Ghi chú phân công"
                value={assignNotesDraft}
                onChange={(event) => setAssignNotesDraft(event.target.value)}
                style={{ width: 180 }}
                disabled={!canManageConversation || !canAssign}
              />
              <Button
                type="primary"
                onClick={() => void handleAssign()}
                loading={assignMutation.isPending}
                disabled={!canManageConversation || !canAssign || parsePositiveInteger(assignAgentIdDraft) === null}
              >
                Phân công
              </Button>
            </Space>
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Workflow hội thoại</Typography.Text>
            <Typography.Text type="secondary">
              Gửi kèm expected_workflow_state hiện tại để bắt stale state.
            </Typography.Text>
            <Space wrap align="start">
              <Select<WorkflowWriteState>
                aria-label="Trạng thái workflow hội thoại"
                value={workflowStateDraft}
                options={workflowTargetOptions}
                onChange={(value) => setWorkflowStateDraft(value)}
                style={{ width: 140 }}
                disabled={!canManageConversation || !canUpdateWorkflowState}
              />
              <Input
                aria-label="Lý do cập nhật workflow"
                placeholder="Lý do nếu có"
                value={workflowReasonDraft}
                onChange={(event) => setWorkflowReasonDraft(event.target.value)}
                style={{ width: 180 }}
                disabled={!canManageConversation || !canUpdateWorkflowState}
              />
              <Button
                type="primary"
                onClick={() => void handleWorkflowStateUpdate()}
                loading={workflowMutation.isPending}
                disabled={!canManageConversation || !canUpdateWorkflowState}
              >
                Cập nhật
              </Button>
            </Space>
          </div>

          <div className="staff-conversation-ops-panel">
            <Typography.Text strong>Liên kết nghiệp vụ</Typography.Text>
            <Typography.Text type="secondary">
              {linkCapabilityHelpText()}
            </Typography.Text>
            <Space wrap align="start">
              <Input
                aria-label="Reservation id cần liên kết"
                inputMode="numeric"
                placeholder="Reservation id"
                value={linkReservationIdDraft}
                onChange={(event) => setLinkReservationIdDraft(event.target.value)}
                style={{ width: 120 }}
                disabled={!canManageConversation || !canLink}
              />
              <Input
                aria-label="Waiting-list id cần liên kết"
                inputMode="numeric"
                placeholder="Waiting-list id"
                value={linkWaitingListIdDraft}
                onChange={(event) => setLinkWaitingListIdDraft(event.target.value)}
                style={{ width: 120 }}
                disabled={!canManageConversation || !canLink}
              />
              <Button
                type="primary"
                onClick={() => void handleLinkConversation()}
                loading={linkMutation.isPending}
                disabled={
                  !canManageConversation
                  || !canLink
                  || (
                    parsePositiveInteger(linkReservationIdDraft) === null
                    && parsePositiveInteger(linkWaitingListIdDraft) === null
                    && parsePositiveInteger(linkCustomerUserIdDraft) === null
                  )
                }
              >
                Liên kết
              </Button>
            </Space>
            <Space wrap style={{ marginTop: 8 }}>
              {linkedReservationId ? (
                <Button
                  danger
                  onClick={() => void handleUnlinkConversationLink('reservation')}
                  loading={unlinkReservationMutation.isPending}
                  disabled={!canManageConversation || !canLink}
                >
                  Gỡ đặt bàn
                </Button>
              ) : null}
              {linkedWaitingListId ? (
                <Button
                  danger
                  onClick={() => void handleUnlinkConversationLink('waiting-list')}
                  loading={unlinkWaitingListMutation.isPending}
                  disabled={!canManageConversation || !canLink}
                >
                  Gỡ khách chờ
                </Button>
              ) : null}
            </Space>
          </div>

          <Descriptions bordered size="small" column={1} className="staff-conversation-linkage-grid">
            <Descriptions.Item label="Khách">{conversationCustomerLabel(detailConversation)}</Descriptions.Item>
            <Descriptions.Item label="Phụ trách">{assignmentAgentLabel(currentAssignment)}</Descriptions.Item>
            <Descriptions.Item label="Đặt bàn">{linkedReservationCode ?? (linkedReservationId ? `Đặt bàn #${linkedReservationId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Khách chờ">{linkedWaitingListLabel ?? (linkedWaitingListId ? `Khách chờ #${linkedWaitingListId}` : 'Chưa liên kết')}</Descriptions.Item>
            <Descriptions.Item label="Hoạt động mới">{formatDateTime(detailConversation.latest_activity_at ?? detailConversation.created_at)}</Descriptions.Item>
          </Descriptions>

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
            <Input.TextArea value={noteDraft} rows={2} placeholder="Ghi chú cho bàn giao, rủi ro thời gian..." onChange={(event) => setNoteDraft(event.target.value)} disabled={!canAddInternalNote} />
            <Button type="primary" onClick={() => void handleAddNote()} disabled={!canAddInternalNote || noteDraft.trim() === ''} loading={addNoteMutation.isPending}>Thêm ghi chú</Button>
          </div>

          <div className="staff-conversation-composer staff-conversation-composer-reply">
            <Typography.Text strong>Xếp hàng phản hồi ra ngoài</Typography.Text>
            <Input.TextArea value={replyDraft} rows={3} placeholder="Nội dung phản hồi cần gửi cho khách..." onChange={(event) => setReplyDraft(event.target.value)} disabled={!replyState.canSend} />
            <Button type="primary" onClick={() => void handleReply()} disabled={!replyState.canSend || replyDraft.trim() === ''} loading={replyMutation.isPending}>Xếp hàng phản hồi</Button>
          </div>
        </Space>
      ) : null}
    </div>
  );

  return (
    <div data-testid="conversation-inbox-page" style={{ padding: '16px', background: '#f5f7fa', minHeight: '100%', width: '100%' }}>
      {main}
      <Drawer
        title="Chi tiết Hội thoại"
        placement="right"
        width={580}
        onClose={() => setDetailDrawerOpen(false)}
        open={detailDrawerOpen}
        destroyOnClose={false}
      >
        {side}
      </Drawer>
    </div>
  );

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
