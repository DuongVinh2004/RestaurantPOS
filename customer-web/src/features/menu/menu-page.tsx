"use client";

import Link from "next/link";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Search, ShoppingBag } from "lucide-react";
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
import { listMenuCategories, listMenuItems, previewMenuPreorder } from "./api";

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
      toast.success("Preorder preview refreshed.");
    },
    onError(error) {
      toast.error(error instanceof Error ? error.message : "Preorder preview failed.");
    },
  });

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6">
      <section className="grid gap-5 md:grid-cols-[1fr_320px] md:items-end">
        <div className="space-y-3">
          <Badge variant="outline" className="rounded-md">Live menu</Badge>
          <h1 className="max-w-2xl text-4xl font-semibold leading-tight tracking-normal">Browse the menu before your visit.</h1>
          <p className="max-w-xl text-muted-foreground">
            Search live menu items, review availability, and start a table booking when you are ready.
          </p>
        </div>
        <div className="flex gap-2">
          <Button asChild className="min-h-11 flex-1 rounded-lg">
            <Link href="/booking">Find a table</Link>
          </Button>
          <Button asChild variant="outline" className="min-h-11 flex-1 rounded-lg">
            <Link href="/reservations">My visits</Link>
          </Button>
        </div>
      </section>

      <section className="mt-6 space-y-4">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-3 h-5 w-5 text-muted-foreground" />
          <Input
            value={q}
            onChange={(event) => setQ(event.target.value)}
            placeholder="Search dishes"
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
              All
            </Button>
            {categoriesQuery.data.map((category) => (
              <Button
                type="button"
                key={category.category_id}
                variant={categoryId === category.category_id ? "default" : "outline"}
                className="min-h-10 shrink-0 rounded-lg"
                onClick={() => setCategoryId(category.category_id)}
              >
                {category.name}
              </Button>
            ))}
          </div>
        ) : null}

        {itemsQuery.isLoading ? <LoadingBlock label="Loading menu" /> : null}
        {itemsQuery.error ? <ErrorState error={itemsQuery.error} title="Menu is unavailable" onRetry={() => itemsQuery.refetch()} /> : null}
        {itemsQuery.data && itemsQuery.data.length === 0 ? (
          <EmptyState title="No menu items found" description="Try a different search or clear the selected category." />
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {itemsQuery.data?.map((item) => (
            <Card key={item.item_id} className="overflow-hidden rounded-lg">
              {item.img_url ? (
                // Backend image URLs are restaurant-managed and may be outside the Next image allow-list.
                // eslint-disable-next-line @next/next/no-img-element
                <img src={item.img_url} alt={item.name} className="h-44 w-full object-cover" />
              ) : (
                <div className="flex h-44 items-center justify-center bg-secondary text-sm font-medium text-muted-foreground">
                  {item.category_name ?? "Menu item"}
                </div>
              )}
              <CardContent className="space-y-4 p-4">
                <div className="space-y-1">
                  <div className="flex items-start justify-between gap-3">
                    <h2 className="text-lg font-semibold">{item.name}</h2>
                    <span className="shrink-0 font-semibold">
                      {formatMoney(item.price.amount, item.price.currency ?? "USD")}
                    </span>
                  </div>
                  <p className="line-clamp-2 min-h-11 text-sm text-muted-foreground">
                    {item.description ?? "Details are available from the restaurant."}
                  </p>
                </div>
                <div className="flex items-center justify-between gap-2">
                  <Badge variant={item.is_available ? "outline" : "secondary"} className="rounded-md">
                    {item.is_available ? "Available" : "Unavailable"}
                  </Badge>
                  <Badge variant="outline" className="rounded-md">
                    {item.preorder.enabled ? "Preorder" : "Dine-in only"}
                  </Badge>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <Button asChild variant="outline" className="rounded-lg">
                    <Link href={`/menu/${item.item_id}`}>Details</Link>
                  </Button>
                  <Button
                    type="button"
                    className="rounded-lg"
                    disabled={!item.preorder.enabled || previewMutation.isPending}
                    onClick={() => previewMutation.mutate(item.item_id)}
                  >
                    <ShoppingBag className="mr-2 h-4 w-4" />
                    Preview
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>
    </main>
  );
}
