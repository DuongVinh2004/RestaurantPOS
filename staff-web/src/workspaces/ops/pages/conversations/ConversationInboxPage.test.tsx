import { App as AntdApp } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import type { StaffConversationDetailEnvelope, StaffConversationSummary } from '../../../../shared/api/sdk';
import { buildStaffSession } from '../../../../test/fixtures';
import { ConversationInboxPage } from './ConversationInboxPage';

const confirmActionMock = vi.hoisted(() => vi.fn(async () => true));
const apiMocks = vi.hoisted(() => ({
  addConversationInternalNote: vi.fn(),
  assignConversation: vi.fn(),
  getConversationDetail: vi.fn(),
  linkConversation: vi.fn(),
  listConversations: vi.fn(),
  sendConversationOutboundReply: vi.fn(),
  takeOverConversation: vi.fn(),
  unassignConversation: vi.fn(),
  unlinkConversationReservation: vi.fn(),
  unlinkConversationWaitingList: vi.fn(),
  updateConversationWorkflowState: vi.fn(),
}));

vi.mock('../../../../shared/api/staff-api', () => apiMocks);
vi.mock('../../../../shared/hooks/useConfirmAction', () => ({
  useConfirmAction: () => confirmActionMock,
}));

const initialAuthState = useAuthStore.getState();
const initialFlowState = useFlowStore.getState();

describe('ConversationInboxPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthStore.setState(initialAuthState, true);
    useFlowStore.setState(initialFlowState, true);
    useAuthStore.getState().setSession(buildStaffSession({
      capabilities: ['conversation.manage', 'reservation.manage', 'waiting_list.manage'],
      known_capabilities: ['conversation.manage', 'reservation.manage', 'waiting_list.manage'],
    }));

    apiMocks.listConversations.mockResolvedValue({
      data: [makeConversation()],
      meta: {
        current_page: 1,
        per_page: 15,
        total: 1,
        summary: {
          total: 1,
          assigned: 0,
          unassigned: 1,
          mine: 0,
          views: {
            unassigned: 1,
            overdue: 0,
            waiting_on_customer: 0,
            resolved_today: 0,
          },
        },
      },
    });
    apiMocks.getConversationDetail.mockResolvedValue(makeConversationDetail());
    apiMocks.assignConversation.mockResolvedValue({ data: { action: 'assigned', conversation: makeConversation() } });
    apiMocks.updateConversationWorkflowState.mockResolvedValue({ data: { action: 'workflow_state_updated', conversation: makeConversation() } });
    apiMocks.linkConversation.mockResolvedValue({ data: { action: 'linked', conversation: makeConversation() } });
  });

  it('posts assign, workflow and link payloads from the detail controls', async () => {
    renderWithProviders('/ops/conversations');

    expect(await screen.findByText('Phân công rõ nhân viên')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Staff user id nhận hội thoại'), { target: { value: '77' } });
    fireEvent.click(screen.getByRole('button', { name: 'Phân công' }));

    await waitFor(() => {
      expect(apiMocks.assignConversation).toHaveBeenCalledWith('conv-001', {
        agent_user_id: 77,
        notes: null,
      });
    });

    fireEvent.change(screen.getByLabelText('Lý do cập nhật workflow'), { target: { value: 'Đã kiểm tra thread' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cập nhật workflow' }));

    await waitFor(() => {
      expect(apiMocks.updateConversationWorkflowState).toHaveBeenCalledWith('conv-001', {
        workflow_state: 'Triaged',
        expected_workflow_state: 'Open',
        reason: 'Đã kiểm tra thread',
      });
    });

    fireEvent.change(screen.getByLabelText('Reservation id cần liên kết'), { target: { value: '91' } });
    fireEvent.change(screen.getByLabelText('Ghi chú liên kết hội thoại'), { target: { value: 'Khách hỏi đổi giờ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Liên kết' }));

    await waitFor(() => {
      expect(apiMocks.linkConversation).toHaveBeenCalledWith('conv-001', {
        reservation_id: 91,
        waiting_list_id: null,
        customer_user_id: null,
        notes: 'Khách hỏi đổi giờ',
      });
    });
  });

  it('passes workflow and inbox view filters from the url into the conversation list query', async () => {
    renderWithProviders('/ops/conversations?workflow_state=Resolved&inbox_view=resolved_today&assignment=mine&status=Closed');

    await waitFor(() => {
      expect(apiMocks.listConversations).toHaveBeenCalledWith(expect.objectContaining({
        status: 'Closed',
        workflow_state: 'Resolved',
        inbox_view: 'resolved_today',
        assignment_state: 'mine',
      }));
    });
  });

  it('defaults workflow mutation to the first backend-approved target for assigned conversations', async () => {
    apiMocks.getConversationDetail.mockResolvedValueOnce(makeConversationDetail({
      conversation: makeConversation({
        workflow: {
          state: 'Assigned',
          state_reason: 'assigned',
          state_changed_at: null,
          first_triaged_at: null,
          resolved_at: null,
          closed_at: null,
          is_terminal: false,
          allowed_actions: ['unassign', 'mark_pending_customer', 'resolve', 'close'],
        },
        assignment_state: {
          is_assigned: true,
          is_unassigned: false,
          is_mine: true,
        },
        active_assignment: {
          assignment_id: 11,
          conversation_id: 'conv-001',
          agent_user_id: 7,
          is_active: true,
        },
      }),
      capabilities: {
        can_assign: true,
        can_take_over: true,
        can_unassign: true,
        can_link: true,
        can_update_workflow_state: true,
        workflow_state_targets: ['PendingCustomer', 'Resolved', 'Closed'],
        can_add_internal_note: true,
        can_send_outbound_reply: false,
        outbound_reply: {
          supported: false,
          reason_code: 'unsupported_channel',
          channel: null,
          delivery_mode: null,
          recipient_masked: null,
        },
      },
    }));

    renderWithProviders('/ops/conversations');

    expect(await screen.findByText('Phân công rõ nhân viên')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Lý do cập nhật workflow'), { target: { value: 'Chuyển qua chờ khách' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cập nhật workflow' }));

    await waitFor(() => {
      expect(apiMocks.updateConversationWorkflowState).toHaveBeenCalledWith('conv-001', {
        workflow_state: 'PendingCustomer',
        expected_workflow_state: 'Assigned',
        reason: 'Chuyển qua chờ khách',
      });
    });
  });

  it('locks assignment, linkage and internal note controls for closed conversations', async () => {
    apiMocks.getConversationDetail.mockResolvedValueOnce(makeConversationDetail({
      conversation: makeConversation({
        status: 'Closed',
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
      }),
      capabilities: {
        can_assign: false,
        can_take_over: false,
        can_unassign: false,
        can_link: false,
        can_update_workflow_state: true,
        workflow_state_targets: ['Triaged'],
        can_add_internal_note: false,
        can_send_outbound_reply: false,
        outbound_reply: {
          supported: false,
          reason_code: 'conversation_closed',
          channel: null,
          delivery_mode: null,
          recipient_masked: null,
        },
      },
    }));

    renderWithProviders('/ops/conversations');

    expect(await screen.findByText('Phân công rõ nhân viên')).toBeInTheDocument();

    expect(screen.queryByRole('button', { name: 'Nhận xử lý' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('Staff user id nhận hội thoại')).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Phân công' })).toBeDisabled();
    expect(screen.getByLabelText('Reservation id cần liên kết')).toBeDisabled();
    expect(screen.getByPlaceholderText('Ghi chú cho bàn giao, rủi ro thời gian hoặc theo dõi đặt bàn.')).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Thêm ghi chú' })).toBeDisabled();
  });
});

function renderWithProviders(initialEntry: string) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <AntdApp>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[initialEntry]}>
          <Routes>
            <Route path="/ops/conversations" element={<ConversationInboxPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </AntdApp>,
  );
}

function makeConversation(overrides: Partial<StaffConversationSummary> = {}): StaffConversationSummary {
  return {
    conversation_id: 'conv-001',
    branch_id: 1,
    branch: { branch_name: 'Chi nhánh chính' },
    status: 'Open',
    workflow: {
      state: 'Open',
      state_reason: null,
      state_changed_at: null,
      first_triaged_at: null,
      resolved_at: null,
      closed_at: null,
      is_terminal: false,
      allowed_actions: ['assign', 'link', 'workflow_state'],
    },
    channel: 'WebChat',
    created_at: '2026-04-30T08:00:00Z',
    latest_activity_at: '2026-04-30T08:05:00Z',
    user: { full_name: 'Nguyen Thu', phone: '0909000000' },
    linked_reservation: {
      reservation_id: 90,
      reservation_code: 'RSV-90',
      row_version: 8,
    },
    linked_waiting_list: null,
    counts: {
      messages: 1,
      internal_notes: 0,
      events: 0,
      analyses: 0,
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

function makeConversationDetail(overrides: Partial<StaffConversationDetailEnvelope['data']> = {}): StaffConversationDetailEnvelope {
  const defaultCapabilities = {
    can_assign: true,
    can_take_over: true,
    can_unassign: false,
    can_link: true,
    can_update_workflow_state: true,
    workflow_state_targets: ['Triaged', 'PendingCustomer', 'Closed'],
    can_add_internal_note: true,
    can_send_outbound_reply: false,
    outbound_reply: {
      supported: false,
      reason_code: 'unsupported_channel',
      channel: null,
      delivery_mode: null,
      recipient_masked: null,
    },
  };

  return {
    data: {
      conversation: makeConversation(),
      messages: [],
      events: [],
      analyses: [],
      assignment_history: [],
      ai_assist: {
        status: 'unavailable',
        feature_key: 'staff.conversation_ai_assist',
        summary: null,
        suggested_actions: [],
        risk_flags: [],
        fallback_reason: 'Chưa có dữ liệu.',
        disclaimer: 'Nguồn sự thật vẫn là thread.',
        generated_from: {
          message_count: 0,
          customer_message_count: 0,
          internal_note_count: 0,
          analysis_count: 0,
        },
      },
      capabilities: defaultCapabilities,
      ...overrides,
    },
  };
}
