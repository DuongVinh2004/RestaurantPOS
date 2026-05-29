import { Modal, Form, Input, Switch, Button, Space, Typography } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import {
  createAdminBranch,
  updateAdminBranch,
  type AdminBranch,
  type AdminCreateBranchPayload,
  type AdminUpdateBranchPayload,
} from './settings-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type BranchModalProps = {
  open: boolean;
  onClose: () => void;
  editingBranch?: AdminBranch | null;
};

export function BranchModal({ open, onClose, editingBranch }: BranchModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      if (editingBranch) {
        form.setFieldsValue({
          branch_code: editingBranch.branch_code,
          branch_name: editingBranch.branch_name,
          phone: editingBranch.phone ?? '',
          email: editingBranch.email ?? '',
          address: editingBranch.address ?? '',
          timezone: editingBranch.timezone ?? '',
          currency: editingBranch.currency ?? '',
          is_active: editingBranch.is_active ?? true,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ is_active: true, currency: 'VND', timezone: 'Asia/Ho_Chi_Minh' });
      }
    }
  }, [open, editingBranch, form]);

  const mutation = useMutation({
    mutationFn: async (values: AdminCreateBranchPayload) => {
      if (editingBranch) {
        const updatePayload: AdminUpdateBranchPayload = {
          ...values,
          row_version: editingBranch.row_version ?? 1,
        };
        return updateAdminBranch(editingBranch.branch_id, updatePayload);
      }
      return createAdminBranch(values);
    },
    onSuccess: () => {
      toast.success(editingBranch ? 'Cập nhật chi nhánh thành công' : 'Tạo chi nhánh thành công');
      void queryClient.invalidateQueries({ queryKey: ['admin-settings-branches'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi lưu chi nhánh'));
    },
  });

  return (
    <Modal
      title={editingBranch ? 'Sửa chi nhánh' : 'Tạo chi nhánh mới'}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
      width={560}
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values as AdminCreateBranchPayload)}
        data-testid="admin-branch-form"
      >
        <Form.Item
          label="Mã chi nhánh"
          name="branch_code"
          rules={[{ required: true, message: 'Vui lòng nhập mã chi nhánh' }]}
        >
          <Input
            data-testid="admin-branch-code-input"
            placeholder="VD: HN01"
            disabled={!!editingBranch}
          />
        </Form.Item>
        <Form.Item
          label="Tên chi nhánh"
          name="branch_name"
          rules={[{ required: true, message: 'Vui lòng nhập tên chi nhánh' }]}
        >
          <Input data-testid="admin-branch-name-input" placeholder="VD: Chi nhánh Hà Nội" />
        </Form.Item>
        <Form.Item
          label="Số điện thoại"
          name="phone"
          rules={[
            {
              pattern: /^(\+84|0)[0-9]{8,10}$|^$/,
              message: 'Số điện thoại không hợp lệ',
            },
          ]}
        >
          <Input data-testid="admin-branch-phone-input" placeholder="VD: 0901234567" />
        </Form.Item>
        <Form.Item
          label="Email"
          name="email"
          rules={[{ type: 'email', message: 'Email không hợp lệ' }]}
        >
          <Input data-testid="admin-branch-email-input" placeholder="VD: hanoi@restaurant.com" />
        </Form.Item>
        <Form.Item label="Địa chỉ" name="address">
          <Input placeholder="VD: 123 Phố Huế, Hà Nội" />
        </Form.Item>
        <Form.Item label="Múi giờ" name="timezone">
          <Input data-testid="admin-branch-timezone-input" placeholder="VD: Asia/Ho_Chi_Minh" />
        </Form.Item>
        <Form.Item label="Tiền tệ" name="currency">
          <Input placeholder="VD: VND" />
        </Form.Item>
        <Form.Item label="Trạng thái" name="is_active" valuePropName="checked">
          <Switch
            data-testid="admin-branch-active-switch"
            checkedChildren="Đang hoạt động"
            unCheckedChildren="Tạm tắt"
          />
        </Form.Item>

        {mutation.error ? (
          <Typography.Paragraph type="danger" data-testid="admin-error-alert">
            {formatApiError(mutation.error, 'Chưa lưu được chi nhánh.')}
          </Typography.Paragraph>
        ) : null}

        <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
          <Button onClick={onClose}>Hủy</Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={mutation.isPending}
            data-testid="admin-branch-save-button"
          >
            {editingBranch ? 'Cập nhật chi nhánh' : 'Tạo chi nhánh'}
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
