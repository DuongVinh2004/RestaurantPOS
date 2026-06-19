import { Badge, Tooltip } from 'antd';
import { CloudOutlined, CloudServerOutlined, DisconnectOutlined } from '@ant-design/icons';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';
import { useOfflineQueue } from '../../offline/useOfflineQueue';

export function OfflineStatusIndicator() {
  const isOnline = useOnlineStatus();
  const { queue } = useOfflineQueue();

  const pendingCount = queue.filter((q) => q.status === 'pending_sync' || q.status === 'draft').length;

  if (isOnline && pendingCount === 0) {
    return (
      <Tooltip title="Đang trực tuyến">
        <div style={{ display: 'flex', alignItems: 'center', color: '#52c41a', padding: '0 8px' }}>
          <CloudServerOutlined style={{ fontSize: 16 }} />
        </div>
      </Tooltip>
    );
  }

  if (isOnline && pendingCount > 0) {
    return (
      <Tooltip title={`Đang đồng bộ ${pendingCount} thao tác...`}>
        <div style={{ display: 'flex', alignItems: 'center', color: '#1677ff', padding: '0 8px', gap: 6 }}>
          <Badge count={pendingCount} size="small">
            <CloudOutlined style={{ fontSize: 16, color: '#1677ff' }} />
          </Badge>
          <span style={{ fontSize: 12, fontWeight: 500 }}>Đang đồng bộ</span>
        </div>
      </Tooltip>
    );
  }

  return (
    <Tooltip title={`Mất kết nối. Đã lưu ${pendingCount} thao tác cục bộ.`}>
      <div style={{ display: 'flex', alignItems: 'center', color: '#f5222d', padding: '0 8px', gap: 6 }}>
        <Badge count={pendingCount} size="small" color="#f5222d">
          <DisconnectOutlined style={{ fontSize: 16, color: '#f5222d' }} />
        </Badge>
        <span style={{ fontSize: 12, fontWeight: 500 }}>Ngoại tuyến</span>
      </div>
    </Tooltip>
  );
}
