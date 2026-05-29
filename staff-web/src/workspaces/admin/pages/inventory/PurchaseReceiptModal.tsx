import { Modal, Form, InputNumber, Button, Space, Typography, Alert } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { createAdminPurchaseOrderReceipt } from './inventory-crud-api';
import { toast } from '../../../../shared/ui/feedback/toast';
import { formatApiError } from '../../../../shared/api/errors';

type PurchaseOrderLine = {
  po_line_id: number;
  ingredient_id: number;
  ingredient?: { name: string; unit_code: string } | null;
  ordered_quantity: string | number;
  received_quantity: string | number;
  unit_code?: string | null;
};

type PurchaseReceiptModalProps = {
  open: boolean;
  onClose: () => void;
  purchaseOrderId: number | null;
  purchaseOrderCode: string;
  lines: Array<PurchaseOrderLine>;
};

type ReceiptFormValues = {
  notes?: string;
  lines: Array<{ po_line_id: number; received_quantity: number | null }>;
};

export function PurchaseReceiptModal({
  open,
  onClose,
  purchaseOrderId,
  purchaseOrderCode,
  lines,
}: PurchaseReceiptModalProps) {
  const [form] = Form.useForm<ReceiptFormValues>();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      form.resetFields();
      // Pre-populate lines from PO lines, only show lines with remaining quantity
      const receiptableLines = lines.filter(
        (line) => (Number(line.ordered_quantity) - Number(line.received_quantity)) > 0.0005
      );
      form.setFieldsValue({
        lines: receiptableLines.map((line) => ({
          po_line_id: line.po_line_id,
          received_quantity: null,
        })),
      });
    }
  }, [open, lines, form]);

  const receiptableLines = lines.filter(
    (line) => (Number(line.ordered_quantity) - Number(line.received_quantity)) > 0.0005
  );

  const mutation = useMutation({
    mutationFn: async (values: ReceiptFormValues) => {
      if (!purchaseOrderId) {
        throw new Error('Chưa chọn đơn mua hàng.');
      }

      const payload = {
        notes: values.notes || null,
        lines: receiptableLines
          .map((line, index) => {
            const qty = values.lines[index]?.received_quantity;
            if (qty == null || qty <= 0) return null;
            return {
              purchase_order_line_id: line.po_line_id,
              received_quantity: qty,
              unit_code: line.unit_code ?? line.ingredient?.unit_code ?? null,
            };
          })
          .filter(Boolean) as Array<{
            purchase_order_line_id: number;
            received_quantity: number;
            unit_code: string | null;
          }>,
      };

      if (payload.lines.length === 0) {
        throw new Error('Vui lòng nhập số lượng nhận cho ít nhất một dòng.');
      }

      return createAdminPurchaseOrderReceipt(purchaseOrderId, payload);
    },
    onSuccess: (result) => {
      const receiptCode = (result as any)?.data?.receipt_code ?? '';
      toast.success(`Tạo phiếu nhận ${receiptCode} thành công`);
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-purchase-orders'] });
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-purchase-order-receipts'] });
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredients'] });
      queryClient.invalidateQueries({ queryKey: ['admin-inventory-ingredient-movements'] });
      onClose();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Lỗi khi tạo phiếu nhận hàng'));
    },
  });

  const hasReceivableLines = receiptableLines.length > 0;

  return (
    <Modal
      title={`Tạo phiếu nhận — ${purchaseOrderCode}`}
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnClose
      width={680}
      data-testid="inventory-receipt-form"
    >
      {!hasReceivableLines ? (
        <Alert
          type="warning"
          message="Không còn dòng hàng nào cần nhận"
          description="Tất cả các dòng hàng trong đơn này đã được nhận đủ số lượng."
          showIcon
          data-testid="inventory-receipt-error-alert"
        />
      ) : (
        <Form
          form={form}
          layout="vertical"
          onFinish={(values) => mutation.mutate(values)}
        >
          <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
            Nhập số lượng thực nhận cho từng dòng. Bỏ trống nếu không nhận dòng đó.
          </Typography.Text>

          <div data-testid="inventory-receipt-line-row">
            {receiptableLines.map((line, index) => {
              const remaining = Number(line.ordered_quantity) - Number(line.received_quantity);
              const ingredientName = line.ingredient?.name ?? `Nguyên liệu #${line.ingredient_id}`;
              const unitCode = line.unit_code ?? line.ingredient?.unit_code ?? '';

              return (
                <div
                  key={line.po_line_id}
                  style={{
                    display: 'flex',
                    gap: 12,
                    alignItems: 'flex-start',
                    marginBottom: 8,
                    padding: '8px 12px',
                    background: '#fafafa',
                    borderRadius: 6,
                    border: '1px solid #f0f0f0',
                  }}
                >
                  <div style={{ flex: 1 }}>
                    <Typography.Text strong>{ingredientName}</Typography.Text>
                    <Typography.Text type="secondary" style={{ display: 'block', fontSize: 12 }}>
                      Còn lại: {remaining.toFixed(3)} {unitCode}
                    </Typography.Text>
                  </div>
                  <Form.Item
                    name={['lines', index, 'received_quantity']}
                    style={{ marginBottom: 0, width: 160 }}
                    rules={[
                      {
                        validator: (_, value) => {
                          if (value == null) return Promise.resolve();
                          if (value <= 0) return Promise.reject('Số lượng phải > 0');
                          if (value > remaining + 0.0005) {
                            return Promise.reject(`Vượt quá còn lại (${remaining.toFixed(3)})`);
                          }
                          return Promise.resolve();
                        },
                      },
                    ]}
                  >
                    <InputNumber
                      data-testid="inventory-receipt-quantity-input"
                      min={0.001}
                      max={remaining}
                      step={0.001}
                      placeholder={`Max ${remaining.toFixed(3)}`}
                      style={{ width: '100%' }}
                      suffix={unitCode}
                    />
                  </Form.Item>
                </div>
              );
            })}
          </div>

          {mutation.error ? (
            <Alert
              type="error"
              message={formatApiError(mutation.error, 'Lỗi khi tạo phiếu nhận hàng')}
              showIcon
              style={{ marginBottom: 12 }}
              data-testid="inventory-receipt-error-alert"
            />
          ) : null}

          <Space style={{ width: '100%', justifyContent: 'flex-end', marginTop: 16 }}>
            <Button onClick={onClose}>Hủy</Button>
            <Button
              type="primary"
              htmlType="submit"
              loading={mutation.isPending}
              disabled={mutation.isPending}
              data-testid="inventory-receipt-save-button"
            >
              Xác nhận nhận hàng
            </Button>
          </Space>
        </Form>
      )}
    </Modal>
  );
}
