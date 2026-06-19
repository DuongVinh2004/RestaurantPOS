"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getSelfServiceBlockedState } from "@/features/reservations/self-service-boundary";
import { getPreorderPolicy, getReservationDepositSummaryState } from "@/features/reservations/state";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatMoney } from "@/lib/contracts/format";
import { getReservationPreorder } from "./api";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";

export function PreorderPanel({ reservation }: { reservation: ReservationSummary }) {
  const preorderRollout = customerWebRollout.preorder;
  const preorderQuery = useQuery({
    queryKey: queryKeys.reservations.preorder(reservation.reservation_id),
    queryFn: () => getReservationPreorder(reservation.reservation_id),
    enabled: preorderRollout.enabled,
  });

  if (!preorderRollout.enabled) return null;

  const loadBoundary = preorderQuery.error
    ? getSelfServiceBlockedState("preorder", preorderQuery.error, "Chưa tải được món đặt trước")
    : null;
    
  const preorderPolicy = preorderQuery.data ? getPreorderPolicy(preorderQuery.data) : null;
  const depositSummary = getReservationDepositSummaryState(reservation);
  const isDepositPaid = depositSummary.state === "paid";
  
  // Disable changes if deposit is paid OR management policy restricts it.
  const canManage = Boolean(preorderQuery.data?.management_policy.can_manage) && !isDepositPaid;

  return (
    <Card className="rounded-lg" data-testid="customer-preorder-section">
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
        <CardTitle>Món đặt trước</CardTitle>
        {canManage && (
          <Button asChild variant="outline" size="sm" className="h-8">
            <Link href="/menu">
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              Thêm món
            </Link>
          </Button>
        )}
      </CardHeader>
      <CardContent>
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
            {preorderPolicy.hasPreorder && preorderQuery.data.pre_order.lines.length > 0 ? (
              <div className="space-y-4 text-sm">
                <div className="rounded-lg bg-secondary/30 p-3">
                  <p className="font-medium">{preorderQuery.data.pre_order.totals.quantity} món đã chọn</p>
                  <p className="text-muted-foreground">Tạm tính: {formatMoney(preorderQuery.data.pre_order.totals.subtotal, preorderQuery.data.pre_order.currency)}</p>
                </div>
                
                <div className="space-y-3">
                  {preorderQuery.data.pre_order.lines.map((line) => (
                    <div key={line.order_item_id} className="flex items-start justify-between gap-3 border-b border-secondary pb-3 last:border-0 last:pb-0">
                      <div className="flex items-start gap-3">
                        {/* @ts-expect-error backend dynamic field */}
                        {line.img_url ? (
                          <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-md bg-secondary">
                            {/* @ts-expect-error backend dynamic field */}
                            <img src={line.img_url} alt={line.name} className="h-full w-full object-cover" />
                          </div>
                        ) : (
                          <div className="h-14 w-14 flex-shrink-0 rounded-md bg-secondary/50 flex items-center justify-center">
                            <span className="text-[10px] uppercase text-muted-foreground">No img</span>
                          </div>
                        )}
                        <div>
                          <p className="font-medium text-foreground line-clamp-2">{line.name}</p>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            {line.quantity} phần {line.notes ? `| Ghi chú: ${line.notes}` : ""}
                          </p>
                        </div>
                      </div>
                      <div className="text-right shrink-0">
                        <p className="text-sm font-medium">{formatMoney(line.line_total, line.currency)}</p>
                        {line.quantity > 1 && (
                          <p className="text-xs text-muted-foreground mt-0.5">
                            {formatMoney(line.unit_price, line.currency)} / phần
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
                
                {!canManage && isDepositPaid && (
                  <p className="text-xs text-muted-foreground italic mt-2">
                    * Lịch đặt đã thanh toán cọc nên không thể thay đổi món đặt trước lúc này.
                  </p>
                )}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-4 text-center">
                <p className="text-sm text-muted-foreground">Chưa có món đặt trước</p>
                {canManage && (
                  <Button asChild variant="link" className="mt-1 h-auto p-0">
                    <Link href="/menu">Xem thực đơn ngay</Link>
                  </Button>
                )}
              </div>
            )}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
