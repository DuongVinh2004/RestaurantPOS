import React from 'react';
import { Modal, Form, Input, Button, Typography, Space, InputNumber, Select, type FormInstance } from 'antd';
import { PayReservationDepositRequest } from '../../../../shared/api/staff-api';

export type ReservationDepositPayFormValues = Omit<PayReservationDepositRequest, 'row_version'>;

export function ReservationDepositPayModal({
  open,
  title,
  description,
  form,
  submitting,
  submitLabel,
  onCancel,
  onSubmit,
}: {
  open: boolean;
  title: string;
  description: string;
  form: FormInstance<ReservationDepositPayFormValues>;
  submitting?: boolean;
  submitLabel: string;
  onCancel: () => void;
  onSubmit: (values: ReservationDepositPayFormValues) => void;
}) {
  return (
    <Modal
      title={title}
      open={open}
      onCancel={submitting ? undefined : onCancel}
      footer={null}
      mask={{ closable: !submitting }}
      closable={!submitting}
    >
      <Space orientation="vertical" size={12} style={{ width: '100%' }}>
        <Typography.Text type="secondary">{description}</Typography.Text>
        <Form<ReservationDepositPayFormValues> form={form} layout="vertical" onFinish={onSubmit}>
          <Form.Item name="amount" label="Số tiền thanh toán" rules={[{ required: true, message: 'Nhập số tiền.' }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="payment_method" label="Phương thức thanh toán" rules={[{ required: true, message: 'Chọn phương thức thanh toán.' }]}>
            <Select
              options={[
                { value: 'BankTransfer', label: 'Chuyển khoản' },
                { value: 'Card', label: 'Thẻ' },
                { value: 'Cash', label: 'Tiền mặt' },
                { value: 'Other', label: 'Khác' },
              ]}
            />
          </Form.Item>
          <Form.Item name="payment_provider" label="Đối tác (VD: VNPay, MoMo)">
            <Input autoComplete="off" placeholder="Tùy chọn..." />
          </Form.Item>
          <Form.Item name="transaction_code" label="Mã giao dịch">
            <Input autoComplete="off" placeholder="Nhập mã giao dịch..." />
          </Form.Item>
          <Form.Item name="notes" label="Ghi chú">
            <Input.TextArea autoComplete="off" rows={2} placeholder="Ghi chú..." />
          </Form.Item>
          <div className="staff-modal-footer">
            <Button onClick={onCancel} disabled={submitting}>Đóng</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {submitLabel}
            </Button>
          </div>
        </Form>
      </Space>
    </Modal>
  );
}
