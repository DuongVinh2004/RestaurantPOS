"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { listReservations } from "./api";
import { ReservationCard } from "./reservation-card";

type Bucket = "upcoming" | "history" | "all";

export function ReservationsPage() {
  const [bucket, setBucket] = useState<Bucket>("upcoming");
  const reservationsQuery = useQuery({
    queryKey: queryKeys.reservations.list(bucket),
    queryFn: () => listReservations(bucket),
  });

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-4xl font-semibold tracking-normal">Lịch đặt</h1>
          <p className="mt-2 max-w-xl text-muted-foreground">
            Xem các lượt ghé sắp tới, theo dõi đặt cọc và thanh toán hóa đơn khi nhà hàng đã sẵn sàng.
          </p>
        </div>
        <Button asChild className="min-h-11 rounded-lg">
          <Link href="/reservations/new">Tạo lịch đặt</Link>
        </Button>
      </section>

      <Tabs value={bucket} onValueChange={(value) => setBucket(value as Bucket)} className="mb-5">
        <TabsList className="rounded-lg">
          <TabsTrigger value="upcoming" className="rounded-md">Sắp tới</TabsTrigger>
          <TabsTrigger value="history" className="rounded-md">Lịch sử</TabsTrigger>
          <TabsTrigger value="all" className="rounded-md">Tất cả</TabsTrigger>
        </TabsList>
      </Tabs>

      {reservationsQuery.isLoading ? <LoadingBlock label="Đang tải lịch đặt" /> : null}
      {reservationsQuery.error ? (
        <ErrorState error={reservationsQuery.error} title="Chưa tải được lịch đặt" onRetry={() => reservationsQuery.refetch()} />
      ) : null}
      {reservationsQuery.data?.length === 0 ? (
        <EmptyState
          title="Chưa có lịch đặt"
          description="Tìm bàn hoặc tạo lịch đặt mới để bắt đầu lượt ghé nhà hàng."
          action={
            <Button asChild className="rounded-lg">
              <Link href="/booking">Tìm bàn</Link>
            </Button>
          }
        />
      ) : null}

      <div className="grid gap-3 md:grid-cols-2">
        {reservationsQuery.data?.map((reservation) => (
          <ReservationCard key={reservation.reservation_id} reservation={reservation} />
        ))}
      </div>
    </main>
  );
}
