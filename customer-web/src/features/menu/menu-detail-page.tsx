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

export function MenuDetailPage({ id }: { id: number }) {
  const itemQuery = useQuery({
    queryKey: queryKeys.menu.item(id),
    queryFn: () => getMenuItem(id),
  });

  if (itemQuery.isLoading) {
    return (
      <main className="mx-auto w-full max-w-4xl px-4 py-6">
        <LoadingBlock label="Loading menu item" />
      </main>
    );
  }

  if (itemQuery.error || !itemQuery.data) {
    return (
      <main className="mx-auto w-full max-w-4xl px-4 py-6">
        <ErrorState error={itemQuery.error} title="Menu item is unavailable" onRetry={() => itemQuery.refetch()} />
      </main>
    );
  }

  const item = itemQuery.data.data;

  return (
    <main className="mx-auto w-full max-w-4xl px-4 py-6">
      <Button asChild variant="ghost" className="mb-4 rounded-lg">
        <Link href="/">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to menu
        </Link>
      </Button>

      <div className="grid gap-5 md:grid-cols-[1fr_320px]">
        <section className="overflow-hidden rounded-lg border bg-card">
          {item.img_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={item.img_url} alt={item.name} className="h-72 w-full object-cover" />
          ) : (
            <div className="flex h-72 items-center justify-center bg-secondary text-muted-foreground">
              {item.category_name ?? "Menu item"}
            </div>
          )}
          <div className="space-y-4 p-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h1 className="text-3xl font-semibold tracking-normal">{item.name}</h1>
                <p className="mt-2 text-muted-foreground">{item.description ?? "Ask the restaurant team for details."}</p>
              </div>
              <span className="text-xl font-semibold">{formatMoney(item.price.amount, item.price.currency ?? "USD")}</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <Badge variant="outline" className="rounded-md">{item.category_name ?? "Menu"}</Badge>
              <Badge variant={item.is_available ? "outline" : "secondary"} className="rounded-md">
                {item.is_available ? "Available" : "Unavailable"}
              </Badge>
              <Badge variant="outline" className="rounded-md">
                {item.preorder.enabled ? "Preorder supported" : "Dine-in only"}
              </Badge>
            </div>
          </div>
        </section>

        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Preorder policy</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-muted-foreground">
            <p>Cutoff: {item.preorder.cutoff_minutes} minutes before visit.</p>
            <p>Daily quota: {item.preorder.quota_per_day ?? "No limit published"}.</p>
            <p>{item.preorder.requires_preview_validation ? "Preview is validated before replacement." : "Preview validation is not required."}</p>
            <Button asChild className="mt-2 w-full rounded-lg">
              <Link href="/booking">Book a table</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </main>
  );
}
