import { Button, Col, Form, Input, InputNumber, Modal, Row, Space, Typography } from 'antd';
import type { FormInstance } from 'antd';
import { InlineState } from '../../../../shared/ui/states/StateBlocks';

export type CreateWaitingValues = {
  guest_name: string;
  phone?: string;
  guest_count: number;
  user_id?: number;
  priority?: number;
  notes?: string;
};

export function WaitingCreateModal({
  open,
  form,
  submitting,
  branchId,
  defaultBranchId,
  onCancel,
  onSubmit,
}: {
  open: boolean;
  form: FormInstance<CreateWaitingValues>;
  submitting?: boolean;
  branchId?: number | null;
  defaultBranchId?: number | null;
  onCancel: () => void;
  onSubmit: (values: CreateWaitingValues) => void;
}) {
  return (
    <Modal
      title="Thêm lượt chờ"
      open={open}
      onCancel={submitting ? undefined : onCancel}
      footer={null}
      mask={{ closable: !submitting }}
      closable={!submitting}
    >
      <Space orientation="vertical" size={12} style={{ width: '100%' }}>
        <Typography.Text type="secondary">
          Thêm khách hàng vào danh sách chờ.
        </Typography.Text>
        <Form<CreateWaitingValues>
          form={form}
          layout="vertical"
          initialValues={{ guest_count: 2, priority: 0 }}
          onFinish={onSubmit}
        >
          <Form.Item name="guest_name" label="Tên khách" rules={[{ required: true, message: 'Nhập tên khách.' }]}>
            <Input autoComplete="name" placeholder="Tên khách đang chờ…" />
          </Form.Item>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="phone" label="Số điện thoại">
                <Input autoComplete="tel" inputMode="tel" placeholder="090…" type="tel" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="guest_count" label="Số khách" rules={[{ required: true, message: 'Nhập số khách.' }]}>
                <InputNumber min={1} max={30} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="user_id" label="Mã khách hàng liên kết">
                <InputNumber min={1} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="priority" label="Mức ưu tiên">
                <InputNumber min={-999} max={999} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="notes" label="Ghi chú">
            <Input.TextArea autoComplete="off" rows={3} placeholder="Ghi chú tiếp đón nếu cần…" />
          </Form.Item>
          <InlineState
            tone="info"
            eyebrow="Ngữ cảnh chi nhánh"
            title={`Đang dùng ngữ cảnh chi nhánh ${branchId ?? defaultBranchId ?? 'mặc định'} từ shell nhân viên.`}
            description="Lượt chờ mới sẽ được tạo theo branch context đang neo ở shell để dữ liệu list, board và đơn hàng tiếp theo luôn khớp nhau."
            className="staff-inline-note"
          />
          <Button type="primary" htmlType="submit" loading={submitting} block style={{ marginTop: 16 }}>
            Thêm lượt chờ
          </Button>
        </Form>
      </Space>
    </Modal>
  );
}
