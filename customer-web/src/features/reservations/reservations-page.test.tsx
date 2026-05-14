import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { ReservationsPage } from "./reservations-page";

const mocks = vi.hoisted(() => ({
  listReservations: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

vi.mock("./api", () => ({
  listReservations: mocks.listReservations,
}));

function createReservation(overrides: Partial<ReservationSummary> = {}): ReservationSummary {
  return {
    reservation_id: 501,
    reservation_code: "RSV-501",
    access_scope: "owner",
    status: "Confirmed",
    row_version: 3,
    guest_count: 2,
    start_time: "2026-05-07T11:30:00Z",
    end_time: "2026-05-07T13:00:00Z",
    deposit_status: "NotRequired",
    final_bill_amount: "0.00",
    bill_currency: "VND",
    customer_self_service: {
      can_attempt_cancel: true,
      can_attempt_reschedule: true,
    },
    ...overrides,
  };
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ReservationsPage />
    </QueryClientProvider>,
  );
}

describe("ReservationsPage", () => {
  beforeEach(() => {
    mocks.listReservations.mockReset();
  });

  it("renders the reservation list workspace for upcoming visits", async () => {
    mocks.listReservations.mockResolvedValue([createReservation()]);

    renderPage();

    expect(await screen.findByRole("heading", { name: "Lịch đặt" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Đặt bàn mới" })).toHaveAttribute("href", "/reservations/new");
    expect(await screen.findByText("Lượt ghé nổi bật")).toBeInTheDocument();
    expect(screen.getByText("Tổng khách từ dữ liệu hiện có")).toBeInTheDocument();
    const detailLinks = await screen.findAllByRole("link", { name: "Mở chi tiết" });
    expect(detailLinks.map((link) => link.getAttribute("href"))).toContain("/reservations/501");
  });

  it("fails closed with the booking CTA when there are no reservations yet", async () => {
    mocks.listReservations.mockResolvedValue([]);

    renderPage();

    expect(await screen.findByText("Chưa có lịch đặt")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Tìm bàn" })).toHaveAttribute("href", "/booking");
  });
});
