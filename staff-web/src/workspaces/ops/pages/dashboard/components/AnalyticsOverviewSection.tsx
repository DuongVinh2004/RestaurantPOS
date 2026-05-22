import React from 'react';
import { Card, Col, Row, Statistic, Table, Typography, Spin, Alert } from 'antd';
import { useAnalyticsOverview } from '../../../../../domains/reporting/reporting-hub/analytics-overview-hook';

const { Title } = Typography;

export const AnalyticsOverviewSection: React.FC = () => {
  // Use last 30 days by default
  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - 30);
  
  const { data, isLoading, error } = useAnalyticsOverview(
    from.toISOString().split('T')[0],
    to.toISOString().split('T')[0]
  );

  if (isLoading) {
    return <Spin size="large" />;
  }

  if (error || !data) {
    return <Alert type="error" message="Failed to load analytics overview" />;
  }

  const { overview, top_items, payment_summary } = data;

  const itemColumns = [
    { title: 'Item', dataIndex: 'name', key: 'name' },
    { title: 'Qty Sold', dataIndex: 'quantity', key: 'quantity' },
  ];

  return (
    <div style={{ marginTop: 24, marginBottom: 24 }}>
      <Title level={4}>Advanced Analytics Overview (Last 30 Days)</Title>
      
      <Row gutter={[16, 16]}>
        <Col span={6}>
          <Card>
            <Statistic title="Total Revenue" value={overview.total_revenue} precision={0} prefix="₫" />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="Total Reservations" value={overview.total_reservations} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="Cancelled" value={overview.cancelled_count} valueStyle={{ color: '#cf1322' }} />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic title="No Show" value={overview.no_show_count} valueStyle={{ color: '#fa8c16' }} />
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col span={12}>
          <Card title="Top Selling Items">
            <Table
              dataSource={top_items}
              columns={itemColumns}
              rowKey="name"
              pagination={false}
              size="small"
            />
          </Card>
        </Col>
        <Col span={12}>
          <Card title="Payment Summary">
            <Table
              dataSource={payment_summary}
              columns={[
                { title: 'Method', dataIndex: 'method', key: 'method' },
                { title: 'Amount', dataIndex: 'amount', key: 'amount', render: (val) => `₫${val.toLocaleString()}` },
              ]}
              rowKey="method"
              pagination={false}
              size="small"
            />
          </Card>
        </Col>
      </Row>
    </div>
  );
};
