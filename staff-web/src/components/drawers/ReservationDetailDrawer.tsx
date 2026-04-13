import { Button, Descriptions, Divider, Drawer, Space, Typography } from 'antd';
import type { ReservationEnvelope, StaffOrderReadEnvelope } from '../../core/api/sdk';
import { formatDateTime } from '../../core/utils/format';
import {
  getReservationGuestLabel,
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../core/utils/reservation-guest';
import { getReservationTableLabel } from '../../core/utils/reservation-tables';
import { reservationTone } from '../../core/utils/status';
import { StaffFacingAlert } from '../feedback/StaffFacingAlert';
import { StatusChip } from '../status/StatusChip';

type ReservationDetail = ReservationEnvelope['data'];

export function ReservationDetailDrawer({
  open,
  reservation,
  activeOrder,
  busy,
  onClose,
  onAssignBestFit,
  onAssignCurrentTable,
  onCheckIn,
  onCancelReservation,
  onOpenOrder,
  onOpenCheckout,
}: {
  open: boolean;
  reservation: ReservationDetail | null;
  activeOrder: StaffOrderReadEnvelope | null;
  busy?: boolean;
  onClose: () => void;
  onAssignBestFit?: () => void;
  onAssignCurrentTable?: () => void;
  onCheckIn?: () => void;
  onCancelReservation?: () => void;
  onOpenOrder?: () => void;
  onOpenCheckout?: () => void;
}) {
  const customerLabel = getReservationGuestLabel(reservation);
  const isSnapshotOnlyGuest = isReservationSnapshotOnlyGuest(reservation);
  const tableLabel = getReservationTableLabel(reservation);

  return (
    <Drawer
      title={reservation ? reservation.reservation_code : 'Chi tiết đặt bàn'}
      placement="right"
      styles={{ wrapper: { width: 500, maxWidth: '100vw' } }}
      open={open}
      onClose={onClose}
      extra={reservation ? (
        <Space wrap size={8}>
          <StatusChip label={reservation.status} tone={reservationTone(reservation.status)} />
          {isSnapshotOnlyGuest ? (
            <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
          ) : null}
        </Space>
      ) : null}
      footer={reservation ? (
        <div className="staff-drawer-footer">
          <Button onClick={onAssignBestFit} loading={busy} disabled={!onAssignBestFit}>
            Gán bàn tốt nhất
          </Button>
          <Button onClick={onAssignCurrentTable} loading={busy} disabled={!onAssignCurrentTable}>
            Dùng bàn đang chọn
          </Button>
          <Button type="primary" onClick={onCheckIn} loading={busy} disabled={!onCheckIn}>
            Nhận bàn ngay
          </Button>
          <Button danger onClick={onCancelReservation} loading={busy} disabled={!onCancelReservation}>
            Hủy đặt bàn
          </Button>
        </div>
      ) : null}
    >
      {!reservation ? (
        <Typography.Text type="secondary">
          Chọn một đặt bàn để xem chi tiết.
        </Typography.Text>
      ) : (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <StaffFacingAlert
            tone="info"
            eyebrow="Triage nhanh"
            title={customerLabel}
            description={`Khung giờ ${formatDateTime(reservation.start_time)} đến ${formatDateTime(reservation.end_time)} • ${reservation.guest_count ?? 'Chưa rõ'} khách`}
            meta={`Bàn hiện tại: ${tableLabel}`}
          />

          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Khách">
              <Space wrap size={8}>
                <Typography.Text>{customerLabel}</Typography.Text>
                {isSnapshotOnlyGuest ? (
                  <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                ) : null}
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Bắt đầu">
              {formatDateTime(reservation.start_time)}
            </Descriptions.Item>
            <Descriptions.Item label="Kết thúc">
              {formatDateTime(reservation.end_time)}
            </Descriptions.Item>
            <Descriptions.Item label="Số khách">
              {reservation.guest_count ?? 'Không có'}
            </Descriptions.Item>
            <Descriptions.Item label="Bàn">
              {tableLabel}
            </Descriptions.Item>
            <Descriptions.Item label="Phiên bản thao tác">
              {reservation.row_version}
            </Descriptions.Item>
          </Descriptions>

          <StaffFacingAlert
            tone={activeOrder ? 'success' : 'warning'}
            eyebrow="Luồng tiếp theo"
            title={activeOrder ? `Đã có đơn #${activeOrder.data.order.order_id}` : 'Chưa có đơn đang phục vụ'}
            description={activeOrder
              ? 'Có thể mở thẳng đơn đang chạy để tiếp tục món, bếp hoặc thanh toán.'
              : 'Nếu khách đã ngồi bàn, hãy mở màn hình đơn hàng để tạo flow phục vụ ngay từ đặt bàn này.'}
            actions={(
              <Space wrap>
                <Button type={activeOrder ? 'primary' : 'default'} onClick={onOpenOrder}>
                  {activeOrder ? 'Mở đơn đang phục vụ' : 'Mở màn hình đơn hàng'}
                </Button>
                {activeOrder && onOpenCheckout ? (
                  <Button onClick={onOpenCheckout}>
                    Mở thanh toán
                  </Button>
                ) : null}
              </Space>
            )}
          />

          <Divider style={{ margin: 0 }} />

          <div>
            <Typography.Text strong>Ghi nhớ trước khi thao tác</Typography.Text>
            <div className="staff-mini-list" style={{ marginTop: 12 }}>
              <div className="staff-mini-list-item">
                <Typography.Text strong>Kiểm tra bàn gán</Typography.Text>
                <Typography.Text type="secondary">Đổi chi nhánh hoặc đổi bàn đang chọn sẽ làm gợi ý hiện tại không còn đáng tin cậy.</Typography.Text>
              </div>
              <div className="staff-mini-list-item">
                <Typography.Text strong>Giữ đúng phiên bản thao tác</Typography.Text>
                <Typography.Text type="secondary">Nếu detail này đã cũ, hãy tải lại trước khi nhận bàn, gán bàn hoặc hủy đặt bàn để tránh ghi đè thao tác mới hơn.</Typography.Text>
              </div>
            </div>
          </div>
        </Space>
      )}
    </Drawer>
  );
}
