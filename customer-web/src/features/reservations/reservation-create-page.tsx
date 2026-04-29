"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
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
import { createRoundedFutureLocalDateTimeInput, formatLocalDateTimeInput, parseLocalDateTimeInput } from "@/lib/contracts/datetime";
import { userFacingApiMessage } from "@/lib/api/errors";
import { formatDateTime } from "@/lib/contracts/format";
import { cancelTableHold, getTableHold, refreshTableHold } from "@/features/table-booking/api";
import { parseTableHoldState } from "@/features/table-booking/state";
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

function getTableIdsFromLiveHold(
  hold: { tables?: Array<{ table_id: number }> | null } | null,
  fallback: number[] | undefined,
): number[] | undefined {
  if (!hold || !Array.isArray(hold.tables)) {
    return fallback;
  }

  const tableIds = hold.tables
    .map((table) => table.table_id)
    .filter((tableId): tableId is number => Number.isInteger(tableId) && tableId > 0);

  return tableIds.length > 0 ? tableIds : fallback;
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
  const [openedAtMs] = useState(() => Date.now());
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
  const holdQuery = useQuery({
    queryKey: holdId ? queryKeys.tableBooking.hold(holdId) : ["tables", "hold", "none"],
    queryFn: () => getTableHold(holdId as string),
    enabled: Boolean(holdId),
    retry: false,
  });
  const liveHold = holdQuery.data ?? null;
  const liveHoldState = liveHold ? parseTableHoldState(liveHold) : null;
  const liveHoldStartTime = liveHold?.start_time ? formatLocalDateTimeInput(new Date(liveHold.start_time)) : holdStartTime;
  const liveHoldDurationMinutes =
    typeof liveHold?.duration_minutes === "number" && liveHold.duration_minutes > 0 ? liveHold.duration_minutes : holdDurationMinutes;
  const liveHoldTableIds = getTableIdsFromLiveHold(liveHold, tableIds);
  const hasLockedHoldDetails = Boolean(holdId && liveHoldStartTime && liveHoldDurationMinutes && holdGuestCount);
  const expiredHold = Boolean(
    holdId &&
      (holdQuery.isSuccess
        ? !liveHoldState?.isActive
        : (holdStatus && holdStatus !== "Holding") ||
          (holdExpiresAt && Date.parse(holdExpiresAt) <= openedAtMs)),
  );

  useEffect(() => {
    if (!holdId || !liveHold) {
      return;
    }

    const currentValues = form.getValues();

    form.reset({
      ...currentValues,
      start_time: liveHoldStartTime ?? currentValues.start_time,
      duration_minutes: liveHoldDurationMinutes ?? currentValues.duration_minutes,
      guest_count: holdGuestCount ?? currentValues.guest_count,
    });
  }, [form, holdGuestCount, holdId, liveHold, liveHoldDurationMinutes, liveHoldStartTime]);
  const refreshHoldMutation = useMutation({
    mutationFn: ({ holdId: liveHoldId, rowVersion }: { holdId: string; rowVersion: number }) => refreshTableHold(liveHoldId, rowVersion),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
      toast.success("Đã gia hạn giữ bàn.");
    },
  });
  const cancelHoldMutation = useMutation({
    mutationFn: ({ holdId: liveHoldId, rowVersion }: { holdId: string; rowVersion: number }) => cancelTableHold(liveHoldId, rowVersion),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
      toast.success("Đã hủy giữ bàn.");
    },
  });

  const createMutation = useMutation({
    mutationFn: (values: ReservationFormValues) => createReservation({ ...values, hold_id: holdId, table_ids: liveHoldTableIds }),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.reservations.detail(result.reservation_id), result);
      void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.lists, refetchType: "inactive" });
      toast.success("Đã tạo lịch đặt.");
      router.push(`/reservations/${result.reservation_id}`);
    },
  });
  const holdActionError = refreshHoldMutation.error ?? cancelHoldMutation.error;
  const holdActionPending = refreshHoldMutation.isPending || cancelHoldMutation.isPending;

  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-6">
      <section className="mb-5">
        <h1 className="text-4xl font-semibold tracking-normal">Tạo lịch đặt</h1>
        <p className="mt-2 text-muted-foreground">
          Điền thông tin liên hệ để nhà hàng xác nhận lượt ghé của bạn.
        </p>
      </section>

      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Thông tin lượt ghé</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => createMutation.mutate(values))}>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="guest_name">Tên khách</Label>
                <Input id="guest_name" className="min-h-11 rounded-lg" {...form.register("guest_name")} />
                {form.formState.errors.guest_name ? <p className="text-sm text-destructive">{form.formState.errors.guest_name.message}</p> : null}
              </div>
              <div className="space-y-2">
                <Label htmlFor="guest_phone">Số điện thoại</Label>
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
                <Label htmlFor="start_time">Giờ bắt đầu</Label>
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
                <Label htmlFor="duration_minutes">Thời lượng phút</Label>
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
                <Label htmlFor="guest_count">Số khách</Label>
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
              <Label htmlFor="notes">Ghi chú</Label>
              <Textarea id="notes" className="min-h-24 rounded-lg" {...form.register("notes")} />
            </div>
            {holdId ? (
              <Alert variant={expiredHold ? "destructive" : "default"} className="rounded-lg">
                <AlertDescription>
                  {holdQuery.isLoading ? (
                    <>Đang kiểm tra bàn giữ {holdId} trước khi tạo lịch đặt.</>
                  ) : holdQuery.error ? (
                    <>
                      Chưa xác minh được bàn giữ {holdId}. {userFacingApiMessage(holdQuery.error)}
                    </>
                  ) : expiredHold ? (
                    <>
                      Bàn giữ {liveHoldState?.holdId ?? holdId} không còn hiệu lực
                      {liveHoldState?.expiresAt ? ` từ ${formatDateTime(liveHoldState.expiresAt)}` : holdExpiresAt ? ` từ ${formatDateTime(holdExpiresAt)}` : ""}. Hãy tìm bàn
                      trống lại trước khi tạo lịch đặt.
                    </>
                  ) : hasLockedHoldDetails ? (
                    <>
                      Đang dùng bàn giữ {liveHoldState?.holdId ?? holdId} cho {holdGuestCount} khách, bắt đầu{" "}
                      {formatDateTime(parseLocalDateTimeInput(liveHoldStartTime as string)?.toISOString() ?? null)} trong{" "}
                      {liveHoldDurationMinutes} phút. Hãy tìm lại nếu bạn cần đổi thông tin. Bàn:{" "}
                      {liveHoldTableIds?.join(", ") || "theo bàn giữ"}.
                    </>
                  ) : (
                    <>Đang dùng bàn giữ {liveHoldState?.holdId ?? holdId}. Kiểm tra kỹ thông tin trước khi gửi. Bàn: {liveHoldTableIds?.join(", ") || "theo bàn giữ"}.</>
                  )}
                </AlertDescription>
              </Alert>
            ) : null}
            {holdId && liveHoldState?.isActive ? (
              <div className="grid gap-3 sm:grid-cols-2">
                <Button
                  type="button"
                  variant="outline"
                  className="rounded-lg"
                  disabled={holdActionPending}
                  onClick={() => refreshHoldMutation.mutate({ holdId: liveHoldState.holdId, rowVersion: liveHoldState.rowVersion })}
                >
                  {refreshHoldMutation.isPending ? "Đang gia hạn" : "Gia hạn giữ bàn"}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="rounded-lg"
                  disabled={holdActionPending}
                  onClick={() => cancelHoldMutation.mutate({ holdId: liveHoldState.holdId, rowVersion: liveHoldState.rowVersion })}
                >
                  {cancelHoldMutation.isPending ? "Đang nhả bàn" : "Nhả bàn"}
                </Button>
              </div>
            ) : null}
            {holdActionError ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(holdActionError)}</AlertDescription>
              </Alert>
            ) : null}
            {expiredHold || Boolean(holdQuery.error) ? (
              <Button asChild variant="outline" className="w-full rounded-lg">
                <Link href="/booking">Tìm bàn trống lại</Link>
              </Button>
            ) : null}
            {createMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(createMutation.error)}</AlertDescription>
              </Alert>
            ) : null}
            <Button
              type="submit"
              className="min-h-11 w-full rounded-lg"
              disabled={createMutation.isPending || holdActionPending || expiredHold || holdQuery.isLoading || Boolean(holdQuery.error)}
            >
              {createMutation.isPending ? "Đang tạo lịch đặt" : "Tạo lịch đặt"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
