"use client";

import Link from "next/link";
import {
  CalendarDays,
  CheckCircle2,
  ChevronRight,
  Clock3,
  MessageSquareText,
  ShoppingBag,
  Trash2,
  Utensils,
} from "lucide-react";
import { useState } from "react";
import { useSearchParams } from "next/navigation";
import {
  AppButton,
  AppCard,
  AppSelect,
  AppTextarea,
  ConfirmDialog,
  EmptyState,
  PriceText,
  QuantityStepper,
  StatusPill,
} from "@/components/customer/ui";
import { cn } from "@/lib/utils";
import {
  useLocalPreorderCart,
  type LocalPreorderCartItem,
  type LocalPreorderServeTiming,
} from "./local-cart";

const serveTimingOptions: Array<{ value: LocalPreorderServeTiming; label: string }> = [
  { value: "when_arrived", label: "Khi tôi đến" },
  { value: "after_seated", label: "Sau khi ngồi vào bàn" },
  { value: "custom_note", label: "Dùng ghi chú riêng" },
];

const cartImageProfiles: Array<{ keywords: string[]; src: string }> = [
  { keywords: ["phở", "pho", "bún", "mì", "noodle", "soup"], src: "/customer-web/fallback-noodle.jpg" },
  { keywords: ["cơm", "rice", "curry", "gà", "chicken"], src: "/customer-web/fallback-rice.jpg" },
  { keywords: ["salad", "rau", "chay", "vegetable"], src: "/customer-web/fallback-salad.jpg" },
  { keywords: ["tráng miệng", "dessert", "cake", "bánh", "kem"], src: "/customer-web/fallback-dessert.jpg" },
  { keywords: ["drink", "nước", "tea", "coffee", "cà phê", "trà"], src: "/customer-web/fallback-drink.jpg" },
];

const defaultCartImage = "/customer-web/fallback-default.jpg";

function imageSrcForCartItem(item: LocalPreorderCartItem): string {
  if (item.image_url) {
    return item.image_url;
  }

  const searchable = item.name.toLocaleLowerCase("vi-VN");

  return cartImageProfiles.find((profile) => profile.keywords.some((keyword) => searchable.includes(keyword)))?.src ?? defaultCartImage;
}

function lineTotal(item: LocalPreorderCartItem): number | null {
  const unitPrice = Number(item.price_amount ?? 0);

  return Number.isFinite(unitPrice) ? unitPrice * item.quantity : null;
}

export function PreorderCartPanel({
  branchId,
  branchName,
  compact = false,
  className,
}: {
  branchId: number | null | undefined;
  branchName?: string | null;
  compact?: boolean;
  className?: string;
}) {
  const searchParams = useSearchParams();
  const returnTo = searchParams?.get("return_to");
  const isReturningToBooking = Boolean(returnTo);
  
  const cartState = useLocalPreorderCart(branchId);
  const { cart, quantity, subtotal, submitItems, hasUnsupportedNotes } = cartState;
  const [confirmClear, setConfirmClear] = useState(false);
  const availableQuantity = submitItems.reduce((total, item) => total + item.quantity, 0);
  const branchLabel = branchName ?? "Chưa chọn chi nhánh";

  return (
    <AppCard className={cn("overflow-hidden p-0 gap-0", compact ? "text-sm flex flex-col h-full lg:h-[calc(100vh-6.5rem)] lg:max-h-[calc(100vh-6.5rem)]" : undefined, className)}>
      <div className="shrink-0 border-b bg-[linear-gradient(135deg,var(--restaurant-amber-soft),white_54%,var(--restaurant-teal-soft))] p-4 pr-10 sm:p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-3">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
              <ShoppingBag className="h-5 w-5" />
            </span>
            <div className="min-w-0">
              <p className="text-xs font-semibold text-primary/80 uppercase tracking-wider">Giỏ món đặt trước</p>
              <h2 className={cn("mt-0.5 font-bold leading-tight tracking-tight", compact ? "text-lg" : "text-xl")}>
                {quantity > 0 ? `${quantity} món đã chọn` : "Sẵn sàng thêm món"}
              </h2>
              <p className="text-xs text-muted-foreground mt-0.5 truncate max-w-[16rem]">
                Chi nhánh: <span className="font-semibold text-foreground">{branchLabel}</span>
              </p>
            </div>
          </div>
          {quantity > 0 ? (
            <StatusPill
              label={`${availableQuantity}/${quantity} khả dụng`}
              tone={availableQuantity === quantity ? "success" : "warning"}
              className="shrink-0 whitespace-nowrap"
            />
          ) : null}
        </div>
      </div>

      {quantity === 0 ? (
        <div className={cn("space-y-4 p-4 sm:p-5", compact ? "lg:overflow-y-auto" : undefined)}>
          <EmptyState
            icon={<ShoppingBag className="h-12 w-12" />}
            title="Giỏ món đặt trước đang trống"
            description="Thêm món còn phục vụ từ thực đơn. Nhà bếp chỉ nhận món đặt trước sau khi có lịch đặt."
            action={
              <AppButton asChild variant="outline">
                <Link href="/menu">
                  <Utensils className="h-4 w-4 mr-2" />
                  Chọn món từ thực đơn
                </Link>
              </AppButton>
            }
          />
          <div className="grid gap-2 text-sm sm:grid-cols-3">
            <div className="rounded-lg border bg-secondary/40 p-3">
              <CheckCircle2 className="h-4 w-4 text-primary" />
              <p className="mt-2 font-medium">Chọn món</p>
            </div>
            <div className="rounded-lg border bg-secondary/40 p-3">
              <CalendarDays className="h-4 w-4 text-primary" />
              <p className="mt-2 font-medium">Giữ bàn</p>
            </div>
            <div className="rounded-lg border bg-secondary/40 p-3">
              <Clock3 className="h-4 w-4 text-primary" />
              <p className="mt-2 font-medium">Bếp xác nhận</p>
            </div>
          </div>
        </div>
      ) : (
        <>
          <div className={cn(compact ? "flex-1 min-h-0 overflow-y-auto" : "flex flex-col")}>
            <div
              className={cn("divide-y", compact ? "max-h-none shrink-0" : "max-h-[32rem] overflow-y-auto")}
              aria-label="Danh sách món trong giỏ đặt trước"
            >
              {cart.items.map((item) => {
                const canSubmit = item.is_available && item.preorder_enabled;

                return (
                  <article key={item.item_id} className="p-3 sm:p-3.5">
                    <div className="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-3">
                      <div className="h-14 w-14 overflow-hidden rounded-lg border bg-secondary shrink-0">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={imageSrcForCartItem(item)}
                          alt={`Ảnh món ${item.name}`}
                          className="aspect-square h-full w-full object-cover"
                          loading="lazy"
                          onError={(event) => {
                            const image = event.currentTarget;

                            if (image.dataset.fallbackApplied === "true") {
                              return;
                            }

                            image.dataset.fallbackApplied = "true";
                            image.src = defaultCartImage;
                          }}
                        />
                      </div>
                      <div className="min-w-0 flex flex-col justify-between">
                        <div className="flex items-start justify-between gap-2">
                          <h3 className="truncate font-semibold text-sm leading-tight">{item.name}</h3>
                          <AppButton
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Xóa ${item.name}`}
                            className="h-7 w-7 text-muted-foreground hover:text-destructive shrink-0 -mt-1 -mr-1"
                            onClick={() => cartState.removeItem(item.item_id)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </AppButton>
                        </div>
                        <div className="flex items-center justify-between text-xs text-muted-foreground mt-0.5">
                           <div>
                             <PriceText amount={item.price_amount} currency={item.currency} className="font-semibold text-foreground" />
                             <span className="mx-1">/</span>
                             <span>phần</span>
                           </div>
                           {!canSubmit && (
                             <span className="text-[11px] font-semibold text-amber-600 bg-amber-50 dark:bg-amber-950/20 px-1.5 py-0.5 rounded border border-amber-100 dark:border-amber-900/20">
                               Chưa thể đặt trước
                             </span>
                           )}
                        </div>
                      </div>
                    </div>

                    <div className="mt-2.5 flex items-center justify-between gap-2">
                      <QuantityStepper
                        label={item.name}
                        value={item.quantity}
                        min={0}
                        onChange={(nextQuantity) => cartState.updateQuantity(item.item_id, nextQuantity)}
                      />
                      <div className="text-right">
                        <span className="block text-[11px] text-muted-foreground leading-none mb-0.5">Thành tiền</span>
                        <PriceText amount={lineTotal(item)} currency={item.currency} className="font-bold text-sm text-primary" />
                      </div>
                    </div>

                    <details className="group mt-2.5 rounded-lg border bg-background">
                      <summary className="flex min-h-8 cursor-pointer list-none items-center justify-between gap-2 px-2.5 text-xs font-medium">
                        <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                          <MessageSquareText className="h-3.5 w-3.5 text-primary/75" />
                          {item.note ? "Sửa ghi chú" : "Thêm ghi chú"}
                        </span>
                        <ChevronRight className="h-3.5 w-3.5 text-muted-foreground transition group-open:rotate-90" />
                      </summary>
                      <div className="border-t p-2">
                        <AppTextarea
                          label={`Ghi chú cho ${item.name}`}
                          value={item.note}
                          onChange={(event) => cartState.updateNote(item.item_id, event.target.value)}
                          className="min-h-14 text-xs"
                          placeholder="Ví dụ: ít cay, để riêng nước sốt..."
                        />
                      </div>
                    </details>
                  </article>
                );
              })}
            </div>

            <div className="shrink-0 border-t bg-secondary/35 p-4 sm:p-5">
              <div className="flex items-start gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-background text-primary">
                  <Clock3 className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1 space-y-3">
                  <div>
                    <h3 className="font-semibold">Cách phục vụ</h3>
                    <p className="text-sm text-muted-foreground">Lưu mong muốn cho lượt ghé này trước khi xác nhận đặt bàn.</p>
                  </div>
                  <div className="grid gap-3">
                    <AppSelect
                      label="Thời điểm phục vụ"
                      value={cart.serve_timing}
                      onValueChange={(value) => cartState.setServeTiming(value as LocalPreorderServeTiming, cart.serve_note)}
                      options={serveTimingOptions}
                    />
                    <AppTextarea
                      label="Ghi chú phục vụ"
                      value={cart.serve_note}
                      onChange={(event) => cartState.setServeTiming(cart.serve_timing, event.target.value)}
                      className="min-h-20"
                      placeholder="Ví dụ: lên món sau khi đủ khách..."
                    />
                  </div>
                </div>
              </div>

              {hasUnsupportedNotes ? (
                <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                  Ghi chú đang được giữ trong giỏ này. Khi gửi yêu cầu đặt trước, nhà hàng hiện chỉ nhận món và số lượng.
                </p>
              ) : null}
            </div>
          </div>

          <div className="shrink-0 space-y-3 border-t bg-background/95 p-4 sm:p-5">
            <div className="flex items-end justify-between gap-3">
              <div>
                <p className="text-sm text-muted-foreground">Tạm tính</p>
                <PriceText amount={subtotal.amount} currency={subtotal.currency} className="text-xl" />
              </div>
              <p className="text-right text-sm text-muted-foreground">
                <span className="font-medium">{submitItems.length}</span> món có thể gửi
                <span className="block font-medium">{availableQuantity} phần</span>
              </p>
            </div>
             <div className="flex gap-2">
               {submitItems.length > 0 ? (
                 <AppButton asChild className="flex-1">
                   <Link href={returnTo ?? "/reservations/new"}>
                     <ShoppingBag className="h-4 w-4" />
                     {isReturningToBooking ? "Tiếp tục đặt bàn" : "Tiếp tục đặt bàn"}
                   </Link>
                 </AppButton>
               ) : (
                 <AppButton type="button" disabled className="flex-1">
                   <ShoppingBag className="h-4 w-4" />
                   Không có món khả dụng
                 </AppButton>
               )}
               <AppButton 
                 type="button" 
                 variant="outline" 
                 size="icon" 
                 className="shrink-0 text-muted-foreground hover:text-destructive hover:bg-destructive/5" 
                 aria-label="Xóa giỏ"
                 onClick={() => setConfirmClear(true)}
               >
                 <Trash2 className="h-4 w-4" />
               </AppButton>
             </div>
          </div>
        </>
      )}

      <ConfirmDialog
        open={confirmClear}
        onOpenChange={setConfirmClear}
        title="Xóa giỏ món đặt trước?"
        description="Thao tác này xóa các món đã lưu cho chi nhánh và phiên hiện tại."
        confirmLabel="Xóa giỏ"
        destructive
        onConfirm={cartState.clear}
      />
    </AppCard>
  );
}
