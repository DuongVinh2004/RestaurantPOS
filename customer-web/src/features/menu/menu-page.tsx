"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, CalendarDays, ChevronLeft, ChevronRight, Heart, Search, ShoppingBag, SlidersHorizontal, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import {
  AppBadge,
  AppButton,
  AppCard,
  EmptyState,
  ErrorState,
  PriceText,
  StatusPill,
} from "@/components/customer/ui";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useBranchSelection } from "@/features/branch/hooks";
import { PreorderCartPanel } from "@/features/preorder/cart-panel";
import { useLocalPreorderCart } from "@/features/preorder/local-cart";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { listMenuCategories, listMenuItems, type MenuItem } from "./api";
import { MenuItemImage } from "./menu-item-image";

type MenuSort = "recommended" | "price_asc" | "price_desc";

const menuHeroImageUrl = "/customer-web/menu-hero.jpg";
const menuPageSize = 9;
const favoriteMenuItemsStorageKey = "restaurantpos.customer-web.favorite-menu-items";

const sortOptions = [
  { value: "recommended", label: "Đề xuất" },
  { value: "price_asc", label: "Giá thấp đến cao" },
  { value: "price_desc", label: "Giá cao đến thấp" },
];

function parseCategoryId(value: string | null): number | null {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function parseSort(value: string | null): MenuSort {
  return value === "price_asc" || value === "price_desc" ? value : "recommended";
}

function parsePage(value: string | null): number {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
}

function clampPage(page: number, totalPages: number): number {
  return Math.min(Math.max(page, 1), Math.max(totalPages, 1));
}

function visiblePageNumbers(currentPage: number, totalPages: number): Array<number | "ellipsis"> {
  const pages = new Set([1, currentPage - 1, currentPage, currentPage + 1, totalPages]);
  const sorted = Array.from(pages)
    .filter((page) => page >= 1 && page <= totalPages)
    .sort((left, right) => left - right);
  const visible: Array<number | "ellipsis"> = [];

  sorted.forEach((page, index) => {
    const previous = sorted[index - 1];

    if (previous && page - previous > 1) {
      visible.push("ellipsis");
    }

    visible.push(page);
  });

  return visible;
}

function priceNumber(item: MenuItem): number {
  const amount = Number(item.price.amount ?? 0);

  return Number.isFinite(amount) ? amount : 0;
}

function sortMenuItems(items: MenuItem[], sort: MenuSort): MenuItem[] {
  const nextItems = [...items];

  if (sort === "price_asc") {
    nextItems.sort((left, right) => priceNumber(left) - priceNumber(right));
  }

  if (sort === "price_desc") {
    nextItems.sort((left, right) => priceNumber(right) - priceNumber(left));
  }

  return nextItems;
}

function readFavoriteMenuItemIds(): Set<number> {
  if (typeof window === "undefined") {
    return new Set();
  }

  try {
    const rawValue = window.localStorage.getItem(favoriteMenuItemsStorageKey);
    const parsed = rawValue ? JSON.parse(rawValue) : [];

    if (!Array.isArray(parsed)) {
      return new Set();
    }

    return new Set(
      parsed
        .map((item) => Number(item))
        .filter((item): item is number => Number.isInteger(item) && item > 0),
    );
  } catch {
    return new Set();
  }
}

function storeFavoriteMenuItemIds(ids: Set<number>): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(favoriteMenuItemsStorageKey, JSON.stringify([...ids]));
}

export function MenuPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [searchText, setSearchText] = useState(() => searchParams.get("q") ?? "");
  const [debouncedSearch, setDebouncedSearch] = useState(searchText);
  const [categoryId, setCategoryId] = useState<number | null>(() => parseCategoryId(searchParams.get("category")));
  const [availableOnly, setAvailableOnly] = useState(() => searchParams.get("available") === "1");
  const [favoritesOnly, setFavoritesOnly] = useState(false);
  const [preorderOnly, setPreorderOnly] = useState(() => featureFlags.preorder && searchParams.get("preorder") === "1");
  const [sort, setSort] = useState<MenuSort>(() => parseSort(searchParams.get("sort")));
  const [selectedPage, setSelectedPage] = useState(() => parsePage(searchParams.get("page")));
  const [favoriteItemIds, setFavoriteItemIds] = useState<Set<number>>(() => readFavoriteMenuItemIds());
  const didInitializeFilters = useRef(false);
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const cart = useLocalPreorderCart(selectedBranch?.branchId ?? null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(searchText.trim()), 300);

    return () => window.clearTimeout(timer);
  }, [searchText]);

  const filters = useMemo(
    () => ({
      q: debouncedSearch || null,
      categoryId,
      preorderOnly: featureFlags.preorder && preorderOnly ? true : null,
    }),
    [categoryId, debouncedSearch, preorderOnly],
  );
  const itemsQuery = useQuery({
    queryKey: queryKeys.menu.items(filters),
    queryFn: () => listMenuItems(filters),
  });
  const categoriesQuery = useQuery({
    queryKey: queryKeys.menu.categories(),
    queryFn: listMenuCategories,
    enabled: featureFlags.menuCategories,
  });
  const displayedItems = useMemo(() => {
    const items = itemsQuery.data ?? [];
    const filtered = items.filter((item) => {
      if (availableOnly && !item.is_available) {
        return false;
      }

      if (favoritesOnly && !favoriteItemIds.has(item.item_id)) {
        return false;
      }

      return true;
    });

    return sortMenuItems(filtered, sort);
  }, [availableOnly, favoriteItemIds, favoritesOnly, itemsQuery.data, sort]);
  const totalPages = Math.max(1, Math.ceil(displayedItems.length / menuPageSize));
  const currentPage = clampPage(selectedPage, totalPages);
  const pageStartIndex = displayedItems.length > 0 ? (currentPage - 1) * menuPageSize : 0;
  const paginatedItems = displayedItems.slice(pageStartIndex, pageStartIndex + menuPageSize);
  const pageFrom = displayedItems.length > 0 ? pageStartIndex + 1 : 0;
  const pageTo = Math.min(pageStartIndex + paginatedItems.length, displayedItems.length);
  const pageForUrl = itemsQuery.data ? currentPage : selectedPage;
  const hasActiveFilters = Boolean(searchText.trim() || categoryId || availableOnly || favoritesOnly || (featureFlags.preorder && preorderOnly) || sort !== "recommended");

  useEffect(() => {
    if (!didInitializeFilters.current) {
      didInitializeFilters.current = true;
      return;
    }

    setSelectedPage(1);
  }, [availableOnly, categoryId, debouncedSearch, favoritesOnly, preorderOnly, sort]);

  useEffect(() => {
    const params = new URLSearchParams();

    if (debouncedSearch) {
      params.set("q", debouncedSearch);
    }

    if (categoryId) {
      params.set("category", String(categoryId));
    }

    if (availableOnly) {
      params.set("available", "1");
    }

    if (featureFlags.preorder && preorderOnly) {
      params.set("preorder", "1");
    }

    if (sort !== "recommended") {
      params.set("sort", sort);
    }

    if (pageForUrl > 1) {
      params.set("page", String(pageForUrl));
    }

    const nextUrl = params.toString() ? `${pathname}?${params.toString()}` : pathname;
    const currentUrl = searchParams.toString() ? `${pathname}?${searchParams.toString()}` : pathname;

    if (nextUrl !== currentUrl) {
      router.replace(nextUrl, { scroll: false });
    }
  }, [availableOnly, categoryId, debouncedSearch, pageForUrl, pathname, preorderOnly, router, searchParams, sort]);

  const clearFilters = () => {
    setSearchText("");
    setDebouncedSearch("");
    setCategoryId(null);
    setAvailableOnly(false);
    setFavoritesOnly(false);
    setPreorderOnly(false);
    setSort("recommended");
    setSelectedPage(1);
  };

  const changePage = (nextPage: number) => {
    setSelectedPage(clampPage(nextPage, totalPages));
    window.setTimeout(() => {
      document.getElementById("menu-results")?.scrollIntoView?.({ block: "start", behavior: "smooth" });
    }, 0);
  };

  const addToCart = (item: MenuItem) => {
    if (!selectedBranch) {
      toast.error("Chọn chi nhánh trước khi thêm món đặt trước.");
      return;
    }

    if (!featureFlags.preorder || !item.preorder.enabled || !item.is_available) {
      toast.error("Món này hiện chưa thể đặt trước.");
      return;
    }

    cart.addItem(item, 1);
    toast.success(`Đã thêm ${displayMenuText(item.name, "món")} vào giỏ đặt trước.`);
  };

  const toggleFavorite = (item: MenuItem) => {
    const itemName = displayMenuText(item.name, "món");

    setFavoriteItemIds((current) => {
      const next = new Set(current);
      const isFavorite = next.has(item.item_id);

      if (isFavorite) {
        next.delete(item.item_id);
        toast.message(`Đã bỏ ${itemName} khỏi món yêu thích.`);
      } else {
        next.add(item.item_id);
        toast.success(`Đã lưu ${itemName} vào món yêu thích.`);
      }

      storeFavoriteMenuItemIds(next);

      return next;
    });
  };

  return (
    <main className="mx-auto w-full max-w-7xl px-4 py-7 pb-28 lg:pb-10">
      <section className="overflow-hidden rounded-lg border bg-card shadow-[var(--restaurant-shadow)]">
        <div className="grid gap-0 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div className="space-y-6 p-5 sm:p-6 lg:p-8">
            <div className="space-y-3">
              <h1 className="max-w-2xl text-4xl font-bold leading-tight tracking-normal">
                Xem món đang phục vụ trước khi giữ bàn
              </h1>
              <p className="max-w-xl text-base leading-7 text-muted-foreground">
                Tìm món, lọc theo tình trạng phục vụ và chọn món bạn muốn thưởng thức khi đến nhà hàng.
              </p>
            </div>
            <div className="grid gap-2 text-sm sm:grid-cols-2">
              <span className="rounded-lg border bg-secondary/45 px-3 py-2">
                <span className="block text-muted-foreground">Chi nhánh</span>
                <span className="block truncate font-semibold">{selectedBranch ? selectedBranch.branchName : "Đang tải chi nhánh"}</span>
              </span>
              <span className="rounded-lg border bg-secondary/45 px-3 py-2">
                <span className="block text-muted-foreground">Gợi ý</span>
                <span className="block font-semibold">Đặt bàn để thưởng thức tại nhà hàng</span>
              </span>
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
              <AppButton asChild>
                <Link href="/booking">
                  <CalendarDays className="h-4 w-4" />
                  Đặt bàn
                </Link>
              </AppButton>
              <AppButton asChild variant="outline">
                <Link href="/reservations">
                  Theo dõi lịch đặt
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </AppButton>
            </div>
          </div>
          <div className="relative min-h-72 overflow-hidden bg-secondary lg:min-h-full">
            <Image
              src={menuHeroImageUrl}
              alt="Các món ăn đã chuẩn bị trên bàn nhà hàng"
              fill
              className="object-cover"
              priority
              sizes="(min-width: 1024px) 360px, 100vw"
            />
            <div className="absolute bottom-4 right-4 rounded-md bg-background/92 px-3 py-2 text-sm font-semibold text-foreground shadow-sm">
              Gợi ý hôm nay
            </div>
          </div>
        </div>
      </section>

      <div className={`mt-6 grid min-w-0 gap-5 ${featureFlags.preorder ? "lg:grid-cols-[minmax(0,1fr)_360px]" : ""}`}>
        <section className="min-w-0 space-y-4">
          <AppCard className="min-w-0 overflow-hidden p-0 lg:sticky lg:top-[5rem] lg:z-20">
            <div className="space-y-3 p-3 sm:p-4">
              <div className="grid min-w-0 gap-2 lg:grid-cols-[minmax(16rem,1fr)_12rem_auto] lg:items-center">
                <div className="relative min-w-0">
                  <Label htmlFor="menu-search" className="sr-only">Tìm trong thực đơn</Label>
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="menu-search"
                    type="search"
                    value={searchText}
                    onChange={(event) => setSearchText(event.target.value)}
                    placeholder="Tìm mì, cơm, đồ uống..."
                    className="min-h-11 rounded-lg pl-9 lg:min-h-10"
                  />
                </div>

                <div className="min-w-0">
                  <Label htmlFor="menu-sort" className="sr-only">Sắp xếp</Label>
                  <Select value={sort} onValueChange={(value) => setSort(parseSort(value))}>
                    <SelectTrigger id="menu-sort" className="min-h-11 w-full rounded-lg lg:min-h-10" aria-label="Sắp xếp">
                      <SelectValue placeholder="Sắp xếp" />
                    </SelectTrigger>
                    <SelectContent>
                      {sortOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="flex min-h-10 items-center justify-between gap-3 rounded-lg border bg-secondary/45 px-3 text-sm lg:justify-center">
                  <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                    <SlidersHorizontal className="h-4 w-4" />
                    Bộ lọc
                  </span>
                  <span className="font-semibold tabular-nums">{displayedItems.length} món</span>
                </div>
              </div>

              <div className="flex min-w-0 gap-2 overflow-x-auto pb-1" aria-label="Bộ lọc thực đơn">
                {featureFlags.menuCategories && categoriesQuery.data?.length ? (
                  <AppButton
                    type="button"
                    variant={categoryId === null ? "default" : "outline"}
                    size="sm"
                    className="min-h-9 shrink-0 px-3"
                    onClick={() => setCategoryId(null)}
                  >
                    Tất cả
                  </AppButton>
                ) : null}
                <AppButton
                  type="button"
                  variant={availableOnly ? "default" : "outline"}
                  size="sm"
                  className="min-h-9 shrink-0 px-3"
                  onClick={() => setAvailableOnly((current) => !current)}
                >
                  Còn phục vụ
                </AppButton>
                <AppButton
                  type="button"
                  variant={favoritesOnly ? "default" : "outline"}
                  size="sm"
                  className="min-h-9 shrink-0 px-3"
                  onClick={() => setFavoritesOnly((current) => !current)}
                >
                  <Heart className={favoritesOnly ? "h-3.5 w-3.5 fill-current" : "h-3.5 w-3.5"} />
                  Yêu thích ({favoriteItemIds.size})
                </AppButton>
                {featureFlags.preorder ? (
                  <AppButton
                    type="button"
                    variant={preorderOnly ? "default" : "outline"}
                    size="sm"
                    className="min-h-9 shrink-0 px-3"
                    onClick={() => setPreorderOnly((current) => !current)}
                  >
                    Có thể thêm món
                  </AppButton>
                ) : null}
                {featureFlags.menuCategories && categoriesQuery.data?.length ? (
                  <>
                    <span className="h-9 w-px shrink-0 bg-border" aria-hidden="true" />
                    {categoriesQuery.data.map((category) => (
                      <AppButton
                        type="button"
                        key={category.category_id}
                        variant={categoryId === category.category_id ? "default" : "outline"}
                        size="sm"
                        className="min-h-9 shrink-0 px-3"
                        onClick={() => setCategoryId(category.category_id)}
                      >
                        {displayMenuText(category.name, "Danh mục")}
                      </AppButton>
                    ))}
                  </>
                ) : null}
                {hasActiveFilters ? (
                  <AppButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-9 shrink-0 px-3 text-muted-foreground"
                    onClick={clearFilters}
                  >
                    <X className="h-3.5 w-3.5" />
                    Xóa lọc
                  </AppButton>
                ) : null}
              </div>
            </div>
          </AppCard>

          {itemsQuery.isLoading ? (
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true">
              {Array.from({ length: 6 }).map((_, index) => (
                <AppCard key={index} className="h-80 animate-pulse bg-secondary/50" />
              ))}
            </div>
          ) : null}
          {itemsQuery.error ? (
            <ErrorState error={itemsQuery.error} title="Chưa tải được thực đơn" onRetry={() => itemsQuery.refetch()} />
          ) : null}
          {itemsQuery.data && displayedItems.length === 0 ? (
            <EmptyState
              title="Không có món phù hợp"
              description="Thử từ khóa, danh mục hoặc bộ lọc tình trạng khác."
            />
          ) : null}

          {itemsQuery.data && displayedItems.length > 0 ? (
            <div id="menu-results" className="flex flex-col gap-1 rounded-lg border bg-card px-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
              <p className="text-muted-foreground">
                Hiển thị {pageFrom}-{pageTo} trong {displayedItems.length} món
              </p>
              {totalPages > 1 ? (
                <p className="font-semibold tabular-nums">
                  Trang {currentPage}/{totalPages}
                </p>
              ) : null}
            </div>
          ) : null}

          <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {paginatedItems.map((item) => {
              const itemName = displayMenuText(item.name, "Món");
              const categoryName = displayMenuText(item.category_name, "Thực đơn");
              const description = displayMenuText(item.description, "Nhà hàng sẽ bổ sung mô tả khi bạn gọi món.");
              const canPreorder = featureFlags.preorder && item.preorder.enabled && item.is_available;

              return (
                <AppCard key={item.item_id} className="group min-w-0 overflow-hidden transition hover:-translate-y-0.5 hover:border-primary/35">
                  <div className="relative">
                    <MenuItemImage item={item} className="aspect-[4/3] h-auto" />
                    <AppButton
                      type="button"
                      variant={favoriteItemIds.has(item.item_id) ? "default" : "outline"}
                      size="icon"
                      className="absolute right-3 top-3 bg-background/92 backdrop-blur"
                      aria-label={favoriteItemIds.has(item.item_id) ? `Bỏ yêu thích ${itemName}` : `Yêu thích ${itemName}`}
                      onClick={() => toggleFavorite(item)}
                    >
                      <Heart className={favoriteItemIds.has(item.item_id) ? "h-4 w-4 fill-current" : "h-4 w-4"} />
                    </AppButton>
                    <div className="absolute bottom-3 left-3 flex flex-wrap gap-2">
                      <AppBadge className="bg-background/92">Món gợi ý</AppBadge>
                      <AppBadge className="bg-background/92">
                        {item.is_available ? "Còn phục vụ" : "Tạm hết"}
                      </AppBadge>
                      <AppBadge className="bg-background/92">{categoryName}</AppBadge>
                    </div>
                  </div>
                  <div className="space-y-4 p-4">
                    <div className="space-y-1">
                      <div className="flex items-start justify-between gap-3">
                        <h2 className="text-lg font-semibold">{itemName}</h2>
                        <PriceText amount={item.price.amount} currency={item.price.currency} />
                      </div>
                      <p className="line-clamp-2 min-h-11 text-sm text-muted-foreground">{description}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {featureFlags.preorder && item.preorder.enabled ? (
                        <StatusPill label="Có thể thêm trước" tone="success" />
                      ) : (
                        <StatusPill label="Thưởng thức tại nhà hàng" tone="neutral" />
                      )}
                      {!item.is_available ? <StatusPill label="Tạm hết" tone="warning" /> : null}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                      {featureFlags.menuItemDetail ? (
                        <AppButton asChild variant="outline" className="min-w-0 w-full">
                          <Link href={`/menu/${item.item_id}`}>Chi tiết</Link>
                        </AppButton>
                      ) : (
                        <AppButton type="button" variant="outline" className="min-w-0 w-full" disabled>
                          Chi tiết
                        </AppButton>
                      )}
                      {featureFlags.preorder ? (
                        <AppButton type="button" className="min-w-0 w-full" disabled={!canPreorder} onClick={() => addToCart(item)}>
                          <ShoppingBag className="h-4 w-4" />
                          Thêm món
                        </AppButton>
                      ) : (
                        <AppButton asChild className="min-w-0 w-full">
                          <Link href="/booking">
                            <CalendarDays className="h-4 w-4" />
                            Đặt bàn
                          </Link>
                        </AppButton>
                      )}
                    </div>
                  </div>
                </AppCard>
              );
            })}
          </div>

          <MenuPager currentPage={currentPage} totalPages={totalPages} onPageChange={changePage} />
        </section>

        {featureFlags.preorder ? (
          <aside className="order-first space-y-4 lg:fixed lg:right-[max(1rem,calc((100vw-80rem)/2+1rem))] lg:top-[5.25rem] lg:z-30 lg:w-[360px] lg:order-none">
            <PreorderCartPanel branchId={selectedBranch?.branchId ?? null} branchName={selectedBranch?.branchName ?? null} compact />
          </aside>
        ) : null}
      </div>
    </main>
  );
}

function MenuPager({
  currentPage,
  totalPages,
  onPageChange,
}: {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}) {
  if (totalPages <= 1) {
    return null;
  }

  const pages = visiblePageNumbers(currentPage, totalPages);

  return (
    <nav
      aria-label="Phân trang thực đơn"
      className="flex flex-col gap-3 rounded-lg border bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <p className="text-sm text-muted-foreground">Chuyển trang để xem tiếp món, không cần tải lại thực đơn.</p>
      <div className="flex items-center justify-between gap-2 sm:justify-end">
        <AppButton
          type="button"
          variant="outline"
          size="sm"
          className="min-h-10 px-3"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
        >
          <ChevronLeft className="h-4 w-4" />
          Trang trước
        </AppButton>
        <div className="hidden items-center gap-1 sm:flex">
          {pages.map((page, index) =>
            page === "ellipsis" ? (
              <span key={`ellipsis-${index}`} className="px-2 text-sm text-muted-foreground">
                ...
              </span>
            ) : (
              <AppButton
                key={page}
                type="button"
                variant={page === currentPage ? "default" : "outline"}
                size="sm"
                className="min-h-10 min-w-10 px-2"
                aria-label={`Trang ${page}`}
                aria-current={page === currentPage ? "page" : undefined}
                onClick={() => onPageChange(page)}
              >
                {page}
              </AppButton>
            ),
          )}
        </div>
        <span className="min-w-16 text-center text-sm font-semibold tabular-nums sm:hidden">
          {currentPage}/{totalPages}
        </span>
        <AppButton
          type="button"
          variant="outline"
          size="sm"
          className="min-h-10 px-3"
          disabled={currentPage >= totalPages}
          onClick={() => onPageChange(currentPage + 1)}
        >
          Trang sau
          <ChevronRight className="h-4 w-4" />
        </AppButton>
      </div>
    </nav>
  );
}
