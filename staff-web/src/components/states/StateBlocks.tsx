import type { ReactNode } from 'react';
import { Alert, Empty, Result, Spin } from 'antd';

export function InlineError({
  message,
  extra,
}: {
  message: string;
  extra?: ReactNode;
}) {
  return (
    <Alert
      type="error"
      showIcon
      title={message}
      description={extra}
    />
  );
}

export function InlineLoading({ tip = 'Äang táº£i...' }: { tip?: string }) {
  return (
    <div className="staff-inline-loading">
      <Spin description={tip} />
    </div>
  );
}

export function EmptyBlock({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <Empty
      image={Empty.PRESENTED_IMAGE_SIMPLE}
      description={
        <div>
          <div className="staff-empty-title">{title}</div>
          <div className="staff-empty-description">{description}</div>
        </div>
      }
    />
  );
}

export function FullPageState({
  title,
  description,
  status,
  extra,
}: {
  title: string;
  description: string;
  status: '403' | '404' | '500' | 'warning' | 'info' | 'success';
  extra?: ReactNode;
}) {
  return (
    <Result
      status={status}
      title={title}
      subTitle={description}
      extra={extra}
    />
  );
}

