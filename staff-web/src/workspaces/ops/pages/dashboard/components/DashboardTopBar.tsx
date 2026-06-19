import { Button, Typography } from 'antd';
import { RefreshCcw } from 'lucide-react';
import type { DashboardFocusModel } from '../dashboard-view-model';

export function DashboardTopBar({
  focus,
  onRefresh,
  refreshing = false,
}: {
  focus: DashboardFocusModel;
  onRefresh: () => void | Promise<void>;
  refreshing?: boolean;
}) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
      <div>
        <Typography.Title level={2} style={{ margin: 0 }}>
          {focus.title}
        </Typography.Title>
      </div>
      <div>
        <Button icon={<RefreshCcw size={16} />} loading={refreshing} onClick={() => void onRefresh()}>
          Làm mới
        </Button>
      </div>
    </div>
  );
}
