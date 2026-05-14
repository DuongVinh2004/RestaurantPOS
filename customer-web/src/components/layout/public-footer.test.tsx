import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PublicFooter } from "./public-footer";

const mocks = vi.hoisted(() => ({
  getRestaurantProfile: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("@/features/restaurant/api", () => ({
  getRestaurantProfile: mocks.getRestaurantProfile,
}));

function renderFooter(isAuthenticated = false) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <PublicFooter isAuthenticated={isAuthenticated} />
    </QueryClientProvider>,
  );
}

function profile() {
  return {
    branch_id: 1,
    branch_code: "MS-HK",
    branch_name: "Mộc Sen Bistro - Hoàn Kiếm",
    timezone: "Asia/Ho_Chi_Minh",
    business_hours: Array.from({ length: 7 }, (_, day) => ({
      day_of_week: day,
      periods: [{ start_time: "09:00", end_time: "22:00" }],
    })),
    today_hours: {
      day_of_week: 4,
      periods: [{ start_time: "09:00", end_time: "22:00" }],
      is_closed: false,
    },
    current_status: {
      is_open: true,
      reason: null,
      checked_at_local: "2026-04-30 10:15:00",
      timezone: "Asia/Ho_Chi_Minh",
    },
  };
}

describe("PublicFooter", () => {
  beforeEach(() => {
    mocks.getRestaurantProfile.mockReset();
    mocks.getRestaurantProfile.mockResolvedValue(profile());
  });

  it("renders restaurant contact, map, social links, and backend hours", async () => {
    renderFooter();

    expect(screen.getAllByText("Mộc Sen Bistro").length).toBeGreaterThan(0);
    expect(screen.getByText("24 Tràng Tiền, Hoàn Kiếm, Hà Nội")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /024 3824 5588/ })).toHaveAttribute("href", "tel:02438245588");
    expect(screen.getByRole("link", { name: /hello@mocsenbistro\.vn/ })).toHaveAttribute("href", "mailto:hello@mocsenbistro.vn");
    expect(screen.getByRole("link", { name: /Facebook/ })).toHaveAttribute("href", "https://www.facebook.com/mocsenbistro");
    expect(screen.getByTitle("Bản đồ Mộc Sen Bistro")).toHaveAttribute("src", expect.stringContaining("google.com/maps"));

    expect(await screen.findByText("Đang mở cửa")).toBeInTheDocument();
    expect(screen.getAllByText("09:00 - 22:00").length).toBeGreaterThan(0);
    expect(screen.getByText("Giờ Việt Nam")).toBeInTheDocument();

    const mapSection = screen.getByLabelText("Bản đồ và liên kết");
    expect(within(mapSection).getByRole("link", { name: "Đăng nhập" })).toHaveAttribute("href", "/login");
  });

  it("links authenticated customers back to their reservations", async () => {
    renderFooter(true);

    expect(await screen.findByText("Đang mở cửa")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Lịch đặt" })).toHaveAttribute("href", "/reservations");
  });
});
