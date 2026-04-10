import { describe, expect, it } from 'vitest';
import type { StaffConversationDetailEnvelope, StaffConversationSummary } from '../../core/api/sdk';
import {
  assignmentAgentLabel,
  buildConversationInboxSearch,
  conversationBranchLabel,
  conversationReservationId,
  conversationSummaryStats,
  conversationTitle,
  outboundReplyState,
  readConversationInboxUrlState,
} from './conversation-inbox';

function makeConversation(overrides: Partial<StaffConversationSummary> = {}): StaffConversationSummary {
  return {
    conversation_id: 'conv-001',
    branch_id: 4,
    status: 'Open',
    channel: 'WebChat',
    counts: {
      messages: 2,
      internal_notes: 1,
      events: 1,
      analyses: 1,
    },
    assignment_state: {
      is_assigned: false,
      is_unassigned: true,
      is_mine: false,
    },
    ...overrides,
  };
}

describe('conversation inbox helpers', () => {
  it('prefers user name over fallback identifiers for the title', () => {
    const title = conversationTitle(makeConversation({
      user: { full_name: 'Nguyen Thu' },
      linked_reservation: { reservation_code: 'RSV-100' },
    }));

    expect(title).toBe('Nguyen Thu');
  });

  it('reads branch and reservation metadata from record payloads', () => {
    const conversation = makeConversation({
      branch: { branch_name: 'District 1' },
      linked_reservation: { reservation_id: 99 },
    });

    expect(conversationBranchLabel(conversation)).toBe('District 1');
    expect(conversationReservationId(conversation)).toBe(99);
  });

  it('parses summary counters with safe zero fallbacks', () => {
    expect(conversationSummaryStats({ total: 8, assigned: 5, mine: 2 })).toEqual({
      total: 8,
      assigned: 5,
      unassigned: 0,
      mine: 2,
    });
  });

  it('reads outbound reply capability from the detail envelope', () => {
    const state = outboundReplyState({
      conversation: makeConversation(),
      messages: [],
      events: [],
      analyses: [],
      ai_assist: {
        status: 'ready',
        feature_key: 'staff.conversation_ai_assist',
        summary: null,
        suggested_actions: [],
        risk_flags: [],
        disclaimer: 'Source of truth remains the thread.',
        generated_from: {
          message_count: 0,
          customer_message_count: 0,
          internal_note_count: 0,
          analysis_count: 0,
        },
      },
      assignment_history: [],
      capabilities: {
        can_send_outbound_reply: true,
        outbound_reply: {
          supported: true,
          reason_code: null,
          channel: 'Email',
          delivery_mode: 'real',
          recipient_masked: 'n***@example.test',
        },
      },
    } satisfies StaffConversationDetailEnvelope['data']);

    expect(state).toEqual({
      canSend: true,
      supported: true,
      reasonCode: null,
      channel: 'Email',
      deliveryMode: 'real',
      recipientMasked: 'n***@example.test',
    });
  });

  it('renders assignee fallback when agent profile is absent', () => {
    expect(assignmentAgentLabel({
      assignment_id: 7,
      conversation_id: 'conv-001',
      agent_user_id: 42,
      is_active: true,
    })).toBe('Nhân viên #42');
  });

  it('reads url-driven queue state with safe defaults', () => {
    expect(readConversationInboxUrlState('?status=Pending&assignment=mine&channel=Zalo&q=late%20guest&page=3&conversation=conv-007&tab=history')).toEqual({
      status: 'Pending',
      assignment: 'mine',
      channel: 'Zalo',
      q: 'late guest',
      page: 3,
      conversationId: 'conv-007',
      tab: 'history',
    });
  });

  it('preserves journey params when writing inbox url state', () => {
    expect(buildConversationInboxSearch(
      '?source=reservation&reservation_id=12',
      {
        status: 'Open',
        assignment: 'unassigned',
        channel: 'all',
        q: 'guest',
        page: 2,
        conversationId: 'conv-009',
        tab: 'ai',
      },
    )).toBe('source=reservation&reservation_id=12&status=Open&assignment=unassigned&q=guest&page=2&conversation=conv-009&tab=ai');
  });
});
