"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { ErrorState, EmptyState } from "@/components/states/state-blocks";
import type { AvailableTablesCollectionEnvelope, TableHoldEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";
import { formatDateTime } from "@/lib/contracts/format";
import { createTableHold, searchAvailableTables } from "./api";
import { availabilitySearchSchema, type AvailabilitySearchValues } from "./schemas";

function localTomorrow() {
  const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
  date.setMinutes(0, 0, 0);
  return date.toISOString().slice(0, 16);
}

export function TableBookingPage() {
  const [availability, setAvailability] = useState<AvailableTablesCollectionEnvelope | null>(null);
  const [hold, setHold] = useState<TableHoldEnvelope | null>(null);
  const [selectedTableIds, setSelectedTableIds] = useState<number[]>([]);
  const form = useForm<AvailabilitySearchValues>({
    resolver: zodResolver(availabilitySearchSchema),
    defaultValues: {
      start_time: localTomorrow(),
      duration_minutes: 90,
      guest_count: 2,
    },
  });

  const searchMutation = useMutation({
    mutationFn: searchAvailableTables,
    onSuccess(result) {
      setAvailability(result);
      setHold(null);
      setSelectedTableIds([]);
    },
  });

  const holdMutation = useMutation({
    mutationFn: () => createTableHold(form.getValues(), selectedTableIds),
    onSuccess(result) {
      setHold(result);
      toast.success("Table hold created.");
    },
  });

  const tables = availability?.data ?? [];

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-6 space-y-3">
        <Badge variant="outline" className="rounded-md">Live availability</Badge>
        <h1 className="text-4xl font-semibold tracking-normal">Find a table.</h1>
        <p className="max-w-xl text-muted-foreground">
          Search available tables, hold the best option, then create the reservation with the same browser session.
        </p>
      </section>

      <div className="grid gap-5 lg:grid-cols-[360px_1fr]">
        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Visit details</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => searchMutation.mutate(values))}>
              <div className="space-y-2">
                <Label htmlFor="start_time">Date and time</Label>
                <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...form.register("start_time")} />
                {form.formState.errors.start_time ? <p className="text-sm text-destructive">{form.formState.errors.start_time.message}</p> : null}
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Guests</Label>
                  <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...form.register("guest_count", { valueAsNumber: true })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="duration_minutes">Minutes</Label>
                  <Input id="duration_minutes" type="number" min={30} className="min-h-11 rounded-lg" {...form.register("duration_minutes", { valueAsNumber: true })} />
                </div>
              </div>
              <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={searchMutation.isPending}>
                {searchMutation.isPending ? "Searching" : "Search tables"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <section className="space-y-4">
          {searchMutation.error ? <ErrorState error={searchMutation.error} title="Availability search failed" /> : null}

          {availability && tables.length === 0 ? (
            <EmptyState title="No tables are available" description="Try another time or reduce the party size." />
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2">
            {tables.map((table) => {
              const selected = selectedTableIds.includes(table.table_id);
              return (
                <button
                  type="button"
                  key={table.table_id}
                  className={`rounded-lg border bg-card p-4 text-left transition ${
                    selected ? "border-primary ring-2 ring-primary/20" : "hover:border-primary/50"
                  }`}
                  onClick={() =>
                    setSelectedTableIds((current) =>
                      current.includes(table.table_id)
                        ? current.filter((id) => id !== table.table_id)
                        : [...current, table.table_id],
                    )
                  }
                >
                  <div className="flex items-center justify-between">
                    <span className="font-semibold">{table.table_code ?? `Table ${table.table_id}`}</span>
                    <Badge variant="outline" className="rounded-md">{table.status}</Badge>
                  </div>
                  <p className="mt-2 text-sm text-muted-foreground">Seats {table.seats ?? "not listed"}</p>
                </button>
              );
            })}
          </div>

          {availability ? (
            <div className="rounded-lg border bg-card p-4">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="font-medium">{selectedTableIds.length} table selected</p>
                  <p className="text-sm text-muted-foreground">Hold uses an idempotent live table-hold mutation.</p>
                </div>
                <Button
                  type="button"
                  className="rounded-lg"
                  disabled={selectedTableIds.length === 0 || holdMutation.isPending}
                  onClick={() => holdMutation.mutate()}
                >
                  {holdMutation.isPending ? "Creating hold" : "Create hold"}
                </Button>
              </div>
              {holdMutation.error ? <div className="mt-4"><ErrorState error={holdMutation.error} title="Could not create hold" /></div> : null}
            </div>
          ) : null}

          {hold ? (
            <Card className="rounded-lg border-primary">
              <CardContent className="space-y-3 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="font-semibold">Hold {hold.data.hold_id}</p>
                    <p className="text-sm text-muted-foreground">Expires {formatDateTime(hold.data.expire_at ?? null)}</p>
                  </div>
                  <Badge variant="outline" className="rounded-md">{hold.data.hold_status}</Badge>
                </div>
                <Button asChild className="w-full rounded-lg">
                  <Link href={`/reservations/new?hold_id=${encodeURIComponent(hold.data.hold_id)}&tables=${selectedTableIds.join(",")}`}>
                    Continue to reservation
                  </Link>
                </Button>
              </CardContent>
            </Card>
          ) : null}
        </section>
      </div>
    </main>
  );
}
