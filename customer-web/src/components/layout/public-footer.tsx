"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { CalendarDays, Clock3, ExternalLink, Mail, MapPin, Phone, ReceiptText } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { queryKeys } from "@/lib/api/query-keys";
import { cn } from "@/lib/utils";
import { getRestaurantProfile } from "@/features/restaurant/api";
import {
  customerFooterContact,
  formatTimezone,
  googleMapsEmbedUrl,
  googleMapsUrl,
  openStatusLabel,
  todayHoursLabel,
  weeklyHours,
} from "@/features/restaurant/state";

export function PublicFooter({ isAuthenticated }: { isAuthenticated: boolean }) {
  const profileQuery = useQuery({
    queryKey: queryKeys.restaurant.profile,
    queryFn: getRestaurantProfile,
    staleTime: 5 * 60 * 1000,
    retry: 1,
  });

  const profile = profileQuery.data ?? null;
  const hours = weeklyHours(profile);
  const statusLabel = profileQuery.isError ? "Giờ mở cửa đang cập nhật" : openStatusLabel(profile);
  const todayLabel = profileQuery.isError ? "Theo lịch chi nhánh trong hệ thống" : todayHoursLabel(profile);
  const timezoneLabel = formatTimezone(profile?.current_status.timezone ?? profile?.timezone);
  const mapUrl = googleMapsUrl();
  const reservationHref = isAuthenticated ? "/reservations" : "/login";

  return (
    <footer className="border-t bg-card/90">
      <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-9 lg:grid-cols-[1.15fr_0.85fr]">
        <div className="grid gap-7 md:grid-cols-[1fr_1fr]">
          <section className="space-y-5" aria-label="Thông tin nhà hàng">
            <Link href="/" className="inline-flex flex-col leading-tight">
              <span className="flex items-center gap-2 text-lg font-semibold">
                <span className="h-2.5 w-2.5 rounded-full bg-primary" />
                {customerFooterContact.name}
              </span>
              <span className="text-sm text-muted-foreground">{customerFooterContact.tagline}</span>
            </Link>

            <div className="flex flex-col gap-2 sm:flex-row">
              <Button asChild className="min-h-10 rounded-lg">
                <Link href="/booking">
                  <CalendarDays className="h-4 w-4" />
                  Đặt bàn
                </Link>
              </Button>
              <Button asChild variant="outline" className="min-h-10 rounded-lg">
                <Link href="/">
                  <ReceiptText className="h-4 w-4" />
                  Xem thực đơn
                </Link>
              </Button>
            </div>

            <div className="space-y-3 text-sm">
              <a className="flex items-start gap-3 text-foreground hover:text-primary" href={mapUrl} target="_blank" rel="noreferrer">
                <MapPin className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{customerFooterContact.address}</span>
              </a>
              <a className="flex items-center gap-3 text-foreground hover:text-primary" href={`tel:${customerFooterContact.phone}`}>
                <Phone className="h-4 w-4 shrink-0" />
                <span>{customerFooterContact.phoneDisplay}</span>
              </a>
              <a className="flex items-center gap-3 text-foreground hover:text-primary" href={`mailto:${customerFooterContact.email}`}>
                <Mail className="h-4 w-4 shrink-0" />
                <span className="break-all">{customerFooterContact.email}</span>
              </a>
            </div>
          </section>

          <section className="space-y-5" aria-label="Giờ mở cửa">
            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-sm font-semibold">Giờ mở cửa</h2>
                <Badge
                  variant="outline"
                  className={cn(
                    "rounded-md",
                    profile?.current_status.is_open
                      ? "border-emerald-200 bg-emerald-50 text-emerald-800"
                      : "border-amber-200 bg-amber-50 text-amber-900",
                  )}
                >
                  {statusLabel}
                </Badge>
              </div>
              <div className="flex items-start gap-3 text-sm text-muted-foreground">
                <Clock3 className="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                  Hôm nay: <span className="font-medium text-foreground">{todayLabel}</span>
                  <span className="block">{timezoneLabel}</span>
                </p>
              </div>
            </div>

            <dl className="grid gap-2 text-sm">
              {(hours.length > 0 ? hours : [{ day: "Hôm nay", hours: todayLabel }]).map((item) => (
                <div key={item.day} className="grid grid-cols-[5.5rem_1fr] gap-3">
                  <dt className="text-muted-foreground">{item.day}</dt>
                  <dd className="font-medium">{item.hours}</dd>
                </div>
              ))}
            </dl>
          </section>
        </div>

        <section className="space-y-4" aria-label="Bản đồ và liên kết">
          <div className="overflow-hidden rounded-lg border bg-muted">
            <iframe
              title="Bản đồ RestaurantPOS"
              src={googleMapsEmbedUrl()}
              className="h-52 w-full border-0"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          </div>

          <div className="grid gap-2 text-sm sm:grid-cols-2">
            <FooterLink href={mapUrl} label="Mở Google Maps" />
            <FooterLink href={customerFooterContact.facebookUrl} label="Facebook" />
            <FooterInternalLink href={reservationHref} label={isAuthenticated ? "Lịch đặt" : "Đăng nhập"} />
            <FooterInternalLink href="/booking" label="Tìm bàn" />
          </div>
        </section>
      </div>

      <div className="border-t">
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} {customerFooterContact.name}. All rights reserved.</p>
          <p>Thông tin giờ mở cửa được đồng bộ theo chi nhánh mặc định.</p>
        </div>
      </div>
    </footer>
  );
}

function FooterLink({ href, label }: { href: string; label: string }) {
  return (
    <a className="inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 font-medium hover:bg-accent" href={href} target="_blank" rel="noreferrer">
      {label}
      <ExternalLink className="h-4 w-4" />
    </a>
  );
}

function FooterInternalLink({ href, label }: { href: string; label: string }) {
  return (
    <Link className="inline-flex min-h-10 items-center rounded-lg border px-3 font-medium hover:bg-accent" href={href}>
      {label}
    </Link>
  );
}
