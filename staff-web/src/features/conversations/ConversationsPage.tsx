import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, MessageSquareText, RefreshCcw, SendHorizontal, ShieldCheck, Sparkles } from 'lucide-react';
import {
  addConversationInternalNote,
  getConversationDetail as loadConversationDetail,
  listConversations as loadConversations,
  sendConversationOutboundReply,
  takeOverConversation,
} from '../../core/api/staff-api';
import { isApiStatus } from '../../core/api/errors';
import { useStaffSession } from '../../app/session-context';
import { formatApiError, normalizeApiError } from '../../lib/api-errors';
import { asRecord, formatDateTime, humanizeCode, readBoolean, readNumber, readString } from '../../lib/format';
import type {
  GetV1StaffConversationsQueryParams,
  StaffConversationCollectionEnvelope,
  StaffConversationDetailEnvelope,
} from '../../api/sdk';
import { ActionButton, Banner, EmptyState, MetricCard, Panel, StatusPill } from '../../components/ui';

const conversationStatusOptions = ['all', 'Open', 'Pending', 'Closed', 'Spam'] as const;
const assignmentStateOptions = ['all', 'assigned', 'unassigned', 'mine'] as const;
const detailQuery = {
  message_limit: 20,
  event_limit: 12,
  include_closed_assignments: false,
} satisfies Parameters<typeof loadConversationDetail>[1];

export function ConversationsPage() {
  const { expire } = useStaffSession();
  const [conversations, setConversations] = useState<StaffConversationCollectionEnvelope | null>(null);
  const [detail, setDetail] = useState<StaffConversationDetailEnvelope | null>(null);
  const [selectedConversationId, setSelectedConversationId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<(typeof conversationStatusOptions)[number]>('all');
  const [assignmentStateFilter, setAssignmentStateFilter] = useState<(typeof assignmentStateOptions)[number]>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [listQuery, setListQuery] = useState<GetV1StaffConversationsQueryParams>({ per_page: 16 });
  const [noteDraft, setNoteDraft] = useState('');
  const [replyDraft, setReplyDraft] = useState('');
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const selectedConversation = useMemo(
    () => conversations?.data.find((conversation) => conversation.conversation_id === selectedConversationId) ?? null,
    [conversations, selectedConversationId],
  );
  const conversationCapabilities = asRecord(detail?.data.capabilities);
  const outboundReplyCapability = asRecord(conversationCapabilities?.outbound_reply);
  const canSendReply = readBoolean(conversationCapabilities, 'can_send_outbound_reply') ?? false;
  const outboundReplyChannel = readString(outboundReplyCapability, 'channel');
  const outboundReplyDeliveryMode = readString(outboundReplyCapability, 'delivery_mode');
  const outboundReplyReasonCode = readString(outboundReplyCapability, 'reason_code');
  const outboundReplyRecipient = readString(outboundReplyCapability, 'recipient_masked');
  const aiAssist = asRecord(detail?.data.ai_assist);
  const aiAssistStatus = readString(aiAssist, 'status');
  const aiAssistPriority = readString(aiAssist, 'priority');
  const aiAssistSummary = readString(aiAssist, 'summary');
  const aiAssistFallbackReason = readString(aiAssist, 'fallback_reason');
  const aiAssistProvider = readString(aiAssist, 'provider');
  const aiAssistCostTier = readString(aiAssist, 'cost_tier');
  const aiAssistLatencyBudget = readNumber(aiAssist, 'latency_budget_ms');
  const aiAssistActions = Array.isArray(aiAssist?.suggested_actions) ? aiAssist.suggested_actions : [];
  const aiAssistRiskFlags = Array.isArray(aiAssist?.risk_flags) ? aiAssist.risk_flags : [];
  const activeFilterCount = Number(statusFilter !== 'all') + Number(assignmentStateFilter !== 'all') + Number(searchQuery.trim() !== '');

  const handleError = useCallback(
    (cause: unknown, fallback: string) => {
      if (isApiStatus(cause, 401)) {
        expire('Phien staff da het han. Dang nhap lai de tiep tuc.');
        return;
      }

      setError(formatApiError(cause, fallback));
    },
    [expire],
  );

  const refreshList = useCallback(async (query: GetV1StaffConversationsQueryParams = listQuery) => {
    setBusyKey('refresh-list');
    setError(null);

    try {
      const nextList = await loadConversations(query);
      setConversations(nextList);
      setSelectedConversationId((current) =>
        current && nextList.data.some((conversation) => conversation.conversation_id === current)
          ? current
          : nextList.data[0]?.conversation_id ?? null,
      );
    } catch (cause) {
      handleError(cause, 'Khong tai duoc conversation inbox.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError, listQuery]);

  const refreshDetail = useCallback(async (conversationId: string) => {
    setBusyKey('refresh-detail');
    setError(null);

    try {
      const nextDetail = await loadConversationDetail(conversationId, detailQuery);
      setDetail(nextDetail);
      setNoteDraft('');
      setReplyDraft('');
    } catch (cause) {
      handleError(cause, 'Khong tai duoc conversation detail.');
    } finally {
      setBusyKey(null);
    }
  }, [handleError]);

  const applyFilters = useCallback(() => {
    setListQuery({
      per_page: 16,
      status: statusFilter === 'all' ? undefined : statusFilter,
      assignment_state: assignmentStateFilter === 'all' ? undefined : assignmentStateFilter,
      q: searchQuery.trim() || undefined,
    });
  }, [assignmentStateFilter, searchQuery, statusFilter]);

  useEffect(() => {
    void refreshList(listQuery);
  }, [listQuery, refreshList]);

  useEffect(() => {
    if (!selectedConversationId) {
      setDetail(null);
      return;
    }

    void refreshDetail(selectedConversationId);
  }, [refreshDetail, selectedConversationId]);

  return (
    <div className="space-y-6">
      <Panel>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="eyebrow">Conversation inbox</p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">List, take-over, internal note, outbound reply</h2>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
              Slice nay giu bind thang vao inbox backend that. Detail screen doc capability envelope cua conversation de khoa nut reply khi backend khong cho gui.
            </p>
          </div>
          <ActionButton onClick={applyFilters} busy={busyKey === 'refresh-list'} icon={<RefreshCcw className="h-4 w-4" />}>
            Refresh inbox
          </ActionButton>
        </div>
      </Panel>

      {notice ? <Banner tone="success">{notice}</Banner> : null}
      {error ? <Banner tone="error">{error}</Banner> : null}

      <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <Panel>
          <p className="eyebrow">Conversation list</p>
          <h3 className="text-xl font-semibold text-slate-950">Operational inbox</h3>
          <div className="mt-5 rounded-[24px] bg-slate-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">List filters</p>
                <p className="mt-1 text-sm text-slate-600">
                  Filter list bang query params canonical cua `GET /staff/conversations`, giu nguyen list/detail va workflow hien tai.
                </p>
              </div>
              <StatusPill value={activeFilterCount > 0 ? `${activeFilterCount} filter(s)` : 'No filters'} tone={activeFilterCount > 0 ? 'info' : 'neutral'} />
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-2">
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Status</span>
                <select
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value as (typeof conversationStatusOptions)[number])}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  {conversationStatusOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>
              <label className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Assignment</span>
                <select
                  value={assignmentStateFilter}
                  onChange={(event) => setAssignmentStateFilter(event.target.value as (typeof assignmentStateOptions)[number])}
                  className="mt-3 w-full bg-transparent text-sm outline-none"
                >
                  {assignmentStateOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <label className="mt-3 block rounded-2xl border border-slate-200 bg-white px-4 py-3">
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Search query</span>
              <input
                value={searchQuery}
                onChange={(event) => setSearchQuery(event.target.value)}
                className="mt-3 w-full bg-transparent text-sm outline-none"
                placeholder="Guest, reservation, phone, conversation..."
              />
            </label>

            <div className="mt-4">
              <ActionButton onClick={applyFilters} busy={busyKey === 'refresh-list'} icon={<RefreshCcw className="h-4 w-4" />}>
                Apply filters
              </ActionButton>
            </div>
          </div>
          {(conversations?.data ?? []).length > 0 ? (
            <div className="mt-5 space-y-3">
              {(conversations?.data ?? []).map((conversation) => (
                <button
                  key={conversation.conversation_id}
                  type="button"
                  onClick={() => setSelectedConversationId(conversation.conversation_id)}
                  className={`w-full rounded-[24px] border p-4 text-left transition ${
                    selectedConversationId === conversation.conversation_id
                      ? 'border-sky-300 bg-sky-50'
                      : 'border-slate-200 bg-white hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-slate-900">{conversationTitle(conversation)}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {conversation.channel} | {humanizeCode(conversation.status)} | {formatDateTime(conversation.latest_activity_at ?? conversation.created_at)}
                      </p>
                    </div>
                    <StatusPill value={conversation.assignment_state.is_mine ? 'Mine' : 'Shared'} tone={conversation.assignment_state.is_mine ? 'success' : 'neutral'} />
                  </div>
                  <p className="mt-3 text-sm text-slate-600">{conversation.latest_message?.message_text ?? 'Chua co latest message.'}</p>
                </button>
              ))}
            </div>
          ) : (
            <div className="mt-5">
              <EmptyState
                title="Khong co conversation nao khop filter"
                description="Thu noi rong status, assignment-state hoac search query roi apply filters lai."
              />
            </div>
          )}
        </Panel>

        <Panel>
          <p className="eyebrow">Conversation detail</p>
          <h3 className="text-xl font-semibold text-slate-950">Actionable thread</h3>
          {!selectedConversation || !detail ? (
            <div className="mt-4">
              <EmptyState
                title="Chua chon conversation"
                description="Chon mot dong tu inbox de load `GET /staff/conversations/{conversation_id}`."
              />
            </div>
          ) : (
            <>
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                  <StatusPill value={detail.data.conversation.channel} tone="info" />
                  <StatusPill value={humanizeCode(detail.data.conversation.status)} />
                  <StatusPill value={selectedConversation.assignment_state.is_mine ? 'Assigned to me' : 'Not mine'} tone={selectedConversation.assignment_state.is_mine ? 'success' : 'warning'} />
                </div>
                {!selectedConversation.assignment_state.is_mine ? (
                  <ActionButton onClick={handleTakeOver} busy={busyKey === 'take-over'} icon={<ShieldCheck className="h-4 w-4" />}>
                    Take over
                  </ActionButton>
                ) : null}
              </div>

              <div className="mt-5 grid gap-4 md:grid-cols-3">
                <MetricCard label="Messages" value={String(detail.data.conversation.counts.messages)} />
                <MetricCard label="Internal notes" value={String(detail.data.conversation.counts.internal_notes)} />
                <MetricCard label="Events" value={String(detail.data.conversation.counts.events)} />
              </div>

              <div className="mt-5 rounded-[28px] border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-sky-50/60 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">AI assist</p>
                    <p className="mt-1 text-sm leading-6 text-slate-600">
                      Optional summary layer cho operator. Timeline van la source of truth va fallback khong block thread.
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <StatusPill value={describeAiAssistStatus(aiAssistStatus)} tone={toneForAiAssistStatus(aiAssistStatus)} />
                    {aiAssistPriority ? <StatusPill value={`Priority ${humanizeCode(aiAssistPriority)}`} tone={aiAssistPriority === 'high' ? 'warning' : 'neutral'} /> : null}
                    {aiAssistProvider ? <StatusPill value={humanizeCode(aiAssistProvider)} tone="info" /> : null}
                  </div>
                </div>

                <div className="mt-4 rounded-[24px] bg-white/80 p-4">
                  <div className="flex items-start gap-3">
                    <Sparkles className="mt-0.5 h-4 w-4 text-sky-600" />
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-slate-900">Conversation summary</p>
                      <p className="mt-2 text-sm leading-6 text-slate-700">
                        {aiAssistSummary ?? aiAssistFallbackReason ?? 'AI assist chua co them summary cho thread nay.'}
                      </p>
                    </div>
                  </div>

                  {(aiAssistActions.length > 0 || aiAssistRiskFlags.length > 0) ? (
                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                      <div className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Suggested actions</p>
                        <div className="mt-3 space-y-3">
                          {aiAssistActions.map((action, index) => {
                            const item = asRecord(action);
                            const code = readString(item, 'code') ?? `action-${index}`;
                            const label = readString(item, 'label') ?? humanizeCode(code);
                            const reason = readString(item, 'reason');

                            return (
                              <div key={code} className="rounded-2xl bg-white px-4 py-3">
                                <p className="text-sm font-semibold text-slate-900">{label}</p>
                                {reason ? <p className="mt-1 text-sm leading-6 text-slate-600">{reason}</p> : null}
                              </div>
                            );
                          })}
                        </div>
                      </div>

                      <div className="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Risk flags</p>
                        {aiAssistRiskFlags.length > 0 ? (
                          <div className="mt-3 space-y-3">
                            {aiAssistRiskFlags.map((risk, index) => {
                              const item = asRecord(risk);
                              const code = readString(item, 'code') ?? `risk-${index}`;
                              const label = readString(item, 'label') ?? humanizeCode(code);
                              const severity = readString(item, 'severity') ?? 'low';

                              return (
                                <div key={code} className="flex items-start gap-3 rounded-2xl bg-white px-4 py-3">
                                  <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-500" />
                                  <div className="min-w-0">
                                    <p className="text-sm font-semibold text-slate-900">{label}</p>
                                    <p className="mt-1 text-xs font-medium uppercase tracking-[0.2em] text-slate-500">{severity}</p>
                                  </div>
                                </div>
                              );
                            })}
                          </div>
                        ) : (
                          <p className="mt-3 text-sm leading-6 text-slate-600">Khong co risk flag nao duoc goi y o layer assist nay.</p>
                        )}
                      </div>
                    </div>
                  ) : null}

                  <p className="mt-4 text-xs uppercase tracking-[0.2em] text-slate-500">
                    {aiAssistLatencyBudget ? `Latency budget ${aiAssistLatencyBudget}ms` : 'Latency budget n/a'}
                    {aiAssistCostTier ? ` | Cost ${humanizeCode(aiAssistCostTier)}` : ''}
                  </p>
                </div>
              </div>

              <div className="mt-5 space-y-3">
                {detail.data.messages.map((message) => (
                  <div
                    key={message.message_id}
                    className={`rounded-[24px] px-4 py-4 ${
                      message.is_internal_note ? 'border border-amber-200 bg-amber-50' : 'border border-slate-200 bg-white'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-3">
                      <p className="text-sm font-semibold text-slate-900">
                        {message.is_internal_note ? 'Internal note' : message.sender}
                      </p>
                      <span className="text-xs text-slate-500">{formatDateTime(message.created_at)}</span>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-slate-700">{message.message_text}</p>
                  </div>
                ))}
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-2">
                <div className="rounded-[24px] bg-slate-50 p-5">
                  <p className="text-sm font-semibold text-slate-900">Internal note</p>
                  <textarea
                    aria-label="Internal note draft"
                    value={noteDraft}
                    onChange={(event) => setNoteDraft(event.target.value)}
                    rows={4}
                    className="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none"
                  />
                  <div className="mt-4">
                    <ActionButton onClick={handleAddNote} busy={busyKey === 'note'} icon={<MessageSquareText className="h-4 w-4" />}>
                      Add note
                    </ActionButton>
                  </div>
                </div>

                <div className="rounded-[24px] bg-slate-50 p-5">
                  <p className="text-sm font-semibold text-slate-900">Outbound reply</p>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <StatusPill value={canSendReply ? 'Reply enabled' : 'Reply locked'} tone={canSendReply ? 'success' : 'warning'} />
                    {outboundReplyChannel ? <StatusPill value={`Channel ${outboundReplyChannel}`} tone="info" /> : null}
                    {outboundReplyDeliveryMode ? <StatusPill value={`Mode ${outboundReplyDeliveryMode}`} /> : null}
                  </div>
                  <p className="mt-3 text-sm leading-6 text-slate-600">
                    {describeOutboundReplyState(canSendReply, outboundReplyReasonCode)}
                    {outboundReplyRecipient ? ` Recipient: ${outboundReplyRecipient}.` : ''}
                  </p>
                  <textarea
                    aria-label="Outbound reply draft"
                    value={replyDraft}
                    onChange={(event) => setReplyDraft(event.target.value)}
                    rows={4}
                    disabled={!canSendReply}
                    className="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none disabled:cursor-not-allowed disabled:opacity-60"
                  />
                  <div className="mt-4">
                    <ActionButton
                      onClick={handleReply}
                      busy={busyKey === 'reply'}
                      disabled={!canSendReply}
                      icon={<SendHorizontal className="h-4 w-4" />}
                    >
                      Queue reply
                    </ActionButton>
                  </div>
                </div>
              </div>
            </>
          )}
        </Panel>
      </div>
    </div>
  );

  async function handleTakeOver() {
    if (!selectedConversationId) {
      return;
    }

    setBusyKey('take-over');
    setError(null);

    try {
      await takeOverConversation(selectedConversationId, { notes: 'Taken over from staff-web.' });
      setNotice(`Da take over ${selectedConversationId}.`);
      await refreshList();
      await refreshDetail(selectedConversationId);
    } catch (cause) {
      handleError(cause, 'Khong take over duoc conversation.');
      if (normalizeApiError(cause, '').kind === 'conflict') {
        await Promise.allSettled([refreshList(), refreshDetail(selectedConversationId)]);
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleAddNote() {
    if (!selectedConversationId || noteDraft.trim() === '') {
      return;
    }

    setBusyKey('note');
    setError(null);

    try {
      await addConversationInternalNote(selectedConversationId, {
        message_text: noteDraft.trim(),
        related_reservation_id: readNumber(detail?.data.conversation.linked_reservation, 'reservation_id'),
      });
      setNotice('Da them internal note.');
      await refreshDetail(selectedConversationId);
    } catch (cause) {
      handleError(cause, 'Khong the them internal note.');
      if (normalizeApiError(cause, '').kind === 'conflict') {
        await Promise.allSettled([refreshList(), refreshDetail(selectedConversationId)]);
      }
    } finally {
      setBusyKey(null);
    }
  }

  async function handleReply() {
    if (!selectedConversationId || replyDraft.trim() === '') {
      return;
    }

    setBusyKey('reply');
    setError(null);

    try {
      await sendConversationOutboundReply(selectedConversationId, {
        message_text: replyDraft.trim(),
        related_reservation_id: readNumber(detail?.data.conversation.linked_reservation, 'reservation_id'),
      });
      setNotice('Da queue outbound reply.');
      await refreshDetail(selectedConversationId);
    } catch (cause) {
      handleError(cause, 'Khong the queue outbound reply.');
      if (normalizeApiError(cause, '').kind === 'conflict') {
        await Promise.allSettled([refreshList(), refreshDetail(selectedConversationId)]);
      }
    } finally {
      setBusyKey(null);
    }
  }

}

function conversationTitle(
  item: StaffConversationCollectionEnvelope['data'][number] | StaffConversationDetailEnvelope['data']['conversation'],
) {
  return (
    readString(item.user, 'full_name') ??
    readString(item.user, 'phone') ??
    readString(item.linked_reservation, 'reservation_code') ??
    String(readNumber(item.linked_reservation, 'reservation_id') ?? item.conversation_id)
  );
}

function describeOutboundReplyState(canSendReply: boolean, reasonCode: string | null): string {
  if (canSendReply) {
    return 'Backend capability envelope dang cho phep queue outbound reply cho thread nay.';
  }

  if (reasonCode) {
    return `Backend dang khoa outbound reply voi ly do ${humanizeCode(reasonCode)}.`;
  }

  return 'Backend capability envelope dang khoa outbound reply cho thread nay.';
}

function describeAiAssistStatus(status: string | null): string {
  if (status === 'ready') {
    return 'AI ready';
  }

  if (status === 'disabled') {
    return 'AI disabled';
  }

  if (status === 'unavailable') {
    return 'AI unavailable';
  }

  return 'AI unknown';
}

function toneForAiAssistStatus(status: string | null): 'success' | 'warning' | 'info' | 'neutral' {
  if (status === 'ready') {
    return 'success';
  }

  if (status === 'disabled') {
    return 'neutral';
  }

  if (status === 'unavailable') {
    return 'warning';
  }

  return 'info';
}
