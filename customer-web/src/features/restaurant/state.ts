import type { RestaurantProfile } from "./api";

export const customerFooterContact = {
  name: "RestaurantPOS",
  tagline: "Bữa ăn gọn gàng, thân thiện và đúng hẹn.",
  address: "Trường Đại học Xây dựng Hà Nội",
  phone: "0961702575",
  phoneDisplay: "0961 702 575",
  email: "duongvinhdxv@gmail.com",
  facebookUrl: "https://www.facebook.com/duong.vinh.339875",
};

const dayLabels = ["Chủ nhật", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"];

export function googleMapsUrl(address = customerFooterContact.address): string {
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
}

export function googleMapsEmbedUrl(address = customerFooterContact.address): string {
  return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
}

export function formatDayLabel(dayOfWeek: number): string {
  return dayLabels[dayOfWeek] ?? "Ngày khác";
}

export function formatPeriods(periods: RestaurantProfile["today_hours"]["periods"] | undefined): string {
  if (!periods || periods.length === 0) {
    return "Đóng cửa";
  }

  return periods
    .map((period) => {
      if (period.start_time === "00:00" && period.end_time === "24:00") {
        return "Mở cả ngày";
      }

      return `${period.start_time}-${period.end_time}`;
    })
    .join(", ");
}

export function formatTimezone(timezone: string | null | undefined): string {
  if (timezone === "Asia/Ho_Chi_Minh") {
    return "Giờ Việt Nam";
  }

  return timezone ?? "Giờ nhà hàng";
}

export function weeklyHours(profile: RestaurantProfile | null | undefined): Array<{ day: string; hours: string }> {
  return (profile?.business_hours ?? []).map((day) => ({
    day: formatDayLabel(day.day_of_week),
    hours: formatPeriods(day.periods),
  }));
}

export function todayHoursLabel(profile: RestaurantProfile | null | undefined): string {
  if (!profile) {
    return "Đang tải giờ mở cửa";
  }

  return formatPeriods(profile.today_hours.periods);
}

export function openStatusLabel(profile: RestaurantProfile | null | undefined): string {
  if (!profile) {
    return "Đang tải";
  }

  return profile.current_status.is_open ? "Đang mở cửa" : "Đang đóng cửa";
}
