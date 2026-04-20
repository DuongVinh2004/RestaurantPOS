"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { queryKeys } from "@/lib/api/query-keys";
import { createRoundedFutureLocalDateTimeInput, parseLocalDateTimeInput } from "@/lib/contracts/datetime";
import { userFacingApiMessage } from "@/lib/api/errors";
import { formatDateTime } from "@/lib/contracts/format";
import { createReservation } from "./api";
import { reservationFormSchema, type ReservationFormValues } from "./schemas";

function parseTableIds(value: string | null): number[] | undefined {
  const ids = (value ?? "")
    .split(",")
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item > 0);

  return ids.length ? ids : undefined;
}

function parsePositiveInteger(value: string | null): number | null {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function parseHoldStartTime(value: string | null): string | null {
  return value && parseLocalDateTimeInput(value) ? value : null;
}

function parseHoldExpiresAt(value: string | null): string | null {
  if (!value) {
    return null;
  }

  const parsed = Date.parse(value);

  return Number.isFinite(parsed) ? new Date(parsed).toISOString() : null;
}

export function ReservationCreatePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const holdId = searchParams.get("hold_id");
  const holdStatus = searchParams.get("hold_status");
  const holdExpiresAt = parseHoldExpiresAt(searchParams.get("hold_expires_at"));
  const tableIds = parseTableIds(searchParams.get("tables"));
  const holdStartTime = parseHoldStartTime(searchParams.get("start_time"));
  const holdDurationMinutes = parsePositiveInteger(searchParams.get("duration_minutes"));
  const holdGuestCount = parsePositiveInteger(searchParams.get("guest_count"));
  const hasLockedHoldDetails = Boolean(holdId && holdStartTime && holdDurationMinutes && holdGuestCount);
  const [openedAtMs] = useState(() => Date.now());
  const expiredHold = Boolean(
    holdId &&
      ((holdStatus && holdStatus !== "Holding") ||
        (holdExpiresAt && Date.parse(holdExpiresAt) <= openedAtMs)),
  );
  const form = useForm<ReservationFormValues>({
    resolver: zodResolver(reservationFormSchema),
    defaultValues: {
      guest_name: "",
      guest_phone: "",
      guest_email: "",
      start_time: holdStartTime ?? createRoundedFutureLocalDateTimeInput(),
      duration_minutes: holdDurationMinutes ?? 90,
      guest_count: holdGuestCount ?? 2,
      notes: "",
    },
  });

  const createMutation = useMutation({
    mutationFn: (values: ReservationFormValues) => createReservation({ ...values, hold_id: holdId, table_ids: tableIds }),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.reservations.detail(result.reservation_id), result);
      void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.lists, refetchType: "inactive" });
      toast.success("Reservation created.");
      router.push(`/reservations/${result.reservation_id}`);
    },
  });

  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-6">
      <section className="mb-5">
        <h1 className="text-4xl font-semibold tracking-normal">Create reservation</h1>
        <p className="mt-2 text-muted-foreground">
          This sends the live reservation create request with the current customer session and idempotency key.
        </p>
      </section>

      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Visit details</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => createMutation.mutate(values))}>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="guest_name">Guest name</Label>
                <Input id="guest_name" className="min-h-11 rounded-lg" {...form.register("guest_name")} />
                {form.formState.errors.guest_name ? <p className="text-sm text-destructive">{form.formState.errors.guest_name.message}</p> : null}
              </div>
              <div className="space-y-2">
                <Label htmlFor="guest_phone">Phone</Label>
                <Input id="guest_phone" className="min-h-11 rounded-lg" {...form.register("guest_phone")} />
                {form.formState.errors.guest_phone ? <p className="text-sm text-destructive">{form.formState.errors.guest_phone.message}</p> : null}
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="guest_email">Email</Label>
              <Input id="guest_email" type="email" className="min-h-11 rounded-lg" {...form.register("guest_email")} />
              {form.formState.errors.guest_email ? <p className="text-sm text-destructive">{form.formState.errors.guest_email.message}</p> : null}
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-2 sm:col-span-1">
                <Label htmlFor="start_time">Start time</Label>
                <Input
                  id="start_time"
                  type="datetime-local"
                  className="min-h-11 rounded-lg"
                  disabled={hasLockedHoldDetails}
                  {...form.register("start_time")}
                />
                {form.formState.errors.start_time ? <p className="text-sm text-destructive">{form.formState.errors.start_time.message}</p> : null}
              </div>
              <div className="space-y-2">
                <Label htmlFor="duration_minutes">Minutes</Label>
                <Input
                  id="duration_minutes"
                  type="number"
                  min={30}
                  className="min-h-11 rounded-lg"
                  disabled={hasLockedHoldDetails}
                  {...form.register("duration_minutes", { valueAsNumber: true })}
                />
                {form.formState.errors.duration_minutes ? <p className="text-sm text-destructive">{form.formState.errors.duration_minutes.message}</p> : null}
              </div>
              <div className="space-y-2">
                <Label htmlFor="guest_count">Guests</Label>
                <Input
                  id="guest_count"
                  type="number"
                  min={1}
                  className="min-h-11 rounded-lg"
                  disabled={hasLockedHoldDetails}
                  {...form.register("guest_count", { valueAsNumber: true })}
                />
                {form.formState.errors.guest_count ? <p className="text-sm text-destructive">{form.formState.errors.guest_count.message}</p> : null}
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="notes">Notes</Label>
              <Textarea id="notes" className="min-h-24 rounded-lg" {...form.register("notes")} />
            </div>
            {holdId ? (
              <Alert variant={expiredHold ? "destructive" : "default"} className="rounded-lg">
                <AlertDescription>
                  {expiredHold ? (
                    <>
                      Table hold {holdId} is no longer active{holdExpiresAt ? ` as of ${formatDateTime(holdExpiresAt)}` : ""}. Search
                      availability again before creating a reservation.
                    </>
                  ) : hasLockedHoldDetails ? (
                    <>
                      Using table hold {holdId} for {holdGuestCount} guests starting{" "}
                      {formatDateTime(parseLocalDateTimeInput(holdStartTime as string)?.toISOString() ?? null)} for{" "}
                      {holdDurationMinutes} minutes. Search again if you need to change visit details. Tables:{" "}
                      {tableIds?.join(", ") || "from hold"}.
                    </>
                  ) : (
                    <>Using table hold {holdId}. Review visit details carefully before submitting. Tables: {tableIds?.join(", ") || "from hold"}.</>
                  )}
                </AlertDescription>
              </Alert>
            ) : null}
            {expiredHold ? (
              <Button asChild variant="outline" className="w-full rounded-lg">
                <Link href="/booking">Search availability again</Link>
              </Button>
            ) : null}
            {createMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(createMutation.error)}</AlertDescription>
              </Alert>
            ) : null}
            <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={createMutation.isPending || expiredHold}>
              {createMutation.isPending ? "Creating reservation" : "Create reservation"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
