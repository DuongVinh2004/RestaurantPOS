import { Typography } from 'antd';

export function DashboardSectionHeader({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <div className="staff-dashboard-section-header">
      <Typography.Title level={4}>{title}</Typography.Title>
      <Typography.Paragraph type="secondary">{description}</Typography.Paragraph>
    </div>
  );
}
