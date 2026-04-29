import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { DepositPanel } from "./deposit-panel";

const mocks = vi.hoisted(() => ({
  getDepositPreview: vi.fn(),
  acknowledgeDeposit: vi.fn(),
  submitDepositIntent: vi.fn(),
  revokeDepositIntent: vi.fn(),
  createDepositPaymentSession: vi.fn(),
  refreshDepositPaymentSession: vi.fn(),
  confirmDepositPaymentSession: vi.fn(),
}));

vi.mock("./api", () => ({
  getDepositPreview: mocks.getDepositPreview,
  acknowledgeDeposit: mocks.acknowledgeDeposit,
  submitDepositIntent: mocks.submitDepositIntent,
  revokeDepositIntent: mocks.revokeDepositIntent,
  createDepositPaymentSession: mocks.createDepositPaymentSession,
  refreshDepositPaymentSession: mocks.refreshDepositPaymentSession,
  confirmDepositPaymentSession: mocks.confirmDepositPaymentSession,
}));

function createReservation(overrides: Partial<ReservationSummary> = {}): ReservationSummary {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    status: "Confirmed",
    row_version: 4,
    deposit_status: "Pending",
    deposit_required_amount: "25.00",
    deposit_paid_amount: "0.00",
    bill_currency: "USD",
    ...overrides,
  };
}

function createPaymentSessionEnvelope(rowVersion: number, sessionStatus = "Pending", overrides: Record<string, unknown> = {}) {
  const terminal = sessionStatus === "Succeeded";

  return {
    reservation_id: 7,
    deposit: {
      status: "Pending",
      outstanding_amount: "25.00",
      currency: "USD",
    },
    payment_session: {
      deposit_payment_session_id: 301,
      reservation_id: 7,
      provider_code: "mockpay",
      provider_session_code: "dep-1",
      amount: "25.00",
      currency: "USD",
      session_status: sessionStatus,
      settlement_status: terminal ? "Succeeded" : "Pending",
      provider_expires_at: "2026-05-20T19:00:00Z",
      confirmed_at: terminal ? "2026-04-18T18:15:00Z" : null,
      row_version: rowVersion,
      created_at: "2026-04-18T18:00:00Z",
      updated_at: "2026-04-18T18:00:15Z",
      ...overrides,
    },
  };
}

function renderPanel() {
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
      <DepositPanel reservation={createReservation()} />
    </QueryClientProvider>,
  );
}

describe("DepositPanel", () => {
  beforeEach(() => {
    mocks.getDepositPreview.mockReset();
    mocks.acknowledgeDeposit.mockReset();
    mocks.submitDepositIntent.mockReset();
    mocks.revokeDepositIntent.mockReset();
    mocks.createDepositPaymentSession.mockReset();
    mocks.refreshDepositPaymentSession.mockReset();
    mocks.confirmDepositPaymentSession.mockReset();

    mocks.getDepositPreview.mockResolvedValue({
      reservation: createReservation(),
      deposit: {
        status: "Pending",
        outstanding_amount: "25.00",
        currency: "USD",
        self_service: {
          can_acknowledge: false,
          can_submit_intent: false,
          can_revoke_intent: false,
          can_create_payment_session: true,
        },
      },
    });
  });
  it("renders deposit payment session details and refreshes using the payment session row version", async () => {
    const user = userEvent.setup();

    mocks.createDepositPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(11));
    mocks.refreshDepositPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(12));
    mocks.confirmDepositPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(12, "Succeeded"));

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Thanh toán đặt cọc" }));

    await waitFor(() => {
      expect(mocks.createDepositPaymentSession).toHaveBeenCalledWith(7, 4);
    });
    expect(await screen.findByText("dep-1")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cập nhật trạng thái" }));

    await waitFor(() => {
      expect(mocks.refreshDepositPaymentSession).toHaveBeenCalledWith(7, 301, 11);
    });

    await user.click(await screen.findByRole("button", { name: "Xác nhận thanh toán" }));

    await waitFor(() => {
      expect(mocks.confirmDepositPaymentSession).toHaveBeenCalledWith(7, 301, 12);
    });
    expect(await screen.findByText("Đã ghi nhận thanh toán")).toBeInTheDocument();
    expect(screen.getByText("Đã ghi nhận")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Xác nhận thanh toán" })).not.toBeInTheDocument();
  });

  it("shows refresh guidance when a deposit mutation hits a stale row version", async () => {
    const user = userEvent.setup();

    mocks.createDepositPaymentSession.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Validation error.",
      errorCode: "stale_row_version",
      categoryCode: "stale_write",
      requestId: "req-deposit-stale",
      validationErrors: {
        row_version: ["Changed."],
      },
    });

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Thanh toán đặt cọc" }));

    expect(await screen.findByText("Thông tin đặt cọc đã thay đổi")).toBeInTheDocument();
    expect(screen.getByText("Thông tin đã thay đổi trong lúc bạn thao tác.")).toBeInTheDocument();
    expect(screen.getByText("Tải lại trang để lấy thông tin mới nhất rồi thử lại.")).toBeInTheDocument();
  });

  it("renders a settled deposit state when no further customer action is open", async () => {
    mocks.getDepositPreview.mockResolvedValue({
      reservation: createReservation({
        deposit_status: "Paid",
        deposit_paid_amount: "25.00",
      }),
      deposit: {
        status: "Paid",
        outstanding_amount: "0.00",
        currency: "USD",
        self_service: {
          can_acknowledge: false,
          can_submit_intent: false,
          can_revoke_intent: false,
          can_create_payment_session: false,
        },
      },
    });

    renderPanel();

    expect(await screen.findAllByText("Đặt cọc đã xử lý")).toHaveLength(2);
    expect(screen.getByText("Khoản đặt cọc đã được xử lý cho lịch đặt này.")).toBeInTheDocument();
  });
});
