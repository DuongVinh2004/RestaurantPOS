import { Modal, Form, InputNumber, Button, Space, Typography, Alert, Select } from 'antd';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { getAdminMenuItemRecipe, upsertAdminMenuItemRecipe } from './inventory-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';
import { InlineLoading } from '../../../../shared/ui/states/StateBlocks';

type Ingredient = {
  ingredient_id: number;
  name: string;
  unit_code: string;
  is_active: boolean;
};

type RecipeModalProps = {
  open: boolean;
  onClose: () => void;
  menuItemId: number | null;
  menuItemName: string;
  ingredients: Array<Ingredient>;
};

type RecipeLine = {
  ingredient_id: number;
  quantity: number;
  unit_code?: string | null;
  notes?: string | null;
};

type RecipeFormValues = {
  lines: Array<RecipeLine>;
};

export function RecipeModal({
  open,
  onClose,
  menuItemId,
  menuItemName,
  ingredients,
}: RecipeModalProps) {
  const [form] = Form.useForm<RecipeFormValues>();
  const queryClient = useQueryClient();

  const recipeQuery = useQuery({
    queryKey: ['admin-inventory-recipe', menuItemId],
    queryFn: () => getAdminMenuItemRecipe(menuItemId as number),
    enabled: open && menuItemId !== null,
  });

  useEffect(() => {
    if (open && recipeQuery.data) {
      const lines = (recipeQuery.data as any).data ?? [];
      form.setFieldsValue({
        lines: lines.map((line: any) => ({
          ingredient_id: line.ingredient_id,
          quantity: Number(line.quantity),
          unit_code: line.unit_code ?? null,
        })),
      });
    } else if (open && !recipeQuery.data) {
      form.resetFields();
      form.setFieldsValue({ lines: [] });
    }
  }, [open, recipeQuery.data, form]);

  const rowVersion = (recipeQuery.data as any)?.meta?.row_version ?? 0;

  const mutation = useMutation({
    mutationFn: async (values: RecipeFormValues) => {
      if (!menuItemId) throw new Error('Chưa chọn món ăn.');

      return upsertAdminMenuItemRecipe(menuItemId, {
        row_version: rowVersion,
        lines: (values.lines ?? []).map((line) => ({
          ingredient_id: line.ingredient_id,
          quantity: Number(line.quantity),
          unit_code: line.unit_code ?? null,
          notes: line.notes ?? null,
        })),
      });
    },
    onSuccess: () => {
      toast.success('Cập nhật định mức nguyên liệu thành công');
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-recipe', menuItemId] });
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredients'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi cập nhật định mức nguyên liệu'));
    },
  });

  const activeIngredients = ingredients.filter((i) => i.is_active);

  return (
    <Modal
      title={`Định mức nguyên liệu — ${menuItemName}`}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
      width={700}
      data-testid="inventory-recipe-form"
    >
      {recipeQuery.isLoading ? (
        <InlineLoading tip="Đang tải định mức..." />
      ) : (
        <Form
          form={form}
          layout="vertical"
          onFinish={(values) => mutation.mutate(values)}
        >
          <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
            Thiết lập nguyên liệu và định lượng cần dùng cho mỗi phần ăn của món này.
          </Typography.Text>

          <Form.List name="lines">
            {(fields, { add, remove }) => (
              <>
                {fields.map(({ key, name, ...restField }) => (
                  <Space
                    key={key}
                    style={{ display: 'flex', marginBottom: 8 }}
                    align="baseline"
                    data-testid="inventory-recipe-line-row"
                  >
                    <Form.Item
                      {...restField}
                      name={[name, 'ingredient_id']}
                      rules={[{ required: true, message: 'Chọn nguyên liệu' }]}
                      style={{ width: 280 }}
                    >
                      <Select
                        showSearch
                        placeholder="Chọn nguyên liệu"
                        data-testid="inventory-recipe-ingredient-select"
                        options={activeIngredients.map((i) => ({
                          label: `${i.name} (${i.unit_code})`,
                          value: i.ingredient_id,
                        }))}
                        filterOption={(input, option) =>
                          (option?.label ?? '').toString().toLowerCase().includes(input.toLowerCase())
                        }
                      />
                    </Form.Item>
                    <Form.Item
                      {...restField}
                      name={[name, 'quantity']}
                      rules={[
                        { required: true, message: 'Nhập định lượng' },
                        { type: 'number', min: 0.001, message: 'Phải > 0' },
                      ]}
                    >
                      <InputNumber
                        data-testid="inventory-recipe-quantity-input"
                        placeholder="Định lượng"
                        min={0.001}
                        step={0.001}
                        style={{ width: 140 }}
                      />
                    </Form.Item>
                    <MinusCircleOutlined onClick={() => remove(name)} style={{ color: 'red' }} />
                  </Space>
                ))}
                <Form.Item>
                  <Button
                    data-testid="inventory-recipe-add-line-button"
                    type="dashed"
                    onClick={() => add()}
                    block
                    icon={<PlusOutlined />}
                  >
                    Thêm nguyên liệu
                  </Button>
                </Form.Item>
              </>
            )}
          </Form.List>

          {mutation.error ? (
            <Alert
              type="error"
              message={formatApiError(mutation.error, 'Lỗi khi cập nhật định mức')}
              showIcon
              style={{ marginBottom: 12 }}
            />
          ) : null}

          <Space style={{ width: '100%', justifyContent: 'flex-end', marginTop: 8 }}>
            <Button onClick={onClose}>Hủy</Button>
            <Button
              type="primary"
              htmlType="submit"
              loading={mutation.isPending}
              disabled={mutation.isPending}
              data-testid="inventory-recipe-save-button"
            >
              Lưu định mức
            </Button>
          </Space>
        </Form>
      )}
    </Modal>
  );
}
