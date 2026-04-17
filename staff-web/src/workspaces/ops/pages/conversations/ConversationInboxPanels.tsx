import { Descriptions, Space, Typography } from 'antd';
import type {
  StaffConversationAiAssist,
  StaffConversationAssignment,
  StaffConversationEvent,
  StaffConversationMessage,
} from '../../../../shared/api/sdk';
import { formatDateTime, humanizeCode } from '../../../../shared/utils/format';
import { type StatusTone } from '../../../../shared/status/status';
import { EmptyBlock } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';

export function MessageThread({ messages }: { messages: Array<StaffConversationMessage> }) {
  if (messages.length === 0) {
    return (
      <EmptyBlock
        title="Chưa có tin nhắn trong cửa sổ này"
        description="Khi có trao đổi với khách, nội bộ hoặc hệ thống, thread sẽ hiện tại đây theo đúng vai trò gửi."
      />
    );
  }

  return (
    <div className="staff-conversation-thread">
      {messages.map((message) => (
        <article
          key={message.message_id}
          className={`staff-conversation-message staff-conversation-message-${messageType(message)}`}
        >
          <div className="staff-conversation-message-head">
            <div className="staff-conversation-message-author">
              <Typography.Text strong>{message.is_internal_note ? 'Ghi chú nội bộ' : messageSenderLabel(message)}</Typography.Text>
              <Typography.Text type="secondary">{formatDateTime(message.created_at)}</Typography.Text>
            </div>
            <Space wrap size={6}>
              <StatusChip label={humanizeCode(message.message_type)} tone="default" variant="freshness" />
              {message.processing_status ? (
                <StatusChip label={humanizeCode(message.processing_status)} tone="processing" variant="freshness" />
              ) : null}
              {message.related_reservation_id ? (
                <StatusChip label={`Đặt bàn #${message.related_reservation_id}`} tone="success" variant="entity" />
              ) : null}
            </Space>
          </div>

          <Typography.Paragraph className="staff-conversation-message-body">
            {message.message_text}
          </Typography.Paragraph>

          {message.files?.length ? (
            <div className="staff-conversation-message-links">
              {message.files.map((file) => (
                <Typography.Link key={file.file_id} href={file.file_url} target="_blank" rel="noreferrer">
                  Tệp #{file.file_id}
                </Typography.Link>
              ))}
            </div>
          ) : null}

          {message.entities?.length ? (
            <div className="staff-conversation-message-entities">
              {message.entities.map((entity) => (
                <StatusChip
                  key={entity.message_entity_id}
                  label={`${humanizeCode(entity.entity_type)}: ${entity.entity_normalized ?? entity.entity_text}`}
                  tone="warning"
                  variant="severity"
                />
              ))}
            </div>
          ) : null}
        </article>
      ))}
    </div>
  );
}

export function AiAssistPanel({ aiAssist }: { aiAssist?: StaffConversationAiAssist }) {
  if (!aiAssist) {
    return (
      <EmptyBlock
        title="AI chưa có dữ liệu hỗ trợ"
        description="Khi hệ thống sinh được tóm tắt hoặc gợi ý hành động, phần này sẽ tập trung chúng vào một khối riêng."
      />
    );
  }

  return (
    <div className="staff-conversation-ai-panel">
      <div className={`staff-conversation-ai-summary staff-conversation-ai-summary-${aiAssist.status}`}>
        <Typography.Text className="staff-eyebrow">AI hỗ trợ</Typography.Text>
        <Typography.Title level={4}>
          {aiAssist.summary ?? aiAssist.fallback_reason ?? 'Chưa có tóm tắt AI cho hội thoại này.'}
        </Typography.Title>
        <Space wrap size={6}>
          <StatusChip label={humanizeCode(aiAssist.status)} tone={aiAssist.status === 'ready' ? 'success' : aiAssist.status === 'unavailable' ? 'warning' : 'processing'} variant="severity" />
          <StatusChip label={humanizeCode(aiAssist.priority ?? 'normal')} tone={aiPriorityTone(aiAssist.priority)} variant="entity" />
        </Space>
      </div>

      <Descriptions bordered size="small" column={1} className="staff-conversation-ai-details">
        <Descriptions.Item label="Nhà cung cấp">{aiAssist.provider ?? 'Không có'}</Descriptions.Item>
        <Descriptions.Item label="Mô hình">{aiAssist.model ?? 'Không có'}</Descriptions.Item>
        <Descriptions.Item label="Sinh từ dữ liệu">
          {`${aiAssist.generated_from.message_count} tin nhắn / ${aiAssist.generated_from.internal_note_count} ghi chú / ${aiAssist.generated_from.analysis_count} phân tích`}
        </Descriptions.Item>
      </Descriptions>

      {aiAssist.suggested_actions.length > 0 ? (
        <div className="staff-conversation-ai-actions">
          <Typography.Text strong>Hành động gợi ý</Typography.Text>
          <div className="staff-mini-list">
            {aiAssist.suggested_actions.map((action) => (
              <div key={action.code} className="staff-mini-list-item staff-conversation-ai-action-item">
                <Typography.Text strong>{action.label}</Typography.Text>
                <Typography.Text type="secondary">{action.reason ?? humanizeCode(action.code)}</Typography.Text>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      {aiAssist.risk_flags.length > 0 ? (
        <div className="staff-conversation-ai-risks">
          <Typography.Text strong>Cờ rủi ro</Typography.Text>
          <Space wrap size={6}>
            {aiAssist.risk_flags.map((risk) => (
              <StatusChip key={risk.code} label={`${risk.label} (${humanizeCode(risk.severity)})`} tone={riskTone(risk.severity)} variant="severity" />
            ))}
          </Space>
        </div>
      ) : null}

      <Typography.Paragraph type="secondary" className="staff-conversation-ai-disclaimer">
        {aiAssist.disclaimer}
      </Typography.Paragraph>
    </div>
  );
}

export function HistoryPanel({
  assignments,
  events,
}: {
  assignments: Array<StaffConversationAssignment>;
  events: Array<StaffConversationEvent>;
}) {
  if (assignments.length === 0 && events.length === 0) {
    return (
      <EmptyBlock
        title="Chưa có lịch sử phân công hoặc sự kiện"
        description="Khi ownership hoặc sự kiện hệ thống thay đổi, timeline này sẽ giúp staff nhìn lại ngữ cảnh bàn giao."
      />
    );
  }

  return (
    <div className="staff-conversation-history-panel">
      {assignments.length > 0 ? (
        <div className="staff-conversation-history-section">
          <Typography.Text strong>Lịch sử phân công</Typography.Text>
          <div className="staff-conversation-timeline">
            {assignments.map((assignment) => (
              <div key={assignment.assignment_id} className="staff-conversation-timeline-item">
                <span className="staff-conversation-timeline-marker" aria-hidden="true" />
                <Typography.Text strong>{assignmentAgentLabel(assignment)}</Typography.Text>
                <Typography.Text type="secondary">
                  {`${assignment.is_active ? 'Đang hiệu lực' : 'Đã nhả'} • ${formatDateTime(assignment.assigned_at)}${assignment.released_at ? ` -> ${formatDateTime(assignment.released_at)}` : ''}`}
                </Typography.Text>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      {events.length > 0 ? (
        <div className="staff-conversation-history-section">
          <Typography.Text strong>Sự kiện</Typography.Text>
          <div className="staff-conversation-timeline">
            {events.map((event) => (
              <div key={event.event_id} className="staff-conversation-timeline-item">
                <span className="staff-conversation-timeline-marker" aria-hidden="true" />
                <Typography.Text strong>{humanizeCode(event.event_type)}</Typography.Text>
                <Typography.Text type="secondary">{`${eventActorLabel(event)} • ${formatDateTime(event.created_at)}`}</Typography.Text>
              </div>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}

function messageType(message: StaffConversationMessage): 'internal' | 'customer' | 'staff' | 'system' {
  if (message.is_internal_note) {
    return 'internal';
  }

  switch ((message.sender ?? '').toLowerCase()) {
    case 'customer':
    case 'guest':
      return 'customer';
    case 'staff':
    case 'agent':
      return 'staff';
    default:
      return 'system';
  }
}

function messageSenderLabel(message: StaffConversationMessage): string {
  return recordString(message.sender_user, 'full_name') ?? humanizeCode(message.sender);
}

function assignmentAgentLabel(assignment: StaffConversationAssignment): string {
  return recordString(assignment.agent, 'full_name') ?? `Nhân viên #${assignment.agent_user_id}`;
}

function eventActorLabel(event: StaffConversationEvent): string {
  return recordString(event.by_user, 'full_name') ?? (event.event_by_user_id ? `Người dùng #${event.event_by_user_id}` : 'Hệ thống');
}

function recordString(source: Record<string, unknown> | null | undefined, key: string): string | null {
  const value = source?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function aiPriorityTone(priority: StaffConversationAiAssist['priority']): StatusTone {
  switch (priority) {
    case 'high':
      return 'warning';
    case 'low':
      return 'default';
    default:
      return 'processing';
  }
}

function riskTone(severity: 'low' | 'medium' | 'high'): StatusTone {
  switch (severity) {
    case 'high':
      return 'error';
    case 'medium':
      return 'warning';
    default:
      return 'default';
  }
}
