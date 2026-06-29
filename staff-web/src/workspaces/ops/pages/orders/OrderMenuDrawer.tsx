import { Button, Drawer, Input, InputNumber, Space, Typography } from 'antd';
import { InlineLoading, ApiStateBlock, EmptyBlock } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { formatMoney } from '../../../../shared/utils/format';

export function OrderMenuDrawer({
  open,
  onClose,
  menuSearch,
  setMenuSearch,
  menuCategorySummary,
  menuQuery,
  menuItems,
  getMenuDraft,
  updateMenuDraft,
  handleAddMenuItem,
  findMergeableOrderLine,
  orderItems,
  currentPaymentStatus,
  addItemMutationPending,
  addItemMutationVariables,
  resolvedOrderId,
  paymentMergeLocked,
}: {
  open: boolean;
  onClose: () => void;
  menuSearch: string;
  setMenuSearch: (value: string) => void;
  menuCategorySummary: string[];
  menuQuery: any;
  menuItems: any[];
  getMenuDraft: (itemId: number) => { qty: number; note: string };
  updateMenuDraft: (itemId: number, patch: { qty?: number; note?: string }) => void;
  handleAddMenuItem: (item: any) => void;
  findMergeableOrderLine: (orderItems: any[], itemId: number, note: string, paymentStatus: string | null) => any;
  orderItems: any[];
  currentPaymentStatus: string | null;
  addItemMutationPending: boolean;
  addItemMutationVariables: any;
  resolvedOrderId: number | null;
  paymentMergeLocked: boolean;
}) {
  function getMenuInitial(name: string): string {
    return name.trim().charAt(0).toUpperCase() || 'M';
  }

  function normalizeMenuQty(value: number | null): number {
    if (!value || Number.isNaN(value)) {
      return 1;
    }
    return Math.min(30, Math.max(1, Math.trunc(value)));
  }

  return (
    <Drawer
      title="Gọi món nhanh"
      placement="right"
      width={480}
      onClose={onClose}
      open={open}
      destroyOnClose
    >
      <Space orientation="vertical" size={14} style={{ width: '100%' }}>
        <div className="staff-order-menu-hint">
          Chọn món trực tiếp từ danh sách. Dòng món chưa khóa sẽ được cộng số lượng nếu cùng món và cùng ghi chú bếp.
        </div>
        {paymentMergeLocked ? (
          <div className="staff-order-menu-guard">
            Đơn đã ghi nhận thanh toán. Món gọi thêm sẽ tách thành dòng mới để không lẫn phần đã thu.
          </div>
        ) : null}
        <Input.Search
          allowClear
          placeholder="Tìm món"
          value={menuSearch}
          onChange={(event) => setMenuSearch(event.target.value)}
          onSearch={setMenuSearch}
        />
        {menuCategorySummary.length > 0 ? (
          <div className="staff-order-menu-category-row">
            {menuCategorySummary.map((categoryName) => (
              <span key={categoryName}>{categoryName}</span>
            ))}
          </div>
        ) : null}
        {menuQuery.isLoading ? <InlineLoading tip="Đang tải danh mục món..." /> : null}
        {menuQuery.error ? (
          <ApiStateBlock
            error={menuQuery.error}
            fallback="Không thể tải danh mục món cho nhân viên."
            onRetry={() => {
              void menuQuery.refetch();
            }}
          />
        ) : null}
        {!menuQuery.isLoading && !menuQuery.error && menuItems.length === 0 ? (
          <EmptyBlock
            title="Không tìm thấy món"
            description="Thử đổi từ khóa hoặc kiểm tra danh mục món đang mở bán."
          />
        ) : null}
        <div className="staff-order-menu-list">
          {menuItems.map((item) => {
            const draft = getMenuDraft(item.item_id);
            const mergeTarget = findMergeableOrderLine(orderItems, item.item_id, draft.note, currentPaymentStatus);
            const isAddingThisItem = addItemMutationPending && addItemMutationVariables?.menu_item_id === item.item_id;

            return (
              <div key={item.item_id} className={`staff-order-menu-item ${!item.is_available ? 'staff-order-menu-item-disabled' : ''}`}>
                <div className="staff-order-menu-thumb">
                  {item.img_url ? <img src={item.img_url} alt={item.name} /> : <span>{getMenuInitial(item.name)}</span>}
                </div>
                <div className="staff-order-menu-copy">
                  <div className="staff-order-menu-title-row">
                    <Typography.Text strong>{item.name}</Typography.Text>
                    <Typography.Text strong>{formatMoney(item.price.amount, item.price.currency ?? 'VND')}</Typography.Text>
                  </div>
                  <Typography.Text type="secondary">
                    {item.category_name ?? item.code}
                    {mergeTarget ? ` • đang có ${mergeTarget.quantity} phần chưa khóa` : ''}
                  </Typography.Text>
                  {item.description ? (
                    <Typography.Text type="secondary" className="staff-order-menu-description">
                      {item.description}
                    </Typography.Text>
                  ) : null}
                  {!item.is_available ? (
                    <Typography.Text className="staff-order-menu-disabled-reason" type="secondary">
                      Tạm ngừng nhận gọi món ở ca hiện tại.
                    </Typography.Text>
                  ) : null}
                  <Input
                    size="small"
                    placeholder="Ghi chú bếp cho món này"
                    value={draft.note}
                    onChange={(event) => updateMenuDraft(item.item_id, { note: event.target.value })}
                  />
                </div>
                <div className="staff-order-menu-actions">
                  <InputNumber
                    aria-label={`Số lượng ${item.name}`}
                    min={1}
                    max={30}
                    value={draft.qty}
                    onChange={(value) => updateMenuDraft(item.item_id, { qty: normalizeMenuQty(value) })}
                  />
                  <Button
                    type={mergeTarget ? 'default' : 'primary'}
                    disabled={!resolvedOrderId || !item.is_available}
                    loading={isAddingThisItem}
                    onClick={() => handleAddMenuItem(item)}
                  >
                    {mergeTarget ? 'Cộng số lượng' : 'Thêm món'}
                  </Button>
                  {!item.is_available ? <StatusChip label="Ngừng bán" tone="warning" variant="severity" /> : null}
                </div>
              </div>
            );
          })}
        </div>
      </Space>
    </Drawer>
  );
}
