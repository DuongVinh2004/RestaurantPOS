import { beforeEach, describe, expect, it } from "vitest";
import { branchFromRestaurantProfile, clearSelectedBranchId, getSelectedBranchId, persistSelectedBranchId } from "./state";
import type { RestaurantProfile } from "@/features/restaurant/api";

function profile(overrides: Partial<RestaurantProfile> = {}): RestaurantProfile {
  return {
    branch_id: 7,
    branch_code: "MAIN",
    branch_name: "Main Branch",
    timezone: "Asia/Ho_Chi_Minh",
    business_hours: [
      {
        day_of_week: 1,
        periods: [{ start_time: "09:00", end_time: "22:00" }],
      },
    ],
    today_hours: {
      day_of_week: 1,
      periods: [{ start_time: "09:00", end_time: "22:00" }],
      is_closed: false,
    },
    current_status: {
      is_open: true,
      reason: null,
      checked_at_local: "2026-05-07 10:00:00",
      timezone: "Asia/Ho_Chi_Minh",
    },
    ...overrides,
  };
}

describe("branch state", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it("persists a valid selected branch id", () => {
    expect(getSelectedBranchId()).toBeNull();

    persistSelectedBranchId(7);

    expect(getSelectedBranchId()).toBe(7);

    clearSelectedBranchId();

    expect(getSelectedBranchId()).toBeNull();
  });

  it("normalizes the public restaurant profile into a customer branch", () => {
    const branch = branchFromRestaurantProfile(profile());

    expect(branch).toMatchObject({
      branchId: 7,
      branchCode: "MAIN",
      branchName: "Main Branch",
      isOpen: true,
      statusLabel: "Đang mở cửa",
      todayHoursLabel: "09:00 - 22:00",
    });
    expect(branch.weeklyHours).toEqual([{ day: "Thứ hai", hours: "09:00 - 22:00" }]);
    expect(branch.directionsUrl).toContain("google.com/maps");
  });
});
