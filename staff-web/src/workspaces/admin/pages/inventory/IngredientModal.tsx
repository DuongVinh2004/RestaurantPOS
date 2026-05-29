import { Modal, Form, Input, Switch, Button, Space } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useEffect } from 'react';
import { createAdminIngredient, updateAdminIngredient, AdminCreateIngredientPayload } from './inventory-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type IngredientModalProps = {
  open: boolean;
  onClose: () => void;
  editingIngredient?: any | null;
};

export function IngredientModal({ open, onClose, editingIngredient }: IngredientModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      if (editingIngredient) {
        form.setFieldsValue({
          name: editingIngredient.name,
          code: editingIngredient.code,
          unit_code: editingIngredient.unit_code,
          description: editingIngredient.description,
          is_active: editingIngredient.is_active ?? true,
        });
      } else {
        form.resetFields();
        form.setFieldsValue({ is_active: true });
      }
    }
  }, [open, editingIngredient, form]);

  const mutation = useMutation({
    mutationFn: async (values: AdminCreateIngredientPayload) => {
      if (editingIngredient) {
        return updateAdminIngredient(editingIngredient.ingredient_id, values);
      }
      return createAdminIngredient(values);
    },
    onSuccess: () => {
      toast.success(editingIngredient ? 'Cập nhật nguyên liệu thành công' : 'Tạo nguyên liệu thành công');
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredients'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi lưu nguyên liệu'));
    },
  });

  return (
    <Modal
      title={editingIngredient ? 'Sửa nguyên liệu' : 'Thêm nguyên liệu mới'}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values)}
        data-testid="inventory-ingredient-form"
      >
        <Form.Item
          label="Tên nguyên liệu"
          name="name"
          rules={[{ required: true, message: 'Vui lòng nhập tên nguyên liệu' }]}
        >
          <Input data-testid="inventory-ingredient-name-input" placeholder="VD: Cà chua" />
        </Form.Item>
        <Form.Item label="Mã nguyên liệu" name="code">
          <Input data-testid="inventory-ingredient-code-input" placeholder="VD: ING_TOMATO" />
        </Form.Item>
        <Form.Item
          label="Đơn vị tính"
          name="unit_code"
          rules={[{ required: true, message: 'Vui lòng nhập đơn vị' }]}
        >
          <Input data-testid="inventory-ingredient-unit-select" placeholder="VD: kg, gam, chai" />
        </Form.Item>
        <Form.Item label="Mô tả" name="description">
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
            data-testid="inventory-ingredient-save-button"
          >
            Lưu nguyên liệu
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
