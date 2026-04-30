"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm, useWatch } from "react-hook-form";
import { CheckCircle2, Search } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { BookingProgress } from "@/components/booking/booking-progress";
import { StickyBookingSummary } from "@/components/booking/sticky-booking-summary";
import { queryKeys } from "@/lib/api/query-keys";
import { createRoundedFutureLocalDateTimeInput, formatLocalDateTimeInput, parseLocalDateTimeInput } from "@/lib/contracts/datetime";
import { userFacingApiMessage } from "@/lib/api/errors";
import { formatDateTime } from "@/lib/contracts/format";
import { formatCustomerTableName } from "@/lib/i18n/customer-display";
import { getTableHold, refreshTableHold } from "@/features/table-booking/api";
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

function formatHeldTables(
  hold: { tables?: Array<{ table_id: number; table_code?: string | null; zone?: string | null }> | null } | null,
  fallback: number[] | undefined,
): string {
  if (hold?.tables?.length) {
    return hold.tables
      .map((table) => formatCustomerTableName(table.table_code ?? null, table.zone ?? null, table.table_id))
      .join(", ");
  }

  return fallback?.map((tableId) => formatCustomerTableName(null, null, tableId)).join(", ") || "theo bàn đã chọn";
}

function formatVisitStartForCustomer(value: string | null | undefined): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value ?? "");

  if (!match) {
    return "Chưa chọn";
  }

  const [, year, month, day, hour, minute] = match;

  return `${hour}:${minute}, ${day}/${month}/${year}`;
}

function HoldDetailItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border bg-background/80 px-3 py-2">
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words text-sm font-semibold text-foreground">{value}</dd>
    </div>
  );
}

function HeldTableNotice({
  tableLabel,
  startLabel,
  guestCount,
  durationMinutes,
}: {
  tableLabel: string;
  startLabel: string;
  guestCount: number;
  durationMinutes: number;
}) {
  return (
    <section aria-label="Thông tin bàn đang giữ" className="space-y-3">
      <div>
        <p className="font-semibold text-foreground">Bàn đang được giữ cho bạn</p>
        <p className="mt-1 text-xs text-muted-foreground">Nhà hàng sẽ dùng thông tin này để xác nhận đặt bàn.</p>
      </div>
      <dl className="grid gap-2 sm:grid-cols-2">
        <HoldDetailItem label="Bàn đã chọn" value={tableLabel} />
        <HoldDetailItem label="Thời gian đến" value={startLabel} />
        <HoldDetailItem label="Số khách" value={`${guestCount} khách`} />
        <HoldDetailItem label="Thời lượng giữ bàn" value={`${durationMinutes} phút`} />
      </dl>
    </section>
  );
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
  const watchedGuestCount = useWatch({ control: form.control, name: "guest_count" });
  const watchedDurationMinutes = useWatch({ control: form.control, name: "duration_minutes" });
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
  const liveHoldTableLabel = formatHeldTables(liveHold, tableIds);
  const hasLockedHoldDetails = Boolean(holdId && liveHoldStartTime && liveHoldDurationMinutes && holdGuestCount);
  const expiredHold = Boolean(
    holdId &&
      (holdQuery.isSuccess
        ? !liveHoldState?.isActive
        : (holdStatus && holdStatus !== "Holding") ||
          (holdExpiresAt && Date.parse(holdExpiresAt) <= openedAtMs)),
  );
  const visitStartLabel = liveHoldStartTime
    ? formatVisitStartForCustomer(liveHoldStartTime)
    : "Chưa chọn";
  const bookingSummaryItems = [
    { label: "Ngày giờ", value: visitStartLabel },
    { label: "Số khách", value: `${holdGuestCount ?? watchedGuestCount} khách` },
    { label: "Thời lượng", value: `${liveHoldDurationMinutes ?? watchedDurationMinutes} phút` },
    { label: "Bàn đã chọn", value: liveHoldTableLabel },
  ];

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
    },
  });

  useEffect(() => {
    if (!liveHoldState?.isActive || !liveHoldState.expiresAt || refreshHoldMutation.isPending) {
      return;
    }

    const expiresAtMs = Date.parse(liveHoldState.expiresAt);
    if (!Number.isFinite(expiresAtMs)) {
      return;
    }

    const delayMs = Math.max(1000, expiresAtMs - Date.now() - 30_000);
    const timer = window.setTimeout(() => {
      refreshHoldMutation.mutate({ holdId: liveHoldState.holdId, rowVersion: liveHoldState.rowVersion });
    }, delayMs);

    return () => window.clearTimeout(timer);
  }, [liveHoldState?.expiresAt, liveHoldState?.holdId, liveHoldState?.isActive, liveHoldState?.rowVersion, refreshHoldMutation]);

  const createMutation = useMutation({
    mutationFn: (values: ReservationFormValues) => createReservation({ ...values, hold_id: holdId, table_ids: liveHoldTableIds }),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.reservations.detail(result.reservation_id), result);
      void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.lists, refetchType: "inactive" });
      toast.success("Đã tạo lịch đặt.");
      router.push(`/reservations/${result.reservation_id}`);
    },
  });

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6 pb-28 lg:pb-8">
      <section className="mb-5 space-y-3">
        <h1 className="text-4xl font-semibold tracking-normal">Xác nhận đặt bàn</h1>
        <p className="mt-2 text-muted-foreground">
          Điền thông tin liên hệ để nhà hàng xác nhận đặt bàn.
        </p>
        <BookingProgress currentStep="guest" />
      </section>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Thông tin khách</CardTitle>
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
                <Label htmlFor="duration_minutes">Thời lượng</Label>
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
                    <>Đang kiểm tra bàn đã chọn trước khi tạo lịch đặt.</>
                  ) : holdQuery.error ? (
                    <>
                      Chưa xác minh được bàn đã chọn. {userFacingApiMessage(holdQuery.error)}
                    </>
                  ) : expiredHold ? (
                    <>
                      Bàn đã chọn không còn hiệu lực
                      {liveHoldState?.expiresAt ? ` từ ${formatDateTime(liveHoldState.expiresAt)}` : holdExpiresAt ? ` từ ${formatDateTime(holdExpiresAt)}` : ""}.
                      Hãy tìm bàn phù hợp lại trước khi tạo lịch đặt.
                    </>
                  ) : hasLockedHoldDetails ? (
                    <HeldTableNotice
                      tableLabel={liveHoldTableLabel}
                      startLabel={formatVisitStartForCustomer(liveHoldStartTime)}
                      guestCount={holdGuestCount ?? watchedGuestCount}
                      durationMinutes={liveHoldDurationMinutes ?? watchedDurationMinutes}
                    />
                  ) : (
                    <section aria-label="Thông tin bàn đang giữ" className="space-y-2">
                      <p className="font-semibold text-foreground">Bàn đang được giữ cho bạn</p>
                      <p>
                        Bàn đã chọn: <span className="font-medium text-foreground">{liveHoldTableLabel}</span>. Kiểm tra lại thời gian và số khách trước khi gửi.
                      </p>
                    </section>
                  )}
                </AlertDescription>
              </Alert>
            ) : null}
            {refreshHoldMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(refreshHoldMutation.error)}</AlertDescription>
              </Alert>
            ) : null}
            {expiredHold || Boolean(holdQuery.error) ? (
              <Button asChild variant="outline" className="w-full rounded-lg">
                <Link href="/booking">
                  <Search className="mr-2 h-4 w-4" />
                  Tìm bàn phù hợp lại
                </Link>
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
              disabled={createMutation.isPending || refreshHoldMutation.isPending || expiredHold || holdQuery.isLoading || Boolean(holdQuery.error)}
            >
              <CheckCircle2 className="mr-2 h-4 w-4" />
              {createMutation.isPending ? "Đang tạo lịch đặt" : "Tạo lịch đặt"}
            </Button>
          </form>
        </CardContent>
      </Card>

      <StickyBookingSummary
        title="Tóm tắt trước khi xác nhận"
        items={bookingSummaryItems}
        holdCode={holdId}
        holdExpiresAt={liveHoldState?.expiresAt ?? holdExpiresAt}
        holdStatusLabel={liveHoldState?.statusLabel}
        primaryActionLabel={createMutation.isPending ? "Đang tạo lịch đặt" : "Xác nhận đặt bàn"}
        primaryActionDisabled={createMutation.isPending || refreshHoldMutation.isPending || expiredHold || holdQuery.isLoading || Boolean(holdQuery.error)}
        onPrimaryAction={() => form.handleSubmit((values) => createMutation.mutate(values))()}
        onRefreshHold={
          liveHoldState?.isActive
            ? () => refreshHoldMutation.mutate({ holdId: liveHoldState.holdId, rowVersion: liveHoldState.rowVersion })
            : undefined
        }
        refreshPending={refreshHoldMutation.isPending}
      />
      </div>
    </main>
  );
}
