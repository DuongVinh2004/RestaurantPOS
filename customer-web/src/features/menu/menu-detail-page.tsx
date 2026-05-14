"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, CalendarDays, ShoppingBag } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import {
  AppBadge,
  AppButton,
  AppCard,
  AppTextarea,
  EmptyState,
  ErrorState,
  PriceText,
  QuantityStepper,
  SectionHeader,
  StatusPill,
} from "@/components/customer/ui";
import { useBranchSelection } from "@/features/branch/hooks";
import { PreorderCartPanel } from "@/features/preorder/cart-panel";
import { useLocalPreorderCart } from "@/features/preorder/local-cart";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { getMenuItem } from "./api";
import { MenuItemImage } from "./menu-item-image";

export function MenuDetailPage({ id }: { id: number }) {
  const [quantity, setQuantity] = useState(1);
  const [note, setNote] = useState("");
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const cart = useLocalPreorderCart(selectedBranch?.branchId ?? null);
  const itemQuery = useQuery({
    queryKey: queryKeys.menu.item(id),
    queryFn: () => getMenuItem(id),
  });

  if (itemQuery.isLoading) {
    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        <AppCard className="h-96 animate-pulse bg-secondary/50" />
      </main>
    );
  }

  if (itemQuery.error || !itemQuery.data) {
    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        <ErrorState error={itemQuery.error} title="Chưa tải được món" onRetry={() => itemQuery.refetch()} />
      </main>
    );
  }

  const item = itemQuery.data;
  const itemName = displayMenuText(item.name, "Món");
  const categoryName = displayMenuText(item.category_name, "Thực đơn");
  const description = displayMenuText(item.description, "Nhà hàng sẽ bổ sung mô tả khi bạn gọi món.");
  const canPreorder = featureFlags.preorder && item.is_available && item.preorder.enabled;

  const addToCart = () => {
    if (!selectedBranch) {
      toast.error("Chọn chi nhánh trước khi thêm món đặt trước.");
      return;
    }

    if (!canPreorder) {
      toast.error("Món này hiện chưa thể đặt trước.");
      return;
    }

    cart.addItem(item, quantity, note);
    toast.success(`Đã thêm ${itemName} vào giỏ đặt trước.`);
  };

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:pb-8">
      <AppButton asChild variant="ghost" className="mb-4">
        <Link href="/menu">
          <ArrowLeft className="h-4 w-4" />
          Quay lại thực đơn
        </Link>
      </AppButton>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section className="space-y-5">
          <AppCard className="group overflow-hidden">
            <MenuItemImage item={item} className="h-80" />
            <div className="space-y-5 p-5">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-2">
                  <AppBadge>{categoryName}</AppBadge>
                  <h1 className="text-3xl font-semibold tracking-normal">{itemName}</h1>
                  <p className="max-w-2xl text-muted-foreground">{description}</p>
                </div>
                <PriceText amount={item.price.amount} currency={item.price.currency} className="text-2xl" />
              </div>

              <div className="flex flex-wrap gap-2">
                <StatusPill label={item.is_available ? "Còn phục vụ" : "Tạm hết"} tone={item.is_available ? "success" : "warning"} />
                {featureFlags.preorder && item.preorder.enabled ? (
                  <StatusPill label="Có thể thêm trước" tone="info" />
                ) : (
                  <StatusPill label="Thưởng thức tại nhà hàng" tone="neutral" />
                )}
                {item.preorder.requires_preview_validation ? <StatusPill label="Kiểm tra lại trước khi gửi" tone="info" /> : null}
              </div>
            </div>
          </AppCard>

          <AppCard className="p-5">
            <SectionHeader
              title="Thông tin món"
              description="Thông tin cơ bản để bạn chọn món trước khi đặt bàn."
            />
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              <DetailItem label="Thành phần" value="Nhà hàng sẽ bổ sung thông tin này sau." />
              <DetailItem label="Dị ứng" value="Vui lòng ghi chú khi đặt bàn hoặc hỏi nhân viên." />
              <DetailItem label="Tùy chọn và topping" value="Nhà hàng sẽ xác nhận tùy chọn khi bạn gọi món." />
              <DetailItem
                label="Hạn chót đặt trước"
                value={
                  item.preorder.enabled
                    ? `${item.preorder.cutoff_minutes} phút trước lượt ghé`
                    : "Món này chưa mở đặt trước"
                }
              />
            </dl>
          </AppCard>
        </section>

        <aside className="space-y-4 lg:sticky lg:top-[5.25rem] lg:h-fit">
          <AppCard className="p-5">
            <SectionHeader
              eyebrow="Chọn món"
              title={canPreorder ? "Thêm món trước" : "Đặt bàn để thưởng thức"}
              description={
                canPreorder
                  ? "Món đã lưu nằm trong phiên này và có thể đính kèm khi bạn tạo lịch đặt."
                  : "Món này vẫn có thể thưởng thức tại nhà hàng khi còn phục vụ."
              }
            />
            <div className="mt-4 space-y-4">
              {selectedBranch ? (
                <p className="rounded-lg border bg-secondary/30 p-3 text-sm">
                  Chi nhánh: <span className="font-medium">{selectedBranch.branchName}</span>
                </p>
              ) : (
                <EmptyState title="Đang tải chi nhánh" description="Chọn chi nhánh trước khi thêm món đặt trước." />
              )}
              <QuantityStepper value={quantity} min={1} max={20} label={itemName} onChange={setQuantity} />
              <AppTextarea
                label="Ghi chú món"
                value={note}
                onChange={(event) => setNote(event.target.value)}
                helperText="Ghi chú được giữ trong phiên này để bạn xem lại trước khi xác nhận."
              />
              {featureFlags.preorder ? (
                <AppButton type="button" className="w-full" disabled={!canPreorder || !selectedBranch} onClick={addToCart}>
                  <ShoppingBag className="h-4 w-4" />
                  Thêm món
                </AppButton>
              ) : null}
              <AppButton asChild variant="outline" className="w-full">
                <Link href="/booking">
                  <CalendarDays className="h-4 w-4" />
                  Đặt bàn
                </Link>
              </AppButton>
            </div>
          </AppCard>

          <PreorderCartPanel branchId={selectedBranch?.branchId ?? null} branchName={selectedBranch?.branchName ?? null} compact />
        </aside>
      </div>
    </main>
  );
}

function DetailItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border p-3">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd className="mt-1 font-medium">{value}</dd>
    </div>
  );
}
