import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { ReservationDetailPage } from "./reservation-detail-page";

const mocks = vi.hoisted(() => ({
  getReservation: vi.fn(),
  cancelReservation: vi.fn(),
  rescheduleReservation: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

vi.mock("@/features/deposit/deposit-panel", () => ({
  DepositPanel: () => <div data-testid="deposit-panel" />,
}));

vi.mock("@/features/billing/billing-panel", () => ({
  BillingPanel: () => <div data-testid="billing-panel" />,
}));

vi.mock("@/features/preorder/preorder-panel", () => ({
  PreorderPanel: () => <div data-testid="preorder-panel" />,
}));

vi.mock("@/features/vouchers/benefits-panel", () => ({
  BenefitsPanel: () => <div data-testid="benefits-panel" />,
}));

vi.mock("./api", () => ({
  getReservation: mocks.getReservation,
  cancelReservation: mocks.cancelReservation,
  rescheduleReservation: mocks.rescheduleReservation,
}));

function createReservation(overrides: Partial<ReservationSummary> = {}): ReservationSummary {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    access_scope: "owner",
    status: "Confirmed",
    row_version: 4,
    guest_count: 4,
    start_time: "2026-04-18T11:30:00Z",
    end_time: "2026-04-18T13:00:00Z",
    deposit_status: "Pending",
    final_bill_amount: "0.00",
    bill_currency: "USD",
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
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ReservationDetailPage id={7} />
    </QueryClientProvider>,
  );
}

describe("ReservationDetailPage", () => {
  beforeEach(() => {
    mocks.getReservation.mockReset();
    mocks.cancelReservation.mockReset();
    mocks.rescheduleReservation.mockReset();
  });

  it("renders the reservation workspace with a dedicated hold section", async () => {
    mocks.getReservation.mockResolvedValue(
      createReservation({
        hold_summary: {
          current: {
            hold_id: "hold-123",
            status: "Expired",
            expires_at: "2020-04-18T10:00:00Z",
            table_ids: [7, 8],
          },
        },
      }),
    );

    renderPage();

    expect(await screen.findByText("Chi tiết lượt ghé")).toBeInTheDocument();
    expect(screen.getAllByText("Bàn giữ")[0]).toBeInTheDocument();
    expect(screen.getAllByText("Bàn giữ đã hết hạn")[0]).toBeInTheDocument();
  });

  it("shows a no-longer-manageable state when the reservation is inactive", async () => {
    mocks.getReservation.mockResolvedValue(
      createReservation({
        status: "Cancelled",
      }),
    );

    renderPage();

    expect(await screen.findByText("Không còn thao tác trực tuyến")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Yêu cầu đổi giờ" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Hủy lịch đặt" })).not.toBeInTheDocument();
  });

  it("shows when reservation access is coming from a linked session", async () => {
    mocks.getReservation.mockResolvedValue(
      createReservation({
        access_scope: "session",
      }),
    );

    renderPage();

    expect(await screen.findByText("Phiên ghé nhà hàng đã liên kết")).toBeInTheDocument();
    expect(screen.getByText("Bạn đang xem lịch đặt qua phiên ghé nhà hàng trên trình duyệt này.")).toBeInTheDocument();
  });

  it("refetches reservation detail after a stale conflict and keeps the refresh guidance visible", async () => {
    const user = userEvent.setup();

    mocks.getReservation.mockResolvedValue(createReservation());
    mocks.cancelReservation.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "The reservation was updated elsewhere.",
      errorCode: "row_version_conflict",
      categoryCode: "validation_error",
      requestId: "req-reservation-conflict",
      validationErrors: null,
    });

    renderPage();

    await screen.findByRole("button", { name: "Hủy lịch đặt" });
    await user.click(screen.getByRole("button", { name: "Hủy lịch đặt" }));
    await user.click(await screen.findByRole("button", { name: "Hủy lịch đặt" }));

    await waitFor(() => {
      expect(mocks.cancelReservation).toHaveBeenCalledWith(7, 4, "");
    });
    await waitFor(() => {
      expect(mocks.getReservation).toHaveBeenCalledTimes(2);
    });

    expect(await screen.findByText("Thông tin lịch đặt đã thay đổi")).toBeInTheDocument();
    expect(screen.getByText(/Thông tin đã thay đổi trong lúc bạn thao tác/i)).toBeInTheDocument();
  });

  it("prevents cancel and reschedule from running at the same time", async () => {
    const user = userEvent.setup();

    mocks.getReservation.mockResolvedValue(createReservation());
    mocks.rescheduleReservation.mockReturnValue(new Promise(() => {}));

    renderPage();

    await user.click(await screen.findByRole("button", { name: "Yêu cầu đổi giờ" }));

    expect(screen.getByRole("button", { name: "Hủy lịch đặt" })).toBeDisabled();
    expect(mocks.cancelReservation).not.toHaveBeenCalled();
  });

  it("labels reservation access errors as unavailable to the signed-in account", async () => {
    mocks.getReservation.mockRejectedValue({
      kind: "not_found",
      status: 404,
      message: "Reservation data was not found.",
      errorCode: "not_found",
      categoryCode: "not_found",
      requestId: "req-reservation-not-found",
      validationErrors: null,
    });

    renderPage();

    expect(await screen.findByText("Lịch đặt không khả dụng")).toBeInTheDocument();
    expect(screen.getByText("Không tìm thấy lịch đặt hoặc lịch đặt không còn mở cho khách tự thao tác.")).toBeInTheDocument();
  });
});
