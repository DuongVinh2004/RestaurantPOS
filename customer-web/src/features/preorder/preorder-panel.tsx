"use client";

import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import { getPreorderPolicy } from "@/features/reservations/state";
import { getReservationPreorder } from "./api";

export function PreorderPanel({ reservationId }: { reservationId: number }) {
  const preorderRollout = customerWebRollout.preorder;
  const preorderQuery = useQuery({
    queryKey: queryKeys.reservations.preorder(reservationId),
    queryFn: () => getReservationPreorder(reservationId),
    enabled: preorderRollout.enabled,
  });
  const loadBoundary = preorderQuery.error ? getSelfServiceBlockedState("preorder", preorderQuery.error, "Chưa tải được món đặt trước") : null;
  const preorderPolicy = preorderQuery.data ? getPreorderPolicy(preorderQuery.data) : null;

  if (!preorderRollout.enabled) {
    return (
      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Món đặt trước</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState title={preorderRollout.disabledTitle} description={preorderRollout.disabledDescription} />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Món đặt trước</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {preorderQuery.isLoading ? <LoadingBlock label="Đang tải món đặt trước" /> : null}
        {loadBoundary ? (
          loadBoundary.kind === "error" ? (
            <ErrorState error={loadBoundary.error} title={loadBoundary.title} onRetry={() => preorderQuery.refetch()} />
          ) : (
            <EmptyState title={loadBoundary.title} description={loadBoundary.description} />
          )
        ) : null}
        {preorderQuery.data && preorderPolicy ? (
          <>
            <div className="rounded-lg bg-secondary p-4 text-sm">
              <p className="font-medium">{preorderPolicy.title}</p>
              <p className="mt-1 text-muted-foreground">{preorderPolicy.message}</p>
            </div>
            {preorderPolicy.hasPreorder ? (
              <>
                <div className="rounded-lg border p-4 text-sm">
                  <p className="font-medium">{preorderQuery.data.pre_order.totals.quantity} món đã ghi nhận</p>
                  <p className="mt-1 text-muted-foreground">
                    {formatMoney(preorderQuery.data.pre_order.totals.subtotal, preorderQuery.data.pre_order.currency)} cho khung giờ{" "}
                    {formatDateTime(preorderQuery.data.pre_order.service_time)}
                  </p>
                </div>
                <div className="rounded-lg border border-dashed bg-secondary/20 p-4 text-sm text-muted-foreground">
                  {preorderPolicy.managementMessage}
                </div>
              </>
            ) : (
              <EmptyState title={preorderPolicy.title} description={preorderPolicy.message} />
            )}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
