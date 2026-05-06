import { describe, expect, it } from 'vitest';
import type { StaffConversationDetailEnvelope, StaffConversationSummary } from '../../shared/api/sdk';
import { staffRoutePaths } from '../../app/router/workspace-paths';
import {
  assignmentAgentLabel,
  buildConversationInboxSearch,
  buildConversationWaitingListPath,
  conversationBranchLabel,
  conversationCanTakeOver,
  conversationCanUnassignMine,
  conversationInboxViewStats,
  conversationMutationCapabilities,
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
    workflow: {
      state: 'Unassigned',
      state_reason: null,
      state_changed_at: null,
      first_triaged_at: null,
      resolved_at: null,
      closed_at: null,
      is_terminal: false,
      allowed_actions: ['take_over'],
    },
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
    operational: {
      is_overdue: false,
      overdue_after_minutes: 15,
      queue_bucket: 'active',
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

  it('reads operational inbox view counters with safe zero fallbacks', () => {
    expect(conversationInboxViewStats({
      views: {
        unassigned: 3,
        overdue: 1,
        waiting_on_customer: 2,
      },
    })).toEqual({
      unassigned: 3,
      overdue: 1,
      waitingOnCustomer: 2,
      resolvedToday: 0,
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

  it('reads lifecycle-aware mutation capability targets from the detail envelope', () => {
    expect(conversationMutationCapabilities({
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
        can_assign: false,
        can_take_over: false,
        can_unassign: false,
        can_link: false,
        can_update_workflow_state: true,
        can_add_internal_note: false,
        workflow_state_targets: ['Triaged', 'Closed', 'Assigned'],
      },
    } satisfies StaffConversationDetailEnvelope['data'])).toEqual({
      canAssign: false,
      canTakeOver: false,
      canUnassign: false,
      canLink: false,
      canUpdateWorkflowState: true,
      canAddInternalNote: false,
      workflowStateTargets: ['Triaged', 'Closed'],
    });
  });

  it('filters bulk ownership actions away from resolved or closed conversations', () => {
    expect(conversationCanTakeOver(makeConversation({
      workflow: {
        state: 'Open',
        state_reason: 'open',
        state_changed_at: null,
        first_triaged_at: null,
        resolved_at: null,
        closed_at: null,
        is_terminal: false,
        allowed_actions: ['assign'],
      },
    }))).toBe(true);

    expect(conversationCanTakeOver(makeConversation({
      workflow: {
        state: 'Resolved',
        state_reason: 'resolved',
        state_changed_at: null,
        first_triaged_at: null,
        resolved_at: null,
        closed_at: null,
        is_terminal: true,
        allowed_actions: ['reopen'],
      },
    }))).toBe(false);

    expect(conversationCanUnassignMine(makeConversation({
      workflow: {
        state: 'Closed',
        state_reason: 'closed',
        state_changed_at: null,
        first_triaged_at: null,
        resolved_at: null,
        closed_at: null,
        is_terminal: true,
        allowed_actions: ['reopen'],
      },
      assignment_state: {
        is_assigned: true,
        is_unassigned: false,
        is_mine: true,
      },
    }))).toBe(false);
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
    expect(readConversationInboxUrlState('?status=Pending&workflow_state=Resolved&inbox_view=resolved_today&assignment=mine&channel=Zalo&q=late%20guest&page=3&conversation=conv-007&tab=history')).toEqual({
      status: 'Pending',
      workflowState: 'Resolved',
      inboxView: 'resolved_today',
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
        workflowState: 'Triaged',
        inboxView: 'overdue',
        assignment: 'unassigned',
        channel: 'all',
        q: 'guest',
        page: 2,
        conversationId: 'conv-009',
        tab: 'ai',
      },
    )).toBe('source=reservation&reservation_id=12&status=Open&workflow_state=Triaged&inbox_view=overdue&assignment=unassigned&q=guest&page=2&conversation=conv-009&tab=ai');
  });

  it('builds a waiting-list focus path for linked waiting entries', () => {
    expect(buildConversationWaitingListPath(51)).toBe(`${staffRoutePaths.ops.waitingList}?focus=51`);
  });
});
