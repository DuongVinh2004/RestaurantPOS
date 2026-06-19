import { Modal, Button, Typography } from 'antd';
import { ExclamationCircleFilled } from '@ant-design/icons';

const { Text } = Typography;

export function ConflictResolutionModal({
  open,
  onReload,
  onClose,
}: {
  open: boolean;
  onReload: () => void;
  onClose: () => void;
}) {
  return (
    <Modal
      open={open}
      title={
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <ExclamationCircleFilled style={{ color: '#faad14', fontSize: 22 }} />
          <span>Xung đột dữ liệu (OCC)</span>
        </div>
      }
      onCancel={onClose}
      footer={[
        <Button key="cancel" onClick={onClose}>
          Đóng
        </Button>,
        <Button key="reload" type="primary" onClick={onReload}>
          Tải lại dữ liệu mới nhất
        </Button>,
      ]}
    >
      <div style={{ marginTop: 16 }}>
        <Text>
          Thao tác vừa rồi bị từ chối do <strong>dữ liệu đã bị thay đổi bởi người khác</strong> (hoặc trên thiết bị khác). 
          Hệ thống ngăn chặn để tránh ghi đè dữ liệu.
        </Text>
        <div style={{ marginTop: 16, padding: 12, background: '#fffbe6', border: '1px solid #ffe58f', borderRadius: 6 }}>
          Vui lòng tải lại trang để xem bản ghi mới nhất, sau đó thực hiện lại thao tác nếu vẫn cần thiết.
        </div>
      </div>
    </Modal>
  );
}
