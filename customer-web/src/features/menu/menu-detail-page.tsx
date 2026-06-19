"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, CalendarDays, ShoppingBag, Compass, AlertCircle, Sparkles, Clock } from "lucide-react";
import { useState, type ComponentType } from "react";
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
      <Link
        href="/menu"
        className="group mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft className="h-4 w-4 transition-transform group-hover:-translate-x-1" />
        <span>Quay lại thực đơn</span>
      </Link>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section className="space-y-6">
          <AppCard className="group overflow-hidden border border-primary/10 bg-background/80 backdrop-blur-md shadow-md rounded-2xl transition-all hover:shadow-lg">
            <MenuItemImage item={item} className="h-80 md:h-[420px]" />
            <div className="space-y-5 p-6">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-3">
                  <AppBadge className="rounded-md px-2.5 py-0.5 text-xs font-semibold">{categoryName}</AppBadge>
                  <h1 className="text-3xl font-bold tracking-tight text-foreground">{itemName}</h1>
                  <p className="max-w-2xl text-base text-muted-foreground leading-relaxed">{description}</p>
                </div>
                <PriceText amount={item.price.amount} currency={item.price.currency} className="text-3xl font-extrabold text-primary sm:text-right" />
              </div>

              <div className="flex flex-wrap gap-2 pt-2">
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

          <AppCard className="p-6 border border-primary/10 bg-background/80 backdrop-blur-md shadow-md rounded-2xl">
            <SectionHeader
              title="Thông tin món ăn"
              description="Thông tin cơ bản để bạn chọn món trước khi đặt bàn."
              className="mb-5"
            />
            <dl className="grid gap-4 sm:grid-cols-2">
              <DetailItem label="Thành phần" value="Nhà hàng sẽ bổ sung thông tin này sau." icon={Compass} />
              <DetailItem label="Dị ứng" value="Vui lòng ghi chú khi đặt bàn hoặc hỏi nhân viên." icon={AlertCircle} />
              <DetailItem label="Tùy chọn và topping" value="Nhà hàng sẽ xác nhận tùy chọn khi bạn gọi món." icon={Sparkles} />
              <DetailItem
                label="Hạn chót đặt trước"
                value={
                  item.preorder.enabled
                    ? `${item.preorder.cutoff_minutes} phút trước lượt ghé`
                    : "Món này chưa mở đặt trước"
                }
                icon={Clock}
              />
            </dl>
          </AppCard>
        </section>

        <aside className="space-y-5 lg:sticky lg:top-[5.25rem] lg:h-fit">
          <AppCard className="p-6 border border-primary/10 bg-background/80 backdrop-blur-md shadow-md rounded-2xl">
            <SectionHeader
              eyebrow="Chọn món"
              title={canPreorder ? "Thêm món trước" : "Đặt bàn để thưởng thức"}
              description={
                canPreorder
                  ? "Món đã lưu nằm trong phiên này và có thể đính kèm khi bạn tạo lịch đặt."
                  : "Món này vẫn có thể thưởng thức tại nhà hàng khi còn phục vụ."
              }
            />
            <div className="mt-5 space-y-4">
              {selectedBranch ? (
                <div className="rounded-xl border border-primary/5 bg-secondary/30 p-3.5 text-sm flex items-center justify-between">
                  <span className="text-muted-foreground">Chi nhánh đã chọn:</span>
                  <span className="font-semibold text-foreground">{selectedBranch.branchName}</span>
                </div>
              ) : (
                <EmptyState title="Đang tải chi nhánh" description="Chọn chi nhánh trước khi thêm món đặt trước." />
              )}
              <QuantityStepper value={quantity} min={1} max={20} label={itemName} onChange={setQuantity} />
              <AppTextarea
                label="Ghi chú món"
                value={note}
                onChange={(event) => setNote(event.target.value)}
                helperText="Ghi chú được giữ trong phiên này để bạn xem lại trước khi xác nhận."
                className="rounded-xl"
              />
              {featureFlags.preorder ? (
                <AppButton
                  type="button"
                  className="w-full h-11 rounded-xl font-semibold shadow-sm hover:scale-[1.01] active:scale-[0.99] transition-all"
                  disabled={!canPreorder || !selectedBranch}
                  onClick={addToCart}
                >
                  <ShoppingBag className="h-4 w-4 mr-2" />
                  Thêm món
                </AppButton>
              ) : null}
              <AppButton
                asChild
                variant="outline"
                className="w-full h-11 rounded-xl font-semibold border-muted-foreground/20 hover:bg-secondary hover:scale-[1.01] active:scale-[0.99] transition-all"
              >
                <Link href="/booking">
                  <CalendarDays className="h-4 w-4 mr-2" />
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

function DetailItem({
  label,
  value,
  icon: Icon,
}: {
  label: string;
  value: string;
  icon: ComponentType<{ className?: string }>;
}) {
  return (
    <div className="flex items-start gap-3.5 rounded-xl border border-primary/5 bg-secondary/20 p-4 transition-all hover:bg-secondary/35">
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="h-5 w-5" />
      </div>
      <div className="space-y-1">
        <dt className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{label}</dt>
        <dd className="text-sm font-medium text-foreground">{value}</dd>
      </div>
    </div>
  );
}

