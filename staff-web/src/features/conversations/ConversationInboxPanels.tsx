import { Alert, Card, Descriptions, Empty, Space, Typography } from 'antd';
import type {
  StaffConversationAiAssist,
  StaffConversationAssignment,
  StaffConversationEvent,
  StaffConversationMessage,
} from '../../core/api/sdk';
import { formatDateTime, humanizeCode } from '../../core/utils/format';
import { type StatusTone } from '../../core/utils/status';
import { StatusChip } from '../../components/status/StatusChip';

export function MessageThread({ messages }: { messages: Array<StaffConversationMessage> }) {
  if (messages.length === 0) {
    return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có tin nhắn trong cửa sổ chi tiết hiện tại." />;
  }

  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      {messages.map((message) => (
        <Card
          key={message.message_id}
          size="small"
          type="inner"
          title={message.is_internal_note ? 'Ghi chú nội bộ' : messageSenderLabel(message)}
          extra={formatDateTime(message.created_at)}
        >
          <Space orientation="vertical" size={8} style={{ width: '100%' }}>
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              {message.message_text}
            </Typography.Paragraph>
            <Space wrap size={6}>
              <StatusChip label={humanizeCode(message.message_type)} tone="default" />
              {message.processing_status ? (
                <StatusChip label={humanizeCode(message.processing_status)} tone="processing" />
              ) : null}
              {message.related_reservation_id ? (
                <StatusChip label={`Đặt bàn #${message.related_reservation_id}`} tone="success" />
              ) : null}
            </Space>
            {message.files?.length ? (
              <div className="staff-mini-list">
                {message.files.map((file) => (
                  <Typography.Link key={file.file_id} href={file.file_url} target="_blank" rel="noreferrer">
                    Tệp #{file.file_id}
                  </Typography.Link>
                ))}
              </div>
            ) : null}
            {message.entities?.length ? (
              <Space wrap size={6}>
                {message.entities.map((entity) => (
                  <StatusChip
                    key={entity.message_entity_id}
                    label={`${humanizeCode(entity.entity_type)}: ${entity.entity_normalized ?? entity.entity_text}`}
                    tone="warning"
                  />
                ))}
              </Space>
            ) : null}
          </Space>
        </Card>
      ))}
    </Space>
  );
}

export function AiAssistPanel({ aiAssist }: { aiAssist?: StaffConversationAiAssist }) {
  if (!aiAssist) {
    return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có dữ liệu AI hỗ trợ cho hội thoại này." />;
  }

  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      <Alert
        type={aiAssist.status === 'ready' ? 'success' : aiAssist.status === 'unavailable' ? 'warning' : 'info'}
        showIcon
        title={`AI hỗ trợ: ${humanizeCode(aiAssist.status)}`}
        description={aiAssist.summary ?? aiAssist.fallback_reason ?? 'Chưa có tóm tắt AI cho hội thoại này.'}
      />

      <Descriptions bordered size="small" column={1}>
        <Descriptions.Item label="Nhà cung cấp">{aiAssist.provider ?? 'Không có'}</Descriptions.Item>
        <Descriptions.Item label="Mô hình">{aiAssist.model ?? 'Không có'}</Descriptions.Item>
        <Descriptions.Item label="Mức ưu tiên">
          <StatusChip label={humanizeCode(aiAssist.priority ?? 'normal')} tone={aiPriorityTone(aiAssist.priority)} />
        </Descriptions.Item>
        <Descriptions.Item label="Sinh từ dữ liệu">
          {`${aiAssist.generated_from.message_count} tin nhắn / ${aiAssist.generated_from.internal_note_count} ghi chú / ${aiAssist.generated_from.analysis_count} phân tích`}
        </Descriptions.Item>
      </Descriptions>

      {aiAssist.suggested_actions.length > 0 ? (
        <Card size="small" title="Hành động gợi ý">
          <div className="staff-mini-list">
            {aiAssist.suggested_actions.map((action) => (
              <div key={action.code} className="staff-mini-list-item">
                <Typography.Text strong>{action.label}</Typography.Text>
                <Typography.Text type="secondary">{action.reason ?? humanizeCode(action.code)}</Typography.Text>
              </div>
            ))}
          </div>
        </Card>
      ) : null}

      {aiAssist.risk_flags.length > 0 ? (
        <Card size="small" title="Cờ rủi ro">
          <Space wrap size={6}>
            {aiAssist.risk_flags.map((risk) => (
              <StatusChip key={risk.code} label={`${risk.label} (${humanizeCode(risk.severity)})`} tone={riskTone(risk.severity)} />
            ))}
          </Space>
        </Card>
      ) : null}

      <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
        {aiAssist.disclaimer}
      </Typography.Paragraph>
    </Space>
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
    return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có lịch sử phân công hoặc sự kiện trong cửa sổ chi tiết hiện tại." />;
  }

  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      {assignments.length > 0 ? (
        <Card size="small" title="Lịch sử phân công">
          <div className="staff-mini-list">
            {assignments.map((assignment) => (
              <div key={assignment.assignment_id} className="staff-mini-list-item">
                <Typography.Text strong>{assignmentAgentLabel(assignment)}</Typography.Text>
                <Typography.Text type="secondary">
                  {`${assignment.is_active ? 'Đang hiệu lực' : 'Đã nhả'} • ${formatDateTime(assignment.assigned_at)}${assignment.released_at ? ` -> ${formatDateTime(assignment.released_at)}` : ''}`}
                </Typography.Text>
              </div>
            ))}
          </div>
        </Card>
      ) : null}

      {events.length > 0 ? (
        <Card size="small" title="Sự kiện">
          <div className="staff-mini-list">
            {events.map((event) => (
              <div key={event.event_id} className="staff-mini-list-item">
                <Typography.Text strong>{humanizeCode(event.event_type)}</Typography.Text>
                <Typography.Text type="secondary">{`${eventActorLabel(event)} • ${formatDateTime(event.created_at)}`}</Typography.Text>
              </div>
            ))}
          </div>
        </Card>
      ) : null}
    </Space>
  );
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
