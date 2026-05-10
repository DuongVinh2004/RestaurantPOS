"use client";

import type { RestaurantProfile } from "@/features/restaurant/api";
import { customerFooterContact, googleMapsUrl } from "@/features/restaurant/state";

const selectedBranchKey = "restaurantpos.customer.selected-branch.v1";

export type CustomerBranch = {
  branchId: number;
  branchCode: string;
  branchName: string;
  timezone: string;
  isOpen: boolean;
  statusReason: string | null;
  statusLabel: string;
  todayHoursLabel: string;
  weeklyHours: Array<{ day: string; hours: string }>;
  address: string;
  phone: string;
  phoneDisplay: string;
  directionsUrl: string;
};

const dayLabels = ["Chủ nhật", "Thứ hai", "Thứ ba", "Thứ tư", "Thứ năm", "Thứ sáu", "Thứ bảy"];

function browserStorage(): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

export function getSelectedBranchId(): number | null {
  const raw = browserStorage()?.getItem(selectedBranchKey);
  const parsed = raw ? Number(raw) : NaN;

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

export function persistSelectedBranchId(branchId: number): void {
  if (!Number.isInteger(branchId) || branchId <= 0) {
    return;
  }

  browserStorage()?.setItem(selectedBranchKey, String(branchId));
}

export function clearSelectedBranchId(): void {
  browserStorage()?.removeItem(selectedBranchKey);
}

export function branchFromRestaurantProfile(profile: RestaurantProfile): CustomerBranch {
  const address = customerFooterContact.address;

  return {
    branchId: profile.branch_id,
    branchCode: profile.branch_code,
    branchName: profile.branch_name,
    timezone: profile.current_status.timezone || profile.timezone,
    isOpen: profile.current_status.is_open,
    statusReason: profile.current_status.reason,
    statusLabel: profile.current_status.is_open ? "Đang mở cửa" : "Đang đóng cửa",
    todayHoursLabel: formatPeriods(profile.today_hours.periods),
    weeklyHours: profile.business_hours.map((day) => ({
      day: dayLabels[day.day_of_week] ?? "Ngày khác",
      hours: formatPeriods(day.periods),
    })),
    address,
    phone: customerFooterContact.phone,
    phoneDisplay: customerFooterContact.phoneDisplay,
    directionsUrl: googleMapsUrl(address),
  };
}

export function formatPeriods(periods: Array<{ start_time: string; end_time: string }> | undefined): string {
  if (!periods || periods.length === 0) {
    return "Đóng cửa";
  }

  return periods
    .map((period) => {
      if (period.start_time === "00:00" && period.end_time === "24:00") {
        return "Mở cả ngày";
      }

      return `${period.start_time} - ${period.end_time}`;
    })
    .join(", ");
}
