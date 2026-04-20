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
import { ErrorState, EmptyState, LoadingBlock } from "@/components/states/state-blocks";
import type { TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";
import { createRoundedFutureLocalDateTimeInput } from "@/lib/contracts/datetime";
import { formatDateTime } from "@/lib/contracts/format";
import { createTableHold, searchAvailableTables, type AvailableTablesResult } from "./api";
import { availabilitySearchSchema, type AvailabilitySearchValues } from "./schemas";
import { parseAvailabilityMeta, parseTableHoldState } from "./state";

export function TableBookingPage() {
  const [availability, setAvailability] = useState<AvailableTablesResult | null>(null);
  const [hold, setHold] = useState<TableHold | null>(null);
  const [heldVisitDetails, setHeldVisitDetails] = useState<AvailabilitySearchValues | null>(null);
  const [heldTableIds, setHeldTableIds] = useState<number[]>([]);
  const [selectedTableIds, setSelectedTableIds] = useState<number[]>([]);
  const form = useForm<AvailabilitySearchValues>({
    resolver: zodResolver(availabilitySearchSchema),
    defaultValues: {
      start_time: createRoundedFutureLocalDateTimeInput(),
      duration_minutes: 90,
      guest_count: 2,
    },
  });

  const searchMutation = useMutation({
    mutationFn: searchAvailableTables,
    onMutate() {
      setHold(null);
      setHeldVisitDetails(null);
      setHeldTableIds([]);
      setSelectedTableIds([]);
    },
    onSuccess(result) {
      setAvailability(result);
    },
  });

  const holdMutation = useMutation({
    mutationFn: (values: AvailabilitySearchValues) => createTableHold(values, selectedTableIds),
    onSuccess(result, values) {
      setHold(result);
      setHeldVisitDetails(values);
      setHeldTableIds([...selectedTableIds]);
      toast.success("Table hold created.");
    },
  });

  const tables = availability?.tables ?? [];
  const availabilityMeta = availability ? parseAvailabilityMeta(availability.meta, tables.length) : null;
  const holdState = hold ? parseTableHoldState(hold) : null;

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
          {searchMutation.isPending ? <LoadingBlock label="Searching availability" /> : null}
          {searchMutation.error ? (
            <ErrorState
              error={searchMutation.error}
              title="Availability search failed"
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {availability && tables.length === 0 ? (
            <EmptyState title="No tables are available" description="Try another time or reduce the party size." />
          ) : null}
          {availabilityMeta ? (
            <p className="text-sm text-muted-foreground">
              Showing {availabilityMeta.count} available table{availabilityMeta.count === 1 ? "" : "s"} in{" "}
              {availabilityMeta.branchTimezone ?? availabilityMeta.timezone ?? "the restaurant timezone"}.
            </p>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2">
            {tables.map((table) => {
              const selected = selectedTableIds.includes(table.table_id);
              return (
                <button
                  type="button"
                  key={table.table_id}
                  disabled={searchMutation.isPending || holdMutation.isPending}
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
                  disabled={selectedTableIds.length === 0 || holdMutation.isPending || searchMutation.isPending}
                  onClick={() => holdMutation.mutate(form.getValues())}
                >
                  {holdMutation.isPending ? "Creating hold" : "Create hold"}
                </Button>
              </div>
              {holdMutation.error ? (
                <div className="mt-4">
                  <ErrorState
                    error={holdMutation.error}
                    title="Could not create hold"
                    onRetry={() => holdMutation.mutate(form.getValues())}
                  />
                </div>
              ) : null}
            </div>
          ) : null}

          {hold && heldVisitDetails && holdState ? (
            <Card className="rounded-lg border-primary">
              <CardContent className="space-y-3 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="font-semibold">Hold {holdState.holdId}</p>
                    <p className="text-sm text-muted-foreground">Expires {formatDateTime(holdState.expiresAt)}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {heldVisitDetails.guest_count} guests, {heldVisitDetails.duration_minutes} minutes, starting{" "}
                      {formatDateTime(new Date(heldVisitDetails.start_time).toISOString())}
                    </p>
                  </div>
                  <Badge variant="outline" className="rounded-md">{holdState.status}</Badge>
                </div>
                {holdState.isActive ? (
                  <Button asChild className="w-full rounded-lg">
                    <Link
                      href={`/reservations/new?hold_id=${encodeURIComponent(holdState.holdId)}&hold_status=${encodeURIComponent(holdState.status)}&hold_expires_at=${encodeURIComponent(holdState.expiresAt ?? "")}&tables=${heldTableIds.join(",")}&start_time=${encodeURIComponent(heldVisitDetails.start_time)}&duration_minutes=${heldVisitDetails.duration_minutes}&guest_count=${heldVisitDetails.guest_count}`}
                    >
                      Continue to reservation
                    </Link>
                  </Button>
                ) : (
                  <EmptyState
                    title="This hold already expired"
                    description="Search availability again to create a fresh hold before continuing to the reservation form."
                    action={
                      <Button type="button" className="rounded-lg" onClick={() => searchMutation.mutate(form.getValues())}>
                        Search again
                      </Button>
                    }
                  />
                )}
              </CardContent>
            </Card>
          ) : null}
        </section>
      </div>
    </main>
  );
}
