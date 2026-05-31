import { Modal, Form, Input, Switch, Button, Space, Typography } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import {
  createAdminKitchenStation,
  updateAdminKitchenStation,
  type AdminKitchenStation,
  type AdminCreateKitchenStationPayload,
} from './settings-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type KitchenStationModalProps = {
  open: boolean;
  onClose: () => void;
  editingStation?: AdminKitchenStation | null;
};

export function KitchenStationModal({ open, onClose, editingStation }: KitchenStationModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      if (editingStation) {
        form.setFieldsValue({
          name: editingStation.name,
          description: editingStation.description ?? '',
          is_active: editingStation.is_active ?? true,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ is_active: true });
      }
    }
  }, [open, editingStation, form]);

  const mutation = useMutation({
    mutationFn: async (values: AdminCreateKitchenStationPayload) => {
      if (editingStation) {
        return updateAdminKitchenStation(editingStation.station_id, values);
      }
      return createAdminKitchenStation(values);
    },
    onSuccess: () => {
      toast.success(editingStation ? 'Cập nhật trạm bếp thành công' : 'Tạo trạm bếp thành công');
      void queryClient.invalidateQueries({ queryKey: ['admin-kitchen-stations'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi lưu trạm bếp'));
    },
  });

  return (
    <Modal
      title={editingStation ? 'Sửa trạm bếp' : 'Tạo trạm bếp mới'}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values as AdminCreateKitchenStationPayload)}
        data-testid="admin-kitchen-station-form"
      >
        <Form.Item
          label="Tên trạm bếp"
          name="name"
          rules={[
            { required: true, message: 'Vui lòng nhập tên trạm bếp' },
            { min: 1, max: 100, message: 'Tên từ 1 đến 100 ký tự' },
          ]}
        >
          <Input
            data-testid="admin-kitchen-station-name-input"
            placeholder="VD: Bếp nóng, Bếp nguội, Bar"
          />
        </Form.Item>
        <Form.Item label="Mô tả" name="description">
          <Input.TextArea rows={2} placeholder="Mô tả thêm về trạm bếp" />
        </Form.Item>
        <Form.Item label="Trạng thái" name="is_active" valuePropName="checked">
          <Switch
            data-testid="admin-kitchen-station-active-switch"
            checkedChildren="Đang hoạt động"
            unCheckedChildren="Tạm tắt"
          />
        </Form.Item>

        {mutation.error ? (
          <Typography.Paragraph type="danger">
            {formatApiError(mutation.error, 'Chưa lưu được trạm bếp.')}
          </Typography.Paragraph>
        ) : null}

        <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
          <Button onClick={onClose}>Hủy</Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={mutation.isPending}
            data-testid="admin-kitchen-station-save-button"
          >
            {editingStation ? 'Cập nhật trạm bếp' : 'Tạo trạm bếp'}
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
