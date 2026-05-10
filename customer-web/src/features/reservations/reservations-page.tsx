"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { CalendarClock, CalendarPlus, RefreshCw, ReceiptText, UsersRound, WalletCards, type LucideIcon } from "lucide-react";
import { useState } from "react";
import { AppButton, AppCard, StatusPill } from "@/components/customer/ui";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { formatDateTime } from "@/lib/contracts/format";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { listReservations } from "./api";
import { ReservationCard } from "./reservation-card";
import { getReservationBillSummaryState, getReservationDepositSummaryState } from "./state";

type Bucket = "upcoming" | "history" | "all";

const bucketLabels: Record<Bucket, string> = {
  upcoming: "sắp tới",
  history: "lịch sử",
  all: "tất cả",
};

export function ReservationsPage() {
  const [bucket, setBucket] = useState<Bucket>("upcoming");
  const reservationsQuery = useQuery({
    queryKey: queryKeys.reservations.list(bucket),
    queryFn: () => listReservations(bucket),
  });
  const reservations = reservationsQuery.data ?? [];
  const summary = buildReservationSummary(reservations);
  const primaryReservation = getPrimaryReservation(reservations, bucket);

  return (
    <main className="mx-auto w-full max-w-7xl px-4 py-7 pb-28 lg:pb-10">
      <AppCard className="mb-5 p-5 sm:p-6">
        <section className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <div className="mb-3 flex flex-wrap items-center gap-2">
              <StatusPill label={`Đang xem ${bucketLabels[bucket]}`} tone="info" />
              {reservationsQuery.isFetching && !reservationsQuery.isLoading ? (
                <span className="inline-flex min-h-7 items-center gap-1 rounded-md border px-2 text-xs font-medium text-muted-foreground">
                  <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                  Đang cập nhật
                </span>
              ) : null}
            </div>
            <h1 className="text-4xl font-bold tracking-normal">Lịch đặt</h1>
            <p className="mt-2 max-w-2xl text-base leading-7 text-muted-foreground">
              Xem các lượt ghé sắp tới, theo dõi đặt cọc và thanh toán hóa đơn khi nhà hàng đã sẵn sàng.
            </p>
          </div>
          <AppButton asChild className="min-h-12">
            <Link href="/reservations/new">
              <CalendarPlus className="h-4 w-4" />
              Tạo lịch đặt
            </Link>
          </AppButton>
        </section>
      </AppCard>

      <section className="mb-5 space-y-4">
        <Tabs value={bucket} onValueChange={(value) => setBucket(value as Bucket)}>
          <TabsList className="rounded-lg border bg-card">
            <TabsTrigger value="upcoming" className="rounded-md">Sắp tới</TabsTrigger>
            <TabsTrigger value="history" className="rounded-md">Lịch sử</TabsTrigger>
            <TabsTrigger value="all" className="rounded-md">Tất cả</TabsTrigger>
          </TabsList>
        </Tabs>

        {reservations.length > 0 ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <ReservationSummaryTile
                icon={CalendarClock}
                label="Lượt ghé"
                value={String(summary.reservationCount)}
                description={`Trong nhóm ${bucketLabels[bucket]}`}
              />
              <ReservationSummaryTile
                icon={UsersRound}
                label="Số khách"
                value={String(summary.guestCount)}
                description="Tổng khách từ dữ liệu hiện có"
              />
              <ReservationSummaryTile
                icon={WalletCards}
                label="Đặt cọc"
                value={String(summary.depositActionCount)}
                description="Lượt còn bước cần xử lý"
              />
              <ReservationSummaryTile
                icon={ReceiptText}
                label="Hóa đơn"
                value={String(summary.billReadyCount)}
                description="Lượt có hóa đơn để xem"
              />
            </div>

            {primaryReservation ? (
              <div className="rounded-lg border bg-secondary/35 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-muted-foreground">Lượt ghé nổi bật</p>
                    <p className="mt-1 text-lg font-semibold">{formatDateTime(primaryReservation.start_time ?? primaryReservation.booking_time ?? null)}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {primaryReservation.reservation_code} · {primaryReservation.guest_count ?? "Chưa có"} khách
                    </p>
                  </div>
                  <AppButton asChild variant="outline" className="shrink-0">
                    <Link href={`/reservations/${primaryReservation.reservation_id}`}>Mở chi tiết</Link>
                  </AppButton>
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </section>

      {reservationsQuery.isLoading ? <LoadingBlock label="Đang tải lịch đặt" /> : null}
      {reservationsQuery.error ? (
        <ErrorState error={reservationsQuery.error} title="Chưa tải được lịch đặt" onRetry={() => reservationsQuery.refetch()} />
      ) : null}
      {!reservationsQuery.isLoading && !reservationsQuery.error && reservationsQuery.data?.length === 0 ? (
        <EmptyState
          title="Chưa có lịch đặt"
          description="Tìm bàn hoặc tạo lịch đặt mới để bắt đầu lượt ghé nhà hàng."
          action={
            <AppButton asChild>
              <Link href="/booking">Tìm bàn</Link>
            </AppButton>
          }
        />
      ) : null}

      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {reservations.map((reservation) => (
          <ReservationCard key={reservation.reservation_id} reservation={reservation} />
        ))}
      </div>
    </main>
  );
}

function ReservationSummaryTile({
  icon: Icon,
  label,
  value,
  description,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  description: string;
}) {
  return (
    <div className="rounded-lg border bg-card p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-muted-foreground">{label}</p>
          <p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p>
        </div>
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
          <Icon className="h-4 w-4" />
        </span>
      </div>
      <p className="mt-3 text-sm text-muted-foreground">{description}</p>
    </div>
  );
}

function buildReservationSummary(reservations: ReservationSummary[]) {
  return reservations.reduce(
    (summary, reservation) => {
      const deposit = getReservationDepositSummaryState(reservation);
      const bill = getReservationBillSummaryState(reservation);

      return {
        reservationCount: summary.reservationCount + 1,
        guestCount: summary.guestCount + (reservation.guest_count ?? 0),
        depositActionCount: summary.depositActionCount + (deposit.requiresAction ? 1 : 0),
        billReadyCount: summary.billReadyCount + (bill.available ? 1 : 0),
      };
    },
    {
      reservationCount: 0,
      guestCount: 0,
      depositActionCount: 0,
      billReadyCount: 0,
    },
  );
}

function getPrimaryReservation(reservations: ReservationSummary[], bucket: Bucket): ReservationSummary | null {
  if (reservations.length === 0) {
    return null;
  }

  const sorted = [...reservations].sort((left, right) => getReservationTime(left) - getReservationTime(right));

  if (bucket === "history") {
    return sorted[sorted.length - 1] ?? null;
  }

  const now = Date.now();
  return sorted.find((reservation) => getReservationTime(reservation) >= now) ?? sorted[0] ?? null;
}

function getReservationTime(reservation: ReservationSummary): number {
  const timestamp = Date.parse(reservation.start_time ?? reservation.booking_time ?? "");
  return Number.isFinite(timestamp) ? timestamp : Number.MAX_SAFE_INTEGER;
}
