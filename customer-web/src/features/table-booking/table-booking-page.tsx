"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
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
import { queryKeys } from "@/lib/api/query-keys";
import { cancelTableHold, createTableHold, refreshTableHold, searchAvailableTables, type AvailableTablesResult } from "./api";
import { availabilitySearchSchema, type AvailabilitySearchValues } from "./schemas";
import { parseAvailabilityMeta, parseTableHoldState } from "./state";

function getTableIdsFromHold(hold: TableHold): number[] {
  return Array.isArray(hold.tables)
    ? hold.tables
        .map((table) => table.table_id)
        .filter((tableId): tableId is number => Number.isInteger(tableId) && tableId > 0)
    : [];
}

export function TableBookingPage() {
  const queryClient = useQueryClient();
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
      const nextTableIds = getTableIdsFromHold(result);
      setHold(result);
      setHeldVisitDetails(values);
      setHeldTableIds(nextTableIds.length > 0 ? nextTableIds : [...selectedTableIds]);
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
      toast.success("Đã giữ bàn tạm thời.");
    },
  });
  const refreshHoldMutation = useMutation({
    mutationFn: ({ holdId, rowVersion }: { holdId: string; rowVersion: number }) => refreshTableHold(holdId, rowVersion),
    onSuccess(result) {
      const nextTableIds = getTableIdsFromHold(result);
      setHold(result);
      setHeldTableIds(nextTableIds.length > 0 ? nextTableIds : heldTableIds);
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
      toast.success("Đã gia hạn giữ bàn.");
    },
  });
  const cancelHoldMutation = useMutation({
    mutationFn: ({ holdId, rowVersion }: { holdId: string; rowVersion: number }) => cancelTableHold(holdId, rowVersion),
    onSuccess(result) {
      const nextTableIds = getTableIdsFromHold(result);
      setHold(result);
      setHeldTableIds(nextTableIds.length > 0 ? nextTableIds : heldTableIds);
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
      toast.success("Đã hủy giữ bàn.");
    },
  });

  const tables = availability?.tables ?? [];
  const availabilityMeta = availability ? parseAvailabilityMeta(availability.meta, tables.length) : null;
  const holdState = hold ? parseTableHoldState(hold) : null;
  const holdMutationError = refreshHoldMutation.error ?? cancelHoldMutation.error;
  const holdActionPending = refreshHoldMutation.isPending || cancelHoldMutation.isPending;

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-6 space-y-3">
        <Badge variant="outline" className="rounded-md">Bàn trống</Badge>
        <h1 className="text-4xl font-semibold tracking-normal">Tìm bàn phù hợp.</h1>
        <p className="max-w-xl text-muted-foreground">
          Chọn thời gian, số khách và giữ bàn tạm thời trước khi hoàn tất đặt chỗ.
        </p>
      </section>

      <div className="grid gap-5 lg:grid-cols-[360px_1fr]">
        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Thông tin ghé nhà hàng</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => searchMutation.mutate(values))}>
              <div className="space-y-2">
                <Label htmlFor="start_time">Ngày và giờ</Label>
                <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...form.register("start_time")} />
                {form.formState.errors.start_time ? <p className="text-sm text-destructive">{form.formState.errors.start_time.message}</p> : null}
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Số khách</Label>
                  <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...form.register("guest_count", { valueAsNumber: true })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="duration_minutes">Thời lượng phút</Label>
                  <Input id="duration_minutes" type="number" min={30} className="min-h-11 rounded-lg" {...form.register("duration_minutes", { valueAsNumber: true })} />
                </div>
              </div>
              <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={searchMutation.isPending}>
                {searchMutation.isPending ? "Đang tìm bàn" : "Tìm bàn"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <section className="space-y-4">
          {searchMutation.isPending ? <LoadingBlock label="Đang tìm bàn trống" /> : null}
          {searchMutation.error ? (
            <ErrorState
              error={searchMutation.error}
              title="Chưa tìm được bàn trống"
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {availability && tables.length === 0 ? (
            <EmptyState title="Chưa có bàn trống" description="Thử khung giờ khác hoặc giảm số khách." />
          ) : null}
          {availabilityMeta ? (
            <p className="text-sm text-muted-foreground">
              Đang hiển thị {availabilityMeta.count} bàn trống theo múi giờ{" "}
              {availabilityMeta.branchTimezone ?? availabilityMeta.timezone ?? "của nhà hàng"}.
            </p>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2">
            {tables.map((table) => {
              const selected = selectedTableIds.includes(table.table_id);
              return (
                <button
                  type="button"
                  key={table.table_id}
                  aria-label={`Chọn ${table.table_code ?? `bàn ${table.table_id}`}`}
                  aria-pressed={selected}
                  disabled={searchMutation.isPending || holdMutation.isPending || holdActionPending}
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
                    <span className="font-semibold">{table.table_code ?? `Bàn ${table.table_id}`}</span>
                    <Badge variant="outline" className="rounded-md">{table.status}</Badge>
                  </div>
                  <p className="mt-2 text-sm text-muted-foreground">Sức chứa {table.seats ?? "chưa có"} khách</p>
                </button>
              );
            })}
          </div>

          {availability ? (
            <div className="rounded-lg border bg-card p-4">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="font-medium">{selectedTableIds.length} bàn đã chọn</p>
                  <p className="text-sm text-muted-foreground">Nhà hàng sẽ giữ bàn tạm thời để bạn hoàn tất đặt chỗ.</p>
                </div>
                <Button
                  type="button"
                  className="rounded-lg"
                  disabled={selectedTableIds.length === 0 || holdMutation.isPending || searchMutation.isPending || holdActionPending}
                  onClick={() => holdMutation.mutate(form.getValues())}
                >
                  {holdMutation.isPending ? "Đang giữ bàn" : "Giữ bàn"}
                </Button>
              </div>
              {holdMutation.error ? (
                <div className="mt-4">
                  <ErrorState
                    error={holdMutation.error}
                    title="Chưa giữ được bàn"
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
                    <p className="font-semibold">Bàn đang được giữ</p>
                    <p className="text-sm text-muted-foreground">Hết hạn {formatDateTime(holdState.expiresAt)}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {heldVisitDetails.guest_count} khách, {heldVisitDetails.duration_minutes} phút, bắt đầu{" "}
                      {formatDateTime(new Date(heldVisitDetails.start_time).toISOString())}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">Mã giữ bàn: {holdState.holdId}</p>
                  </div>
                  <Badge variant="outline" className="rounded-md">{holdState.status}</Badge>
                </div>
                {holdMutationError ? (
                  <ErrorState
                    error={holdMutationError}
                    title="Chưa cập nhật được bàn giữ"
                    onRetry={() => {
                      if (!holdState.isActive) {
                        return;
                      }

                      if (refreshHoldMutation.error) {
                        refreshHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
                        return;
                      }

                      if (cancelHoldMutation.error) {
                        cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
                      }
                    }}
                  />
                ) : null}
                {holdState.isActive ? (
                  <div className="grid gap-3 sm:grid-cols-3">
                    <Button asChild className="rounded-lg">
                      <Link
                        href={`/reservations/new?hold_id=${encodeURIComponent(holdState.holdId)}&hold_status=${encodeURIComponent(holdState.status)}&hold_expires_at=${encodeURIComponent(holdState.expiresAt ?? "")}&tables=${heldTableIds.join(",")}&start_time=${encodeURIComponent(heldVisitDetails.start_time)}&duration_minutes=${heldVisitDetails.duration_minutes}&guest_count=${heldVisitDetails.guest_count}`}
                      >
                        Tiếp tục đặt chỗ
                      </Link>
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={holdActionPending}
                      onClick={() => refreshHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion })}
                    >
                      {refreshHoldMutation.isPending ? "Đang gia hạn" : "Gia hạn giữ bàn"}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={holdActionPending}
                      onClick={() => cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion })}
                    >
                      {cancelHoldMutation.isPending ? "Đang hủy" : "Hủy giữ bàn"}
                    </Button>
                  </div>
                ) : (
                  <EmptyState
                    title="Bàn giữ đã hết hạn"
                    description="Tìm bàn trống lại để giữ bàn mới trước khi đặt chỗ."
                    action={
                      <Button type="button" className="rounded-lg" onClick={() => searchMutation.mutate(form.getValues())}>
                        Tìm lại
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
