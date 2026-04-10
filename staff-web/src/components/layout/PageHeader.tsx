import type { ReactNode } from 'react';
import { Space, Typography } from 'antd';

export function PageHeader({
  eyebrow,
  title,
  description,
  extra,
}: {
  eyebrow?: string;
  title: string;
  description: string;
  extra?: ReactNode;
}) {
  return (
    <div className="staff-page-header">
      <div>
        {eyebrow ? (
          <Typography.Text className="staff-eyebrow">
            {eyebrow}
          </Typography.Text>
        ) : null}
        <Typography.Title level={3} style={{ marginBottom: 8, marginTop: 8 }}>
          {title}
        </Typography.Title>
        <Typography.Paragraph type="secondary" style={{ marginBottom: 0, maxWidth: 760 }}>
          {description}
        </Typography.Paragraph>
      </div>
      {extra ? <Space wrap>{extra}</Space> : null}
    </div>
  );
}
