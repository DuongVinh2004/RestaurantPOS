import React, { useState } from 'react';
import {
  Alert,
  Badge,
  Button,
  Card,
  Col,
  Empty,
  Row,
  Select,
  Skeleton,
  Space,
  Statistic,
  Table,
  Tag,
  Tooltip,
  Typography,
} from 'antd';
import {
  ClockCircleOutlined,
  ExclamationCircleOutlined,
  ReloadOutlined,
  RightOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import type { ActionPriority, ActionType, CommandCenterAction } from '../../../../domains/ops/command-center/command-center-hook';
import { useCommandCenter } from '../../../../domains/ops/command-center/command-center-hook';

const { Title, Text } = Typography;
const { Option } = Select;

// ── Constants ────────────────────────────────────────────────────────────────

const TYPE_LABELS: Record<ActionType, string> = {
  reservation_upcoming: 'Upcoming',
  reservation_needs_check_in: 'Check-in Overdue',
  deposit_pending: 'Deposit Pending',
  deposit_expired: 'Deposit Expired',
  preorder_pending: 'Preorder Review',
  bill_payment_pending: 'Bill Payment',
  checkout_pending: 'Checkout',
  waiting_list_pending: 'Waiting List',
};

const TYPE_COLORS: Record<ActionType, string> = {
  reservation_upcoming: 'blue',
  reservation_needs_check_in: 'volcano',
  deposit_pending: 'orange',
  deposit_expired: 'red',
  preorder_pending: 'purple',
  bill_payment_pending: 'geekblue',
  checkout_pending: 'cyan',
  waiting_list_pending: 'default',
};

const PRIORITY_COLORS: Record<ActionPriority, string> = {
  high: 'red',
  normal: 'blue',
  low: 'default',
};

// ── Deep link resolver ───────────────────────────────────────────────────────

function resolveDeepLink(action: CommandCenterAction): string {
  const base = '/ops';
  const link = action.deep_link ?? '';
  if (link.startsWith('/reservations/')) return `${base}${link}`;
  if (link === '/waiting-list') return `${base}/waiting-list`;
  if (link.includes('/preorder')) return `${base}${link}`;
  return `${base}/reservations/${action.entity_id}`;
}

// ── Summary cards ────────────────────────────────────────────────────────────

interface SummaryCardsProps {
  summary: {
    open_actions: number;
    high_priority: number;
    deposit_pending: number;
    preorder_pending: number;
    payment_pending: number;
    reservation_upcoming: number;
  };
}

function SummaryCards({ summary }: SummaryCardsProps) {
  return (
    <Row gutter={[12, 12]}>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic
            title="Open Actions"
            value={summary.open_actions}
            prefix={<Badge status="processing" />}
          />
        </Card>
      </Col>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic
            title="High Priority"
            value={summary.high_priority}
            valueStyle={{ color: summary.high_priority > 0 ? '#cf1322' : undefined }}
            prefix={<ExclamationCircleOutlined />}
          />
        </Card>
      </Col>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic title="Deposit Pending" value={summary.deposit_pending} />
        </Card>
      </Col>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic title="Preorder Review" value={summary.preorder_pending} />
        </Card>
      </Col>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic title="Payment Pending" value={summary.payment_pending} />
        </Card>
      </Col>
      <Col xs={12} sm={8} lg={4}>
        <Card size="small">
          <Statistic title="Upcoming" value={summary.reservation_upcoming} />
        </Card>
      </Col>
    </Row>
  );
}

// ── Filter bar ────────────────────────────────────────────────────────────────

interface FilterBarProps {
  typeFilter: ActionType | undefined;
  priorityFilter: ActionPriority | undefined;
  horizonHours: number;
  onTypeChange: (v: ActionType | undefined) => void;
  onPriorityChange: (v: ActionPriority | undefined) => void;
  onHorizonChange: (v: number) => void;
  onRefresh: () => void;
  loading: boolean;
}

function FilterBar({
  typeFilter,
  priorityFilter,
  horizonHours,
  onTypeChange,
  onPriorityChange,
  onHorizonChange,
  onRefresh,
  loading,
}: FilterBarProps) {
  return (
    <Space wrap style={{ marginBottom: 12 }}>
      <Select
        allowClear
        placeholder="Filter by type"
        value={typeFilter}
        onChange={onTypeChange}
        style={{ minWidth: 180 }}
        size="small"
      >
        {(Object.entries(TYPE_LABELS) as [ActionType, string][]).map(([value, label]) => (
          <Option key={value} value={value}>{label}</Option>
        ))}
      </Select>

      <Select
        allowClear
        placeholder="Priority"
        value={priorityFilter}
        onChange={onPriorityChange}
        style={{ minWidth: 120 }}
        size="small"
      >
        <Option value="high">High</Option>
        <Option value="normal">Normal</Option>
        <Option value="low">Low</Option>
      </Select>

      <Select
        value={horizonHours}
        onChange={onHorizonChange}
        style={{ minWidth: 140 }}
        size="small"
      >
        <Option value={2}>Next 2 hrs</Option>
        <Option value={6}>Next 6 hrs</Option>
        <Option value={12}>Next 12 hrs</Option>
        <Option value={24}>Next 24 hrs</Option>
        <Option value={48}>Next 48 hrs</Option>
      </Select>

      <Button
        size="small"
        icon={<ReloadOutlined />}
        onClick={onRefresh}
        loading={loading}
      >
        Refresh
      </Button>
    </Space>
  );
}

// ── Action table ─────────────────────────────────────────────────────────────

interface ActionTableProps {
  actions: CommandCenterAction[];
  onNavigate: (path: string) => void;
}

function ActionTable({ actions, onNavigate }: ActionTableProps) {
  const columns = [
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      width: 160,
      render: (type: ActionType) => (
        <Tag color={TYPE_COLORS[type]}>{TYPE_LABELS[type] ?? type}</Tag>
      ),
    },
    {
      title: 'Priority',
      dataIndex: 'priority',
      key: 'priority',
      width: 90,
      render: (p: ActionPriority) => (
        <Tag color={PRIORITY_COLORS[p]}>{p.toUpperCase()}</Tag>
      ),
    },
    {
      title: 'Action',
      key: 'action',
      render: (_: unknown, row: CommandCenterAction) => (
        <div>
          <Text strong>{row.title}</Text>
          <br />
          <Text type="secondary" style={{ fontSize: 12 }}>{row.description}</Text>
        </div>
      ),
    },
    {
      title: 'Due',
      dataIndex: 'due_at',
      key: 'due_at',
      width: 110,
      render: (dueAt: string | null) =>
        dueAt ? (
          <Tooltip title={dueAt}>
            <Text style={{ fontSize: 12 }}>
              <ClockCircleOutlined style={{ marginRight: 4 }} />
              {new Date(dueAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}
            </Text>
          </Tooltip>
        ) : (
          <Text type="secondary" style={{ fontSize: 12 }}>—</Text>
        ),
    },
    {
      title: '',
      key: 'action_cta',
      width: 50,
      render: (_: unknown, row: CommandCenterAction) => (
        <Button
          size="small"
          type="text"
          icon={<RightOutlined />}
          onClick={() => onNavigate(resolveDeepLink(row))}
          aria-label={`Open ${row.title}`}
        />
      ),
    },
  ];

  if (actions.length === 0) {
    return (
      <Empty
        image={Empty.PRESENTED_IMAGE_SIMPLE}
        description="No pending actions. All operations are clear."
        style={{ padding: '32px 0' }}
      />
    );
  }

  return (
    <Table
      dataSource={actions}
      columns={columns}
      rowKey="id"
      size="small"
      pagination={false}
      rowClassName={(row) => row.priority === 'high' ? 'command-center-row-high' : ''}
    />
  );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export function CommandCenterPage() {
  const navigate = useNavigate();

  const [typeFilter, setTypeFilter] = useState<ActionType | undefined>(undefined);
  const [priorityFilter, setPriorityFilter] = useState<ActionPriority | undefined>(undefined);
  const [horizonHours, setHorizonHours] = useState<number>(24);

  const { data, isLoading, error, refetch, isFetching } = useCommandCenter({
    type: typeFilter,
    priority: priorityFilter,
    horizon_hours: horizonHours,
    limit: 50,
  });

  return (
    <div style={{ padding: '16px 20px' }}>
      {/* Header */}
      <div style={{ marginBottom: 16 }}>
        <Title level={4} style={{ margin: 0 }}>Operations Command Center</Title>
        <Text type="secondary" style={{ fontSize: 13 }}>
          Pending actions across all domains — auto-refreshes every 60 s
        </Text>
      </div>

      {/* Error state */}
      {error ? (
        <Alert
          type="error"
          message="Failed to load command center"
          description={error instanceof Error ? error.message : 'Unknown error'}
          style={{ marginBottom: 12 }}
          action={<Button size="small" onClick={() => refetch()}>Retry</Button>}
        />
      ) : null}

      {/* Summary cards */}
      {isLoading ? (
        <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
          {Array.from({ length: 6 }).map((_, i) => (
            <Col key={i} xs={12} sm={8} lg={4}>
              <Card size="small"><Skeleton.Input active size="small" style={{ width: '100%' }} /></Card>
            </Col>
          ))}
        </Row>
      ) : data ? (
        <div style={{ marginBottom: 16 }}>
          <SummaryCards summary={data.summary} />
        </div>
      ) : null}

      {/* Filter bar */}
      <FilterBar
        typeFilter={typeFilter}
        priorityFilter={priorityFilter}
        horizonHours={horizonHours}
        onTypeChange={setTypeFilter}
        onPriorityChange={setPriorityFilter}
        onHorizonChange={setHorizonHours}
        onRefresh={() => refetch()}
        loading={isFetching}
      />

      {/* Action list */}
      {isLoading ? (
        <Skeleton active paragraph={{ rows: 5 }} />
      ) : (
        <ActionTable
          actions={data?.actions ?? []}
          onNavigate={navigate}
        />
      )}
    </div>
  );
}
