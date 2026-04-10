import { Button, Descriptions, Divider, Drawer, Space, Typography } from 'antd';
import type { ReservationEnvelope, StaffOrderReadEnvelope } from '../../core/api/sdk';
import { formatDateTime } from '../../core/utils/format';
import { reservationTone } from '../../core/utils/status';
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
  onOpenOrder,
}: {
  open: boolean;
  reservation: ReservationDetail | null;
  activeOrder: StaffOrderReadEnvelope | null;
  busy?: boolean;
  onClose: () => void;
  onAssignBestFit?: () => void;
  onAssignCurrentTable?: () => void;
  onCheckIn?: () => void;
  onOpenOrder?: () => void;
}) {
  return (
    <Drawer
      title={reservation ? reservation.reservation_code : 'Chi tiáº¿t Ä‘áº·t bÃ n'}
      placement="right"
      size={440}
      open={open}
      onClose={onClose}
      extra={reservation ? <StatusChip label={reservation.status} tone={reservationTone(reservation.status)} /> : null}
    >
      {!reservation ? (
        <Typography.Text type="secondary">
          Chá»n má»™t Ä‘áº·t bÃ n Ä‘á»ƒ xem chi tiáº¿t.
        </Typography.Text>
      ) : (
        <Space orientation="vertical" size={16} style={{ width: '100%' }}>
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="KhÃ¡ch">
              {typeof reservation.user === 'object' && reservation.user && 'full_name' in reservation.user
                ? String(reservation.user.full_name ?? reservation.user.phone ?? 'KhÃ¡ch vÃ£ng lai')
                : 'KhÃ¡ch vÃ£ng lai'}
            </Descriptions.Item>
            <Descriptions.Item label="Báº¯t Ä‘áº§u">
              {formatDateTime(reservation.start_time)}
            </Descriptions.Item>
            <Descriptions.Item label="Káº¿t thÃºc">
              {formatDateTime(reservation.end_time)}
            </Descriptions.Item>
            <Descriptions.Item label="Sá»‘ khÃ¡ch">
              {reservation.guest_count ?? 'KhÃ´ng cÃ³'}
            </Descriptions.Item>
            <Descriptions.Item label="BÃ n">
              {Array.isArray(reservation.table_ids) && reservation.table_ids.length > 0
                ? reservation.table_ids.join(', ')
                : 'ChÆ°a gÃ¡n bÃ n'}
            </Descriptions.Item>
            <Descriptions.Item label="PhiÃªn báº£n dÃ²ng">
              {reservation.row_version}
            </Descriptions.Item>
          </Descriptions>

          <div>
            <Typography.Text strong>HÃ nh Ä‘á»™ng váº­n hÃ nh</Typography.Text>
            <div className="staff-action-row">
              <Button onClick={onAssignBestFit} loading={busy} disabled={!onAssignBestFit}>
                GÃ¡n bÃ n phÃ¹ há»£p nháº¥t
              </Button>
              <Button onClick={onAssignCurrentTable} loading={busy} disabled={!onAssignCurrentTable}>
                GÃ¡n bÃ n hiá»‡n táº¡i
              </Button>
              <Button type="primary" onClick={onCheckIn} loading={busy} disabled={!onCheckIn}>
                Nháº­n bÃ n
              </Button>
            </div>
          </div>

          <Divider style={{ margin: 0 }} />

          <div>
            <Typography.Text strong>Ngá»¯ cáº£nh Ä‘Æ¡n hÃ ng</Typography.Text>
            <div className="staff-order-callout">
              {activeOrder ? (
                <>
                  <Typography.Paragraph style={{ marginBottom: 8 }}>
                    ÄÆ¡n hÃ ng Ä‘ang phá»¥c vá»¥ #{activeOrder.data.order.order_id} Ä‘Ã£ Ä‘Æ°á»£c gáº¯n vá»›i Ä‘áº·t bÃ n nÃ y.
                  </Typography.Paragraph>
                  <Button type="primary" onClick={onOpenOrder}>
                    Má»Ÿ Ä‘Æ¡n Ä‘ang phá»¥c vá»¥
                  </Button>
                </>
              ) : (
                <>
                  <Typography.Paragraph style={{ marginBottom: 8 }}>
                    ChÆ°a cÃ³ Ä‘Æ¡n hÃ ng Ä‘ang phá»¥c vá»¥. Tiáº¿p tá»¥c sang mÃ n hÃ¬nh Ä‘Æ¡n hÃ ng Ä‘á»ƒ táº¡o má»›i tá»« ngá»¯ cáº£nh Ä‘áº·t bÃ n nÃ y.
                  </Typography.Paragraph>
                  <Button onClick={onOpenOrder}>
                    Má»Ÿ mÃ n hÃ¬nh Ä‘Æ¡n hÃ ng
                  </Button>
                </>
              )}
            </div>
          </div>
        </Space>
      )}
    </Drawer>
  );
}

