"use client";

import Link from "next/link";
import { useMutation, useQuery } from "@tanstack/react-query";
import { CalendarDays, Clock3, Search, ShieldCheck, ShoppingBag, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { createRoundedFutureLocalDateTimeInput, toUtcIsoFromLocalDateTimeInput } from "@/lib/contracts/datetime";
import { formatMoney } from "@/lib/contracts/format";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { listMenuCategories, listMenuItems, previewMenuPreorder } from "./api";
import { MenuItemImage } from "./menu-item-image";

const menuHeroImageUrl = "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80";

const trustCues = [
  { icon: Clock3, label: "Giữ bàn tạm thời khi bạn chọn bàn" },
  { icon: ShieldCheck, label: "Đặt cọc và thanh toán qua luồng bảo vệ" },
  { icon: Sparkles, label: "Gợi ý bàn theo số khách và khung giờ" },
];

export function MenuPage() {
  const [q, setQ] = useState("");
  const [categoryId, setCategoryId] = useState<number | null>(null);

  const filters = useMemo(() => ({ q: q || null, categoryId }), [categoryId, q]);
  const itemsQuery = useQuery({
    queryKey: queryKeys.menu.items(filters),
    queryFn: () => listMenuItems(filters),
  });
  const categoriesQuery = useQuery({
    queryKey: queryKeys.menu.categories(),
    queryFn: listMenuCategories,
    enabled: featureFlags.menuCategories,
  });
  const previewMutation = useMutation({
    mutationFn: (itemId: number) =>
      previewMenuPreorder({
        start_time: toUtcIsoFromLocalDateTimeInput(createRoundedFutureLocalDateTimeInput()),
        pre_order_items: [{ item_id: itemId, quantity: 1 }],
    }),
    onSuccess() {
      toast.success("Đã kiểm tra món đặt trước.");
    },
    onError(error) {
      toast.error(error instanceof Error ? error.message : "Chưa kiểm tra được món đặt trước.");
    },
  });

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6">
      <section className="overflow-hidden rounded-lg border bg-card shadow-sm">
        <div className="grid gap-0 lg:grid-cols-[minmax(0,1fr)_420px]">
          <div className="space-y-5 p-5 sm:p-6 lg:p-8">
            <Badge variant="outline" className="rounded-md">Thực đơn RestaurantPOS</Badge>
            <div className="space-y-3">
              <h1 className="max-w-2xl text-4xl font-semibold leading-tight tracking-normal">Chọn món ngon, giữ bàn đúng giờ</h1>
              <p className="max-w-xl text-muted-foreground">
                Xem món đang phục vụ, kiểm tra món đặt trước và tìm bàn phù hợp trước khi bạn đến nhà hàng.
              </p>
            </div>
            <div className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-3">
              {trustCues.map((cue) => {
                const Icon = cue.icon;

                return (
                  <span key={cue.label} className="flex min-h-11 items-center gap-2 rounded-lg border bg-background/70 px-3">
                    <Icon className="h-4 w-4 text-primary" />
                    {cue.label}
                  </span>
                );
              })}
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
              <Button asChild className="min-h-11 rounded-lg">
                <Link href="/booking">
                  <CalendarDays className="mr-2 h-4 w-4" />
                  Tìm bàn
                </Link>
              </Button>
              <Button asChild variant="outline" className="min-h-11 rounded-lg">
                <Link href="/reservations">Xem lịch đặt</Link>
              </Button>
            </div>
          </div>
          <div className="relative min-h-64 overflow-hidden bg-secondary lg:min-h-full">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={menuHeroImageUrl} alt="Bàn ăn được chuẩn bị sẵn tại nhà hàng" className="h-full w-full object-cover" />
            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-4 text-sm font-medium text-white">
              Thực đơn rõ giá, đặt bàn rõ trạng thái.
            </div>
          </div>
        </div>
      </section>

      <section className="mt-6 space-y-4">
        <div className="sticky top-[4.75rem] z-20 space-y-3 rounded-lg border bg-background/95 p-3 shadow-sm backdrop-blur">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-3 h-5 w-5 text-muted-foreground" />
            <Input
              type="search"
              aria-label="Tìm món trong thực đơn"
              value={q}
              onChange={(event) => setQ(event.target.value)}
              placeholder="Tìm phở, cơm, đồ uống..."
              className="min-h-12 rounded-lg pl-10"
            />
          </div>

          {featureFlags.menuCategories && categoriesQuery.data?.length ? (
            <div className="flex gap-2 overflow-x-auto pb-1">
            <Button
              type="button"
              variant={categoryId === null ? "default" : "outline"}
              className="min-h-10 shrink-0 rounded-lg"
              onClick={() => setCategoryId(null)}
            >
              Tất cả
            </Button>
            {categoriesQuery.data.map((category) => (
              <Button
                type="button"
                key={category.category_id}
                variant={categoryId === category.category_id ? "default" : "outline"}
                className="min-h-10 shrink-0 rounded-lg"
                onClick={() => setCategoryId(category.category_id)}
              >
                {displayMenuText(category.name, "Danh mục")}
              </Button>
            ))}
            </div>
          ) : null}
        </div>

        {itemsQuery.isLoading ? <LoadingBlock label="Đang tải thực đơn" /> : null}
        {itemsQuery.error ? <ErrorState error={itemsQuery.error} title="Chưa tải được thực đơn" onRetry={() => itemsQuery.refetch()} /> : null}
        {itemsQuery.data && itemsQuery.data.length === 0 ? (
          <EmptyState title="Chưa có món phù hợp" description="Thử từ khóa khác hoặc bỏ lọc danh mục đang chọn." />
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {itemsQuery.data?.map((item) => {
            const itemName = displayMenuText(item.name, "Món ăn");
            const categoryName = displayMenuText(item.category_name, "Món ăn");
            const description = displayMenuText(item.description, "Nhà hàng sẽ cung cấp thêm thông tin khi bạn gọi món.");

            return (
              <Card key={item.item_id} className="group overflow-hidden rounded-lg border bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div className="relative">
                  <MenuItemImage item={item} className="aspect-[4/3] h-auto" />
                  <div className="absolute bottom-3 left-3 flex flex-wrap gap-2">
                    <Badge variant={item.is_available ? "outline" : "secondary"} className="rounded-md bg-background/90">
                      {item.is_available ? "Còn phục vụ" : "Tạm hết"}
                    </Badge>
                    <Badge variant="outline" className="rounded-md bg-background/90">
                      {categoryName}
                    </Badge>
                  </div>
                </div>
                <CardContent className="space-y-4 p-4">
                  <div className="space-y-1">
                    <div className="flex items-start justify-between gap-3">
                      <h2 className="text-lg font-semibold">{itemName}</h2>
                      <span className="shrink-0 font-semibold">
                        {formatMoney(item.price.amount, item.price.currency ?? "VND")}
                      </span>
                    </div>
                    <p className="line-clamp-2 min-h-11 text-sm text-muted-foreground">
                      {description}
                    </p>
                  </div>
                  <div className="flex items-center justify-between gap-2">
                    <Badge variant="outline" className="rounded-md">
                      {item.preorder.enabled ? "Có thể đặt trước" : "Dùng tại bàn"}
                    </Badge>
                    {!item.is_available ? <span className="text-sm font-medium text-muted-foreground">Hỏi nhân viên để được gợi ý món khác.</span> : null}
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    {featureFlags.menuItemDetail ? (
                      <Button asChild variant="outline" className="min-h-11 rounded-lg">
                        <Link href={`/menu/${item.item_id}`}>Chi tiết</Link>
                      </Button>
                    ) : (
                      <Button type="button" variant="outline" className="min-h-11 rounded-lg" disabled>
                        Chi tiết
                      </Button>
                    )}
                    <Button
                      type="button"
                      className="min-h-11 rounded-lg"
                      disabled={!item.preorder.enabled || previewMutation.isPending}
                      onClick={() => previewMutation.mutate(item.item_id)}
                    >
                      <ShoppingBag className="mr-2 h-4 w-4" />
                      Kiểm tra đặt trước
                    </Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </section>
    </main>
  );
}
