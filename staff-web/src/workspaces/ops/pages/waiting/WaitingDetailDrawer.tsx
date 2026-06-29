import { Button, Card, Descriptions, Drawer, Form, Input, InputNumber, Select, Space, Typography } from 'antd';
import type { FormInstance } from 'antd';
import type { StaffWaitingListEntry } from '../../../../shared/api/sdk';
import { InlineState, BranchPolicyState } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { translateUiCode } from '../../../../shared/utils/translation';
import { waitingTone, type StatusTone } from '../../../../shared/status/status';

export type NotifyWaitingValues = {
  table_id?: number;
  hold_minutes?: number;
};

export type SeatWaitingValues = {
  user_id?: number;
  service_minutes?: number;
  notes?: string;
};

export type CancelWaitingValues = {
  cancel_reason?: string;
};

export function waitingResponseTone(status: string | null | undefined): StatusTone {
  switch ((status ?? '').toLowerCase()) {
    case 'arrival_confirmed':
    case 'accepted':
      return 'success';
    case 'pending':
      return 'processing';
    case 'declined':
    case 'invite_expired':
      return 'warning';
    default:
      return 'default';
  }
}

export function WaitingDetailDrawer({
  open,
  selectedEntry,
  notifySupported,
  seatSupported,
  advanceSupported,
  selectedReleasedTable,
  boardAccess,
  availableTableOptions,
  notifyForm,
  seatForm,
  cancelForm,
  busy,
  onClose,
  onNotify,
  onSeat,
  onCancel,
  onAdvanceQueue,
}: {
  open: boolean;
  selectedEntry: StaffWaitingListEntry | null;
  notifySupported: boolean;
  seatSupported: boolean;
  advanceSupported: boolean;
  selectedReleasedTable: any;
  boardAccess: boolean;
  availableTableOptions: any[];
  notifyForm: FormInstance<NotifyWaitingValues>;
  seatForm: FormInstance<SeatWaitingValues>;
  cancelForm: FormInstance<CancelWaitingValues>;
  busy: boolean;
  onClose: () => void;
  onNotify: (values: NotifyWaitingValues) => void;
  onSeat: (values: SeatWaitingValues) => void;
  onCancel: (values: CancelWaitingValues) => void;
  onAdvanceQueue: () => void;
}) {
  return (
    <Drawer
      title="Chi tiết lượt chờ"
      placement="right"
      width={480}
      onClose={onClose}
      open={open}
      destroyOnClose
      maskClosable={!busy}
      closable={!busy}
    >
      {!selectedEntry ? null : (
        <Space orientation="vertical" size={16} style={{ width: '100%' }}>
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label="Khách">
              {selectedEntry.guest_name ?? `Lượt chờ #${selectedEntry.waiting_id}`}
            </Descriptions.Item>
            <Descriptions.Item label="Trạng thái">
              <Space>
                <StatusChip label={selectedEntry.status} tone={waitingTone(selectedEntry.status)} />
                <StatusChip label={selectedEntry.current_response_state} tone={waitingResponseTone(selectedEntry.current_response_state)} />
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Cửa sổ mời khách">
              {selectedEntry.invite_window.is_active
                ? `Còn ${selectedEntry.invite_window.seconds_remaining}s`
                : selectedEntry.invite_window.is_expired
                  ? 'Đã hết hạn'
                  : 'Chưa kích hoạt'}
            </Descriptions.Item>
            <Descriptions.Item label="Hành động gợi ý">
              {translateUiCode(selectedEntry.orchestration.recommended_action)}
            </Descriptions.Item>
            <Descriptions.Item label="Bàn vừa được nhả">
              {selectedReleasedTable?.table_code ?? 'Không áp dụng'}
            </Descriptions.Item>
            <Descriptions.Item label="Phiên bản dòng">
              {selectedEntry.row_version}
            </Descriptions.Item>
          </Descriptions>

          {notifySupported ? (
            <Card size="small" title="Báo khách">
              <Form<NotifyWaitingValues> form={notifyForm} layout="vertical" onFinish={onNotify}>
                {boardAccess && availableTableOptions.length > 0 ? (
                  <Form.Item
                    name="table_id"
                    label="Bàn còn trống"
                    rules={[{ required: true, message: 'Chọn bàn để giữ chỗ khi báo khách.' }]}
                  >
                    <Select
                      showSearch
                      optionFilterProp="label"
                      placeholder="Chọn một bàn còn trống"
                      options={availableTableOptions}
                    />
                  </Form.Item>
                ) : (
                  <>
                    <BranchPolicyState
                      title="Không lấy được gợi ý từ sơ đồ bàn"
                      description="Phiên hiện tại không đọc được sơ đồ bàn hoặc chưa có bàn phù hợp. Hãy nhập thủ công mã bàn để giữ chỗ khi báo khách."
                      className="staff-inline-note"
                    />
                    <Form.Item
                      name="table_id"
                      label="Mã bàn"
                      rules={[{ required: true, message: 'Nhập mã bàn.' }]}
                    >
                      <InputNumber min={1} style={{ width: '100%' }} />
                    </Form.Item>
                  </>
                )}
                <Form.Item name="hold_minutes" label="Số phút giữ chỗ">
                  <InputNumber min={1} max={60} style={{ width: '100%' }} />
                </Form.Item>
                <Button type="primary" htmlType="submit" loading={busy} block>
                  Báo khách hiện tại
                </Button>
              </Form>
            </Card>
          ) : null}

          {advanceSupported ? (
            <Card size="small" title="Đẩy hàng chờ">
              <Space orientation="vertical" size={12} style={{ width: '100%' }}>
                <Typography.Text type="secondary">
                  Gợi ý kết quả: {translateUiCode(selectedEntry.orchestration.advance_queue.resulting_action)}
                </Typography.Text>
                {selectedEntry.orchestration.advance_queue.next_candidate ? (
                  <InlineState
                    tone="info"
                    eyebrow="Ứng viên kế tiếp"
                    title={`Ứng viên kế tiếp: ${selectedEntry.orchestration.advance_queue.next_candidate.guest_name ?? selectedEntry.orchestration.advance_queue.next_candidate.waiting_id}`}
                    description={`Độ lệch chỗ ngồi ${selectedEntry.orchestration.advance_queue.next_candidate.capacity_fit.seat_delta}`}
                    className="staff-inline-note"
                  />
                ) : null}
                <Button
                  onClick={onAdvanceQueue}
                  disabled={!selectedEntry.orchestration.advance_queue.can_apply_now}
                  loading={busy}
                  block
                >
                  Đẩy hàng chờ
                </Button>
              </Space>
            </Card>
          ) : null}

          {seatSupported ? (
            <Card size="small" title="Xếp bàn và mở đơn hàng">
              {!selectedEntry.user_id ? (
                <InlineState
                  tone="warning"
                  eyebrow="Điều kiện vào bàn"
                  title="Cần có mã khách hàng trước khi xếp bàn"
                  description="Lượt chờ này chưa liên kết khách hàng. Hãy nhập mã khách hàng tại đây trước khi chuyển lượt chờ thành đặt bàn."
                  className="staff-inline-note"
                />
              ) : null}
              <Form<SeatWaitingValues> form={seatForm} layout="vertical" onFinish={onSeat}>
                {!selectedEntry.user_id ? (
                  <Form.Item
                    name="user_id"
                    label="Mã khách hàng"
                    rules={[{ required: true, message: 'Nhập mã khách hàng liên kết.' }]}
                  >
                    <InputNumber min={1} style={{ width: '100%' }} />
                  </Form.Item>
                ) : null}
                <Form.Item name="service_minutes" label="Số phút phục vụ">
                  <InputNumber min={30} max={480} style={{ width: '100%' }} />
                </Form.Item>
                <Form.Item name="notes" label="Ghi chú xếp bàn">
                  <Input.TextArea autoComplete="off" rows={3} placeholder="Ghi chú xếp bàn nếu cần…" />
                </Form.Item>
                <Button type="primary" htmlType="submit" loading={busy} block>
                  Xếp bàn và mở đơn hàng
                </Button>
              </Form>
            </Card>
          ) : null}

          <Card size="small" title="Hủy lượt chờ">
            <Form<CancelWaitingValues> form={cancelForm} layout="vertical" onFinish={onCancel}>
              <Form.Item name="cancel_reason" label="Lý do hủy">
                <Input.TextArea autoComplete="off" rows={2} placeholder="Lý do hủy nếu cần…" />
              </Form.Item>
              <Button danger htmlType="submit" loading={busy} block>
                Hủy lượt chờ
              </Button>
            </Form>
          </Card>
        </Space>
      )}
    </Drawer>
  );
}
