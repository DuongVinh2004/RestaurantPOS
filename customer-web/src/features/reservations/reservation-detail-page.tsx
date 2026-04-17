"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status/status-badge";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { BillingPanel } from "@/features/billing/billing-panel";
import { DepositPanel } from "@/features/deposit/deposit-panel";
import { PreorderPanel } from "@/features/preorder/preorder-panel";
import { BenefitsPanel } from "@/features/vouchers/benefits-panel";
import { cancelReservation, getReservation, rescheduleReservation } from "./api";
import { reservationActionSchema, type ReservationActionValues } from "./schemas";

export function ReservationDetailPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const reservationQuery = useQuery({
    queryKey: queryKeys.reservations.detail(id),
    queryFn: () => getReservation(id),
  });
  const actionForm = useForm<ReservationActionValues>({
    resolver: zodResolver(reservationActionSchema),
    values: {
      row_version: reservationQuery.data?.data.row_version ?? 1,
      reason: "",
      guest_count: reservationQuery.data?.data.guest_count ?? 2,
      start_time: "",
    },
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.reservations.detail(id) });
    queryClient.invalidateQueries({ queryKey: queryKeys.reservations.list("upcoming") });
  };

  const cancelMutation = useMutation({
    mutationFn: (values: ReservationActionValues) => cancelReservation(id, values.row_version, values.reason),
    onSuccess() {
      toast.success("Reservation cancelled.");
      invalidate();
    },
  });
  const rescheduleMutation = useMutation({
    mutationFn: (values: ReservationActionValues) => {
      const start = values.start_time ? new Date(values.start_time) : null;
      const end = start ? new Date(start.getTime() + 90 * 60_000) : null;

      return rescheduleReservation(id, {
        row_version: values.row_version,
        start_time: start?.toISOString(),
        end_time: end?.toISOString(),
        guest_count: values.guest_count,
        reason: values.reason,
      });
    },
    onSuccess() {
      toast.success("Reservation rescheduled.");
      invalidate();
    },
  });

  if (reservationQuery.isLoading) {
    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        <LoadingBlock label="Loading reservation" />
      </main>
    );
  }

  if (reservationQuery.error || !reservationQuery.data) {
    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        <ErrorState error={reservationQuery.error} title="Reservation is unavailable" onRetry={() => reservationQuery.refetch()} />
      </main>
    );
  }

  const reservation = reservationQuery.data.data;

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <Button asChild variant="ghost" className="mb-4 rounded-lg">
        <Link href="/reservations">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to reservations
        </Link>
      </Button>

      <section className="mb-5 rounded-lg border bg-card p-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="text-sm text-muted-foreground">{reservation.reservation_code}</p>
            <h1 className="mt-1 text-3xl font-semibold tracking-normal">{formatDateTime(reservation.start_time ?? reservation.booking_time ?? null)}</h1>
            <p className="mt-2 text-muted-foreground">{reservation.guest_count ?? "Not set"} guests</p>
          </div>
          <StatusBadge status={reservation.status} />
        </div>
        <div className="mt-5 grid gap-3 sm:grid-cols-3">
          <div className="rounded-lg bg-secondary p-4">
            <p className="text-sm text-muted-foreground">Deposit</p>
            <p className="font-semibold">{reservation.deposit_status ?? "Not required"}</p>
          </div>
          <div className="rounded-lg bg-secondary p-4">
            <p className="text-sm text-muted-foreground">Bill</p>
            <p className="font-semibold">{formatMoney(reservation.final_bill_amount, reservation.bill_currency ?? "USD")}</p>
          </div>
          <div className="rounded-lg bg-secondary p-4">
            <p className="text-sm text-muted-foreground">Row version</p>
            <p className="font-semibold">{reservation.row_version}</p>
          </div>
        </div>
      </section>

      <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
        <section className="space-y-5">
          <DepositPanel reservationId={reservation.reservation_id} rowVersion={reservation.row_version} />
          <BillingPanel reservationId={reservation.reservation_id} rowVersion={reservation.row_version} />
          <PreorderPanel reservationId={reservation.reservation_id} rowVersion={reservation.row_version} />
          <BenefitsPanel reservationId={reservation.reservation_id} />
        </section>

        <Card className="h-fit rounded-lg">
          <CardContent className="space-y-4 p-4">
            <h2 className="text-lg font-semibold">Manage reservation</h2>
            <form className="space-y-3" onSubmit={actionForm.handleSubmit((values) => rescheduleMutation.mutate(values))}>
              <input type="hidden" {...actionForm.register("row_version", { valueAsNumber: true })} />
              <div className="space-y-2">
                <Label htmlFor="start_time">New start time</Label>
                <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...actionForm.register("start_time")} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="guest_count">Guests</Label>
                <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...actionForm.register("guest_count", { valueAsNumber: true })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="reason">Reason or note</Label>
                <Textarea id="reason" className="min-h-20 rounded-lg" {...actionForm.register("reason")} />
              </div>
              <Button type="submit" variant="outline" className="w-full rounded-lg" disabled={rescheduleMutation.isPending}>
                {rescheduleMutation.isPending ? "Rescheduling" : "Reschedule"}
              </Button>
            </form>
            <Button
              type="button"
              variant="destructive"
              className="w-full rounded-lg"
              disabled={cancelMutation.isPending}
              onClick={actionForm.handleSubmit((values) => cancelMutation.mutate(values))}
            >
              {cancelMutation.isPending ? "Cancelling" : "Cancel reservation"}
            </Button>
            {cancelMutation.error || rescheduleMutation.error ? (
              <ErrorState error={cancelMutation.error ?? rescheduleMutation.error} title="Reservation action failed" />
            ) : null}
          </CardContent>
        </Card>
      </div>
    </main>
  );
}
