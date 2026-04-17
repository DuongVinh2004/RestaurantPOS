import type {
  GetV1StaffConversationsQueryParams,
  StaffConversationAssignment,
  StaffConversationCollectionEnvelope,
  StaffConversationDetailEnvelope,
  StaffConversationSummary,
} from '../../shared/api/sdk';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export type ConversationListItem = StaffConversationCollectionEnvelope['data'][number];
export type ConversationItem = ConversationListItem | StaffConversationSummary;

export type ConversationSummaryStats = {
  total: number;
  assigned: number;
  unassigned: number;
  mine: number;
};

export type OutboundReplyState = {
  canSend: boolean;
  supported: boolean;
  reasonCode: string | null;
  channel: string | null;
  deliveryMode: string | null;
  recipientMasked: string | null;
};

export type ConversationInboxTab = 'messages' | 'ai' | 'history';
export type ConversationStatusFilter = 'all' | NonNullable<GetV1StaffConversationsQueryParams['status']>;
export type ConversationAssignmentFilter = 'all' | NonNullable<GetV1StaffConversationsQueryParams['assignment_state']>;
export type ConversationChannelFilter = 'all' | NonNullable<GetV1StaffConversationsQueryParams['channel']>;

export type ConversationInboxUrlState = {
  status: ConversationStatusFilter;
  assignment: ConversationAssignmentFilter;
  channel: ConversationChannelFilter;
  q: string;
  page: number;
  conversationId: string | null;
  tab: ConversationInboxTab;
};

export function conversationTitle(item: ConversationItem): string {
  return (
    readString(item.user, 'full_name')
    ?? readString(item.user, 'phone')
    ?? readString(item.linked_reservation, 'reservation_code')
    ?? readString(item.linked_waiting_list, 'guest_name')
    ?? `Hội thoại ${item.conversation_id}`
  );
}

export function conversationCustomerLabel(item: ConversationItem): string {
  return (
    readString(item.user, 'full_name')
    ?? readString(item.user, 'phone')
    ?? readString(item.user, 'email')
    ?? 'Khách chưa xác định'
  );
}

export function conversationBranchLabel(item: ConversationItem): string {
  return (
    readString(item.branch, 'branch_name')
    ?? readString(item.branch, 'branch_code')
    ?? `Chi nhánh #${item.branch_id}`
  );
}

export function conversationReservationId(item: ConversationItem): number | null {
  return readNumber(item.linked_reservation, 'reservation_id');
}

export function conversationReservationCode(item: ConversationItem): string | null {
  return readString(item.linked_reservation, 'reservation_code');
}

export function conversationWaitingListId(item: ConversationItem): number | null {
  return readNumber(item.linked_waiting_list, 'waiting_id');
}

export function conversationWaitingLabel(item: ConversationItem): string | null {
  return (
    readString(item.linked_waiting_list, 'guest_name')
    ?? nullableNumberLabel(readNumber(item.linked_waiting_list, 'waiting_id'), 'Khách chờ')
  );
}

export function assignmentAgentLabel(assignment: StaffConversationAssignment | null | undefined): string {
  if (!assignment) {
    return 'Chưa phân công';
  }

  return readString(assignment.agent, 'full_name') ?? `Nhân viên #${assignment.agent_user_id}`;
}

export function conversationSummaryStats(summary: Record<string, unknown> | null | undefined): ConversationSummaryStats {
  return {
    total: readNumber(summary, 'total') ?? 0,
    assigned: readNumber(summary, 'assigned') ?? 0,
    unassigned: readNumber(summary, 'unassigned') ?? 0,
    mine: readNumber(summary, 'mine') ?? 0,
  };
}

export function outboundReplyState(detail: StaffConversationDetailEnvelope['data'] | null | undefined): OutboundReplyState {
  const capabilities = asRecord(detail?.capabilities);
  const reply = asRecord(capabilities?.outbound_reply);

  return {
    canSend: readBoolean(capabilities, 'can_send_outbound_reply') ?? false,
    supported: readBoolean(reply, 'supported') ?? false,
    reasonCode: readString(reply, 'reason_code'),
    channel: readString(reply, 'channel'),
    deliveryMode: readString(reply, 'delivery_mode'),
    recipientMasked: readString(reply, 'recipient_masked'),
  };
}

export function readConversationInboxUrlState(search: string | URLSearchParams): ConversationInboxUrlState {
  const params = toSearchParams(search);

  return {
    status: readEnumValue(params.get('status'), ['all', 'Open', 'Pending', 'Closed', 'Spam'], 'all'),
    assignment: readEnumValue(params.get('assignment'), ['all', 'assigned', 'unassigned', 'mine'], 'all'),
    channel: readEnumValue(params.get('channel'), ['all', 'WebChat', 'Facebook', 'Zalo', 'Whatsapp', 'Instagram', 'Line', 'Other'], 'all'),
    q: params.get('q')?.trim() ?? '',
    page: readPositivePage(params.get('page')),
    conversationId: readConversationId(params.get('conversation')),
    tab: readEnumValue(params.get('tab'), ['messages', 'ai', 'history'], 'messages'),
  };
}

export function buildConversationInboxSearch(
  currentSearch: string | URLSearchParams,
  patch: Partial<ConversationInboxUrlState>,
): string {
  const params = toSearchParams(currentSearch);
  const merged = {
    ...readConversationInboxUrlState(params),
    ...patch,
  } satisfies ConversationInboxUrlState;

  setOrDelete(params, 'status', merged.status !== 'all' ? merged.status : null);
  setOrDelete(params, 'assignment', merged.assignment !== 'all' ? merged.assignment : null);
  setOrDelete(params, 'channel', merged.channel !== 'all' ? merged.channel : null);
  setOrDelete(params, 'q', merged.q !== '' ? merged.q : null);
  setOrDelete(params, 'page', merged.page > 1 ? String(merged.page) : null);
  setOrDelete(params, 'conversation', merged.conversationId);
  setOrDelete(params, 'tab', merged.tab !== 'messages' ? merged.tab : null);

  return params.toString();
}

export function buildConversationWaitingListPath(waitingListId: number): string {
  const params = new URLSearchParams();
  params.set('focus', String(waitingListId));

  return `${staffRoutePaths.ops.waitingList}?${params.toString()}`;
}

function nullableNumberLabel(value: number | null, prefix: string): string | null {
  return typeof value === 'number' ? `${prefix} #${value}` : null;
}

function toSearchParams(search: string | URLSearchParams): URLSearchParams {
  if (search instanceof URLSearchParams) {
    return new URLSearchParams(search);
  }

  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}

function readString(source: unknown, key: string): string | null {
  const value = asRecord(source)?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function readNumber(source: unknown, key: string): number | null {
  const value = asRecord(source)?.[key];
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
  }

  return null;
}

function readBoolean(source: unknown, key: string): boolean | null {
  const value = asRecord(source)?.[key];
  return typeof value === 'boolean' ? value : null;
}

function readEnumValue<TValue extends string>(
  value: string | null,
  allowed: ReadonlyArray<TValue>,
  fallback: TValue,
): TValue {
  return value && allowed.includes(value as TValue) ? (value as TValue) : fallback;
}

function readPositivePage(value: string | null): number {
  if (!value) {
    return 1;
  }

  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
}

function readConversationId(value: string | null): string | null {
  return value && value.trim() !== '' ? value.trim() : null;
}

function setOrDelete(params: URLSearchParams, key: string, value: string | null): void {
  if (!value) {
    params.delete(key);
    return;
  }

  params.set(key, value);
}
