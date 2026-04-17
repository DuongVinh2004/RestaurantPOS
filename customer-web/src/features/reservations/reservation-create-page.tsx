"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { queryKeys } from "@/lib/api/query-keys";
import { userFacingApiMessage } from "@/lib/api/errors";
import { createReservation } from "./api";
import { reservationFormSchema, type ReservationFormValues } from "./schemas";

function defaultStartTime() {
  const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
  date.setMinutes(0, 0, 0);
  return date.toISOString().slice(0, 16);
}

function parseTableIds(value: string | null): number[] | undefined {
  const ids = (value ?? "")
    .split(",")
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item > 0);

  return ids.length ? ids : undefined;
}

export function ReservationCreatePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const form = useForm<ReservationFormValues>({
    resolver: zodResolver(reservationFormSchema),
    defaultValues: {
      guest_name: "",
      guest_phone: "",
      guest_email: "",
      start_time: defaultStartTime(),
      duration_minutes: 90,
      guest_count: 2,
      notes: "",
    },
  });
  const holdId = searchParams.get("hold_id");
  const tableIds = parseTableIds(searchParams.get("tables"));

  const createMutation = useMutation({
    mutationFn: (values: ReservationFormValues) => createReservation({ ...values, hold_id: holdId, table_ids: tableIds }),
    onSuccess(result) {
      queryClient.invalidateQueries({ queryKey: queryKeys.reservations.list("upcoming") });
      toast.success("Reservation created.");
      router.push(`/reservations/${result.data.reservation_id}`);
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
                <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...form.register("start_time")} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="duration_minutes">Minutes</Label>
                <Input id="duration_minutes" type="number" min={30} className="min-h-11 rounded-lg" {...form.register("duration_minutes", { valueAsNumber: true })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="guest_count">Guests</Label>
                <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...form.register("guest_count", { valueAsNumber: true })} />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="notes">Notes</Label>
              <Textarea id="notes" className="min-h-24 rounded-lg" {...form.register("notes")} />
            </div>
            {holdId ? (
              <Alert className="rounded-lg">
                <AlertDescription>Using table hold {holdId}. Tables: {tableIds?.join(", ") || "from hold"}.</AlertDescription>
              </Alert>
            ) : null}
            {createMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(createMutation.error)}</AlertDescription>
              </Alert>
            ) : null}
            <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={createMutation.isPending}>
              {createMutation.isPending ? "Creating reservation" : "Create reservation"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
