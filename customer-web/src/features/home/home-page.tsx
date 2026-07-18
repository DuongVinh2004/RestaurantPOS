"use client";

import Image from "next/image";
import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  CalendarDays,
  Clock3,
  MapPin,
  ReceiptText,
  Sparkles,
  UsersRound,
  ShoppingBag,
  Flame,
  Percent,
  Utensils,
  CupSoda,
} from "lucide-react";
import { toast } from "sonner";
import { AppButton, AppCard, EmptyState, PriceText, StatusPill } from "@/components/customer/ui";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { ResponsivePageShell } from "@/components/customer/layout";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import { useCustomerIdentity, useCustomerSession } from "@/features/auth/hooks";
import { listMenuItems, type MenuItem } from "@/features/menu/api";
import { MenuItemImage } from "@/features/menu/menu-item-image";
import { useLocalPreorderCart } from "@/features/preorder/local-cart";
import { listReservations } from "@/features/reservations/api";
import { queryKeys } from "@/lib/api/query-keys";
import { customerBrand } from "@/lib/brand/customer-brand";
import { featureFlags } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { displayMenuText } from "@/lib/i18n/customer-display";
import { trackCustomerEvent } from "@/lib/analytics/events";


const heroPlateUrl = "/customer-web/hero-plate.png";

export function HomePage() {
  const identity = useCustomerIdentity();
  const customerSession = useCustomerSession();
  const branchSelection = useBranchSelection();
  const selectedBranch = branchSelection.selectedBranch;
  const displayName = identity.isKnownCustomer ? identity.displayName : "bạn";
  const hasCustomerContext = identity.isAuthenticated || identity.hasGuestSession || customerSession.hasGuestSession;

  const [guestCount, setGuestCount] = useState("2");
  const [dateKey, setDateKey] = useState("today");
  const [time, setTime] = useState("19:00");
  const [selectedCombo, setSelectedCombo] = useState<MenuItem | null>(null);
  const [isComboModalOpen, setIsComboModalOpen] = useState(false);

  const featuredMenuQuery = useQuery({
    queryKey: queryKeys.menu.items({ q: null, categoryId: null, preorderOnly: null }),
    queryFn: () => listMenuItems({ q: null, categoryId: null, preorderOnly: null }),
    staleTime: 60_000,
  });
  
  const upcomingReservationsQuery = useQuery({
    queryKey: queryKeys.reservations.list("upcoming"),
    queryFn: () => listReservations("upcoming"),
    enabled: hasCustomerContext,
    staleTime: 30_000,
  });
  const { addItem } = useLocalPreorderCart(selectedBranch?.branchId);

  const { comboItems, bestSellerItems } = useMemo(() => {
    const allItems = featuredMenuQuery.data ?? [];
    const available = allItems.filter((item) => item.is_available);
    
    const combos = available.filter(item => item.is_combo);
    const bestSellers = available.filter(item => item.is_best_seller);

    return { comboItems: combos, bestSellerItems: bestSellers };
  }, [featuredMenuQuery.data]);

  const handleAddToCart = (item: MenuItem, e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    e.stopPropagation();
    addItem(item);
    toast.success(`Đã thêm ${item.name} vào giỏ`);
  };

  const nextReservation = upcomingReservationsQuery.data?.[0] ?? null;

  const bookingHref = useMemo(() => {
    const params = new URLSearchParams({
      guest_count: guestCount,
      date: dateKey,
      time,
    });
    if (selectedBranch?.branchId) {
      params.append("branch_id", selectedBranch.branchId.toString());
    }

    return `/booking?${params.toString()}`;
  }, [dateKey, guestCount, time, selectedBranch]);

  return (
    <main className="bg-background min-h-screen">
      {/* Hero Section */}
      <section className="relative min-h-[600px] flex items-center justify-center overflow-hidden bg-teal-950 text-white py-20 pb-[340px] sm:pb-[300px] md:pb-44">
        {/* Background Image with elegant overlay */}
        <div className="absolute inset-0 opacity-75 transition-transform duration-[2000ms] scale-105 hover:scale-100">
          <img
            src={heroPlateUrl}
            alt="Mộc Sen Bistro Fine Dining"
            className="h-full w-full object-cover"
          />
        </div>
        <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/35 to-background" />

        {/* Hero Content */}
        <div className="relative z-10 mx-auto max-w-4xl px-4 text-center space-y-6 md:space-y-8">
          
          {/* Tagline */}
          <div className="inline-flex items-center gap-2 rounded-full border border-teal-500/20 bg-teal-950/60 px-4 py-1.5 text-xs font-semibold tracking-widest text-teal-300 uppercase shadow-lg backdrop-blur-sm animate-fade-in">
            <Sparkles className="h-3.5 w-3.5 text-teal-400 animate-pulse" />
            {identity.isKnownCustomer ? `Xin chào, ${displayName}` : customerBrand.tagline}
          </div>

          {/* Heading */}
          <h1 className="text-3xl sm:text-5xl md:text-6xl font-extrabold tracking-tight leading-[1.15] text-white" aria-label="Chọn món ngon, giữ bàn đúng giờ">
            Khơi nguồn mỹ vị Việt, <span className="bg-gradient-to-r from-teal-300 to-emerald-300 bg-clip-text text-transparent">trọn vẹn niềm vui sum vầy</span>
          </h1>
          {/* Subtitle */}
          <p className="mx-auto max-w-2xl text-sm sm:text-base md:text-lg text-emerald-100/70 leading-relaxed font-light">
            Nơi hội tụ những giá trị nguyên bản của ẩm thực Việt, khơi nguồn từ nguyên liệu bản địa trọn lành và nghệ thuật chế biến đương đại. Hãy để Mộc Sen đồng hành cùng quý khách trong những khoảnh khắc sum vầy đáng nhớ.
          </p>

          {/* CTA Buttons */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-2">
            <AppButton asChild className="min-h-12 w-full sm:w-auto px-8 text-sm font-medium bg-gradient-to-r from-teal-600 to-emerald-500 hover:from-teal-700 hover:to-emerald-600 border-none shadow-lg transition-all duration-300 hover:scale-105">
              <Link href="/booking" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_booking" })}>
                <CalendarDays className="h-4.5 w-4.5 mr-2" />
                Đặt bàn ngay
              </Link>
            </AppButton>
            <AppButton asChild variant="outline" className="min-h-12 w-full sm:w-auto px-8 text-sm font-medium border-white/30 bg-transparent text-white hover:bg-white/10 hover:border-white/50 hover:text-white transition-all duration-300 hover:scale-105">
              <Link href="/menu" onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "hero_menu" })}>
                <ReceiptText className="h-4.5 w-4.5 mr-2" />
                Xem thực đơn
              </Link>
            </AppButton>
          </div>

          {/* Customer Welcome Box / Guest action */}
          <div className="mx-auto pt-2 flex justify-center">
            {identity.isKnownCustomer ? (
              <div className="inline-flex items-center justify-center gap-2 rounded-full border border-teal-500/20 bg-teal-950/40 px-4 py-2 text-xs text-emerald-200 backdrop-blur-sm shadow-md max-w-lg">
                <span>Chào mừng trở lại! Quản lý đặt bàn tại</span>
                <Link className="font-semibold text-teal-300 hover:text-teal-100 underline underline-offset-2 decoration-teal-500/30" href="/reservations">
                  Lịch đặt
                </Link>
              </div>
            ) : (
              <div className="inline-flex flex-col sm:flex-row items-center justify-center gap-x-2 gap-y-1.5 rounded-2xl sm:rounded-full border border-teal-500/20 bg-teal-950/40 px-4 py-2 text-xs text-emerald-200 backdrop-blur-sm shadow-md max-w-xl">
                <span>Quý khách có thể đặt nhanh hoặc</span>
                <Link className="font-semibold text-teal-300 hover:text-teal-100 underline underline-offset-2 decoration-teal-500/30" href="/login">
                  đăng nhập
                </Link>
                {!customerSession.hasGuestSession ? (
                  <>
                    <span className="hidden sm:inline text-teal-500/30">|</span>
                    <button type="button" className="font-semibold text-teal-300 hover:text-teal-100 hover:underline transition" onClick={customerSession.continueAsGuest}>
                      Tiếp tục như khách
                    </button>
                  </>
                ) : null}
              </div>
            )}
          </div>

          {/* Floating Booking Bar */}
          <div className="absolute left-0 right-0 -bottom-10 md:-bottom-12 px-4 z-20">
            <div className="mx-auto w-full max-w-5xl rounded-2xl border border-teal-900/10 bg-background/95 p-5 shadow-restaurant backdrop-blur-md md:p-6 hover:shadow-restaurant-hover transition-all duration-300">
              <div className="grid gap-4 md:grid-cols-[1.2fr_1fr_1fr_1fr_auto] md:items-end text-left text-foreground">
                
                {/* Branch Selection */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold uppercase tracking-wider text-teal-800 flex items-center gap-1 ml-1">
                    <MapPin className="h-3.5 w-3.5 text-teal-600" /> Chi nhánh
                  </label>
                  <SelectedBranchEntry className="w-full justify-between min-h-12 rounded-xl border-teal-600/20 text-teal-800 hover:bg-teal-50" />
                </div>

                {/* Date Selector */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold uppercase tracking-wider text-teal-800 flex items-center gap-1 ml-1">
                    <CalendarDays className="h-3.5 w-3.5 text-teal-600" /> Ngày đến
                  </label>
                  <select
                    value={dateKey}
                    onChange={(e) => setDateKey(e.target.value)}
                    className="h-12 w-full rounded-xl border border-teal-600/20 bg-background px-4 text-sm font-medium text-foreground outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10"
                  >
                    <option value="today">Hôm nay</option>
                    <option value="tomorrow">Ngày mai</option>
                  </select>
                </div>

                {/* Time Selector */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold uppercase tracking-wider text-teal-800 flex items-center gap-1 ml-1">
                    <Clock3 className="h-3.5 w-3.5 text-teal-600" /> Giờ đến
                  </label>
                  <select
                    value={time}
                    onChange={(e) => setTime(e.target.value)}
                    className="h-12 w-full rounded-xl border border-teal-600/20 bg-background px-4 text-sm font-medium text-foreground outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10"
                  >
                    <option value="19:00">19:00</option>
                    <option value="20:00">20:00</option>
                  </select>
                </div>

                {/* Guest Count */}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold uppercase tracking-wider text-teal-800 flex items-center gap-1 ml-1">
                    <UsersRound className="h-3.5 w-3.5 text-teal-600" /> Số khách
                  </label>
                  <select
                    value={guestCount}
                    onChange={(e) => setGuestCount(e.target.value)}
                    className="h-12 w-full rounded-xl border border-teal-600/20 bg-background px-4 text-sm font-medium text-foreground outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10"
                  >
                    <option value="2">2 người</option>
                    <option value="4">4 người</option>
                    <option value="6">6 người</option>
                  </select>
                </div>

                {/* Find Table Button */}
                <div className="pt-2 md:pt-0">
                  <AppButton asChild className="h-11 w-full md:w-auto px-8 bg-gradient-to-r from-teal-700 to-emerald-600 hover:from-teal-800 hover:to-emerald-700 shadow-md">
                    <Link href={bookingHref} onClick={() => trackCustomerEvent("homepage_cta_clicked", { source: "quick_booking" })}>
                      Tìm bàn
                    </Link>
                  </AppButton>
                </div>

              </div>
              
              {!selectedBranch && (
                <p className="mt-3 text-xs text-amber-700 font-medium text-center md:text-left flex items-center gap-1 justify-center md:justify-start animate-pulse">
                  ⚠️ Vui lòng chọn chi nhánh để kiểm tra trạng thái bàn khả dụng chính xác.
                </p>
              )}
            </div>
          </div>

        </div>
      </section>

      {/* Main Content Layout */}
      <ResponsivePageShell className="space-y-24 py-20 pt-28 md:pt-36">
        
        {/* Next Visit Notification (if any) */}
        {nextReservation || hasCustomerContext ? (
          <div className="mx-auto max-w-4xl">
            <NextVisitPanel
              nextReservation={nextReservation}
              isLoading={upcomingReservationsQuery.isLoading}
              branchName={selectedBranch?.branchName ?? customerBrand.fallbackRestaurantName}
            />
          </div>
        ) : null}

        {/* Section 1: Mộc Sen Story (Editorial Brand Story) */}
        <section className="grid gap-12 lg:grid-cols-2 lg:items-center" aria-label="Giới thiệu Mộc Sen Bistro">
          <div className="space-y-6">
            <div className="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-teal-800">
              Câu chuyện Mộc Sen
            </div>
            <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-teal-950">
              Bản sắc ẩm thực Việt tinh hoa giữa nhịp sống hiện đại
            </h2>
            <p className="text-base text-muted-foreground leading-relaxed">
              Kiến tạo một không gian ẩm thực giao hòa giữa nét bình yên xưa cũ và hơi thở thời đại, **Mộc Sen Bistro** là hành trình tìm về những giá trị nguyên bản của hương vị Việt. Mỗi món ăn tại Mộc Sen là một tác phẩm nghệ thuật, nơi các nguyên liệu bản địa được nâng niu, gìn giữ trọn vẹn sự thuần khiết tinh khôi.
            </p>
            <p className="text-base text-muted-foreground leading-relaxed font-light">
              Chúng tôi tin rằng, ẩm thực chân chính khởi nguồn từ sự tử tế. Bằng việc kết nối trực tiếp với những người nông dân tâm huyết trên khắp các vùng miền Việt Nam, Mộc Sen mang đến bàn ăn nguồn nguyên liệu hữu cơ trọn lành nhất. Qua đôi bàn tay tài hoa của các nghệ nhân bếp, tinh hoa ẩm thực truyền thống được tái hiện đầy sáng tạo, đánh thức mọi giác quan và nâng niu sức khỏe của thực khách.
            </p>
            
            {/* Gastronomy Pillars */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-6 border-t border-teal-900/10">
              <div className="space-y-2">
                <span className="text-xl font-bold bg-gradient-to-r from-teal-700 to-emerald-600 bg-clip-text text-transparent">Nguyên Liệu Trọn Lành</span>
                <p className="text-xs text-muted-foreground leading-relaxed">Nguồn nông sản hữu cơ, thuần khiết được vun trồng từ đất mẹ trân quý.</p>
              </div>
              <div className="space-y-2">
                <span className="text-xl font-bold bg-gradient-to-r from-teal-700 to-emerald-600 bg-clip-text text-transparent">Nghệ Thuật Đương Đại</span>
                <p className="text-xs text-muted-foreground leading-relaxed">Hương vị di sản được các đầu bếp biến tấu đầy ngẫu hứng và độc bản.</p>
              </div>
              <div className="space-y-2">
                <span className="text-xl font-bold bg-gradient-to-r from-teal-700 to-emerald-600 bg-clip-text text-transparent">Không Gian Giao Hòa</span>
                <p className="text-xs text-muted-foreground leading-relaxed">Kiến trúc giao thoa giữa nét trầm mặc Á Đông và lối sống tối giản hiện đại.</p>
              </div>
            </div>
          </div>

          {/* Visual Grid Collage */}
          <div className="relative grid grid-cols-2 gap-4">
            <div className="space-y-4">
              <div className="relative h-48 sm:h-64 overflow-hidden rounded-2xl bg-teal-800 shadow-lg group">
                <Image
                  src="/customer-web/rail-seafood.jpg"
                  alt="Hải sản thượng hạng"
                  fill
                  className="object-cover transition-transform duration-1000 group-hover:scale-110"
                  sizes="(max-width: 1024px) 50vw, 25vw"
                />
              </div>
              <div className="relative h-36 sm:h-48 overflow-hidden rounded-2xl bg-teal-800 shadow-lg group">
                <Image
                  src="/customer-web/rail-drink.jpg"
                  alt="Đồ uống thảo mộc"
                  fill
                  className="object-cover transition-transform duration-1000 group-hover:scale-110"
                  sizes="(max-width: 1024px) 50vw, 25vw"
                />
              </div>
            </div>
            <div className="space-y-4 pt-8">
              <div className="relative h-36 sm:h-48 overflow-hidden rounded-2xl bg-teal-800 shadow-lg group">
                <Image
                  src="/customer-web/rail-dessert.jpg"
                  alt="Món ngọt tráng miệng"
                  fill
                  className="object-cover transition-transform duration-1000 group-hover:scale-110"
                  sizes="(max-width: 1024px) 50vw, 25vw"
                />
              </div>
              <div className="relative h-48 sm:h-64 overflow-hidden rounded-2xl bg-teal-800 shadow-lg group">
                <Image
                  src={heroPlateUrl}
                  alt="Món ăn đặc trưng"
                  fill
                  className="object-cover transition-transform duration-1000 group-hover:scale-110"
                  sizes="(max-width: 1024px) 50vw, 25vw"
                />
              </div>
            </div>
          </div>
        </section>

        {/* Section 1.5: Combo Siêu Hời */}
        {comboItems.length > 0 && (
          <section className="space-y-8" aria-label="Combo Siêu Hời">
            <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
              <div className="space-y-2">
                <div className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-rose-800">
                  <Percent className="h-3.5 w-3.5" /> Khuyến mãi đặc biệt
                </div>
                <h2 className="text-3xl font-extrabold tracking-tight text-rose-950">Combo Siêu Hời - Tiết Kiệm Tối Đa</h2>
                <p className="text-sm text-muted-foreground max-w-xl">
                  Thưởng thức trọn vẹn tinh hoa ẩm thực với mức giá ưu đãi bất ngờ. Số lượng có hạn mỗi ngày!
                </p>
              </div>
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
              {comboItems.map((item) => (
                <div key={item.item_id} className="group relative overflow-hidden rounded-3xl border border-rose-900/10 bg-gradient-to-br from-rose-50/50 to-orange-50/50 p-5 transition-all hover:shadow-xl hover:-translate-y-1">
                  <div className="flex gap-4">
                    <div className="relative h-32 w-32 shrink-0 overflow-hidden rounded-2xl cursor-pointer" onClick={() => { setSelectedCombo(item); setIsComboModalOpen(true); }}>
                      <MenuItemImage item={item} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" priority={false} />
                      <div className="absolute top-2 left-2 rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm uppercase tracking-wider">
                        Combo
                      </div>
                      {item.serving_size && (
                        <div className="absolute bottom-2 left-2 flex items-center gap-1 rounded-full bg-black/60 backdrop-blur-sm px-2 py-0.5 text-[10px] font-medium text-white">
                          <UsersRound className="h-3 w-3" /> {item.serving_size} người
                        </div>
                      )}
                    </div>
                    
                    <div className="flex flex-1 flex-col justify-between">
                      <div className="space-y-1 cursor-pointer" onClick={() => { setSelectedCombo(item); setIsComboModalOpen(true); }}>
                        <h3 className="line-clamp-2 font-bold text-rose-950 text-lg leading-tight group-hover:text-rose-700 transition">
                          {item.name}
                        </h3>
                        <p className="line-clamp-2 text-xs text-muted-foreground leading-relaxed">
                          {item.description || "Combo ưu đãi hấp dẫn dành riêng cho thực khách đặt trước."}
                        </p>
                      </div>
                      
                      <div className="mt-3 flex items-end justify-between">
                        <div>
                          {item.compare_at_price_amount && (
                            <span className="text-xs text-muted-foreground line-through decoration-rose-900/30 font-medium">
                              {Number(item.compare_at_price_amount).toLocaleString()}đ
                            </span>
                          )}
                          <PriceText amount={item.price.amount} currency={item.price.currency} className="block font-extrabold text-xl text-rose-700 leading-none mt-0.5" />
                        </div>
                        <AppButton 
                          size="sm" 
                          className="rounded-full bg-rose-600 hover:bg-rose-700 text-white shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                          onClick={(e) => handleAddToCart(item, e)}
                        >
                          <ShoppingBag className="h-3.5 w-3.5 mr-1.5" />
                          Thêm ngay
                        </AppButton>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {/* Modal Chi tiết Combo */}
            <Dialog open={isComboModalOpen} onOpenChange={setIsComboModalOpen}>
              <DialogContent className="sm:max-w-md">
                <DialogHeader>
                  <DialogTitle className="text-xl text-rose-950 flex items-center gap-2">
                    <Percent className="h-5 w-5 text-rose-600" />
                    {selectedCombo?.name}
                  </DialogTitle>
                  <DialogDescription>
                    {selectedCombo?.description || "Chi tiết các món ăn và thức uống đi kèm trong combo ưu đãi."}
                  </DialogDescription>
                </DialogHeader>
                
                <div className="space-y-4 py-4">
                  {selectedCombo?.serving_size && (
                    <div className="flex items-center gap-2 text-sm text-teal-800 bg-teal-50 px-3 py-2 rounded-lg w-fit font-medium">
                      <UsersRound className="h-4 w-4" />
                      Phần ăn thiết kế hoàn hảo cho {selectedCombo.serving_size} người
                    </div>
                  )}

                  {selectedCombo?.combo_components && Array.isArray(selectedCombo.combo_components) && selectedCombo.combo_components.length > 0 ? (
                    <div className="space-y-3 mt-4">
                      <h4 className="font-semibold text-sm text-muted-foreground uppercase tracking-wider">Thành phần Combo</h4>
                      <ul className="space-y-3">
                        {selectedCombo.combo_components.map((comboItem) => (
                          <li key={comboItem.component_item_id} className="flex items-start gap-3 bg-rose-50/30 p-3 rounded-xl border border-rose-900/5">
                            <div className="mt-0.5 bg-white p-1.5 rounded-full shadow-sm">
                              {comboItem.name?.toLowerCase().includes("nước") || comboItem.name?.toLowerCase().includes("trà") ? (
                                <CupSoda className="h-4 w-4 text-sky-600" />
                              ) : (
                                <Utensils className="h-4 w-4 text-orange-600" />
                              )}
                            </div>
                            <div className="flex-1">
                              <div className="font-semibold text-sm text-gray-900">{comboItem.name}</div>
                              <div className="text-xs text-muted-foreground">Số lượng: x{comboItem.quantity}</div>
                            </div>
                          </li>
                        ))}
                      </ul>
                    </div>
                  ) : (
                    <div className="text-sm text-muted-foreground italic bg-gray-50 p-4 rounded-xl text-center">
                      Danh sách món đang được cập nhật...
                    </div>
                  )}
                </div>

                <DialogFooter className="sm:justify-between items-center border-t pt-4">
                  <div>
                    {selectedCombo?.compare_at_price_amount && (
                      <span className="text-xs text-muted-foreground line-through decoration-rose-900/30 font-medium mr-2">
                        {Number(selectedCombo.compare_at_price_amount).toLocaleString()}đ
                      </span>
                    )}
                    <PriceText amount={selectedCombo?.price?.amount} currency={selectedCombo?.price?.currency} className="text-xl font-extrabold text-rose-700" />
                  </div>
                  <AppButton 
                    className="rounded-full bg-rose-600 hover:bg-rose-700 text-white"
                    onClick={(e) => {
                      if (selectedCombo) {
                        handleAddToCart(selectedCombo, e);
                        setIsComboModalOpen(false);
                      }
                    }}
                  >
                    <ShoppingBag className="h-4 w-4 mr-2" />
                    Thêm vào giỏ
                  </AppButton>
                </DialogFooter>
              </DialogContent>
            </Dialog>

          </section>
        )}

        {/* Section 2: Món được yêu thích hôm nay / Món Ngon Bán Chạy */}
        <section className="space-y-8" aria-label="Món ngon bán chạy">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div className="space-y-2">
              <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-amber-800">
                <Flame className="h-3.5 w-3.5 text-amber-600" /> Bán chạy nhất
              </div>
              <h2 className="text-3xl font-extrabold tracking-tight text-teal-950">Tuyển chọn tinh hoa ẩm thực</h2>
              <p className="text-sm text-muted-foreground max-w-xl">
                Những tạo tác ẩm thực đặc sắc nhất, mang đậm dấu ấn sáng tạo của Bếp trưởng, được đông đảo thực khách trân quý tại Mộc Sen Bistro.
              </p>
            </div>
            <AppButton asChild variant="ghost" className="text-teal-800 hover:text-teal-900 self-start sm:self-auto group">
              <Link href="/menu">
                Khám phá thực đơn đầy đủ
                <ArrowRight className="h-4 w-4 ml-1 transition group-hover:translate-x-1" />
              </Link>
            </AppButton>
          </div>

          {featuredMenuQuery.isLoading ? (
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4" aria-busy="true">
              {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="h-64 animate-pulse rounded-2xl bg-teal-900/5 border" />
              ))}
            </div>
          ) : null}

          {!featuredMenuQuery.isLoading && bestSellerItems.length === 0 ? (
            <EmptyState
              title="Thực đơn đang được cập nhật"
              description="Hệ thống đang chuẩn bị danh sách món ăn phục vụ hôm nay tại chi nhánh."
              action={
                <AppButton asChild variant="outline">
                  <Link href="/menu">Xem thực đơn chính</Link>
                </AppButton>
              }
            />
          ) : null}

          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {bestSellerItems.map((item) => (
              <div
                key={item.item_id}
                className="group relative overflow-hidden rounded-2xl border border-teal-900/5 bg-card/45 backdrop-blur-sm p-4 transition-all duration-300 hover:border-teal-700/20 hover:shadow-restaurant-hover hover:-translate-y-1 flex flex-col"
              >
                <Link href={featureFlags.menuItemDetail ? `/menu/${item.item_id}` : "/menu"} className="block relative aspect-[4/3] overflow-hidden rounded-xl bg-muted">
                  <MenuItemImage item={item} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" priority={false} />
                  <div className="absolute inset-0 bg-gradient-to-t from-teal-950/40 to-transparent pointer-events-none" />
                  {item.is_best_seller && (
                    <div className="absolute top-2 left-2 rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-sm flex items-center gap-1">
                      <Flame className="h-3 w-3 fill-white text-white" /> Bán chạy
                    </div>
                  )}
                </Link>
                
                <div className="mt-4 flex flex-col flex-1">
                  <Link href={featureFlags.menuItemDetail ? `/menu/${item.item_id}` : "/menu"} className="flex-1 space-y-2 block">
                    <div className="flex items-start justify-between gap-3">
                      <h3 className="line-clamp-1 font-bold text-teal-950 group-hover:text-teal-700 transition">
                        {displayMenuText(item.name, "Món")}
                      </h3>
                    </div>
                    <p className="line-clamp-2 text-xs text-muted-foreground leading-relaxed font-light">
                      {item.description || "Sự kết hợp tinh tế giữa nguyên liệu bản địa thượng hạng và bí quyết gia truyền độc đáo."}
                    </p>
                  </Link>

                  <div className="mt-3 flex items-center justify-between pt-3 border-t border-teal-900/5">
                    <PriceText amount={item.price.amount} currency={item.price.currency} className="font-extrabold text-teal-800" />
                    <AppButton 
                      size="sm" 
                      variant="outline" 
                      className="h-8 rounded-full border-teal-600/20 text-teal-700 hover:bg-teal-50"
                      onClick={(e) => handleAddToCart(item, e)}
                    >
                      <ShoppingBag className="h-3.5 w-3.5 mr-1" /> Thêm
                    </AppButton>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Section 3: Trải nghiệm Không gian (Ambiance Gallery) */}
        <section className="space-y-8" aria-label="Trải nghiệm Không gian">
          <div className="text-center max-w-2xl mx-auto space-y-2">
            <div className="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-teal-800">
              Không gian ẩm thực
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-teal-950">Thưởng lãm mỹ cảnh & không gian</h2>
            <p className="text-sm text-muted-foreground">
              Mỗi chi nhánh Mộc Sen Bistro là một tác phẩm kiến trúc tinh tế, mang lại cảm giác bình yên thư thái, tôn vinh mọi khoảnh khắc sum vầy.
            </p>
          </div>

          <div className="grid gap-6 md:grid-cols-3">
            {/* Space 1 */}
            <div className="group relative h-80 overflow-hidden rounded-2xl bg-teal-950 shadow-md">
              <Image
                src="/customer-web/rail-seafood.jpg"
                alt="Không gian tiệc riêng ấm cúng"
                fill
                className="object-cover opacity-70 transition-transform duration-1000 group-hover:scale-105 group-hover:opacity-60"
                sizes="(max-width: 768px) 100vw, 33vw"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-teal-950 via-teal-950/20 to-transparent pointer-events-none" />
              <div className="absolute bottom-6 left-6 right-6 text-white space-y-1.5">
                <span className="text-xs uppercase tracking-widest text-emerald-400 font-semibold">Phòng tiệc VIP</span>
                <h3 className="text-lg font-bold">Không gian hội họp riêng tư</h3>
                <p className="text-xs text-emerald-100/70 font-light leading-relaxed">
                  Không gian trầm mặc, kín đáo và trang trọng, kiến tạo sự riêng tư tuyệt đối cho các buổi tiếp đãi đối tác hay sum họp gia đình.
                </p>
              </div>
            </div>

            {/* Space 2 */}
            <div className="group relative h-80 overflow-hidden rounded-2xl bg-teal-950 shadow-md">
              <Image
                src="/customer-web/rail-drink.jpg"
                alt="Sân vườn Al Fresco lãng mạn"
                fill
                className="object-cover opacity-70 transition-transform duration-1000 group-hover:scale-105 group-hover:opacity-60"
                sizes="(max-width: 768px) 100vw, 33vw"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-teal-950 via-teal-950/20 to-transparent pointer-events-none" />
              <div className="absolute bottom-6 left-6 right-6 text-white space-y-1.5">
                <span className="text-xs uppercase tracking-widest text-emerald-400 font-semibold">Al Fresco</span>
                <h3 className="text-lg font-bold">Khu vườn xanh mát lộng gió</h3>
                <p className="text-xs text-emerald-100/70 font-light leading-relaxed">
                  Giao hòa cùng thiên nhiên mát lành, nơi tiếng gió rì rào qua tán lá mang lại cảm giác an nhiên tự tại giữa lòng phố thị.
                </p>
              </div>
            </div>

            {/* Space 3 */}
            <div className="group relative h-80 overflow-hidden rounded-2xl bg-teal-950 shadow-md">
              <Image
                src="/customer-web/rail-dessert.jpg"
                alt="Quầy Bar Trà Việt hiện đại"
                fill
                className="object-cover opacity-70 transition-transform duration-1000 group-hover:scale-105 group-hover:opacity-60"
                sizes="(max-width: 768px) 100vw, 33vw"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-teal-950 via-teal-950/20 to-transparent pointer-events-none" />
              <div className="absolute bottom-6 left-6 right-6 text-white space-y-1.5">
                <span className="text-xs uppercase tracking-widest text-emerald-400 font-semibold">Tea & Cocktail Lounge</span>
                <h3 className="text-lg font-bold">Quầy Bar Trà & Trái cây</h3>
                <p className="text-xs text-emerald-100/70 font-light leading-relaxed">
                  Nơi hội tụ nét đẹp trà Việt truyền thống kết hợp cùng nghệ thuật pha chế cocktail hiện đại từ thảo mộc thiên nhiên.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Section 4: Đánh giá từ Thực khách (Gastronomy Testimonials) */}
        <section className="rounded-3xl border border-teal-900/5 bg-teal-50/20 p-8 sm:p-12" aria-label="Nhận xét từ khách hàng">
          <div className="grid gap-12 lg:grid-cols-[1fr_2fr] lg:items-center">
            <div className="space-y-4">
              <div className="inline-flex items-center gap-1.5 rounded-full bg-teal-100/50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-teal-800">
                Ý kiến thực khách
              </div>
              <h2 className="text-3xl font-extrabold tracking-tight text-teal-950">Cảm xúc người đồng điệu</h2>
              <p className="text-sm text-muted-foreground leading-relaxed">
                Sự tri ân sâu sắc nhất của Mộc Sen là những xúc cảm trọn vẹn và sự đồng cảm trân quý từ quý thực khách sau mỗi hành trình ẩm thực.
              </p>
              <div className="flex items-center gap-1">
                {Array.from({ length: 5 }).map((_, i) => (
                  <span key={i} className="text-amber-500 text-lg">★</span>
                ))}
                <span className="text-xs font-bold text-teal-900 ml-2">Rating 4.9/5 trên toàn hệ thống</span>
              </div>
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
              <div className="rounded-2xl bg-background p-6 border border-teal-900/5 shadow-sm space-y-4">
                <p className="text-sm text-muted-foreground leading-relaxed italic font-light">
                  &ldquo;Một trải nghiệm ẩm thực vô cùng chạm. Không gian bài trí trang nhã đem lại sự tĩnh lặng bình yên trong tâm hồn. Món ăn thanh nhẹ, giữ trọn hương vị tự nhiên và bài trí tinh tế.&rdquo;
                </p>
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-teal-100 flex items-center justify-center font-bold text-teal-800 text-sm">
                    AN
                  </div>
                  <div>
                    <h4 className="text-sm font-bold text-teal-950">Khánh An</h4>
                    <p className="text-[10px] text-muted-foreground">Thành viên Thân thiết</p>
                  </div>
                </div>
              </div>

              <div className="rounded-2xl bg-background p-6 border border-teal-900/5 shadow-sm space-y-4">
                <p className="text-sm text-muted-foreground leading-relaxed italic font-light">
                  &ldquo;Sự chu đáo, tận tụy và lịch thiệp của đội ngũ nhân viên khiến tôi cảm nhận được sự hiếu khách chân thành. Nền tảng đặt bàn hiện đại, kết hợp cùng các món ăn thảo mộc trọn vị thật tuyệt vời.&rdquo;
                </p>
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center font-bold text-emerald-800 text-sm">
                    MH
                  </div>
                  <div>
                    <h4 className="text-sm font-bold text-teal-950">Minh Hoàng</h4>
                    <p className="text-[10px] text-muted-foreground">Khách hàng ẩm thực</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

      </ResponsivePageShell>
    </main>
  );
}

function NextVisitPanel({
  nextReservation,
  isLoading,
  branchName,
}: {
  nextReservation: Awaited<ReturnType<typeof listReservations>>[number] | null;
  isLoading: boolean;
  branchName: string;
}) {
  const visitTime = nextReservation?.start_time ?? nextReservation?.booking_time ?? null;

  return (
    <AppCard className="p-4 border border-teal-600/10 shadow-restaurant">
      <section aria-label="Lượt ghé sắp tới">
        <div className="mb-3 flex items-center justify-between gap-3">
          <h2 className="font-semibold text-teal-700">Lượt ghé sắp tới</h2>
          <StatusPill label="Lịch đặt" tone="info" />
        </div>
        {isLoading ? (
          <div className="h-24 animate-pulse rounded-lg bg-secondary" aria-label="Đang tải lượt ghé sắp tới" />
        ) : nextReservation ? (
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="grid h-[4.75rem] w-[4.75rem] shrink-0 place-items-center rounded-lg border bg-secondary text-center">
                <CalendarDays className="h-5 w-5 text-teal-700" />
                <span className="block text-xs text-muted-foreground">Lượt ghé</span>
              </div>
              <div className="min-w-0 space-y-1">
                <p className="text-lg font-bold">{formatDateTime(visitTime)}</p>
                <p className="text-sm text-muted-foreground">{nextReservation.guest_count ?? "Chưa rõ"} khách</p>
                <p className="truncate text-sm text-muted-foreground">{branchName}</p>
              </div>
            </div>
            <AppButton asChild variant="outline" className="w-full border-teal-600/20 text-teal-800 hover:bg-teal-50">
              <Link href={`/reservations/${nextReservation.reservation_id}`}>
                Xem lượt ghé
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        ) : (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Bạn chưa có lịch đặt sắp tới.</p>
            <AppButton asChild variant="outline" className="w-full border-teal-600/20 text-teal-800 hover:bg-teal-50">
              <Link href="/booking">
                Đặt bàn mới
                <ArrowRight className="h-4 w-4" />
              </Link>
            </AppButton>
          </div>
        )}
      </section>
    </AppCard>
  );
}
