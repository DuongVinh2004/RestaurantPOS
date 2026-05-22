"use client";

import { startTransition, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { listMenuItems } from "@/features/menu/api";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import { getPreorderPolicy } from "@/features/reservations/state";
import { isConflictLikeApiError } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import {
  clearReservationPreorder,
  getReservationPreorder,
  previewReservationPreorder,
  replaceReservationPreorder,
  submitReservationPreorder,
  type ReservationPreorderResult,
} from "./api";
import {
  clearStoredPendingReservationPreorderDraft,
  getReservationPreorderRecoveryMessage,
  readStoredPendingReservationPreorderDraft,
  type StoredPendingReservationPreorderDraft,
} from "./reservation-draft-storage";
import {
  menuItemPrice,
  normalizePreorderCart,
  preorderCartFromReservation,
  preorderCartQuantity,
  preorderCartSignature,
  type PreorderCartItem,
  updatePreorderCartItem,
} from "./cart";

type DraftState = {
  snapshotKey: string;
  items: PreorderCartItem[];
};

type PreviewDraftState = {
  snapshotKey: string;
  cartKey: string;
  payload: ReservationPreorderResult;
};

export function PreorderPanel({ reservationId }: { reservationId: number }) {
  const queryClient = useQueryClient();
  const preorderRollout = customerWebRollout.preorder;
  const [cartDraft, setCartDraft] = useState<DraftState | null>(null);
  const [previewDraft, setPreviewDraft] = useState<PreviewDraftState | null>(null);
  const [pendingDraft, setPendingDraft] =
    useState<StoredPendingReservationPreorderDraft | null>(null);
  const [restoredDraftKey, setRestoredDraftKey] = useState<string | null>(null);
  const preorderQuery = useQuery({
    queryKey: queryKeys.reservations.preorder(reservationId),
    queryFn: () => getReservationPreorder(reservationId),
    enabled: preorderRollout.enabled,
  });
  const menuQuery = useQuery({
    queryKey: queryKeys.menu.items({ preorderOnly: true }),
    queryFn: () => listMenuItems({ preorderOnly: true }),
    enabled: preorderRollout.enabled,
  });

  const preorderSnapshotKey = preorderQuery.data
    ? `${preorderQuery.data.reservation_row_version}:${preorderQuery.data.pre_order.order_row_version ?? "none"}:${preorderQuery.data.pre_order.totals.quantity}`
    : "";
  const baseCart = preorderQuery.data
    ? preorderCartFromReservation(preorderQuery.data)
    : [];
  const cart = cartDraft?.snapshotKey === preorderSnapshotKey ? cartDraft.items : baseCart;
  const cartItems = normalizePreorderCart(cart);
  const baseCartKey = preorderCartSignature(baseCart);
  const cartKey = preorderCartSignature(cartItems);
  const previewResult =
    previewDraft?.snapshotKey === preorderSnapshotKey && previewDraft.cartKey === cartKey
      ? previewDraft.payload
      : null;

  const updateCartItem = (itemId: number, rawQuantity: number) => {
    setCartDraft({
      snapshotKey: preorderSnapshotKey,
      items: updatePreorderCartItem(cart, itemId, rawQuantity),
    });
  };

  const syncPreorder = async (payload: ReservationPreorderResult) => {
    queryClient.setQueryData(queryKeys.reservations.preorder(reservationId), payload);
    clearStoredPendingReservationPreorderDraft(reservationId);
    setCartDraft(null);
    setPendingDraft(null);
    setPreviewDraft(null);
    setRestoredDraftKey(null);
    await queryClient.invalidateQueries({
      queryKey: queryKeys.reservations.detail(reservationId),
      refetchType: "inactive",
    });
    await queryClient.invalidateQueries({
      queryKey: queryKeys.reservations.lists,
      refetchType: "inactive",
    });
  };

  const refreshPreorder = async () => {
    const refreshed = await preorderQuery.refetch();

    if (refreshed.data) {
      setCartDraft(null);
      setPreviewDraft(null);
    }
  };

  useEffect(() => {
    if (!preorderQuery.data) {
      return;
    }

    const stored = readStoredPendingReservationPreorderDraft(reservationId);

    if (!stored) {
      startTransition(() => {
        setPendingDraft(null);
      });
      return;
    }

    const storedItems = normalizePreorderCart(stored.items);
    const storedKey = preorderCartSignature(storedItems);

    if (storedKey === "" || storedKey === baseCartKey) {
      clearStoredPendingReservationPreorderDraft(reservationId);
      startTransition(() => {
        setPendingDraft(null);
        setRestoredDraftKey(null);
      });
      return;
    }

    const nextRestoredDraftKey = `${preorderSnapshotKey}:${storedKey}`;

    if (restoredDraftKey === nextRestoredDraftKey) {
      startTransition(() => {
        setPendingDraft(stored);
      });
      return;
    }

    startTransition(() => {
      setCartDraft({
        snapshotKey: preorderSnapshotKey,
        items: storedItems,
      });
      setPendingDraft(stored);
      setPreviewDraft(null);
      setRestoredDraftKey(nextRestoredDraftKey);
    });
  }, [
    baseCartKey,
    preorderQuery.data,
    preorderSnapshotKey,
    reservationId,
    restoredDraftKey,
  ]);

  const handleMutationError = async (error: unknown) => {
    if (isConflictLikeApiError(error)) {
      await refreshPreorder();
    }
  };

  const previewMutation = useMutation({
    mutationFn: ({ items }: { items: PreorderCartItem[]; snapshotKey: string; cartKey: string }) =>
      previewReservationPreorder(reservationId, { pre_order_items: items }),
    onSuccess(result, variables) {
      setPreviewDraft({
        snapshotKey: variables.snapshotKey,
        cartKey: variables.cartKey,
        payload: result,
      });
    },
    onError: handleMutationError,
  });
  const replaceMutation = useMutation({
    mutationFn: () => {
      if (!preorderQuery.data) {
        throw new Error("Chưa có phiên món đặt trước để cập nhật.");
      }

      return replaceReservationPreorder(reservationId, {
        pre_order_items: cartItems,
        row_version: preorderQuery.data.reservation_row_version,
        pre_order_row_version: preorderQuery.data.pre_order.order_row_version,
      });
    },
    onSuccess: syncPreorder,
    onError: handleMutationError,
  });
  const submitMutation = useMutation({
    mutationFn: () => {
      if (!preorderQuery.data) {
        throw new Error("Chưa có phiên món đặt trước để gửi.");
      }

      return submitReservationPreorder(
        reservationId,
        preorderQuery.data.reservation_row_version,
        preorderQuery.data.pre_order.order_row_version,
      );
    },
    onSuccess: syncPreorder,
    onError: handleMutationError,
  });
  const clearMutation = useMutation({
    mutationFn: () => {
      if (!preorderQuery.data) {
        throw new Error("Chưa có phiên món đặt trước để xóa.");
      }

      return clearReservationPreorder(
        reservationId,
        preorderQuery.data.reservation_row_version,
        preorderQuery.data.pre_order.order_row_version,
      );
    },
    onSuccess: syncPreorder,
    onError: handleMutationError,
  });

  const loadBoundary = preorderQuery.error
    ? getSelfServiceBlockedState("preorder", preorderQuery.error, "Chưa tải được món đặt trước")
    : null;
  const preorderPolicy = preorderQuery.data ? getPreorderPolicy(preorderQuery.data) : null;
  const actionError = previewMutation.error ?? replaceMutation.error ?? clearMutation.error;
  const actionBoundary = actionError
    ? getSelfServiceBlockedState(
        "preorder",
        actionError,
        isConflictLikeApiError(actionError)
          ? "Thông tin món đặt trước đã thay đổi"
          : "Chưa cập nhật được món đặt trước",
      )
    : null;
  const canManage = Boolean(preorderQuery.data?.management_policy.can_manage);
  const hasExistingPreorder = Boolean(preorderQuery.data?.pre_order.present);
  const isDraft = preorderQuery.data?.pre_order.order_status === "draft";
  const hasCartChanges = cartKey !== baseCartKey;
  const previewRequired = canManage && cartItems.length > 0 && hasCartChanges && !previewResult;
  const canRequestPreview = canManage && cartItems.length > 0 && hasCartChanges;
  const canReplace = canManage && cartItems.length > 0 && hasCartChanges && previewResult !== null;
  const canSubmit = canManage && hasExistingPreorder && !hasCartChanges && isDraft;
  const mutationPending = previewMutation.isPending || replaceMutation.isPending || submitMutation.isPending || clearMutation.isPending;
  const cartInputsDisabled = mutationPending || preorderQuery.isFetching || menuQuery.isFetching;
  const pendingDraftMessage = pendingDraft
    ? getReservationPreorderRecoveryMessage(pendingDraft.failure_stage)
    : null;

  if (!preorderRollout.enabled) {
    return null;
  }

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Món đặt trước</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {preorderQuery.isLoading ? <LoadingBlock label="Đang tải món đặt trước" /> : null}
        {menuQuery.isLoading ? <LoadingBlock label="Đang tải món có thể đặt trước" /> : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState
              error={loadBoundary.error}
              title={loadBoundary.title}
              onRetry={() => preorderQuery.refetch()}
            />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}
        {menuQuery.error ? (
          <ErrorState
            error={menuQuery.error}
            title="Chưa tải được danh sách món"
            onRetry={() => menuQuery.refetch()}
          />
        ) : null}
        {preorderQuery.data && preorderPolicy ? (
          <>
            <div className="rounded-lg border bg-secondary/30 p-4 text-sm">
              <p className="font-medium">Bạn có thể chọn món trước để Mộc Sen chuẩn bị nhanh hơn.</p>
              <p className="mt-1 text-muted-foreground">
                Món đặt trước có thể cần đặt cọc tùy theo giá trị đơn hoặc chính sách nhà hàng.
                Bạn có thể bỏ qua bước này và chọn món tại nhà hàng.
              </p>
            </div>
            <div className="rounded-lg bg-secondary p-4 text-sm">
              <p className="font-medium">{preorderPolicy.title}</p>
              <p className="mt-1 text-muted-foreground">{preorderPolicy.message}</p>
            </div>

            {preorderPolicy.hasPreorder ? (
              <div className="space-y-3 rounded-lg border p-4 text-sm">
                <div>
                  <p className="font-medium">
                    {preorderQuery.data.pre_order.totals.quantity} món đã ghi nhận
                  </p>
                  <p className="mt-1 text-muted-foreground">
                    {formatMoney(
                      preorderQuery.data.pre_order.totals.subtotal,
                      preorderQuery.data.pre_order.currency,
                    )}{" "}
                    cho khung giờ {formatDateTime(preorderQuery.data.pre_order.service_time)}
                  </p>
                </div>
                {isDraft ? (
                  <div className="rounded-lg border border-dashed bg-warning/20 p-4 text-sm text-warning-foreground">
                    Đây mới chỉ là bản nháp. Vui lòng nhấn "Gửi xác nhận đặt món" để nhà hàng nhận được yêu cầu.
                  </div>
                ) : null}
                {preorderQuery.data.pre_order.lines.length > 0 ? (
                  <div className="space-y-2">
                    {preorderQuery.data.pre_order.lines.map((line) => (
                      <div
                        key={line.order_item_id}
                        className="rounded-lg bg-secondary/30 p-3"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="font-medium">{line.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {line.quantity} phần
                              {line.notes ? ` | Ghi chú: ${line.notes}` : ""}
                            </p>
                          </div>
                          <p className="text-sm font-medium">
                            {formatMoney(line.line_total, line.currency)}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : null}
                <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                  {preorderPolicy.managementMessage}
                </div>
              </div>
            ) : (
              <EmptyState title={preorderPolicy.title} description={preorderPolicy.message} />
            )}

            {canManage ? (
              <div className="space-y-4 rounded-lg border p-4">
                <div>
                  <h3 className="font-semibold">Chọn món đặt trước</h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Xem trước giỏ món trước khi lưu để Mộc Sen kiểm tra số lượng, thời gian chuẩn bị và chính sách đặt cọc nếu có.
                  </p>
                </div>
                {pendingDraftMessage ? (
                  <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                    {pendingDraftMessage}
                  </div>
                ) : null}
                {menuQuery.data?.length === 0 ? (
                  <EmptyState
                    title="Chưa có món hỗ trợ đặt trước"
                    description="Danh sách món sẽ hiển thị khi nhà hàng bật đặt trước cho thực đơn."
                  />
                ) : null}
                <div className="grid gap-3">
                  {menuQuery.data?.map((item) => {
                    const quantity = preorderCartQuantity(cart, item.item_id);
                    const itemAvailable = item.is_available !== false && item.preorder.enabled !== false;

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
                          <Label htmlFor={`preorder-qty-${item.item_id}`}>Số lượng</Label>
                          <Input
                            id={`preorder-qty-${item.item_id}`}
                            aria-label={`Số lượng ${item.name}`}
                            type="number"
                            min={0}
                            className="min-h-10 rounded-lg"
                            value={quantity}
                            disabled={!itemAvailable || cartInputsDisabled}
                            onChange={(event) =>
                              updateCartItem(item.item_id, Number(event.target.value))
                            }
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
                {previewRequired ? (
                  <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                    Bạn đã thay đổi giỏ món. Vui lòng xem trước lại để kiểm tra số lượng và tổng tiền
                    mới trước khi cập nhật món đặt trước.
                  </div>
                ) : null}
                {previewResult ? (
                  <div className="rounded-lg bg-secondary p-4 text-sm">
                    <p className="font-medium">Bản xem trước</p>
                    <p className="mt-1 text-muted-foreground">
                      {previewResult.pre_order.totals.quantity} món, tạm tính{" "}
                      {formatMoney(
                        previewResult.pre_order.totals.subtotal,
                        previewResult.pre_order.currency,
                      )}
                      .
                    </p>
                  </div>
                ) : null}
                {actionBoundary ? (
                  actionBoundary.kind === "error" ? (
                    <ErrorState
                      error={actionBoundary.error}
                      title={actionBoundary.title}
                      onRetry={refreshPreorder}
                    />
                  ) : (
                    <EmptyState
                      title={actionBoundary.title}
                      description={actionBoundary.description}
                    />
                  )
                ) : null}
                <div className="grid gap-3 sm:grid-cols-3">
                  <Button
                    type="button"
                    variant="outline"
                    className="rounded-lg"
                    disabled={!canRequestPreview || mutationPending}
                    onClick={() => previewMutation.mutate({
                      items: cartItems,
                      snapshotKey: preorderSnapshotKey,
                      cartKey,
                    })}
                  >
                    {previewMutation.isPending ? "Đang xem trước" : "Xem trước món"}
                  </Button>
                  <Button
                    type="button"
                    className="rounded-lg"
                    disabled={!canReplace || mutationPending}
                    onClick={() => replaceMutation.mutate()}
                  >
                    {replaceMutation.isPending ? "Đang cập nhật" : "Cập nhật món đặt trước"}
                  </Button>
                  {canSubmit ? (
                    <Button
                      type="button"
                      className="rounded-lg bg-green-600 hover:bg-green-700 text-white"
                      disabled={mutationPending}
                      onClick={() => submitMutation.mutate()}
                    >
                      {submitMutation.isPending ? "Đang gửi" : "Gửi xác nhận đặt món"}
                    </Button>
                  ) : null}
                  <Button
                    type="button"
                    variant="outline"
                    className="rounded-lg"
                    disabled={!hasExistingPreorder || mutationPending}
                    onClick={() => clearMutation.mutate()}
                  >
                    {clearMutation.isPending ? "Đang xóa" : "Xóa món đặt trước"}
                  </Button>
                </div>
              </div>
            ) : !hasExistingPreorder && preorderQuery.data.management_policy.reasons.length > 0 ? (
              <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                {preorderQuery.data.management_policy.reasons.join(". ")}
              </div>
            ) : null}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
