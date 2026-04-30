"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { BookingProgress } from "@/components/booking/booking-progress";
import { ReservationTimeline, type ReservationTimelineItem } from "@/components/booking/reservation-timeline";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status/status-badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { isConflictLikeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { localDateTimeRangeToUtc } from "@/lib/contracts/datetime";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { BillingPanel } from "@/features/billing/billing-panel";
import { DepositPanel } from "@/features/deposit/deposit-panel";
import { PreorderPanel } from "@/features/preorder/preorder-panel";
import { BenefitsPanel } from "@/features/vouchers/benefits-panel";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { cancelReservation, getReservation, mergeReservationInList, rescheduleReservation, type ReservationList } from "./api";
import { reservationActionSchema, type ReservationActionValues } from "./schemas";
import {
  getReservationActionPolicy,
  getReservationBillSummaryState,
  getReservationDepositSummaryState,
  getReservationDurationMinutes,
  getReservationHoldSummaryState,
  getReservationWorkspaceStatus,
  reservationStartInputValue,
} from "./state";
import { getSelfServiceAccessState, getSelfServiceBlockedState } from "./self-service-boundary";

function WorkspaceSummaryTile({
  eyebrow,
  title,
  description,
  footer,
}: {
  eyebrow: string;
  title: string;
  description: string;
  footer?: string | null;
}) {
  return (
    <div className="rounded-lg bg-secondary p-4">
      <p className="text-sm text-muted-foreground">{eyebrow}</p>
      <p className="mt-2 font-semibold">{title}</p>
      <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      {footer ? <p className="mt-3 text-sm font-medium">{footer}</p> : null}
    </div>
  );
}

function buildReservationTimelineItems({
  reservation,
  holdSummary,
  depositSummary,
  billSummary,
  workspaceStatus,
}: {
  reservation: ReservationSummary;
  holdSummary: ReturnType<typeof getReservationHoldSummaryState>;
  depositSummary: ReturnType<typeof getReservationDepositSummaryState>;
  billSummary: ReturnType<typeof getReservationBillSummaryState>;
  workspaceStatus: ReturnType<typeof getReservationWorkspaceStatus>;
}): ReservationTimelineItem[] {
  const status = (reservation.status ?? "").toLowerCase();
  const isCancelled = status.includes("cancel");
  const isCompleted = status.includes("completed");
  const isServing = status.includes("reserved") || status.includes("checked") || status.includes("seated");

  return [
    {
      key: "created",
      title: "Đã tạo lịch đặt",
      description: "Nhà hàng đã nhận thông tin đặt bàn của bạn.",
      state: "done",
      meta: reservation.reservation_code,
    },
    {
      key: "hold",
      title: holdSummary.title,
      description: holdSummary.description,
      state: holdSummary.state === "active" || holdSummary.state === "released" ? "done" : holdSummary.state === "expired" ? "blocked" : "pending",
      meta: holdSummary.expiresAt ? formatDateTime(holdSummary.expiresAt) : null,
    },
    {
      key: "deposit",
      title: depositSummary.title,
      description: depositSummary.description,
      state: depositSummary.state === "paid" || depositSummary.state === "not_required" ? "done" : depositSummary.requiresAction ? "current" : "pending",
      meta: depositSummary.amount ? formatMoney(depositSummary.amount, depositSummary.currency) : null,
    },
    {
      key: "confirmed",
      title: workspaceStatus.title,
      description: workspaceStatus.description,
      state: isCancelled ? "blocked" : "done",
      meta: workspaceStatus.label,
    },
    {
      key: "service",
      title: isCompleted ? "Lượt ghé đã hoàn tất" : isServing ? "Đang phục vụ tại nhà hàng" : "Chờ đến giờ nhận bàn",
      description: isServing || isCompleted
        ? "Nhân viên sẽ cập nhật món, hóa đơn và thanh toán khi phát sinh."
        : "Khi đến nhà hàng, nhân viên sẽ nhận bàn và mở luồng phục vụ.",
      state: isCompleted ? "done" : isServing ? "current" : "pending",
    },
    {
      key: "bill",
      title: billSummary.title,
      description: billSummary.description,
      state: billSummary.state === "settled" ? "done" : billSummary.available ? "current" : "pending",
      meta: billSummary.available ? formatMoney(billSummary.amount, billSummary.currency) : billSummary.label,
    },
  ];
}

export function ReservationDetailPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const reservationQuery = useQuery({
    queryKey: queryKeys.reservations.detail(id),
    queryFn: () => getReservation(id),
  });
  const actionForm = useForm<ReservationActionValues>({
    resolver: zodResolver(reservationActionSchema),
    values: {
      row_version: reservationQuery.data?.row_version ?? 1,
      reason: "",
      guest_count: reservationQuery.data?.guest_count ?? 2,
      start_time: reservationQuery.data ? reservationStartInputValue(reservationQuery.data) : "",
    },
  });

  const refreshReservation = () => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.detail(id) });
    void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.lists, refetchType: "inactive" });
  };
  const syncReservation = (nextReservation?: ReservationSummary) => {
    if (!nextReservation) {
      refreshReservation();
      return;
    }

    queryClient.setQueryData(queryKeys.reservations.detail(nextReservation.reservation_id), nextReservation);
    queryClient.setQueriesData<ReservationList>({ queryKey: queryKeys.reservations.lists }, (current) =>
      mergeReservationInList(current, nextReservation),
    );
    void queryClient.invalidateQueries({ queryKey: queryKeys.reservations.lists, refetchType: "inactive" });
  };

  const cancelMutation = useMutation({
    mutationFn: (values: ReservationActionValues) => cancelReservation(id, values.row_version, values.reason),
    onSuccess(result) {
      toast.success("Đã hủy lịch đặt.");
      syncReservation(result);
    },
    onError(error) {
      if (isConflictLikeApiError(error)) {
        refreshReservation();
      }
    },
  });
  const rescheduleMutation = useMutation({
    mutationFn: (values: ReservationActionValues) => {
      if (!values.start_time) {
        throw new Error("Chọn giờ mới.");
      }

      const durationMinutes = getReservationDurationMinutes(
        reservationQuery.data ?? {
          reservation_id: id,
          reservation_code: `reservation-${id}`,
          status: "Confirmed",
          row_version: values.row_version,
        },
      );
      const times = localDateTimeRangeToUtc(values.start_time, durationMinutes);

      return rescheduleReservation(id, {
        row_version: values.row_version,
        start_time: times.start_time,
        end_time: times.end_time,
        guest_count: values.guest_count,
        reason: values.reason,
      });
    },
    onSuccess(result) {
      toast.success("Đã gửi giờ mới.");
      syncReservation(result);
    },
    onError(error) {
      if (isConflictLikeApiError(error)) {
        refreshReservation();
      }
    },
  });

  if (reservationQuery.isLoading) {
    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        <LoadingBlock label="Đang tải lịch đặt" />
      </main>
    );
  }

  if (reservationQuery.error || !reservationQuery.data) {
    const boundary =
      reservationQuery.error !== null
        ? getSelfServiceBlockedState("reservation", reservationQuery.error, "Chưa tải được lịch đặt")
        : {
            kind: "unavailable" as const,
            title: "Chưa tải được lịch đặt",
            description: "Hiện chưa có thông tin lịch đặt này.",
          };

    return (
      <main className="mx-auto w-full max-w-5xl px-4 py-6">
        {boundary.kind === "error" ? (
          <ErrorState error={boundary.error} title={boundary.title} onRetry={() => reservationQuery.refetch()} />
        ) : (
          <EmptyState title={boundary.title} description={boundary.description} />
        )}
      </main>
    );
  }

  const reservation = reservationQuery.data;
  const accessState = getSelfServiceAccessState(reservation.access_scope);
  const actionPolicy = getReservationActionPolicy(reservation);
  const workspaceStatus = getReservationWorkspaceStatus(reservation);
  const depositSummary = getReservationDepositSummaryState(reservation);
  const billSummary = getReservationBillSummaryState(reservation);
  const holdSummary = getReservationHoldSummaryState(reservation);
  const actionError = cancelMutation.error ?? rescheduleMutation.error;
  const actionBoundary = actionError
    ? getSelfServiceBlockedState("reservation", actionError, isConflictLikeApiError(actionError) ? "Thông tin lịch đặt đã thay đổi" : "Chưa xử lý được lịch đặt")
    : null;
  const holdFooter = holdSummary.expiresAt
    ? `Đến ${formatDateTime(holdSummary.expiresAt)}${holdSummary.tableCount > 0 ? ` | ${holdSummary.tableCount} bàn` : ""}`
    : holdSummary.tableCount > 0
      ? `${holdSummary.tableCount} bàn`
      : null;
  const depositFooter = depositSummary.amount ? formatMoney(depositSummary.amount, depositSummary.currency) : null;
  const billFooter = billSummary.available ? formatMoney(billSummary.amount, billSummary.currency) : billSummary.label;
  const timelineItems = buildReservationTimelineItems({
    reservation,
    holdSummary,
    depositSummary,
    billSummary,
    workspaceStatus,
  });

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <Button asChild variant="ghost" className="mb-4 rounded-lg">
        <Link href="/reservations">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Quay lại lịch đặt
        </Link>
      </Button>

      <section className="mb-5 rounded-lg border bg-card p-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="text-sm text-muted-foreground">{reservation.reservation_code}</p>
            <h1 className="mt-1 text-3xl font-semibold tracking-normal">{formatDateTime(reservation.start_time ?? reservation.booking_time ?? null)}</h1>
            <p className="mt-2 text-muted-foreground">{reservation.guest_count ?? "Chưa có"} khách</p>
          </div>
          <StatusBadge status={reservation.status} />
        </div>
        <div className="mt-5 rounded-lg bg-secondary/60 p-4">
          <p className="text-sm text-muted-foreground">Trạng thái lịch đặt</p>
          <p className="mt-1 text-lg font-semibold">{workspaceStatus.title}</p>
          <p className="mt-1 text-sm text-muted-foreground">{workspaceStatus.description}</p>
        </div>
        {accessState ? (
          <div className="mt-5 rounded-lg border bg-secondary/40 p-4">
            <p className="text-sm font-medium">{accessState.title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{accessState.description}</p>
          </div>
        ) : null}
        <div className="mt-5 grid gap-3 sm:grid-cols-3">
          <WorkspaceSummaryTile eyebrow="Bàn giữ" title={holdSummary.title} description={holdSummary.description} footer={holdFooter} />
          <WorkspaceSummaryTile eyebrow="Đặt cọc" title={depositSummary.title} description={depositSummary.description} footer={depositFooter} />
          <WorkspaceSummaryTile eyebrow="Hóa đơn" title={billSummary.title} description={billSummary.description} footer={billFooter} />
        </div>
        <div className="mt-5">
          <BookingProgress currentStep="confirm" />
        </div>
      </section>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section className="space-y-5">
          <ReservationTimeline items={timelineItems} />
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">Chi tiết lượt ghé</h2>
            <p className="text-sm text-muted-foreground">Theo dõi bàn giữ, đặt cọc, hóa đơn, món đặt trước và ưu đãi từ một nơi.</p>
          </div>
          <Card className="rounded-lg">
            <CardContent className="space-y-4 p-4">
              <div>
                <h3 className="text-lg font-semibold">Bàn giữ</h3>
                <p className="text-sm text-muted-foreground">Xem bàn giữ tạm thời còn hiệu lực cho lịch đặt này hay không.</p>
              </div>
              {holdSummary.state === "unavailable" ? (
                <EmptyState title={holdSummary.title} description={holdSummary.description} />
              ) : (
                <div className="rounded-lg bg-secondary p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm text-muted-foreground">Trạng thái bàn giữ</p>
                      <p className="text-lg font-semibold">{holdSummary.title}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{holdSummary.description}</p>
                    </div>
                    <StatusBadge status={holdSummary.label} />
                  </div>
                  {holdFooter ? <p className="mt-4 text-sm font-medium">{holdFooter}</p> : null}
                </div>
              )}
            </CardContent>
          </Card>
          <DepositPanel reservation={reservation} onReservationChanged={syncReservation} />
          <BillingPanel reservation={reservation} onReservationChanged={syncReservation} />
          <PreorderPanel reservationId={reservation.reservation_id} />
          <BenefitsPanel reservationId={reservation.reservation_id} />
        </section>

        <Card className="h-fit rounded-lg">
          <CardContent className="space-y-4 p-4">
            <div>
              <h2 className="text-lg font-semibold">Thao tác trực tuyến</h2>
              <p className="mt-1 text-sm text-muted-foreground">{actionPolicy.manageDescription}</p>
            </div>
            {actionPolicy.canCancel || actionPolicy.canReschedule ? (
              <>
                {actionPolicy.canReschedule ? (
                  <form
                    className="space-y-3"
                    onSubmit={actionForm.handleSubmit((values) => {
                      if (!values.start_time) {
                        actionForm.setError("start_time", { message: "Chọn giờ mới." });
                        return;
                      }

                      rescheduleMutation.mutate(values);
                    })}
                  >
                    <input type="hidden" {...actionForm.register("row_version", { valueAsNumber: true })} />
                    <div className="space-y-2">
                      <Label htmlFor="start_time">Giờ mới</Label>
                      <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...actionForm.register("start_time")} />
                      {actionForm.formState.errors.start_time ? (
                        <p className="text-sm text-destructive">{actionForm.formState.errors.start_time.message}</p>
                      ) : null}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="guest_count">Số khách</Label>
                      <Input
                        id="guest_count"
                        type="number"
                        min={1}
                        className="min-h-11 rounded-lg"
                        {...actionForm.register("guest_count", { valueAsNumber: true })}
                      />
                      {actionForm.formState.errors.guest_count ? (
                        <p className="text-sm text-destructive">{actionForm.formState.errors.guest_count.message}</p>
                      ) : null}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="reason">Lý do hoặc ghi chú</Label>
                      <Textarea id="reason" className="min-h-20 rounded-lg" {...actionForm.register("reason")} />
                    </div>
                    <Button type="submit" variant="outline" className="w-full rounded-lg" disabled={rescheduleMutation.isPending}>
                      {rescheduleMutation.isPending ? "Đang gửi giờ mới" : "Yêu cầu đổi giờ"}
                    </Button>
                  </form>
                ) : (
                  <p className="text-sm text-muted-foreground">{actionPolicy.rescheduleReason}</p>
                )}
                {actionPolicy.canCancel ? (
                  <Button
                    type="button"
                    variant="destructive"
                    className="w-full rounded-lg"
                    disabled={cancelMutation.isPending}
                    onClick={actionForm.handleSubmit((values) => cancelMutation.mutate(values))}
                  >
                    {cancelMutation.isPending ? "Đang hủy" : "Hủy lịch đặt"}
                  </Button>
                ) : (
                  <p className="text-sm text-muted-foreground">{actionPolicy.cancelReason}</p>
                )}
              </>
            ) : (
              <EmptyState
                title={actionPolicy.manageTitle}
                description={actionPolicy.manageDescription}
              />
            )}
            {actionBoundary ? (
              actionBoundary.kind === "error" ? (
                <ErrorState error={actionBoundary.error} title={actionBoundary.title} onRetry={refreshReservation} />
              ) : (
                <EmptyState title={actionBoundary.title} description={actionBoundary.description} />
              )
            ) : null}
          </CardContent>
        </Card>
      </div>
    </main>
  );
}
