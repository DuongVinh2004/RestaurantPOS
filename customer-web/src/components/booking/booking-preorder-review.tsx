"use client";

import Link from "next/link";
import { ChevronRight, MessageSquareText, Trash2, ShoppingBag, Plus } from "lucide-react";
import {
  AppButton,
  AppTextarea,
  PriceText,
  QuantityStepper,
} from "@/components/customer/ui";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { useLocalPreorderCart } from "@/features/preorder/local-cart";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { cn } from "@/lib/utils";
import { useEffect, useState } from "react";

const defaultCartImage = "/customer-web/menu/placeholder.jpg";

function imageSrcForCartItem(item: { image_url?: string | null }) {
  if (item.image_url) {
    return item.image_url;
  }
  return defaultCartImage;
}

function lineTotal(item: { price_amount?: string | null; quantity: number }) {
  return String(Number(item.price_amount ?? "0") * item.quantity);
}

function StatusPill({ label, tone }: { label: string; tone: "success" | "warning" | "info" }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium",
        tone === "success" && "bg-emerald-100 text-emerald-800",
        tone === "warning" && "bg-amber-100 text-amber-800",
        tone === "info" && "bg-blue-100 text-blue-800",
      )}
    >
      {label}
    </span>
  );
}

export function BookingPreorderReview({ branchId }: { branchId: number | null }) {
  useEffect(() => {
    ensureCustomerSessionId();
  }, []);
  
  // Lấy dữ liệu giỏ hàng (chỉ cần nhánh đang được chọn)
  const cartState = useLocalPreorderCart(branchId ?? 0);
  const cart = cartState.cart;
  
  // Nếu chưa có chi nhánh hoặc giỏ hàng trống, render trạng thái trống
  if (!branchId || cart.items.length === 0) {
    return (
      <Card className="shadow-restaurant transition-shadow hover:shadow-restaurant-hover">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShoppingBag className="h-5 w-5 text-primary" />
            Món đặt trước
          </CardTitle>
          <CardDescription>Chọn món ngay bây giờ để được phục vụ nhanh nhất khi đến.</CardDescription>
        </CardHeader>
        <CardContent>
          <AppButton asChild variant="outline" className="w-full sm:w-auto">
            <Link href={`/menu?return_to=/reservations/new`}>
              <Plus className="mr-2 h-4 w-4" />
              Chọn món từ Menu
            </Link>
          </AppButton>
        </CardContent>
      </Card>
    );
  }

  const subtotal = cart.items.reduce((acc, item) => acc + Number(item.price_amount) * item.quantity, 0);
  const currency = cart.items[0]?.currency ?? "VND";

  return (
    <Card className="shadow-restaurant transition-shadow hover:shadow-restaurant-hover">
      <CardHeader className="border-b bg-secondary/30 pb-4">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-lg">
            <ShoppingBag className="h-5 w-5 text-primary" />
            Món đặt trước
          </CardTitle>
          <AppButton asChild variant="ghost" size="sm" className="h-8 text-primary">
            <Link href={`/menu?return_to=/reservations/new`}>
              <Plus className="mr-1 h-3.5 w-3.5" />
              Thêm món
            </Link>
          </AppButton>
        </div>
      </CardHeader>
      
      <CardContent className="p-0">
        <div className="divide-y divide-border">
          {cart.items.map((item) => {
            const canSubmit = item.is_available && item.preorder_enabled;

            return (
              <div key={item.item_id} className="p-4 sm:p-5">
                <div className="grid grid-cols-[4.5rem_minmax(0,1fr)_auto] gap-3">
                  <div className="overflow-hidden rounded-lg border bg-secondary shadow-sm">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={imageSrcForCartItem(item)}
                      alt={`Ảnh món ${item.name}`}
                      className="aspect-square h-full w-full object-cover"
                      loading="lazy"
                      onError={(event) => {
                        const image = event.currentTarget;
                        if (image.dataset.fallbackApplied === "true") return;
                        image.dataset.fallbackApplied = "true";
                        image.src = defaultCartImage;
                      }}
                    />
                  </div>
                  <div className="min-w-0">
                    <h3 className="truncate font-semibold leading-tight">{item.name}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                      <PriceText amount={item.price_amount} currency={item.currency} />
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {!canSubmit && (
                        <StatusPill label="Chưa thể đặt trước" tone="warning" />
                      )}
                      {item.note && <StatusPill label="Có ghi chú" tone="info" />}
                    </div>
                  </div>
                  <AppButton
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Xóa ${item.name}`}
                    className="text-muted-foreground hover:text-destructive"
                    onClick={() => cartState.removeItem(item.item_id)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </AppButton>
                </div>

                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <QuantityStepper
                    label={item.name}
                    value={item.quantity}
                    min={0}
                    onChange={(nextQuantity) => cartState.updateQuantity(item.item_id, nextQuantity)}
                  />
                  <div className="rounded-lg bg-secondary/45 px-3 py-2 text-right text-sm">
                    <span className="block text-xs text-muted-foreground">Thành tiền</span>
                    <PriceText amount={lineTotal(item)} currency={item.currency} className="font-semibold text-primary" />
                  </div>
                </div>

                <details className="group mt-3 rounded-lg border bg-background shadow-sm">
                  <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 px-3 text-sm font-medium">
                    <span className="inline-flex items-center gap-2">
                      <MessageSquareText className="h-4 w-4 text-primary" />
                      {item.note ? "Sửa ghi chú món" : "Thêm ghi chú món"}
                    </span>
                    <ChevronRight className="h-4 w-4 text-muted-foreground transition group-open:rotate-90" />
                  </summary>
                  <div className="border-t p-3">
                    <AppTextarea
                      label={`Ghi chú cho ${item.name}`}
                      value={item.note}
                      onChange={(event) => cartState.updateNote(item.item_id, event.target.value)}
                      className="min-h-16"
                      placeholder="Ví dụ: ít cay, để riêng nước sốt..."
                    />
                  </div>
                </details>
              </div>
            );
          })}
        </div>
        
        {/* Footer Tạm tính */}
        <div className="border-t bg-secondary/20 p-4 sm:p-5">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-muted-foreground">Tạm tính ({cart.items.length} món)</span>
            <PriceText amount={String(subtotal)} currency={currency} className="text-xl font-bold text-primary" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
