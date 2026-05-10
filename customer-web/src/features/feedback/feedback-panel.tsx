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
          description="Chưa thể gửi góp ý vì API đánh giá của khách hàng chưa được mở."
          action={<StatusPill label="Cần API" tone="warning" />}
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
          placeholder="Gửi góp ý sẽ khả dụng sau khi backend mở endpoint theo quyền sở hữu khách hàng."
          helperText="Form này đang chỉ đọc, nên không thu thập hoặc lưu góp ý riêng tư trong trình duyệt."
        />
      </div>
    </AppCard>
  );
}
