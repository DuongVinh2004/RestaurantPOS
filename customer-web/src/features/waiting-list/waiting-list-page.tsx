"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useForm, type UseFormReturn } from "react-hook-form";
import { toast } from "sonner";
import { StatusBadge } from "@/components/status/status-badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { isConflictLikeApiError, normalizeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import type { CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";
import { cn } from "@/lib/utils";
import {
  acceptWaitingListEntry,
  cancelWaitingListEntry,
  confirmWaitingListArrival,
  createWaitingListEntry,
  declineWaitingListEntry,
  getWaitingListEntry,
  listWaitingList,
  waitingListMutationEntry,
} from "./api";
import { waitingListCreateSchema, type WaitingListCreateValues } from "./schemas";
import {
  getWaitingListJourneyState,
  getWaitingListOwnerActionPolicy,
  getWaitingListRefreshPolicy,
  getWaitingListSeatResultState,
  sortWaitingListEntries,
  waitingListActionLabels,
  type WaitingListOwnerAction,
} from "./state";

export function WaitingListPage() {
  const waitingListRollout = customerWebRollout.waitingList;

  if (!waitingListRollout.enabled) {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-6">
        <EmptyState
          title={waitingListRollout.disabledTitle}
          description={waitingListRollout.disabledDescription}
          action={
            <div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
              <Button asChild className="rounded-lg">
                <Link href="/booking">Tìm bàn</Link>
              </Button>
              <Button asChild variant="outline" className="rounded-lg">
                <Link href="/reservations">Xem lịch đặt</Link>
              </Button>
            </div>
          }
        />
      </main>
    );
  }

  return <WaitingListWorkspace />;
}

function WaitingListWorkspace() {
  const queryClient = useQueryClient();
  const [selectedIdOverride, setSelectedIdOverride] = useState<number | null>(null);
  const form = useForm<WaitingListCreateValues>({
    resolver: zodResolver(waitingListCreateSchema),
    defaultValues: {
      guest_count: 2,
      guest_name: "",
      phone: "",
      notes: "",
    },
  });
  const refreshPolicy = getWaitingListRefreshPolicy();
  const waitingListQuery = useQuery({
    queryKey: queryKeys.waitingList.list,
    queryFn: listWaitingList,
  });
  const orderedEntries = sortWaitingListEntries(waitingListQuery.data ?? []);
  const selectedId =
    selectedIdOverride !== null && orderedEntries.some((entry) => entry.waiting_id === selectedIdOverride)
      ? selectedIdOverride
      : (orderedEntries[0]?.waiting_id ?? null);
  const selectedListEntry = orderedEntries.find((entry) => entry.waiting_id === selectedId) ?? null;
  const detailQuery = useQuery({
    queryKey: queryKeys.waitingList.detail(selectedId ?? 0),
    queryFn: () => getWaitingListEntry(selectedId as number),
    enabled: selectedId !== null,
  });
  const activeEntry = detailQuery.data ?? (detailQuery.error ? null : selectedListEntry);
  const journeyState = activeEntry ? getWaitingListJourneyState(activeEntry) : null;
  const actionPolicy = activeEntry ? getWaitingListOwnerActionPolicy(activeEntry) : null;
  const seatResultState = activeEntry ? getWaitingListSeatResultState(activeEntry) : null;

  const refreshCurrentView = async (detailId = selectedId) => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.waitingList.list });

    if (detailId !== null) {
      await queryClient.invalidateQueries({ queryKey: queryKeys.waitingList.detail(detailId) });
    }
  };
  const syncWaitingListEntry = (entry: CustomerWaitingListEntry) => {
    setSelectedIdOverride(entry.waiting_id);
    queryClient.setQueryData(queryKeys.waitingList.detail(entry.waiting_id), entry);
    queryClient.setQueryData(queryKeys.waitingList.list, (current: CustomerWaitingListEntry[] | undefined) => {
      if (!current) {
        return [entry];
      }

      const found = current.some((item) => item.waiting_id === entry.waiting_id);

      return found ? current.map((item) => (item.waiting_id === entry.waiting_id ? entry : item)) : [entry, ...current];
    });
    void queryClient.invalidateQueries({ queryKey: queryKeys.waitingList.list, refetchType: "inactive" });
  };
  const createMutation = useMutation({
    mutationFn: createWaitingListEntry,
    onSuccess(result) {
      const entry = result.entry;

      toast.success("Đã đăng ký danh sách chờ. Hãy cập nhật khi nhân viên yêu cầu phản hồi.");
      form.reset();
      syncWaitingListEntry(entry);
    },
    onError: (error) => {
      applyWaitingListValidationErrors(error, form);
    },
  });
  const actionMutation = useMutation({
    mutationFn: async ({
      action,
      id,
      rowVersion,
    }: {
      action: WaitingListOwnerAction;
      id: number;
      rowVersion: number;
    }) => {
      const body = { row_version: rowVersion };

      if (action === "accept") {
        return { action, result: await acceptWaitingListEntry(id, body) };
      }

      if (action === "arrival") {
        return { action, result: await confirmWaitingListArrival(id, body) };
      }

      if (action === "decline") {
        return { action, result: await declineWaitingListEntry(id, body) };
      }

      return { action, result: await cancelWaitingListEntry(id, body) };
    },
    onSuccess({ action, result }) {
      const entry = waitingListMutationEntry(result);

      toast.success(waitingListActionSuccessMessage(action, result));
      syncWaitingListEntry(entry);
    },
    onError: async (error) => {
      if (isConflictLikeApiError(error)) {
        await refreshCurrentView();
      }
    },
  });
  const createError = createMutation.error;
  const actionError = actionMutation.error;
  const detailError = detailQuery.error ? normalizeApiError(detailQuery.error) : null;
  const waitingListLoading = waitingListQuery.isLoading;
  const detailLoading = detailQuery.isLoading && selectedId !== null;

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6">
      <section className="mb-5 space-y-2">
        <h1 className="text-4xl font-semibold tracking-normal">Danh sách chờ</h1>
        <p className="max-w-3xl text-muted-foreground">
          Đăng ký chờ bàn, theo dõi lời mời từ nhà hàng và phản hồi khi bạn vẫn muốn nhận bàn.
        </p>
      </section>

      <div className="grid gap-5 xl:grid-cols-[340px_minmax(0,1fr)]">
        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Đăng ký chờ bàn</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-lg border bg-secondary/30 p-4">
              <p className="font-medium">Thông báo dành cho bạn</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Nhà hàng sẽ cập nhật trạng thái tại đây. Hãy bấm cập nhật khi nhân viên yêu cầu kiểm tra hoặc sau khi bạn phản hồi lời mời.
              </p>
            </div>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit((values) => {
                form.clearErrors();
                createMutation.mutate(values);
              })}
            >
              <div className="space-y-2">
                <Label htmlFor="guest_name">Tên khách</Label>
                <Input id="guest_name" className="min-h-11 rounded-lg" {...form.register("guest_name")} />
                {form.formState.errors.guest_name ? (
                  <p className="text-sm text-destructive">{form.formState.errors.guest_name.message}</p>
                ) : null}
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Số khách</Label>
                  <Input
                    id="guest_count"
                    type="number"
                    min={1}
                    className="min-h-11 rounded-lg"
                    {...form.register("guest_count", { valueAsNumber: true })}
                  />
                  {form.formState.errors.guest_count ? (
                    <p className="text-sm text-destructive">{form.formState.errors.guest_count.message}</p>
                  ) : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="phone">Số điện thoại</Label>
                  <Input id="phone" className="min-h-11 rounded-lg" {...form.register("phone")} />
                  {form.formState.errors.phone ? <p className="text-sm text-destructive">{form.formState.errors.phone.message}</p> : null}
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="notes">Ghi chú</Label>
                <Textarea id="notes" className="min-h-20 rounded-lg" {...form.register("notes")} />
                {form.formState.errors.notes ? <p className="text-sm text-destructive">{form.formState.errors.notes.message}</p> : null}
              </div>
              {createError ? <ErrorState error={createError} title="Chưa đăng ký được danh sách chờ" /> : null}
              <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Đang đăng ký" : "Đăng ký chờ bàn"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <section className="space-y-5">
          <Card className="rounded-lg">
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="space-y-1">
                <CardTitle>Đăng ký của bạn</CardTitle>
                <p className="text-sm text-muted-foreground">{refreshPolicy.description}</p>
              </div>
              <Button
                type="button"
                variant="outline"
                className="rounded-lg"
                disabled={waitingListQuery.isFetching}
                onClick={() => {
                  void refreshCurrentView();
                }}
              >
                {waitingListQuery.isFetching ? "Đang cập nhật" : "Cập nhật danh sách"}
              </Button>
            </CardHeader>
            <CardContent className="space-y-3">
              {waitingListLoading ? <LoadingBlock label="Đang tải danh sách chờ" /> : null}
              {waitingListQuery.error ? (
                <ErrorState error={waitingListQuery.error} title="Chưa tải được danh sách chờ" onRetry={() => void refreshCurrentView()} />
              ) : null}
              {!waitingListLoading && !waitingListQuery.error && orderedEntries.length === 0 ? (
                <EmptyState
                  title="Chưa có đăng ký chờ"
                  description="Đăng ký chờ bàn khi nhà hàng cần bạn ghi danh hoặc chưa có bàn trống ngay."
                />
              ) : null}
              {orderedEntries.map((entry) => {
                const selected = entry.waiting_id === selectedId;
                const journey = getWaitingListJourneyState(entry);

                return (
                  <button
                    key={entry.waiting_id}
                    type="button"
                    className={cn(
                      "w-full rounded-lg border p-4 text-left transition-colors",
                      selected ? "border-primary bg-primary/5" : "border-border hover:border-primary/40 hover:bg-secondary/30",
                    )}
                    onClick={() => setSelectedIdOverride(entry.waiting_id)}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">Mã chờ #{entry.waiting_id}</p>
                        <p className="text-lg font-semibold">
                          {entry.guest_name ?? "Khách"} - {entry.guest_count} khách
                        </p>
                        <p className="text-sm text-muted-foreground">{journey.title}</p>
                      </div>
                      <StatusBadge status={entry.status} />
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                      <span>Đăng ký {formatDateTime(entry.requested_at)}</span>
                      <span>Bước tiếp theo {formatInlineStateLabel(entry.next_step)}</span>
                    </div>
                  </button>
                );
              })}
            </CardContent>
          </Card>

          <Card className="rounded-lg">
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="space-y-1">
                <CardTitle>Chi tiết đăng ký</CardTitle>
                <p className="text-sm text-muted-foreground">
                  Cập nhật thủ công sau khi nhân viên nhắc bạn kiểm tra hoặc sau khi bạn gửi phản hồi.
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                className="rounded-lg"
                disabled={selectedId === null || detailQuery.isFetching}
                onClick={() => {
                  void refreshCurrentView();
                }}
              >
                {detailQuery.isFetching ? "Đang cập nhật" : "Cập nhật chi tiết"}
              </Button>
            </CardHeader>
            <CardContent className="space-y-4">
              {detailLoading ? <LoadingBlock label="Đang tải chi tiết danh sách chờ" /> : null}
              {!detailLoading && detailError?.kind === "not_found" ? (
                <EmptyState
                  title="Đăng ký này không còn khả dụng"
                  description="Cập nhật danh sách chờ để lấy thông tin mới nhất trước khi phản hồi tiếp."
                  action={
                    <Button type="button" variant="outline" className="rounded-lg" onClick={() => void refreshCurrentView()}>
                      Cập nhật đăng ký
                    </Button>
                  }
                />
              ) : null}
              {!detailLoading && detailQuery.error && detailError?.kind !== "not_found" ? (
                <ErrorState error={detailQuery.error} title="Chưa tải được chi tiết danh sách chờ" onRetry={() => void refreshCurrentView()} />
              ) : null}
              {!detailLoading && !detailQuery.error && !activeEntry ? (
                <EmptyState
                  title="Chọn một đăng ký"
                  description="Chọn đăng ký chờ để xem trạng thái, kết quả xếp bàn và thao tác có thể làm."
                />
              ) : null}
              {activeEntry && journeyState && actionPolicy && seatResultState ? (
                <>
                  <div className="space-y-2">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="text-sm text-muted-foreground">Mã chờ #{activeEntry.waiting_id}</p>
                        <h2 className="text-xl font-semibold">
                          {activeEntry.guest_name ?? "Khách"} - {activeEntry.guest_count} khách
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                          Đăng ký {formatDateTime(activeEntry.requested_at)} - Bước tiếp theo {formatInlineStateLabel(activeEntry.next_step)}
                        </p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <StatusBadge status={activeEntry.status} />
                        <StatusBadge status={formatInlineStateLabel(activeEntry.next_step)} />
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-3 lg:grid-cols-2">
                    <div className="rounded-lg bg-secondary p-4">
                      <p className="text-sm text-muted-foreground">Trạng thái chờ</p>
                      <p className="mt-1 text-lg font-semibold">{journeyState.title}</p>
                      <p className="mt-2 text-sm text-muted-foreground">{journeyState.description}</p>
                      <p className="mt-3 text-sm font-medium">{journeyState.nextStep}</p>
                    </div>
                    <div className="rounded-lg bg-secondary p-4">
                      <p className="text-sm text-muted-foreground">Kết quả xếp bàn</p>
                      <p className="mt-1 text-lg font-semibold">{seatResultState.title}</p>
                      <p className="mt-2 text-sm text-muted-foreground">{seatResultState.description}</p>
                      {seatResultState.reservationId !== null ? (
                        <p className="mt-3 text-sm font-medium">Lịch đặt liên kết #{seatResultState.reservationId}</p>
                      ) : null}
                      {seatResultState.tableLabel ? <p className="mt-1 text-sm text-muted-foreground">Bàn gần nhất {seatResultState.tableLabel}</p> : null}
                    </div>
                  </div>

                  <div className="grid gap-3 lg:grid-cols-2">
                    <div className="rounded-lg border p-4">
                      <p className="text-sm text-muted-foreground">Khung phản hồi lời mời</p>
                      <p className="mt-1 font-medium">{describeInviteWindow(activeEntry)}</p>
                      <p className="mt-2 text-sm text-muted-foreground">
                        Thông báo {formatDateTime(activeEntry.notified_at)} - Hết hạn {formatDateTime(activeEntry.notify_window.expires_at)}
                      </p>
                    </div>
                    <div className="rounded-lg border p-4">
                      <p className="text-sm text-muted-foreground">{refreshPolicy.title}</p>
                      <p className="mt-1 font-medium">Phiên bản cập nhật {activeEntry.row_version}</p>
                      <p className="mt-2 text-sm text-muted-foreground">{refreshPolicy.description}</p>
                    </div>
                  </div>

                  <section className="space-y-3">
                    <div>
                      <h3 className="text-lg font-semibold">Thao tác có thể làm</h3>
                      <p className="mt-1 font-medium">{actionPolicy.title}</p>
                      <p className="text-sm text-muted-foreground">{actionPolicy.description}</p>
                    </div>
                    {actionPolicy.availableActions.length > 0 ? (
                      <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        {actionPolicy.availableActions.map((action) => (
                          <Button
                            key={action}
                            type="button"
                            variant="outline"
                            className="rounded-lg"
                            disabled={actionMutation.isPending || !detailQuery.data}
                            onClick={() => {
                              if (!detailQuery.data) {
                                return;
                              }

                              actionMutation.mutate({
                                action,
                                id: detailQuery.data.waiting_id,
                                rowVersion: detailQuery.data.row_version,
                              });
                            }}
                          >
                            {actionMutation.isPending && actionMutation.variables?.action === action
                              ? `${waitingListActionLabels[action]}...`
                              : waitingListActionLabels[action]}
                          </Button>
                        ))}
                      </div>
                    ) : (
                      <EmptyState title={actionPolicy.title} description={actionPolicy.description} />
                    )}
                    {actionError ? (
                      <ErrorState
                        error={actionError}
                        title={isConflictLikeApiError(actionError) ? "Thông tin danh sách chờ đã thay đổi" : "Chưa xử lý được danh sách chờ"}
                        onRetry={() => void refreshCurrentView()}
                      />
                    ) : null}
                  </section>
                </>
              ) : null}
            </CardContent>
          </Card>
        </section>
      </div>
    </main>
  );
}

function applyWaitingListValidationErrors(error: unknown, form: UseFormReturn<WaitingListCreateValues>) {
  const normalized = normalizeApiError(error);
  const fields: Array<keyof WaitingListCreateValues> = ["guest_name", "guest_count", "phone", "notes"];

  for (const field of fields) {
    const message = normalized.validationErrors?.[field]?.[0];

    if (message) {
      form.setError(field, { type: "server", message });
    }
  }
}

function waitingListActionSuccessMessage(
  action: WaitingListOwnerAction,
  result:
    | Awaited<ReturnType<typeof confirmWaitingListArrival>>
    | Awaited<ReturnType<typeof acceptWaitingListEntry>>
    | Awaited<ReturnType<typeof declineWaitingListEntry>>
    | Awaited<ReturnType<typeof cancelWaitingListEntry>>,
) {
  if (action === "arrival" && result.meta) {
    return result.meta.message ?? "Đã xác nhận bạn có mặt. Nhân viên vẫn cần hoàn tất bước xếp bàn.";
  }

  switch (action) {
    case "accept":
      return "Đã nhận lời mời. Hãy xác nhận khi bạn tới nhà hàng.";
    case "arrival":
      return "Đã xác nhận bạn có mặt. Nhân viên vẫn cần hoàn tất bước xếp bàn.";
    case "decline":
      return "Đã từ chối lời mời.";
    case "cancel":
    default:
      return "Đã hủy đăng ký chờ bàn.";
  }
}

function describeInviteWindow(entry: CustomerWaitingListEntry) {
  if (entry.notify_window.is_open) {
    return `Lời mời còn hiệu lực đến ${formatDateTime(entry.notify_window.expires_at)}`;
  }

  if (entry.notify_window.expires_at && entry.status === "Notified") {
    return `Lời mời đã hết hạn lúc ${formatDateTime(entry.notify_window.expires_at)}`;
  }

  if (entry.notified_at) {
    return `Lời mời gần nhất mở lúc ${formatDateTime(entry.notified_at)}`;
  }

  return "Chưa có lời mời đang mở";
}

function formatInlineStateLabel(value: string | null | undefined): string {
  if (!value) {
    return "Unknown";
  }

  return value
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
