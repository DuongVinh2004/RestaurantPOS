"use client";

import { RefreshCw } from "lucide-react";
import { AppButton, AppCard, EmptyState, SectionHeader, StatusPill } from "@/components/customer/ui";
import { formatDateTime } from "@/lib/contracts/format";
import {
  getOrderTrackingState,
  isOrderTrackingStepComplete,
  orderTrackingSteps,
  type CustomerOrderTrackingState,
} from "./order-tracking";

export function OrderTrackingCard({
  activeOrder,
  isRefreshing,
  onRefresh,
}: {
  activeOrder: unknown;
  isRefreshing: boolean;
  onRefresh: () => void;
}) {
  const tracking = getOrderTrackingState(activeOrder);

  if (!tracking.present) {
    return (
      <EmptyState
        title="Chưa có đơn đang mở"
        description="Theo dõi món sẽ hiển thị sau khi nhà hàng mở đơn cho lịch đặt này."
      />
    );
  }

  return (
    <AppCard className="p-4">
      <div className="space-y-5">
        <SectionHeader
          eyebrow={tracking.orderId ? `Đơn #${tracking.orderId}` : "Đơn đang mở"}
          title={tracking.status === "cancelled" ? "Đơn đã hủy" : "Theo dõi món"}
          description={getOrderTrackingDescription(tracking)}
          action={
            <AppButton type="button" variant="outline" disabled={isRefreshing || tracking.terminal} onClick={onRefresh}>
              <RefreshCw className="h-4 w-4" />
              {isRefreshing ? "Đang cập nhật" : "Cập nhật"}
            </AppButton>
          }
        />

        <ol className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6" aria-label="Dòng thời gian trạng thái đơn">
          {orderTrackingSteps.map((step) => {
            const complete = isOrderTrackingStepComplete(tracking.status, step.status);
            const current = tracking.status === step.status;

            return (
              <li key={step.status} className="rounded-lg border bg-background p-3">
                <div className="flex items-center justify-between gap-2">
                  <span className="text-sm font-medium">{step.label}</span>
                  <StatusPill
                    label={current ? "Hiện tại" : complete ? "Xong" : "Tiếp theo"}
                    tone={current ? "info" : complete ? "success" : "neutral"}
                  />
                </div>
              </li>
            );
          })}
        </ol>

        <div className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-lg bg-secondary/45 p-4">
            <p className="text-sm text-muted-foreground">Trạng thái hiện tại</p>
            <p className="mt-1 font-semibold">{tracking.rawStatus ?? tracking.status}</p>
          </div>
          <div className="rounded-lg bg-secondary/45 p-4">
            <p className="text-sm text-muted-foreground">Thời gian còn lại dự kiến</p>
            <p className="mt-1 font-semibold">
              {tracking.estimatedRemainingMinutes !== null
                ? `${tracking.estimatedRemainingMinutes} phút`
                : "Chưa cung cấp"}
            </p>
          </div>
        </div>

        {tracking.createdAt ? (
          <p className="text-sm text-muted-foreground">Đã nhận lúc {formatDateTime(tracking.createdAt)}.</p>
        ) : null}

        {tracking.items.length > 0 ? (
          <div className="space-y-2">
            <h4 className="text-sm font-semibold">Món</h4>
            <div className="grid gap-2">
              {tracking.items.map((item, index) => (
                <div key={item.orderItemId ?? `${item.name}-${index}`} className="rounded-lg border p-3">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-medium">{item.name}</p>
                      <p className="text-sm text-muted-foreground">Số lượng {item.quantity ?? 1}</p>
                    </div>
                    <StatusPill label={item.rawStatus ?? item.status} tone={item.status === "cancelled" ? "danger" : "info"} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">API đơn hàng chưa cung cấp trạng thái theo từng món.</p>
        )}

        <div className="rounded-lg border border-dashed bg-secondary/30 p-4 text-sm text-muted-foreground">
          Chưa kết nối gọi nhân viên từ customer-web. Vui lòng hỏi nhân viên tại nhà hàng nếu bạn cần hỗ trợ.
        </div>
      </div>
    </AppCard>
  );
}

function getOrderTrackingDescription(tracking: CustomerOrderTrackingState): string {
  if (tracking.status === "cancelled") {
    return "Đơn này đã hủy. Hệ thống dừng cập nhật sau trạng thái cuối.";
  }

  if (tracking.terminal) {
    return "Đơn này đã đến trạng thái cuối. Hệ thống đã dừng cập nhật.";
  }

  return "Màn hình này cập nhật an toàn khi đơn vẫn đang xử lý.";
}
