"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, ChevronRight, Heart, Search, ShoppingBag, SlidersHorizontal, X, ArrowRight } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import {
  AppButton,
  AppCard,
  EmptyState,
  ErrorState,
  PriceText,
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
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { BookingProgress } from "@/components/booking/booking-progress";
import { useBranchSelection } from "@/features/branch/hooks";
import { PreorderCartPanel } from "@/features/preorder/cart-panel";
import { useLocalPreorderCart } from "@/features/preorder/local-cart";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { listMenuCategories, listMenuItems, type MenuItem } from "@/features/menu/api";
import { fetchFavorites, addFavorite, removeFavorite, syncFavorites } from "@/features/menu/api-favorites";
import { useAuth } from "@/providers/auth-provider";
import { MenuItemImage } from "@/features/menu/menu-item-image";
import { getTableHold } from "./api";
import { parseTableHoldState } from "./state";
import { ensureCustomerSessionId } from "@/lib/auth/storage";

type MenuSort = "recommended" | "price_asc" | "price_desc";

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
    if (previous && page - previous > 1) visible.push("ellipsis");
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
  if (sort === "price_asc") nextItems.sort((left, right) => priceNumber(left) - priceNumber(right));
  if (sort === "price_desc") nextItems.sort((left, right) => priceNumber(right) - priceNumber(left));
  return nextItems;
}

function readFavoriteMenuItemIds(): Set<number> {
  if (typeof window === "undefined") return new Set();
  try {
    const rawValue = window.localStorage.getItem(favoriteMenuItemsStorageKey);
    const parsed = rawValue ? JSON.parse(rawValue) : [];
    if (!Array.isArray(parsed)) return new Set();
    return new Set(parsed.map(Number).filter((item): item is number => Number.isInteger(item) && item > 0));
  } catch {
    return new Set();
  }
}

function storeFavoriteMenuItemIds(ids: Set<number>): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(favoriteMenuItemsStorageKey, JSON.stringify([...ids]));
}

export function BookingPreorderPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const [searchText, setSearchText] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState(searchText);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [availableOnly, setAvailableOnly] = useState(() => searchParams.get("available") === "1");
  const [favoritesOnly, setFavoritesOnly] = useState(false);
  const [comboOnly, setComboOnly] = useState(() => searchParams.get("combo") === "1");
  const [sort, setSort] = useState<MenuSort>(() => parseSort(searchParams.get("sort")));
  const [selectedPage, setSelectedPage] = useState(1);
  const [localFavoriteItemIds, setLocalFavoriteItemIds] = useState<Set<number>>(() => readFavoriteMenuItemIds());
  const [isMobileCartOpen, setIsMobileCartOpen] = useState(false);
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const cart = useLocalPreorderCart(selectedBranch?.branchId ?? null);
  const [customerSessionId] = useState(() => ensureCustomerSessionId());

  // Hold validation
  const holdId = searchParams.get("hold_id");
  const holdQuery = useQuery({
    queryKey: holdId ? queryKeys.tableBooking.hold(holdId) : ["tables", "hold", "none"],
    queryFn: () => getTableHold(holdId as string),
    enabled: Boolean(holdId),
    retry: false,
  });

  const holdState = holdQuery.data ? parseTableHoldState(holdQuery.data) : null;
  const isHoldActive = holdState?.isActive;

  useEffect(() => {
    if (holdQuery.isFetched && !isHoldActive) {
      toast.error("Thời gian giữ bàn đã hết hạn. Vui lòng chọn bàn lại.");
      router.push("/booking");
    }
  }, [holdQuery.isFetched, isHoldActive, router]);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(searchText.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [searchText]);

  const filters = useMemo(
    () => ({
      q: debouncedSearch || null,
      categoryId,
      preorderOnly: featureFlags.preorder ? true : null, // Always true for this page
    }),
    [categoryId, debouncedSearch],
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

  const favoritesQuery = useQuery({
    queryKey: ["me", "favorites"],
    queryFn: fetchFavorites,
    enabled: isAuthenticated,
    staleTime: 1000 * 60 * 5,
  });

  const activeFavoriteItemIds = useMemo(() => {
    if (isAuthenticated && favoritesQuery.data) {
      return new Set(favoritesQuery.data);
    }
    return localFavoriteItemIds;
  }, [isAuthenticated, favoritesQuery.data, localFavoriteItemIds]);

  const syncMutation = useMutation({
    mutationFn: syncFavorites,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["me", "favorites"] });
    },
  });

  useEffect(() => {
    if (isAuthenticated) {
      const localFavorites = readFavoriteMenuItemIds();
      if (localFavorites.size > 0) {
        syncMutation.mutate(Array.from(localFavorites));
        window.localStorage.removeItem(favoriteMenuItemsStorageKey);
        setLocalFavoriteItemIds(new Set());
      }
    }
  }, [isAuthenticated]); // eslint-disable-line react-hooks/exhaustive-deps

  const displayedItems = useMemo(() => {
    const items = itemsQuery.data ?? [];
    const filtered = items.filter((item) => {
      if (availableOnly && !item.is_available) return false;
      if (favoritesOnly && !activeFavoriteItemIds.has(item.item_id)) return false;
      if (comboOnly && !item.is_combo) return false;
      if (!item.preorder.enabled) return false; // Must be pre-orderable
      return true;
    });
    return sortMenuItems(filtered, sort);
  }, [availableOnly, activeFavoriteItemIds, favoritesOnly, comboOnly, itemsQuery.data, sort]);

  const totalPages = Math.max(1, Math.ceil(displayedItems.length / menuPageSize));
  const currentPage = clampPage(selectedPage, totalPages);
  const pageStartIndex = displayedItems.length > 0 ? (currentPage - 1) * menuPageSize : 0;
  const paginatedItems = displayedItems.slice(pageStartIndex, pageStartIndex + menuPageSize);

  const clearFilters = () => {
    setSearchText("");
    setDebouncedSearch("");
    setCategoryId(null);
    setAvailableOnly(false);
    setFavoritesOnly(false);
    setComboOnly(false);
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

  const addFavoriteMutation = useMutation({
    mutationFn: addFavorite,
    onMutate: async (itemId) => {
      await queryClient.cancelQueries({ queryKey: ["me", "favorites"] });
      const previousFavorites = queryClient.getQueryData<number[]>(["me", "favorites"]);
      if (previousFavorites) {
        queryClient.setQueryData<number[]>(["me", "favorites"], [...previousFavorites, itemId]);
      }
      return { previousFavorites };
    },
    onError: (_err, _newVal, context) => {
      if (context?.previousFavorites) {
        queryClient.setQueryData(["me", "favorites"], context.previousFavorites);
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["me", "favorites"] });
    },
  });

  const removeFavoriteMutation = useMutation({
    mutationFn: removeFavorite,
    onMutate: async (itemId) => {
      await queryClient.cancelQueries({ queryKey: ["me", "favorites"] });
      const previousFavorites = queryClient.getQueryData<number[]>(["me", "favorites"]);
      if (previousFavorites) {
        queryClient.setQueryData<number[]>(["me", "favorites"], previousFavorites.filter(id => id !== itemId));
      }
      return { previousFavorites };
    },
    onError: (_err, _newVal, context) => {
      if (context?.previousFavorites) {
        queryClient.setQueryData(["me", "favorites"], context.previousFavorites);
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["me", "favorites"] });
    },
  });

  const toggleFavorite = (item: MenuItem) => {
    const itemName = displayMenuText(item.name, "món");
    const isFavorite = activeFavoriteItemIds.has(item.item_id);

    if (isAuthenticated) {
      if (isFavorite) {
        removeFavoriteMutation.mutate(item.item_id);
        toast.message(`Đã bỏ ${itemName} khỏi món yêu thích.`);
      } else {
        addFavoriteMutation.mutate(item.item_id);
        toast.success(`Đã lưu ${itemName} vào món yêu thích.`);
      }
    } else {
      setLocalFavoriteItemIds((current) => {
        const next = new Set(current);
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
    }
  };

  const nextStepHref = `/reservations/new?${searchParams.toString()}`;

  if (!isHoldActive) {
    return (
      <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:pb-8 flex justify-center items-center h-64">
        <p className="text-muted-foreground">Đang kiểm tra trạng thái giữ bàn...</p>
      </main>
    );
  }

  return (
    <main className="mx-auto w-full max-w-7xl px-4 py-6 pb-28 lg:pb-10">
      <section className="mb-6 space-y-3">
        <Badge variant="outline" className="rounded-md">Đặt bàn</Badge>
        <h1 className="text-4xl font-semibold tracking-normal">Chọn món đặt trước</h1>
        <p className="max-w-xl text-muted-foreground">
          Chọn các món ăn bạn muốn thưởng thức ngay khi đến. Nhà hàng sẽ chuẩn bị trước để bạn không phải chờ đợi.
        </p>
        <BookingProgress currentStep="preorder" />
      </section>

      <div className={`mt-6 grid min-w-0 gap-6 ${featureFlags.preorder ? "lg:grid-cols-[minmax(0,1fr)_360px]" : ""}`}>
        <section className="min-w-0 space-y-5">
          <AppCard className="min-w-0 border border-primary/10 bg-background/85 backdrop-blur-md lg:sticky lg:top-[5rem] lg:z-20 shadow-md rounded-xl p-2 space-y-2">
            <div className="grid min-w-0 gap-2 lg:grid-cols-[minmax(16rem,1fr)_10.5rem_auto] lg:items-center">
              <div className="relative min-w-0">
                <Label htmlFor="menu-search" className="sr-only">Tìm trong thực đơn</Label>
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="menu-search"
                  type="search"
                  value={searchText}
                  onChange={(event) => setSearchText(event.target.value)}
                  placeholder="Tìm mì, cơm, đồ uống..."
                  className="min-h-9 h-9 rounded-lg pl-8 bg-background/50 border-muted focus-visible:ring-primary/30 text-xs"
                />
              </div>

              <div className="min-w-0">
                <Label htmlFor="menu-sort" className="sr-only">Sắp xếp</Label>
                <Select value={sort} onValueChange={(value) => setSort(parseSort(value))}>
                  <SelectTrigger id="menu-sort" className="min-h-9 h-9 w-full rounded-lg bg-background/50 border-muted focus:ring-primary/30 text-xs" aria-label="Sắp xếp">
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

              <div className="flex min-h-9 h-9 items-center justify-between gap-2.5 rounded-lg border bg-primary/5 border-primary/10 px-3 text-xs font-semibold lg:justify-center">
                <span className="inline-flex items-center gap-1.5 text-primary/80">
                  <SlidersHorizontal className="h-3.5 w-3.5" />
                  Bộ lọc
                </span>
                <span className="tabular-nums text-primary">{displayedItems.length} món</span>
              </div>
            </div>

            <div className="flex min-w-0 gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Bộ lọc thực đơn">
              {featureFlags.menuCategories && categoriesQuery.data?.length ? (
                <AppButton
                  type="button"
                  variant={categoryId === null ? "default" : "outline"}
                  size="sm"
                  className="min-h-7 h-7 shrink-0 px-3 rounded-full text-xs"
                  onClick={() => setCategoryId(null)}
                >
                  Tất cả
                </AppButton>
              ) : null}
              <AppButton
                type="button"
                variant={availableOnly ? "default" : "outline"}
                size="sm"
                className="min-h-7 h-7 shrink-0 px-3 rounded-full text-xs"
                onClick={() => setAvailableOnly((current) => !current)}
              >
                Còn phục vụ
              </AppButton>
              <AppButton
                type="button"
                variant={favoritesOnly ? "default" : "outline"}
                size="sm"
                className="min-h-7 h-7 shrink-0 px-3 rounded-full gap-1 text-xs"
                onClick={() => setFavoritesOnly((current) => !current)}
              >
                <Heart className={favoritesOnly ? "h-3 w-3 fill-current text-destructive" : "h-3 w-3"} />
                Yêu thích ({activeFavoriteItemIds.size})
              </AppButton>
              <AppButton
                type="button"
                variant={comboOnly ? "default" : "outline"}
                size="sm"
                className="min-h-7 h-7 shrink-0 px-3 rounded-full text-xs"
                onClick={() => setComboOnly((current) => !current)}
              >
                Combo
              </AppButton>
              
              {featureFlags.menuCategories && categoriesQuery.data?.length ? (
                <>
                  <span className="h-7 w-px shrink-0 bg-border/60 self-center" aria-hidden="true" />
                  {categoriesQuery.data.map((category) => (
                    <AppButton
                      type="button"
                      key={category.category_id}
                      variant={categoryId === category.category_id ? "default" : "outline"}
                      size="sm"
                      className="min-h-7 h-7 shrink-0 px-3 rounded-full text-xs"
                      onClick={() => setCategoryId(category.category_id)}
                    >
                      {displayMenuText(category.name, "Danh mục")}
                    </AppButton>
                  ))}
                </>
              ) : null}
            </div>
          </AppCard>

          {itemsQuery.isLoading ? (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true">
              {Array.from({ length: 6 }).map((_, index) => (
                <AppCard key={index} className="h-80 animate-pulse bg-secondary/50 rounded-2xl" />
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

          <div id="menu-results" className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {paginatedItems.map((item) => {
              const itemName = displayMenuText(item.name, "Món");
              const categoryName = displayMenuText(item.category_name, "Thực đơn");
              const description = displayMenuText(item.description, "Nhà hàng sẽ bổ sung mô tả khi bạn gọi món.");

              return (
                <AppCard key={item.item_id} className="group min-w-0 flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-primary/20 rounded-2xl">
                  <div className="relative overflow-hidden aspect-[4/3]">
                    <MenuItemImage item={item} className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                    
                    <AppButton
                      type="button"
                      variant={activeFavoriteItemIds.has(item.item_id) ? "default" : "outline"}
                      size="icon"
                      className="absolute right-3 top-3 h-9 w-9 bg-background/85 backdrop-blur hover:bg-background/95 hover:scale-110 active:scale-95 transition-all duration-200 shadow-sm z-10"
                      onClick={() => toggleFavorite(item)}
                    >
                      <Heart className={activeFavoriteItemIds.has(item.item_id) ? "h-4 w-4 fill-current text-destructive" : "h-4 w-4"} />
                    </AppButton>

                    {!item.is_available && (
                      <div className="absolute inset-0 bg-background/70 backdrop-blur-[1.5px] flex items-center justify-center z-[2]">
                        <span className="rounded-full bg-destructive px-3 py-1.5 text-xs font-bold text-destructive-foreground shadow-lg tracking-wide uppercase">
                          Tạm hết
                        </span>
                      </div>
                    )}
                  </div>
                  
                  <div className="flex flex-col flex-1 p-4 space-y-3">
                    <div className="flex items-center justify-between gap-2 min-h-5">
                      <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{categoryName}</span>
                    </div>

                    <div className="space-y-1 flex-1">
                      <div className="flex items-start justify-between gap-2">
                        <h2 className="text-base font-bold line-clamp-1 group-hover:text-primary transition-colors">{itemName}</h2>
                        <PriceText amount={item.price.amount} currency={item.price.currency} className="font-bold text-primary shrink-0" />
                      </div>
                      <p className="line-clamp-2 text-xs text-muted-foreground min-h-[2rem] leading-relaxed">{description}</p>
                    </div>

                    <div className="pt-2">
                      <AppButton 
                        type="button" 
                        size="sm" 
                        className="w-full min-h-9 text-xs rounded-lg" 
                        disabled={!item.is_available} 
                        onClick={() => addToCart(item)}
                      >
                        <ShoppingBag className="h-3.5 w-3.5 mr-1" />
                        Thêm món
                      </AppButton>
                    </div>
                  </div>
                </AppCard>
              );
            })}
          </div>

          <MenuPager currentPage={currentPage} totalPages={totalPages} onPageChange={changePage} />
        </section>

        {featureFlags.preorder ? (
          <aside className="hidden lg:flex lg:flex-col lg:sticky lg:top-[5.5rem] lg:self-start lg:w-[360px] lg:gap-4">
            <PreorderCartPanel branchId={selectedBranch?.branchId ?? null} branchName={selectedBranch?.branchName ?? null} compact />
            
            <AppButton asChild className="w-full py-6 text-base font-semibold shadow-xl hover:scale-[1.02] transition-transform">
              <Link href={nextStepHref}>
                {cart.quantity > 0 ? "Tiếp tục đặt bàn" : "Bỏ qua & Tiếp tục"}
                <ArrowRight className="ml-2 h-5 w-5" />
              </Link>
            </AppButton>
          </aside>
        ) : null}
      </div>

      {featureFlags.preorder && (
        <div className="fixed bottom-[5.5rem] left-0 right-0 z-40 lg:hidden px-4 flex justify-between gap-4">
          <Sheet open={isMobileCartOpen} onOpenChange={setIsMobileCartOpen}>
            <SheetTrigger asChild>
              <AppButton
                size="icon"
                className="h-14 w-14 shrink-0 rounded-full shadow-2xl bg-primary text-primary-foreground relative border border-primary/20"
              >
                <ShoppingBag className="h-6 w-6" />
                <span className="absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-[11px] font-bold text-destructive-foreground ring-2 ring-background">
                  {cart.quantity}
                </span>
              </AppButton>
            </SheetTrigger>
            <SheetContent side="bottom" className="h-[85vh] flex flex-col p-0 rounded-t-3xl overflow-hidden border-t border-primary/10">
              <div className="flex-1 min-h-0 overflow-hidden flex flex-col">
                <PreorderCartPanel branchId={selectedBranch?.branchId ?? null} branchName={selectedBranch?.branchName ?? null} compact className="flex-1 flex flex-col min-h-0" />
              </div>
            </SheetContent>
          </Sheet>

          <AppButton asChild className="flex-1 h-14 rounded-full text-base font-semibold shadow-2xl">
            <Link href={nextStepHref}>
              {cart.quantity > 0 ? "Tiếp tục" : "Bỏ qua"}
              <ArrowRight className="ml-2 h-5 w-5" />
            </Link>
          </AppButton>
        </div>
      )}
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
  if (totalPages <= 1) return null;
  const pages = visiblePageNumbers(currentPage, totalPages);

  return (
    <nav aria-label="Phân trang thực đơn" className="flex items-center justify-center py-4">
      <div className="flex items-center gap-2">
        <AppButton type="button" variant="outline" size="sm" className="min-h-10 px-3 rounded-xl border-muted hover:bg-secondary" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>
          <ChevronLeft className="h-4 w-4 mr-1" /> Trang trước
        </AppButton>
        <div className="hidden items-center gap-1.5 sm:flex">
          {pages.map((page, index) =>
            page === "ellipsis" ? (
              <span key={`ellipsis-${index}`} className="px-2 text-sm text-muted-foreground select-none">...</span>
            ) : (
              <AppButton key={page} type="button" variant={page === currentPage ? "default" : "outline"} size="sm" className="min-h-10 min-w-10 px-2 rounded-xl" aria-label={`Trang ${page}`} aria-current={page === currentPage ? "page" : undefined} onClick={() => onPageChange(page)}>
                {page}
              </AppButton>
            ),
          )}
        </div>
        <AppButton type="button" variant="outline" size="sm" className="min-h-10 px-3 rounded-xl border-muted hover:bg-secondary" disabled={currentPage >= totalPages} onClick={() => onPageChange(currentPage + 1)}>
          Trang sau <ChevronRight className="h-4 w-4 ml-1" />
        </AppButton>
      </div>
    </nav>
  );
}
