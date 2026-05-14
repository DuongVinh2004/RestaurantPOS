"use client";

import { Star } from "lucide-react";
import { AppCard, AppTextarea, SectionHeader, StatusPill } from "@/components/customer/ui";

const ratingRows = ["Tổng thể", "Món ăn", "Phục vụ", "Không gian"];

export function FeedbackPanel() {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Góp ý"
          title="Góp ý sau bữa ăn"
          description="Góp ý trực tuyến đang được chuẩn bị. Khi cần hỗ trợ ngay, bạn có thể trao đổi trực tiếp với nhân viên nhà hàng."
          action={<StatusPill label="Sắp ra mắt" tone="info" />}
        />
        <div className="grid gap-3 sm:grid-cols-2">
          {ratingRows.map((row) => (
            <div key={row} className="rounded-lg border border-dashed bg-secondary/25 p-4">
              <p className="font-medium">Đánh giá {row.toLowerCase()}</p>
              <div className="mt-3 flex gap-1" aria-label={`Chưa thể đánh giá ${row.toLowerCase()}`}>
                {[1, 2, 3, 4, 5].map((value) => (
                  <button
                    key={value}
                    type="button"
                    disabled
                    className="rounded-md p-1 text-muted-foreground opacity-70"
                    aria-label={`${value} sao`}
                  >
                    <Star className="h-5 w-5" />
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
        <AppTextarea
          label="Vấn đề hoặc nhận xét"
          value=""
          disabled
          placeholder="Góp ý của bạn sẽ giúp nhà hàng phục vụ tốt hơn."
          helperText="Mục này hiện chỉ để xem, chưa gửi nội dung từ trang tài khoản."
        />
      </div>
    </AppCard>
  );
}
