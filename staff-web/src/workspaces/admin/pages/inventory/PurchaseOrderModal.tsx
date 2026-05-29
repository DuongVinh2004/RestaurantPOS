import { Modal, Form, Input, Button, Space, Select, InputNumber } from 'antd';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useEffect } from 'react';
import { createAdminPurchaseOrder, AdminCreatePurchaseOrderPayload } from './inventory-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type PurchaseOrderModalProps = {
  open: boolean;
  onClose: () => void;
  suppliers: Array<{ supplier_id: number; name: string }>;
  ingredients: Array<{ ingredient_id: number; name: string; unit_code: string }>;
};

export function PurchaseOrderModal({ open, onClose, suppliers, ingredients }: PurchaseOrderModalProps) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      form.resetFields();
      form.setFieldsValue({
        lines: [{}],
      });
    }
  }, [open, form]);

  const mutation = useMutation({
    mutationFn: async (values: any) => {
      const payload: AdminCreatePurchaseOrderPayload = {
        supplier_id: values.supplier_id,
        order_code: values.order_code || null,
        expected_at: values.expected_at || null,
        notes: values.notes || null,
        lines: values.lines.map((l: any, index: number) => ({
          ingredient_id: l.ingredient_id,
          ordered_quantity: Number(l.ordered_quantity),
          unit_cost: l.unit_cost ? Number(l.unit_cost) : null,
          sort_order: index,
        })),
      };
      return createAdminPurchaseOrder(payload);
    },
    onSuccess: () => {
      toast.success('Tạo đơn mua hàng thành công');
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-purchase-orders'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi lưu đơn mua hàng'));
    },
  });

  return (
    <Modal
      title="Tạo đơn mua hàng mới"
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
      width={800}
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => mutation.mutate(values)}
        data-testid="inventory-po-form"
      >
        <div style={{ display: 'flex', gap: 16 }}>
          <Form.Item
            label="Nhà cung cấp"
            name="supplier_id"
            style={{ flex: 1 }}
            rules={[{ required: true, message: 'Vui lòng chọn nhà cung cấp' }]}
          >
            <Select
              data-testid="inventory-po-supplier-select"
              showSearch
              placeholder="Chọn nhà cung cấp"
              options={suppliers.map((s) => ({ label: s.name, value: s.supplier_id }))}
              filterOption={(input, option) => (option?.label ?? '').toString().toLowerCase().includes(input.toLowerCase())}
            />
          </Form.Item>
          <Form.Item label="Mã đơn hàng (tuỳ chọn)" name="order_code" style={{ flex: 1 }}>
            <Input placeholder="Tự động tạo nếu để trống" />
          </Form.Item>
        </div>

        <Form.Item label="Ghi chú" name="notes">
          <Input.TextArea rows={2} />
        </Form.Item>

        <div style={{ marginBottom: 16, fontWeight: 500 }}>Chi tiết đơn hàng</div>
        
        <Form.List name="lines">
          {(fields, { add, remove }) => (
            <>
              {fields.map(({ key, name, ...restField }) => (
                <Space key={key} style={{ display: 'flex', marginBottom: 8 }} align="baseline" data-testid="inventory-po-line-row">
                  <Form.Item
                    {...restField}
                    name={[name, 'ingredient_id']}
                    rules={[{ required: true, message: 'Chọn NL' }]}
                    style={{ width: 300 }}
                  >
                    <Select
                      showSearch
                      placeholder="Chọn nguyên liệu"
                      data-testid="inventory-po-line-ingredient-select"
                      options={ingredients.map((i) => ({ label: `${i.name} (${i.unit_code})`, value: i.ingredient_id }))}
                      filterOption={(input, option) => (option?.label ?? '').toString().toLowerCase().includes(input.toLowerCase())}
                    />
                  </Form.Item>
                  <Form.Item
                    {...restField}
                    name={[name, 'ordered_quantity']}
                    rules={[{ required: true, message: 'Nhập SL' }]}
                  >
                    <InputNumber data-testid="inventory-po-line-quantity-input" placeholder="SL" min={0.01} style={{ width: 120 }} />
                  </Form.Item>
                  <Form.Item
                    {...restField}
                    name={[name, 'unit_cost']}
                  >
                    <InputNumber data-testid="inventory-po-line-price-input" placeholder="Đơn giá" min={0} style={{ width: 150 }} />
                  </Form.Item>
                  <MinusCircleOutlined onClick={() => remove(name)} style={{ color: 'red' }} />
                </Space>
              ))}
              <Form.Item>
                <Button data-testid="inventory-po-add-line-button" type="dashed" onClick={() => add()} block icon={<PlusOutlined />}>
                  Thêm dòng
                </Button>
              </Form.Item>
            </>
          )}
        </Form.List>

        <Space style={{ width: '100%', justifyContent: 'flex-end', marginTop: 16 }}>
          <Button onClick={onClose}>Hủy</Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={mutation.isPending}
            data-testid="inventory-po-save-button"
          >
            Tạo đơn mua hàng
          </Button>
        </Space>
      </Form>
    </Modal>
  );
}
