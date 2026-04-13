import { Button, Form, Input, InputNumber, Modal, Select, Space, Typography } from 'antd';
import type { FormInstance } from 'antd';
import type { ReservationCreateFormValues } from './reservation-create';

type ReservationCreateTableOption = {
  disabled?: boolean;
  label: string;
  value: number;
};

export function ReservationCreateModal({
  open,
  title,
  description,
  form,
  submitting,
  submitLabel,
  onCancel,
  onSubmit,
  tableOptions,
  tableLoading = false,
  lockedTableLabel,
}: {
  open: boolean;
  title: string;
  description: string;
  form: FormInstance<ReservationCreateFormValues>;
  submitting?: boolean;
  submitLabel: string;
  onCancel: () => void;
  onSubmit: (values: ReservationCreateFormValues) => void;
  tableOptions?: Array<ReservationCreateTableOption>;
  tableLoading?: boolean;
  lockedTableLabel?: string | null;
}) {
  return (
    <Modal title={title} open={open} onCancel={onCancel} footer={null}>
      <Space orientation="vertical" size={12} style={{ width: '100%' }}>
        <Typography.Text type="secondary">{description}</Typography.Text>
        {lockedTableLabel ? (
          <Typography.Text type="secondary">
            Bàn phục vụ: <strong>{lockedTableLabel}</strong>
          </Typography.Text>
        ) : null}
        <Form<ReservationCreateFormValues> form={form} layout="vertical" onFinish={onSubmit}>
          {tableOptions ? (
            <Form.Item
              name="table_ids"
              label="Bàn phục vụ"
              className="reservation-create-table-field"
              rules={[{ required: true, message: 'Chọn ít nhất một bàn phục vụ.' }]}
            >
              <Select
                mode="multiple"
                showSearch
                optionFilterProp="label"
                placeholder="Chọn một hoặc nhiều bàn để gán…"
                options={tableOptions}
                loading={tableLoading}
                popupMatchSelectWidth={false}
                maxTagCount="responsive"
              />
            </Form.Item>
          ) : null}
          <Form.Item name="guest_name" label="Tên khách" rules={[{ required: true, message: 'Nhập tên khách.' }]}>
            <Input autoComplete="name" placeholder="Khách gọi điện…" />
          </Form.Item>
          <Form.Item name="guest_phone" label="Số điện thoại" rules={[{ required: true, message: 'Nhập số điện thoại.' }]}>
            <Input autoComplete="tel" inputMode="tel" placeholder="090…" type="tel" />
          </Form.Item>
          <Form.Item
            name="guest_email"
            label="Email"
            rules={[{ type: 'email', message: 'Email không hợp lệ.' }]}
          >
            <Input autoComplete="email" placeholder="Không bắt buộc…" spellCheck={false} type="email" />
          </Form.Item>
          <Form.Item name="start_time_local" label="Bắt đầu lúc" rules={[{ required: true, message: 'Chọn giờ bắt đầu.' }]}>
            <Input autoComplete="off" type="datetime-local" />
          </Form.Item>
          <Form.Item name="guest_count" label="Số khách" rules={[{ required: true, message: 'Nhập số khách.' }]}>
            <InputNumber min={1} max={30} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item
            name="duration_minutes"
            label="Số phút giữ bàn"
            rules={[{ required: true, message: 'Nhập số phút giữ bàn.' }]}
          >
            <InputNumber min={30} max={480} step={15} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chú">
            <Input.TextArea autoComplete="off" rows={3} placeholder="Ví dụ: khách gọi trước, cần ghế em bé…" />
          </Form.Item>
          <div className="staff-modal-footer">
            <Button onClick={onCancel}>Đóng</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {submitLabel}
            </Button>
          </div>
        </Form>
      </Space>
    </Modal>
  );
}
