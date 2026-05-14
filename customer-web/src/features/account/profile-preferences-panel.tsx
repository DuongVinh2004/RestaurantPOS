"use client";

import { Clock3, Leaf, MapPin, ShieldAlert, UsersRound } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { AppCard, AppInput, AppTextarea, SectionHeader, StatusPill } from "@/components/customer/ui";
import type { CustomerProfile } from "@/lib/auth/session";

const preferenceItems = [
  { label: "Ăn chay", icon: Leaf },
  { label: "Thuần chay", icon: Leaf },
  { label: "Không ăn cay", icon: ShieldAlert },
  { label: "Không ăn hải sản", icon: ShieldAlert },
  { label: "Không ăn hạt", icon: ShieldAlert },
];

export function ProfilePreferencesPanel({ profile }: { profile: CustomerProfile | null }) {
  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Hồ sơ"
          title="Thông tin và sở thích dùng bữa"
          description="Thông tin liên hệ đang dùng cho đặt bàn. Sở thích dùng bữa sẽ giúp nhà hàng phục vụ bạn tốt hơn khi được cập nhật."
          action={<StatusPill label="Chỉ xem" tone="info" />}
        />
        <div className="grid gap-3 sm:grid-cols-3">
          <ProfileField label="Tên" value={profile?.name ?? "Khách"} />
          <ProfileField label="Email" value={profile?.email ?? "Chưa cung cấp"} />
          <ProfileField label="Điện thoại" value={profile?.phone ?? "Chưa cung cấp"} />
        </div>
        <div className="grid gap-3 sm:grid-cols-3">
          <ReadonlyPreference icon={MapPin} label="Chi nhánh yêu thích" value="Chưa chọn" />
          <ReadonlyPreference icon={UsersRound} label="Số khách thường đi" value="Chưa chọn" />
          <ReadonlyPreference icon={Clock3} label="Giờ ăn thường chọn" value="Chưa chọn" />
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          {preferenceItems.map((item) => {
            const Icon = item.icon;

            return (
              <div key={item.label} className="rounded-lg border border-dashed bg-secondary/25 p-3">
                <div className="flex items-center gap-2">
                  <Icon className="h-4 w-4 text-primary" />
                  <p className="text-sm font-medium">{item.label}</p>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">Có thể bổ sung sau</p>
              </div>
            );
          })}
        </div>
        <AppTextarea
          label="Ghi chú dị ứng hoặc sở thích ăn uống"
          value=""
          disabled
          placeholder="Bạn có thể ghi chú dị ứng hoặc món cần tránh khi đặt bàn."
          helperText="Mục này hiện chỉ để xem, chưa lưu thay đổi từ trang tài khoản."
        />
      </div>
    </AppCard>
  );
}

function ProfileField({ label, value }: { label: string; value: string }) {
  return <AppInput label={label} value={value} disabled readOnly />;
}

function ReadonlyPreference({
  icon: Icon,
  label,
  value,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-lg bg-secondary/45 p-4">
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Icon className="h-4 w-4" />
        <span>{label}</span>
      </div>
      <p className="mt-2 font-medium">{value}</p>
    </div>
  );
}
