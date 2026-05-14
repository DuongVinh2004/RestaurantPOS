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
  getDepositPaymentSession: vi.fn(),
  refreshDepositPaymentSession: vi.fn(),
  confirmDepositPaymentSession: vi.fn(),
}));

vi.mock("./api", () => ({
  getDepositPreview: mocks.getDepositPreview,
  acknowledgeDeposit: mocks.acknowledgeDeposit,
  submitDepositIntent: mocks.submitDepositIntent,
  revokeDepositIntent: mocks.revokeDepositIntent,
  createDepositPaymentSession: mocks.createDepositPaymentSession,
  getDepositPaymentSession: mocks.getDepositPaymentSession,
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

function createPaymentSessionEnvelope(
  rowVersion: number,
  sessionStatus = "Pending",
  overrides: Record<string, unknown> = {},
) {
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
    window.sessionStorage.clear();

    mocks.getDepositPreview.mockReset();
    mocks.acknowledgeDeposit.mockReset();
    mocks.submitDepositIntent.mockReset();
    mocks.revokeDepositIntent.mockReset();
    mocks.createDepositPaymentSession.mockReset();
    mocks.getDepositPaymentSession.mockReset();
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

  it("restores a stored payment session through the canonical show route", async () => {
    window.sessionStorage.setItem("restaurantpos.customer.session-id.v1", "browser-session-1");
    window.sessionStorage.setItem(
      "restaurantpos.customer.payment-session.v1.deposit.7",
      JSON.stringify({
        surface: "deposit",
        reservation_id: 7,
        session_id: 301,
        browser_session_id: "browser-session-1",
      }),
    );
    mocks.getDepositPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(15));

    renderPanel();

    await waitFor(() => {
      expect(mocks.getDepositPaymentSession).toHaveBeenCalledWith(7, 301);
    });
    expect(await screen.findByText("dep-1")).toBeInTheDocument();
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
    expect((await screen.findAllByText("Đã ghi nhận thanh toán")).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Đã ghi nhận")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Xác nhận thanh toán" })).not.toBeInTheDocument();
  });

  it("does not allow creating a second deposit payment session while restoring one", async () => {
    window.sessionStorage.setItem("restaurantpos.customer.session-id.v1", "browser-session-1");
    window.sessionStorage.setItem(
      "restaurantpos.customer.payment-session.v1.deposit.7",
      JSON.stringify({
        surface: "deposit",
        reservation_id: 7,
        session_id: 301,
        browser_session_id: "browser-session-1",
      }),
    );
    mocks.getDepositPaymentSession.mockReturnValue(new Promise(() => {}));

    renderPanel();

    expect(await screen.findByRole("button", { name: "Thanh toán đặt cọc" })).toBeDisabled();
    expect(mocks.createDepositPaymentSession).not.toHaveBeenCalled();
  });

  it("prevents deposit preview actions from running at the same time", async () => {
    const user = userEvent.setup();

    mocks.getDepositPreview.mockResolvedValue({
      reservation: createReservation(),
      deposit: {
        status: "Pending",
        outstanding_amount: "25.00",
        currency: "USD",
        self_service: {
          can_acknowledge: true,
          can_submit_intent: true,
          can_revoke_intent: false,
          can_create_payment_session: false,
        },
      },
    });
    mocks.acknowledgeDeposit.mockReturnValue(new Promise(() => {}));

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Tôi đã hiểu yêu cầu đặt cọc" }));

    expect(screen.getByRole("button", { name: "Tôi sẽ tự thanh toán" })).toBeDisabled();
    expect(mocks.submitDepositIntent).not.toHaveBeenCalled();
  });

  it("falls back to the reservation prop row version when deposit preview omits reservation data", async () => {
    const user = userEvent.setup();

    mocks.getDepositPreview.mockResolvedValue({
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
    mocks.createDepositPaymentSession.mockResolvedValue(
      createPaymentSessionEnvelope(11),
    );

    renderPanel();

    await user.click(
      await screen.findByRole("button", { name: "Thanh toán đặt cọc" }),
    );

    await waitFor(() => {
      expect(mocks.createDepositPaymentSession).toHaveBeenCalledWith(7, 4);
    });
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

    expect(screen.getByText("Thông tin đặt bàn vừa được cập nhật. Vui lòng tải lại trạng thái mới nhất trước khi tiếp tục.")).toBeInTheDocument();
    expect(screen.getByText("Thanh toán cọc chưa thành công. Đặt bàn của bạn vẫn được giữ. Món đặt trước chưa được xác nhận chuẩn bị.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Thanh toán lại" })).toBeInTheDocument();
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
    expect(screen.getByText("Khoản đặt cọc đã được ghi nhận cho lịch đặt này.")).toBeInTheDocument();
  });
});
