import { Button, Descriptions, Drawer, Form, Input, InputNumber, Space, Typography } from 'antd';
import type { FormInstance } from 'antd';
import { EmptyBlock } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { orderTone } from '../../../../shared/status/status';
import { translateUiCode } from '../../../../shared/utils/translation';

export function OrderItemDetailDrawer({
  open,
  onClose,
  selectedItem,
  editItemForm,
  handleUpdateItem,
  selectedItemEditable,
  itemConcurrencyMissing,
  updateItemMutationPending,
  allowedStatusTransitions,
  handleStatusTransition,
  updateItemStatusMutationPending,
  orderRowVersion,
}: {
  open: boolean;
  onClose: () => void;
  selectedItem: any;
  editItemForm: FormInstance<any>;
  handleUpdateItem: (values: any) => void;
  selectedItemEditable: boolean;
  itemConcurrencyMissing: boolean;
  updateItemMutationPending: boolean;
  allowedStatusTransitions: any[];
  handleStatusTransition: (status: any) => void;
  updateItemStatusMutationPending: boolean;
  orderRowVersion: number | null | undefined;
}) {
  return (
    <Drawer
      title="Dòng món đang chọn"
      placement="right"
      width={480}
      onClose={onClose}
      open={open}
      destroyOnClose
    >
      <Form layout="vertical" form={editItemForm} onFinish={handleUpdateItem}>
        {!selectedItem ? (
          <EmptyBlock
            title="Chưa chọn dòng món"
            description="Chọn một dòng món để sửa số lượng, ghi chú hoặc chuyển qua các bước của bếp và phục vụ."
          />
        ) : (
          <Space orientation="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={1}>
              <Descriptions.Item label="Dòng món">
                #{selectedItem.order_item_id}
              </Descriptions.Item>
              <Descriptions.Item label="Món">
                {selectedItem.item?.name ?? selectedItem.item_name_snapshot ?? `Món #${selectedItem.item_id}`}
              </Descriptions.Item>
              <Descriptions.Item label="Trạng thái">
                <StatusChip label={selectedItem.status} tone={orderTone(selectedItem.status)} />
              </Descriptions.Item>
              <Descriptions.Item label="Phiên bản đơn hàng">
                {orderRowVersion ?? 'Thiếu'}
              </Descriptions.Item>
              <Descriptions.Item label="Phiên bản dòng món">
                {selectedItem.row_version ?? 'Thiếu'}
              </Descriptions.Item>
            </Descriptions>

            <Form.Item name="qty" label="Số lượng" rules={[{ required: true, message: 'Nhập số lượng món.' }]}>
              <InputNumber min={1} max={30} style={{ width: '100%' }} disabled={!selectedItemEditable || itemConcurrencyMissing} />
            </Form.Item>
            <Form.Item name="note" label="Ghi chú">
              <Input.TextArea rows={3} placeholder="Ghi chú cho bếp hoặc phục vụ" disabled={!selectedItemEditable || itemConcurrencyMissing} />
            </Form.Item>
            <Button
              type="primary"
              htmlType="submit"
              block
              disabled={!selectedItemEditable || itemConcurrencyMissing}
              loading={updateItemMutationPending}
            >
              Lưu thay đổi dòng món
            </Button>

            <Space wrap>
              {allowedStatusTransitions.length === 0 ? (
                <Typography.Text type="secondary">
                  Dòng món này đã ở trạng thái cuối và không thể chuyển tiếp.
                </Typography.Text>
              ) : (
                allowedStatusTransitions.map((status) => (
                  <Button
                    key={status}
                    onClick={() => handleStatusTransition(status)}
                    danger={status === 'Cancelled'}
                    disabled={itemConcurrencyMissing}
                    loading={updateItemStatusMutationPending}
                  >
                    Đánh dấu {translateUiCode(status)}
                  </Button>
                ))
              )}
            </Space>
          </Space>
        )}
      </Form>
    </Drawer>
  );
}
