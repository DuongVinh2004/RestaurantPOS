import { Modal, Form, Input, Switch, Button, Space } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useEffect } from 'react';
import { createAdminSupplier, updateAdminSupplier, AdminCreateSupplierPayload } from './inventory-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type SupplierModalProps = {
  open: boolean;
  onClose: () => void;
  editingSupplier?: any | null;
};

export function SupplierModal({ open, onClose, editingSupplier }: SupplierModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      if (editingSupplier) {
        form.setFieldsValue({
          name: editingSupplier.name,
          contact_name: editingSupplier.contact_name,
          phone: editingSupplier.phone,
          email: editingSupplier.email,
          notes: editingSupplier.notes,
          is_active: editingSupplier.is_active ?? true,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ is_active: true });
      }
    }
  }, [open, editingSupplier, form]);

  const mutation = useMutation({
    mutationFn: async (values: AdminCreateSupplierPayload) => {
      if (editingSupplier) {
        return updateAdminSupplier(editingSupplier.supplier_id, values);
      }
      return createAdminSupplier(values);
    },
    onSuccess: () => {
      toast.success(editingSupplier ? 'Cập nhật nhà cung cấp thành công' : 'Tạo nhà cung cấp thành công');
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-suppliers'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi lưu nhà cung cấp'));
    },
  });

  return (
    <Modal
      title={editingSupplier ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp mới'}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values)}
        data-testid="inventory-supplier-form"
      >
        <Form.Item
          label="Tên nhà cung cấp"
          name="name"
          rules={[{ required: true, message: 'Vui lòng nhập tên nhà cung cấp' }]}
        >
          <Input data-testid="inventory-supplier-name-input" placeholder="VD: Công ty Thực phẩm ABC" />
        </Form.Item>
        <Form.Item label="Người liên hệ" name="contact_name">
          <Input placeholder="Tên người đại diện" />
        </Form.Item>
        <Form.Item label="Số điện thoại" name="phone">
          <Input data-testid="inventory-supplier-phone-input" placeholder="SDT liên lạc" />
        </Form.Item>
        <Form.Item label="Email" name="email">
          <Input data-testid="inventory-supplier-email-input" placeholder="Email liên lạc" />
        </Form.Item>
        <Form.Item label="Ghi chú" name="notes">
          <Input.TextArea rows={2} />
        </Form.Item>
        <Form.Item label="Trạng thái" name="is_active" valuePropName="checked">
          <Switch checkedChildren="Đang dùng" unCheckedChildren="Tạm tắt" />
        </Form.Item>
        <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
          <Button onClick={onClose}>Hủy</Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={mutation.isPending}
            data-testid="inventory-supplier-save-button"
          >
            Lưu nhà cung cấp
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
