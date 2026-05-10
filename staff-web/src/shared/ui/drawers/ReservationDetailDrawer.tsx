import { Button, Descriptions, Divider, Drawer, Space, Typography } from 'antd';
import type { ReservationEnvelope } from '../../api/sdk';
import type { ReservationActiveOrderEnvelope } from '../../api/staff-api';
import { formatDateTime } from '../../utils/format';
import {
  getReservationGuestLabel,
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../../domains/reservations/reservation-guest';
import { getReservationTableLabel } from '../../../domains/reservations/reservation-tables';
import { reservationTone } from '../../status/status';
import { StaffFacingAlert } from '../feedback/StaffFacingAlert';
import { StatusChip } from '../status/StatusChip';

type ReservationDetail = ReservationEnvelope['data'];

export function ReservationDetailDrawer({
  open,
  reservation,
  activeOrder,
  busy,
  onClose,
  onCheckIn,
  onCancelReservation,
  onOpenOrder,
  onOpenCheckout,
}: {
  open: boolean;
  reservation: ReservationDetail | null;
  activeOrder: ReservationActiveOrderEnvelope | null;
  busy?: boolean;
  onClose: () => void;
  onCheckIn?: () => void;
  onCancelReservation?: () => void;
  onOpenOrder?: () => void;
  onOpenCheckout?: () => void;
}) {
  const customerLabel = getReservationGuestLabel(reservation);
  const isSnapshotOnlyGuest = isReservationSnapshotOnlyGuest(reservation);
  const tableLabel = getReservationTableLabel(reservation);
  const footerActions = [
    onCheckIn ? (
      <Button key="check-in" type="primary" onClick={onCheckIn} loading={busy}>
        Nhan ban ngay
      </Button>
    ) : null,
    onCancelReservation ? (
      <Button key="cancel" danger onClick={onCancelReservation} loading={busy}>
        Huy dat ban
      </Button>
    ) : null,
  ].filter(Boolean);

  return (
    <Drawer
      title={reservation ? reservation.reservation_code : 'Chi tiet dat ban'}
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
      footer={reservation && footerActions.length > 0 ? (
        <div className="staff-drawer-footer">
          {footerActions}
        </div>
      ) : null}
    >
      {!reservation ? (
        <Typography.Text type="secondary">
          Chon mot dat ban de xem chi tiet.
        </Typography.Text>
      ) : (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <StaffFacingAlert
            tone="info"
            eyebrow="Triage nhanh"
            title={customerLabel}
            description={`Khung gio ${formatDateTime(reservation.start_time)} den ${formatDateTime(reservation.end_time)} • ${reservation.guest_count ?? 'Chua ro'} khach`}
            meta={`Ban hien tai: ${tableLabel}`}
          />

          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Khach">
              <Space wrap size={8}>
                <Typography.Text>{customerLabel}</Typography.Text>
                {isSnapshotOnlyGuest ? (
                  <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                ) : null}
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Bat dau">
              {formatDateTime(reservation.start_time)}
            </Descriptions.Item>
            <Descriptions.Item label="Ket thuc">
              {formatDateTime(reservation.end_time)}
            </Descriptions.Item>
            <Descriptions.Item label="So khach">
              {reservation.guest_count ?? 'Khong co'}
            </Descriptions.Item>
            <Descriptions.Item label="Ban">
              {tableLabel}
            </Descriptions.Item>
            <Descriptions.Item label="Phien ban thao tac">
              {reservation.row_version}
            </Descriptions.Item>
          </Descriptions>

          <StaffFacingAlert
            tone={activeOrder?.data.order ? 'success' : 'warning'}
            eyebrow="Luong tiep theo"
            title={activeOrder?.data.order ? `Da co don #${activeOrder.data.order.order_id}` : 'Chua co don dang phuc vu'}
            description={activeOrder?.data.order
              ? 'Co the mo thang don dang chay de tiep tuc mon, bep hoac thanh toan.'
              : 'Neu khach da ngoi ban, hay mo man hinh don hang de tao flow phuc vu ngay tu dat ban nay.'}
            actions={(
              <Space wrap>
                <Button type={activeOrder?.data.order ? 'primary' : 'default'} onClick={onOpenOrder}>
                  {activeOrder?.data.order ? 'Mo don dang phuc vu' : 'Mo man hinh don hang'}
                </Button>
                {activeOrder?.data.order && onOpenCheckout ? (
                  <Button onClick={onOpenCheckout}>
                    Mo thanh toan
                  </Button>
                ) : null}
              </Space>
            )}
          />

          <Divider style={{ margin: 0 }} />

          <div>
            <Typography.Text strong>Ghi nho truoc khi thao tac</Typography.Text>
            <div className="staff-mini-list" style={{ marginTop: 12 }}>
              <div className="staff-mini-list-item">
                <Typography.Text strong>Kiem tra ban gan</Typography.Text>
                <Typography.Text type="secondary">Doi chi nhanh hoac doi ban dang chon se lam goi y hien tai khong con dang tin cay.</Typography.Text>
              </div>
              <div className="staff-mini-list-item">
                <Typography.Text strong>Giu dung phien ban thao tac</Typography.Text>
                <Typography.Text type="secondary">Neu detail nay da cu, hay tai lai truoc khi nhan ban hoac huy dat ban de tranh ghi de thao tac moi hon.</Typography.Text>
              </div>
            </div>
          </div>
        </Space>
      )}
    </Drawer>
  );
}
