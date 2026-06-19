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
          business_hours: editingBranch.business_hours ?? [],
          closure_windows: editingBranch.closure_windows ?? [],
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ is_active: true, currency: 'VND', timezone: 'Asia/Ho_Chi_Minh', business_hours: [], closure_windows: [] });
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

        <Typography.Title level={5} style={{ marginTop: 24 }}>Giờ mở cửa</Typography.Title>
        <Form.List name="business_hours">
          {(fields, { add, remove }) => (
            <>
              {fields.map(({ key, name, ...restField }) => (
                <Space key={key} style={{ display: 'flex', marginBottom: 8 }} align="baseline">
                  <Form.Item
                    {...restField}
                    name={[name, 'day_of_week']}
                    rules={[{ required: true, message: 'Nhập ngày' }]}
                  >
                    <Input type="number" min={1} max={7} placeholder="Ngày (1-7)" style={{ width: 100 }} />
                  </Form.Item>
                  <Form.Item
                    {...restField}
                    name={[name, 'periods']}
                    rules={[{ required: true }]}
                  >
                    <Form.List name={[name, 'periods']}>
                      {(pFields, { add: pAdd, remove: pRemove }) => (
                        <Space direction="vertical">
                          {pFields.map((pField) => (
                            <Space key={pField.key}>
                              <Form.Item
                                {...pField}
                                name={[pField.name, 'start_time']}
                                noStyle
                              >
                                <Input placeholder="08:00:00" style={{ width: 100 }} />
                              </Form.Item>
                              <Form.Item
                                {...pField}
                                name={[pField.name, 'end_time']}
                                noStyle
                              >
                                <Input placeholder="22:00:00" style={{ width: 100 }} />
                              </Form.Item>
                              <Button type="text" danger onClick={() => pRemove(pField.name)}>-</Button>
                            </Space>
                          ))}
                          <Button type="dashed" onClick={() => pAdd()} size="small">+ Thêm ca</Button>
                        </Space>
                      )}
                    </Form.List>
                  </Form.Item>
                  <Button type="text" danger onClick={() => remove(name)}>Xóa ngày</Button>
                </Space>
              ))}
              <Form.Item>
                <Button type="dashed" onClick={() => add()} block>
                  + Thêm lịch ngày
                </Button>
              </Form.Item>
            </>
          )}
        </Form.List>

        <Typography.Title level={5}>Lịch đóng cửa (Ngoại lệ)</Typography.Title>
        <Form.List name="closure_windows">
          {(fields, { add, remove }) => (
            <>
              {fields.map(({ key, name, ...restField }) => (
                <Space key={key} style={{ display: 'flex', marginBottom: 8 }} align="baseline">
                  <Form.Item
                    {...restField}
                    name={[name, 'start_local']}
                    rules={[{ required: true }]}
                  >
                    <Input placeholder="Từ (YYYY-MM-DD HH:mm)" />
                  </Form.Item>
                  <Form.Item
                    {...restField}
                    name={[name, 'end_local']}
                    rules={[{ required: true }]}
                  >
                    <Input placeholder="Đến (YYYY-MM-DD HH:mm)" />
                  </Form.Item>
                  <Form.Item
                    {...restField}
                    name={[name, 'reason']}
                  >
                    <Input placeholder="Lý do" />
                  </Form.Item>
                  <Button type="text" danger onClick={() => remove(name)}>Xóa</Button>
                </Space>
              ))}
              <Form.Item>
                <Button type="dashed" onClick={() => add()} block>
                  + Thêm ngoại lệ đóng cửa
                </Button>
              </Form.Item>
            </>
          )}
        </Form.List>

        {mutation.error ? (
          <Typography.Paragraph type="danger" data-testid="admin-error-alert">
            {formatApiError(mutation.error, 'Chưa lưu được chi nhánh.')}
          </Typography.Paragraph>
        ) : null}

        <Space style={{ width: '100%', justifyContent: 'flex-end', marginTop: 16 }}>
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
