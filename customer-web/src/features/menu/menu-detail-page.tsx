"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/contracts/format";
import { getMenuItem } from "./api";
import { MenuItemImage } from "./menu-item-image";

export function MenuDetailPage({ id }: { id: number }) {
  const itemQuery = useQuery({
    queryKey: queryKeys.menu.item(id),
    queryFn: () => getMenuItem(id),
  });

  if (itemQuery.isLoading) {
    return (
      <main className="mx-auto w-full max-w-4xl px-4 py-6">
        <LoadingBlock label="Đang tải món ăn" />
      </main>
    );
  }

  if (itemQuery.error || !itemQuery.data) {
    return (
      <main className="mx-auto w-full max-w-4xl px-4 py-6">
        <ErrorState error={itemQuery.error} title="Chưa tải được món ăn" onRetry={() => itemQuery.refetch()} />
      </main>
    );
  }

  const item = itemQuery.data;

  return (
    <main className="mx-auto w-full max-w-4xl px-4 py-6">
      <Button asChild variant="ghost" className="mb-4 rounded-lg">
        <Link href="/">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Quay lại thực đơn
        </Link>
      </Button>

      <div className="grid gap-5 md:grid-cols-[1fr_320px]">
        <section className="group overflow-hidden rounded-lg border bg-card shadow-sm">
          <MenuItemImage item={item} className="h-72" />
          <div className="space-y-4 p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h1 className="text-3xl font-semibold tracking-normal">{item.name}</h1>
                <p className="mt-2 text-muted-foreground">{item.description ?? "Nhân viên nhà hàng sẽ tư vấn thêm khi bạn gọi món."}</p>
              </div>
              <span className="text-xl font-semibold">{formatMoney(item.price.amount, item.price.currency ?? "USD")}</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <Badge variant="outline" className="rounded-md">{item.category_name ?? "Thực đơn"}</Badge>
              <Badge variant={item.is_available ? "outline" : "secondary"} className="rounded-md">
                {item.is_available ? "Còn phục vụ" : "Tạm hết"}
              </Badge>
              <Badge variant="outline" className="rounded-md">
                {item.preorder.enabled ? "Có thể đặt trước" : "Dùng tại bàn"}
              </Badge>
            </div>
          </div>
        </section>

        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Thông tin đặt trước</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-muted-foreground">
            <p>Nhận đặt trước đến {item.preorder.cutoff_minutes} phút trước giờ đến.</p>
            <p>Giới hạn mỗi ngày: {item.preorder.quota_per_day ?? "Nhà hàng chưa công bố"}.</p>
            <p>{item.preorder.requires_preview_validation ? "Nhà hàng sẽ kiểm tra lại trước khi nhận món đặt trước." : "Món này không cần bước kiểm tra riêng."}</p>
            <Button asChild className="mt-2 w-full rounded-lg">
              <Link href="/booking">Đặt bàn</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </main>
  );
}
