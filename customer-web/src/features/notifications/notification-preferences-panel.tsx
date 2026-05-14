"use client";

import { BellRing, Mail, MessageSquare, Smartphone } from "lucide-react";
import { AppCard, SectionHeader, StatusPill } from "@/components/customer/ui";

const notificationStates = [
  {
    label: "Xác nhận lịch đặt",
    description: "Tin nhắn xác nhận và nhắc lịch đặt bàn.",
    icon: Mail,
  },
  {
    label: "Thông báo chờ bàn",
    description: "Tin nhắn được gọi bàn, nhắc lại và hết hạn phiếu chờ.",
    icon: BellRing,
  },
  {
    label: "Cập nhật thanh toán",
    description: "Trạng thái chờ thanh toán, thành công, thất bại và hoàn tiền.",
    icon: Smartphone,
  },
  {
    label: "Nhắc voucher",
    description: "Tin nhắn mã ưu đãi sắp hết hạn khi nhà hàng mở mục này.",
    icon: MessageSquare,
  },
];

export function NotificationPreferencesPanel() {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Thông báo"
          title="Tùy chọn nhận thông báo"
          description="Chọn cách nhận nhắc lịch và cập nhật từ nhà hàng. Các lựa chọn chỉnh sửa sẽ mở sau."
          action={<StatusPill label="Sắp ra mắt" tone="info" />}
        />
        <div className="grid gap-3 sm:grid-cols-2">
          {notificationStates.map((state) => {
            const Icon = state.icon;

            return (
              <div key={state.label} className="rounded-lg border border-dashed bg-secondary/25 p-4">
                <div className="flex items-start gap-3">
                  <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-background">
                    <Icon className="h-5 w-5 text-primary" />
                  </span>
                  <div>
                    <p className="font-medium">{state.label}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{state.description}</p>
                    <p className="mt-2 text-xs font-medium text-muted-foreground">Đang dùng theo thiết lập của nhà hàng</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </AppCard>
  );
}
