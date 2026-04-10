import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { ConversationsPage } from './ConversationsPage';
import { StaffSessionContext, type StaffSessionContextValue } from '../../app/session-context';
import { buildApiError, buildStaffSession } from '../../test/fixtures';

const apiMocks = vi.hoisted(() => ({
  listConversations: vi.fn(),
  getConversationDetail: vi.fn(),
  takeOverConversation: vi.fn(),
  addConversationInternalNote: vi.fn(),
  sendConversationOutboundReply: vi.fn(),
}));

vi.mock('../../core/api/staff-api', () => apiMocks);

describe('ConversationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('loads list/detail and keeps outbound reply disabled when the backend capability envelope says no', async () => {
    arrangeConversationFixtures({
      detail: buildConversationDetail({
        ai_assist: {
          status: 'disabled',
          fallback_reason: 'Conversation AI assist is disabled for this rollout. Use the canonical timeline instead.',
        },
        capabilities: {
          can_send_outbound_reply: false,
          outbound_reply: {
            supported: false,
            reason_code: 'assigned_to_other_staff',
          },
        },
      }),
    });

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(apiMocks.getConversationDetail).toHaveBeenCalledWith('conv-1', {
      message_limit: 20,
      event_limit: 12,
      include_closed_assignments: false,
    }));
    expect(screen.getByText('Reply locked')).toBeInTheDocument();
    expect(screen.getByText(/Assigned To Other Staff/i)).toBeInTheDocument();
    expect(screen.getByText(/AI disabled/i)).toBeInTheDocument();
    expect(screen.getByText(/canonical timeline/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Queue reply' })).toBeDisabled();
  });

  it('renders AI assist summary, actions, and risk flags when the backend detail envelope is ready', async () => {
    arrangeConversationFixtures();

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByText('AI assist')).toBeInTheDocument());
    expect(screen.getByText(/Reservation RES-77 needs follow-up/i)).toBeInTheDocument();
    expect(screen.getByText('Check reservation')).toBeInTheDocument();
    expect(screen.getByText('Update arrival note')).toBeInTheDocument();
    expect(screen.getByText('Time-sensitive follow-up')).toBeInTheDocument();
  });

  it('applies status, assignment-state, and search filters through the list query params', async () => {
    arrangeConversationFixtures();

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(apiMocks.listConversations).toHaveBeenCalledWith({ per_page: 16 }));

    fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'Pending' } });
    fireEvent.change(screen.getByLabelText('Assignment'), { target: { value: 'mine' } });
    fireEvent.change(screen.getByLabelText('Search query'), { target: { value: 'RES-77' } });
    fireEvent.click(screen.getByRole('button', { name: 'Apply filters' }));

    await waitFor(() =>
      expect(apiMocks.listConversations).toHaveBeenLastCalledWith({
        per_page: 16,
        status: 'Pending',
        assignment_state: 'mine',
        q: 'RES-77',
      }),
    );
  });

  it('takes over the selected conversation and refreshes the inbox state', async () => {
    arrangeConversationFixtures();

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Take over' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Take over' }));

    await waitFor(() => expect(apiMocks.takeOverConversation).toHaveBeenCalledWith('conv-1', {
      notes: 'Taken over from staff-web.',
    }));
    expect(apiMocks.listConversations).toHaveBeenCalledTimes(2);
    expect(apiMocks.getConversationDetail).toHaveBeenCalledTimes(2);
  });

  it('surfaces idempotency-required note failures with the request id', async () => {
    arrangeConversationFixtures();
    apiMocks.addConversationInternalNote.mockRejectedValue(
      buildApiError(422, {
        error_code: 'idempotency_key_required',
        message: 'Validation error.',
        request_id: 'req-note',
      }),
    );

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByText('Actionable thread')).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Internal note draft'), { target: { value: 'Need callback' } });
    fireEvent.click(screen.getByRole('button', { name: 'Add note' }));

    expect(await screen.findByText(/Thiếu khóa chống gửi lặp/i)).toBeInTheDocument();
    expect(screen.getByText(/req-note/i)).toBeInTheDocument();
  });

  it('queues outbound replies when the backend capability envelope allows it', async () => {
    arrangeConversationFixtures();

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByText('Actionable thread')).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Outbound reply draft'), { target: { value: 'Reply from queue' } });
    fireEvent.click(screen.getByRole('button', { name: 'Queue reply' }));

    await waitFor(() =>
      expect(apiMocks.sendConversationOutboundReply).toHaveBeenCalledWith('conv-1', {
        message_text: 'Reply from queue',
        related_reservation_id: 77,
      }),
    );
    expect(apiMocks.getConversationDetail).toHaveBeenCalledTimes(2);
  });

  it('surfaces forbidden reply errors with capability and request context', async () => {
    arrangeConversationFixtures();
    apiMocks.sendConversationOutboundReply.mockRejectedValue(
      buildApiError(403, {
        error_code: 'forbidden',
        message: 'Forbidden.',
        required_capability: 'conversation.manage',
        request_id: 'req-conv-403',
      }),
    );

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByText('Actionable thread')).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText('Outbound reply draft'), { target: { value: 'Reply from queue' } });
    fireEvent.click(screen.getByRole('button', { name: 'Queue reply' }));

    expect(await screen.findByText(/Quyền cần có: conversation.manage/i)).toBeInTheDocument();
    expect(screen.getByText(/req-conv-403/i)).toBeInTheDocument();
  });

  it('reloads conversation state after a take-over conflict', async () => {
    arrangeConversationFixtures();
    apiMocks.takeOverConversation.mockRejectedValue(
      buildApiError(409, {
        error_code: 'conflict',
        message: 'Conflict.',
      }),
    );

    renderWithConversationSession(createSessionContext());

    await waitFor(() => expect(screen.getByRole('button', { name: 'Take over' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Take over' }));

    await waitFor(() => expect(apiMocks.takeOverConversation).toHaveBeenCalledWith('conv-1', {
      notes: 'Taken over from staff-web.',
    }));
    expect(apiMocks.listConversations).toHaveBeenCalledTimes(2);
    expect(apiMocks.getConversationDetail).toHaveBeenCalledTimes(2);
  });

  it('expires the staff session when inbox bootstrap returns 401', async () => {
    arrangeConversationFixtures();
    const session = createSessionContext();
    apiMocks.listConversations.mockRejectedValueOnce(buildApiError(401, {
      error_code: 'unauthorized',
      message: 'Unauthorized.',
    }));

    renderWithConversationSession(session);

    await waitFor(() =>
      expect(session.expire).toHaveBeenCalledWith('Phien staff da het han. Dang nhap lai de tiep tuc.'),
    );
  });
});

function renderWithConversationSession(context: StaffSessionContextValue) {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <StaffSessionContext.Provider value={context}>
        <ConversationsPage />
      </StaffSessionContext.Provider>
    </MemoryRouter>,
  );
}

function arrangeConversationFixtures(overrides: { detail?: ReturnType<typeof buildConversationDetail> } = {}) {
  apiMocks.listConversations.mockResolvedValue(buildConversationList());
  apiMocks.getConversationDetail.mockResolvedValue(overrides.detail ?? buildConversationDetail());
  apiMocks.takeOverConversation.mockResolvedValue(undefined);
  apiMocks.addConversationInternalNote.mockResolvedValue(undefined);
  apiMocks.sendConversationOutboundReply.mockResolvedValue(undefined);
}

function buildConversationList() {
  return {
    data: [
      {
        conversation_id: 'conv-1',
        channel: 'Email',
        status: 'Open',
        created_at: '2026-04-07T09:00:00Z',
        latest_activity_at: '2026-04-07T09:05:00Z',
        user: {
          full_name: 'Nguyen Van A',
        },
        latest_message: {
          message_text: 'Guest asked to move arrival time.',
        },
        counts: {
          messages: 2,
          internal_notes: 0,
          events: 1,
          analyses: 0,
        },
        assignment_state: {
          is_assigned: true,
          is_unassigned: false,
          is_mine: false,
        },
      },
    ],
    meta: {
      total: 1,
    },
  };
}

function buildConversationDetail(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      conversation: {
        conversation_id: 'conv-1',
        channel: 'Email',
        status: 'Open',
        linked_reservation: {
          reservation_id: 77,
          reservation_code: 'RES-77',
        },
        counts: {
          messages: 2,
          internal_notes: 0,
          events: 1,
        },
      },
      messages: [
        {
          message_id: 10,
          sender: 'customer',
          message_text: 'Guest asked to move arrival time.',
          is_internal_note: false,
          created_at: '2026-04-07T09:00:00Z',
        },
      ],
      events: [],
      analyses: [],
      ai_assist: {
        status: 'ready',
        provider: 'local_heuristic',
        model: 'conversation-summary-v1',
        priority: 'high',
        summary: 'Reservation RES-77 needs follow-up. Latest thread signal: Guest asked to move arrival time. Guest is asking for a time or booking change.',
        suggested_actions: [
          {
            code: 'review_reservation',
            label: 'Check reservation',
            reason: 'Conversation is linked to reservation RES-77.',
          },
          {
            code: 'update_arrival_note',
            label: 'Update arrival note',
            reason: 'Latest guest signal suggests an arrival or booking change.',
          },
        ],
        risk_flags: [
          {
            code: 'time_sensitive',
            label: 'Time-sensitive follow-up',
            severity: 'high',
          },
        ],
        disclaimer: 'AI assist is optional. Verify the canonical conversation timeline before acting.',
        latency_budget_ms: 150,
        cost_tier: 'zero',
        generated_from: {
          message_count: 1,
          customer_message_count: 1,
          internal_note_count: 0,
          analysis_count: 0,
        },
      },
      assignment_history: [],
      capabilities: {
        can_send_outbound_reply: true,
        outbound_reply: {
          supported: true,
          channel: 'Email',
          delivery_mode: 'real',
          recipient_masked: 'ngu***@example.test',
        },
      },
      ...overrides,
    },
  };
}

function createSessionContext(overrides: Partial<StaffSessionContextValue> = {}): StaffSessionContextValue {
  return {
    session: buildStaffSession(),
    booting: false,
    notice: null,
    noticeTone: 'success',
    setAuthenticatedSession: vi.fn(),
    setNotice: vi.fn(),
    clearNotice: vi.fn(),
    refresh: vi.fn(),
    logout: vi.fn(),
    expire: vi.fn(),
    ...overrides,
  };
}
