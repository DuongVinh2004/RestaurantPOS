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
          <h1 className="text-4xl font-semibold tracking-normal">Reservations</h1>
          <p className="mt-2 max-w-xl text-muted-foreground">
            Review upcoming visits, manage deposits, and pay bills from live customer routes.
          </p>
        </div>
        <Button asChild className="min-h-11 rounded-lg">
          <Link href="/reservations/new">Create reservation</Link>
        </Button>
      </section>

      <Tabs value={bucket} onValueChange={(value) => setBucket(value as Bucket)} className="mb-5">
        <TabsList className="rounded-lg">
          <TabsTrigger value="upcoming" className="rounded-md">Upcoming</TabsTrigger>
          <TabsTrigger value="history" className="rounded-md">History</TabsTrigger>
          <TabsTrigger value="all" className="rounded-md">All</TabsTrigger>
        </TabsList>
      </Tabs>

      {reservationsQuery.isLoading ? <LoadingBlock label="Loading reservations" /> : null}
      {reservationsQuery.error ? (
        <ErrorState error={reservationsQuery.error} title="Reservations are unavailable" onRetry={() => reservationsQuery.refetch()} />
      ) : null}
      {reservationsQuery.data?.length === 0 ? (
        <EmptyState
          title="No reservations found"
          description="Create a reservation or hold a table to start a new visit."
          action={
            <Button asChild className="rounded-lg">
              <Link href="/booking">Find a table</Link>
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
