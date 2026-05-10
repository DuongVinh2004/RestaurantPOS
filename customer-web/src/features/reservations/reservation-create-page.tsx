"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { startTransition, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm, useWatch } from "react-hook-form";
import { CheckCircle2, Search } from "lucide-react";
import { toast } from "sonner";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { BookingProgress } from "@/components/booking/booking-progress";
import { StickyBookingSummary } from "@/components/booking/sticky-booking-summary";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import { queryKeys } from "@/lib/api/query-keys";
import {
  createRoundedFutureLocalDateTimeInput,
  formatLocalDateTimeInput,
  parseLocalDateTimeInput,
  toUtcIsoFromLocalDateTimeInput,
} from "@/lib/contracts/datetime";
import { userFacingApiMessage } from "@/lib/api/errors";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatCustomerTableName } from "@/lib/i18n/customer-display";
import { useAuth } from "@/providers/auth-provider";
import {
  listMenuItems,
  previewMenuPreorder,
  type MenuPreorderPreview,
} from "@/features/menu/api";
import {
  createReservationWithPreorderDraft,
  isReservationPreorderPersistenceError,
} from "@/features/preorder/reservation-create-flow";
import {
  storePendingReservationPreorderDraft,
} from "@/features/preorder/reservation-draft-storage";
import {
  menuItemPrice,
  parseMenuPreorderPreview,
  preorderCartSignature,
  preorderCartTotalQuantity,
  preorderCartQuantity,
  type PreorderCartItem,
  updatePreorderCartItem,
} from "@/features/preorder/cart";
import {
  clearLocalPreorderCart,
  localCartSubmitItems,
  readLocalPreorderCart,
} from "@/features/preorder/local-cart";
import { getTableHold, refreshTableHold } from "@/features/table-booking/api";
import { parseTableHoldState } from "@/features/table-booking/state";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { reservationFormSchemaForCustomer, type ReservationFormValues } from "./schemas";

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

type MenuPreviewDraftState = {
  startTime: string;
  cartKey: string;
  payload: MenuPreorderPreview;
};

export function ReservationCreatePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const { isAuthenticated, profile } = useAuth();
  const branchSelection = useBranchSelection();
  const holdId = searchParams.get("hold_id");
  const holdStatus = searchParams.get("hold_status");
  const holdExpiresAt = parseHoldExpiresAt(searchParams.get("hold_expires_at"));
  const tableIds = parseTableIds(searchParams.get("tables"));
  const holdStartTime = parseHoldStartTime(searchParams.get("start_time"));
  const holdDurationMinutes = parsePositiveInteger(searchParams.get("duration_minutes"));
  const holdGuestCount = parsePositiveInteger(searchParams.get("guest_count"));
  const branchIdFromParams = parsePositiveInteger(searchParams.get("branch_id"));
  const selectedBranchId = branchIdFromParams ?? branchSelection.selectedBranch?.branchId ?? null;
  const [openedAtMs] = useState(() => Date.now());
  const form = useForm<ReservationFormValues>({
    resolver: zodResolver(reservationFormSchemaForCustomer(isAuthenticated)),
    defaultValues: {
      guest_name: isAuthenticated ? profile?.name ?? "" : "",
      guest_phone: isAuthenticated ? profile?.phone ?? "" : "",
      guest_email: isAuthenticated ? profile?.email ?? "" : "",
      branch_id: selectedBranchId ?? undefined,
      start_time: holdStartTime ?? createRoundedFutureLocalDateTimeInput(),
      duration_minutes: holdDurationMinutes ?? 90,
      guest_count: holdGuestCount ?? 2,
      notes: "",
    },
  });
  const preorderRollout = customerWebRollout.preorder;
  const [preorderCart, setPreorderCart] = useState<PreorderCartItem[]>([]);
  const [menuPreviewDraft, setMenuPreviewDraft] =
    useState<MenuPreviewDraftState | null>(null);
  const watchedGuestCount = useWatch({ control: form.control, name: "guest_count" });
  const watchedDurationMinutes = useWatch({ control: form.control, name: "duration_minutes" });
  const watchedStartTime = useWatch({ control: form.control, name: "start_time" });
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
  const preorderCartKey = preorderCartSignature(preorderCart);
  const preorderQuantity = preorderCartTotalQuantity(preorderCart);
  const preorderStartTime = liveHoldStartTime ?? watchedStartTime;
  const menuQuery = useQuery({
    queryKey: queryKeys.menu.items({ preorderOnly: true }),
    queryFn: () => listMenuItems({ preorderOnly: true }),
    enabled: preorderRollout.enabled,
  });
  const menuPreview =
    menuPreviewDraft?.startTime === preorderStartTime &&
    menuPreviewDraft.cartKey === preorderCartKey
      ? menuPreviewDraft.payload
      : null;
  const menuPreviewState = menuPreview
    ? parseMenuPreorderPreview(menuPreview)
    : null;
  const bookingSummaryItems = [
    { label: "Chi nhánh", value: branchSelection.selectedBranch?.branchName ?? (selectedBranchId ? `#${selectedBranchId}` : "Chưa chọn") },
    { label: "Ngày giờ", value: visitStartLabel },
    { label: "Số khách", value: `${holdGuestCount ?? watchedGuestCount} khách` },
    {
      label: "Thời lượng",
      value: `${liveHoldDurationMinutes ?? watchedDurationMinutes} phút`,
    },
    { label: "Bàn đã chọn", value: liveHoldTableLabel },
    {
      label: "Món đặt trước",
      value: preorderQuantity > 0 ? `${preorderQuantity} món` : "Chưa chọn",
    },
    ...(menuPreviewState?.subtotal
      ? [
          {
            label: "Tạm tính món",
            value: formatMoney(
              menuPreviewState.subtotal,
              menuPreviewState.currency ?? "VND",
            ),
          },
        ]
      : []),
  ];

  useEffect(() => {
    if (!selectedBranchId) {
      return;
    }

    if (form.getValues("branch_id") !== selectedBranchId) {
      form.setValue("branch_id", selectedBranchId, {
        shouldDirty: false,
        shouldValidate: true,
      });
    }
  }, [form, selectedBranchId]);

  useEffect(() => {
    if (!preorderRollout.enabled || !selectedBranchId || preorderCart.length > 0) {
      return;
    }

    const storedCart = readLocalPreorderCart(ensureCustomerSessionId(), selectedBranchId);
    const storedItems = localCartSubmitItems(storedCart);

    if (storedItems.length > 0) {
      startTransition(() => {
        setPreorderCart(storedItems);
      });
    }
  }, [preorderCart.length, preorderRollout.enabled, selectedBranchId]);

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

  useEffect(() => {
    if (!isAuthenticated || !profile) {
      return;
    }

    const fillContactField = (
      field: "guest_name" | "guest_phone" | "guest_email",
      value: string | null | undefined,
    ) => {
      const nextValue = value?.trim() ?? "";
      const currentValue = (form.getValues(field) ?? "").trim();
      const fieldState = form.getFieldState(field);

      if (nextValue === "" || (fieldState.isDirty && currentValue !== "")) {
        return;
      }

      form.setValue(field, nextValue, {
        shouldDirty: false,
        shouldValidate: false,
      });
    };

    fillContactField("guest_name", profile.name);
    fillContactField("guest_phone", profile.phone);
    fillContactField("guest_email", profile.email);
  }, [form, isAuthenticated, profile]);

  const refreshHoldMutation = useMutation({
    mutationFn: ({ holdId: liveHoldId, rowVersion }: { holdId: string; rowVersion: number }) => refreshTableHold(liveHoldId, rowVersion),
    onSuccess(result) {
      queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);
    },
  });
  const previewMenuMutation = useMutation({
    mutationFn: ({ startTime, items }: { startTime: string; items: PreorderCartItem[] }) =>
      previewMenuPreorder({
        start_time: toUtcIsoFromLocalDateTimeInput(startTime),
        pre_order_items: items,
      }),
    onSuccess(result, variables) {
      setMenuPreviewDraft({
        startTime: variables.startTime,
        cartKey: preorderCartSignature(variables.items),
        payload: result,
      });
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

  useEffect(() => {
    if (previewMenuMutation.isPending) {
      return;
    }

    previewMenuMutation.reset();
  }, [preorderCartKey, preorderStartTime, previewMenuMutation]);

  const handleUpdatePreorderItem = (itemId: number, rawQuantity: number) => {
    setPreorderCart((current) => updatePreorderCartItem(current, itemId, rawQuantity));
  };

  const handlePreviewPreorder = () => {
    if (!preorderStartTime) {
      form.setError("start_time", {
        message: "Chọn giờ đến để xem trước món đặt trước.",
      });
      return;
    }

    previewMenuMutation.mutate({
      startTime: preorderStartTime,
      items: preorderCart,
    });
  };

  const createMutation = useMutation({
    mutationFn: (input: { values: ReservationFormValues; preorderItems: PreorderCartItem[] }) =>
      createReservationWithPreorderDraft({
        reservationInput: {
          ...input.values,
          branch_id: input.values.branch_id ?? selectedBranchId ?? undefined,
          hold_id: holdId,
          table_ids: liveHoldTableIds,
        },
        preorderItems: input.preorderItems,
      }),
    onSuccess(result) {
      queryClient.setQueryData(
        queryKeys.reservations.detail(result.reservation.reservation_id),
        result.reservation,
      );
      if (result.preorder) {
        queryClient.setQueryData(
          queryKeys.reservations.preorder(result.reservation.reservation_id),
          result.preorder,
        );
      }
      void queryClient.invalidateQueries({
        queryKey: queryKeys.reservations.lists,
        refetchType: "inactive",
      });
      toast.success(
        result.preorder
          ? "Đã tạo lịch đặt và lưu món đặt trước."
          : "Đã tạo lịch đặt.",
      );
      if (selectedBranchId) {
        clearLocalPreorderCart(ensureCustomerSessionId(), selectedBranchId);
      }
      router.push(`/reservations/${result.reservation.reservation_id}`);
    },
    onError(error, variables) {
      if (!isReservationPreorderPersistenceError(error)) {
        return;
      }

      storePendingReservationPreorderDraft(
        error.reservation.reservation_id,
        variables.preorderItems,
        error.stage,
      );
      queryClient.setQueryData(
        queryKeys.reservations.detail(error.reservation.reservation_id),
        error.reservation,
      );
      void queryClient.invalidateQueries({
        queryKey: queryKeys.reservations.lists,
        refetchType: "inactive",
      });
      toast.error(
        "Đã tạo lịch đặt nhưng món đặt trước chưa lưu. Mở chi tiết để tiếp tục cập nhật.",
      );
      router.push(`/reservations/${error.reservation.reservation_id}`);
    },
  });
  const preorderPreviewRequired =
    preorderRollout.enabled && preorderQuantity > 0 && menuPreview === null;
  const createActionDisabled =
    createMutation.isPending ||
    refreshHoldMutation.isPending ||
    preorderPreviewRequired ||
    expiredHold ||
    holdQuery.isLoading ||
    Boolean(holdQuery.error);
  const createError =
    createMutation.error &&
    !isReservationPreorderPersistenceError(createMutation.error)
      ? createMutation.error
      : null;
  const submitReservation = (values: ReservationFormValues) => {
    if (createActionDisabled) {
      if (preorderPreviewRequired) {
        toast.error("Vui lòng xem trước món đặt trước trước khi tạo lịch đặt.");
      }

      return;
    }

    createMutation.mutate({
      values,
      preorderItems: preorderCart,
    });
  };

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6 pb-28 lg:pb-8">
      <section className="mb-5 space-y-3">
        <h1 className="text-4xl font-semibold tracking-normal">Xác nhận đặt bàn</h1>
        <p className="mt-2 text-muted-foreground">
          {isAuthenticated
            ? "Nhà hàng sẽ dùng thông tin từ tài khoản của bạn để xác nhận đặt bàn."
            : "Điền thông tin liên hệ để nhà hàng xác nhận đặt bàn."}
        </p>
        <BookingProgress currentStep="guest" />
        <div className="max-w-sm">
          <SelectedBranchEntry />
        </div>
      </section>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>{isAuthenticated ? "Thông tin tài khoản" : "Thông tin khách"}</CardTitle>
        </CardHeader>
        <CardContent>
          <form
            className="space-y-4"
            onSubmit={form.handleSubmit(submitReservation)}
          >
            {isAuthenticated ? (
              <Alert className="rounded-lg">
                <AlertDescription>
                  Đang dùng thông tin từ tài khoản{profile?.name ? ` ${profile.name}` : ""}. Bạn không cần nhập lại thông tin liên hệ để tạo lịch đặt.
                </AlertDescription>
              </Alert>
            ) : null}
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
            <div className="space-y-4 rounded-lg border p-4">
              <div>
                <h2 className="text-lg font-semibold">Món đặt trước</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Chọn món ngay trong lúc đặt bàn. Khi lịch đặt được tạo,
                  hệ thống sẽ xác nhận lại giỏ món rồi mới lưu vào reservation.
                </p>
              </div>
              {!preorderRollout.enabled ? (
                <EmptyState
                  title={preorderRollout.disabledTitle}
                  description={preorderRollout.disabledDescription}
                />
              ) : (
                <>
                  {menuQuery.isLoading ? (
                    <LoadingBlock label="Đang tải món có thể đặt trước" />
                  ) : null}
                  {menuQuery.error ? (
                    <ErrorState
                      error={menuQuery.error}
                      title="Chưa tải được danh sách món đặt trước"
                      onRetry={() => menuQuery.refetch()}
                    />
                  ) : null}
                  {menuQuery.data?.length === 0 ? (
                    <EmptyState
                      title="Chưa có món hỗ trợ đặt trước"
                      description="Danh sách món sẽ hiển thị khi nhà hàng bật đặt trước cho thực đơn."
                    />
                  ) : null}
                  {menuQuery.data?.length ? (
                    <div className="grid gap-3">
                      {menuQuery.data.map((item) => {
                        const quantity = preorderCartQuantity(
                          preorderCart,
                          item.item_id,
                        );
                        const itemAvailable =
                          item.is_available !== false &&
                          item.preorder.enabled !== false;

                        return (
                          <div
                            key={item.item_id}
                            className="grid gap-3 rounded-lg bg-secondary/40 p-3 sm:grid-cols-[1fr_120px] sm:items-center"
                          >
                            <div>
                              <p className="font-medium">{item.name}</p>
                              <p className="text-sm text-muted-foreground">
                                {menuItemPrice(item)}
                                {item.preorder.cutoff_minutes
                                  ? ` | Khóa trước ${item.preorder.cutoff_minutes} phút`
                                  : ""}
                                {item.preorder.quota_per_day
                                  ? ` | Tối đa ${item.preorder.quota_per_day}/ngày`
                                  : ""}
                                {!itemAvailable ? " | Tạm chưa khả dụng" : ""}
                              </p>
                            </div>
                            <div className="space-y-2">
                              <Label htmlFor={`reservation-preorder-qty-${item.item_id}`}>
                                Số lượng
                              </Label>
                              <Input
                                id={`reservation-preorder-qty-${item.item_id}`}
                                aria-label={`Số lượng ${item.name}`}
                                type="number"
                                min={0}
                                className="min-h-10 rounded-lg"
                                value={quantity}
                                disabled={!itemAvailable || previewMenuMutation.isPending || createMutation.isPending}
                                onChange={(event) =>
                                  handleUpdatePreorderItem(
                                    item.item_id,
                                    Number(event.target.value),
                                  )
                                }
                              />
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ) : null}
                  {preorderPreviewRequired ? (
                    <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                      Bạn đã thay đổi giỏ món. Vui lòng xem trước lại để
                      kiểm tra số lượng và tạm tính mới trước khi xác nhận.
                    </div>
                  ) : null}
                  {menuPreviewState ? (
                    <div className="rounded-lg bg-secondary p-4 text-sm">
                      <p className="font-medium">Bản xem trước món đặt trước</p>
                      <p className="mt-1 text-muted-foreground">
                        {menuPreviewState.quantity ?? preorderQuantity} món,
                        tạm tính{" "}
                        {formatMoney(
                          menuPreviewState.subtotal,
                          menuPreviewState.currency ?? "VND",
                        )}
                        .
                      </p>
                      {menuPreviewState.policyMessage ? (
                        <p className="mt-2 text-muted-foreground">
                          {menuPreviewState.policyMessage}
                        </p>
                      ) : null}
                      {menuPreviewState.warnings.length > 0 ? (
                        <div className="mt-2 space-y-1 text-muted-foreground">
                          {menuPreviewState.warnings.map((warning) => (
                            <p key={warning}>{warning}</p>
                          ))}
                        </div>
                      ) : null}
                    </div>
                  ) : null}
                  {previewMenuMutation.error ? (
                    <ErrorState
                      error={previewMenuMutation.error}
                      title="Chưa xem trước được món đặt trước"
                      onRetry={handlePreviewPreorder}
                    />
                  ) : null}
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                      {preorderQuantity > 0
                        ? `Đã chọn ${preorderQuantity} món đặt trước.`
                        : "Bạn có thể bỏ qua bước này và quay lại sau trong chi tiết lịch đặt."}
                    </p>
                    <Button
                      type="button"
                      variant="outline"
                      className="rounded-lg"
                      disabled={preorderQuantity === 0 || previewMenuMutation.isPending}
                      onClick={handlePreviewPreorder}
                    >
                      {previewMenuMutation.isPending
                        ? "Đang xem trước món"
                        : "Xem trước món"}
                    </Button>
                  </div>
                </>
              )}
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
            {createError ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(createError)}</AlertDescription>
              </Alert>
            ) : null}
            <Button
              type="submit"
              className="min-h-11 w-full rounded-lg"
              disabled={createActionDisabled}
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
        primaryActionDisabled={createActionDisabled}
        onPrimaryAction={() => form.handleSubmit(submitReservation)()}
      />
      </div>
    </main>
  );
}
