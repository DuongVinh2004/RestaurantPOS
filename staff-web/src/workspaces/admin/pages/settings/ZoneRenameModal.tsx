import { Modal, Form, Input, Button, Space, Typography } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { renameAdminZone } from './settings-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type ZoneRenameModalProps = {
  open: boolean;
  onClose: () => void;
  currentZoneName: string;
};

export function ZoneRenameModal({ open, onClose, currentZoneName }: ZoneRenameModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      form.setFieldsValue({ from: currentZoneName, to: '' });
    }
  }, [open, currentZoneName, form]);

  const mutation = useMutation({
    mutationFn: async (values: { from: string; to: string }) => {
      return renameAdminZone(values.from, values.to);
    },
    onSuccess: () => {
      toast.success('Đổi tên khu vực thành công');
      void queryClient.invalidateQueries({ queryKey: ['admin-settings-zones'] });
      void queryClient.invalidateQueries({ queryKey: ['admin-settings-tables'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi đổi tên khu vực'));
    },
  });

  return (
    <Modal
      title="Đổi tên khu vực"
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values as { from: string; to: string })}
      >
        <Form.Item label="Tên hiện tại" name="from">
          <Input disabled />
        </Form.Item>
        <Form.Item
          label="Tên mới"
          name="to"
          rules={[
            { required: true, message: 'Vui lòng nhập tên mới' },
            { min: 1, max: 64, message: 'Tên khu vực từ 1 đến 64 ký tự' },
          ]}
        >
          <Input
            data-testid="admin-zone-name-input"
            placeholder="VD: Khu VIP"
            autoFocus
          />
        </Form.Item>

        {mutation.error ? (
          <Typography.Paragraph type="danger">
            {formatApiError(mutation.error, 'Chưa đổi được tên khu vực.')}
          </Typography.Paragraph>
        ) : null}

        <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
          <Button onClick={onClose}>Hủy</Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={mutation.isPending}
            data-testid="admin-zone-save-button"
          >
            Đổi tên
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
