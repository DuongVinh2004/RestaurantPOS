import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { BillingPanel } from "./billing-panel";

const mocks = vi.hoisted(() => ({
  getActiveOrder: vi.fn(),
  getBillPreview: vi.fn(),
  getBill: vi.fn(),
  createBillPaymentSession: vi.fn(),
  getBillPaymentSession: vi.fn(),
  refreshBillPaymentSession: vi.fn(),
  confirmBillPaymentSession: vi.fn(),
}));

vi.mock("./api", () => ({
  getActiveOrder: mocks.getActiveOrder,
  getBillPreview: mocks.getBillPreview,
  getBill: mocks.getBill,
  createBillPaymentSession: mocks.createBillPaymentSession,
  getBillPaymentSession: mocks.getBillPaymentSession,
  refreshBillPaymentSession: mocks.refreshBillPaymentSession,
  confirmBillPaymentSession: mocks.confirmBillPaymentSession,
}));

function createReservation(overrides: Partial<ReservationSummary> = {}): ReservationSummary {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    status: "Reserved",
    row_version: 4,
    final_bill_amount: "54.00",
    bill_currency: "USD",
    ...overrides,
  };
}

function createPaymentSessionEnvelope(rowVersion: number, sessionStatus = "Pending") {
  const terminal = sessionStatus === "Succeeded";

  return {
    reservation_id: 7,
    bill: {
      outstanding_amount: "54.00",
      currency: "USD",
    },
    payment_session: {
      deposit_payment_session_id: 0,
      bill_payment_session_id: 401,
      reservation_id: 7,
      provider_code: "mockpay",
      provider_session_code: "bill-1",
      amount: "54.00",
      currency: "USD",
      session_status: sessionStatus,
      settlement_status: terminal ? "Succeeded" : "Pending",
      provider_expires_at: "2030-05-20T20:00:00Z",
      confirmed_at: terminal ? "2026-04-18T18:45:00Z" : null,
      row_version: rowVersion,
      created_at: "2026-04-18T18:00:00Z",
      updated_at: "2026-04-18T18:00:15Z",
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
      <BillingPanel reservation={createReservation()} />
    </QueryClientProvider>,
  );
}

describe("BillingPanel", () => {
  beforeEach(() => {
    window.sessionStorage.clear();

    mocks.getActiveOrder.mockReset();
    mocks.getBillPreview.mockReset();
    mocks.getBill.mockReset();
    mocks.createBillPaymentSession.mockReset();
    mocks.getBillPaymentSession.mockReset();
    mocks.refreshBillPaymentSession.mockReset();
    mocks.confirmBillPaymentSession.mockReset();

    mocks.getActiveOrder.mockResolvedValue({
      reservation_id: 7,
      active_order: null,
    });
    mocks.getBillPreview.mockResolvedValue({
      reservation_id: 7,
      bill_preview: null,
    });
    mocks.getBill.mockResolvedValue({
      reservation_id: 7,
      bill: {
        outstanding_amount: "54.00",
        currency: "USD",
      },
    });
  });

  it("restores a stored bill payment session through the canonical show route", async () => {
    window.sessionStorage.setItem("restaurantpos.customer.session-id.v1", "browser-session-1");
    window.sessionStorage.setItem(
      "restaurantpos.customer.payment-session.v1.bill.7",
      JSON.stringify({
        surface: "bill",
        reservation_id: 7,
        session_id: 401,
        browser_session_id: "browser-session-1",
      }),
    );
    mocks.getBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(18));

    renderPanel();

    await waitFor(() => {
      expect(mocks.getBillPaymentSession).toHaveBeenCalledWith(7, 401);
    });
    expect(await screen.findByText("bill-1")).toBeInTheDocument();
  });

  it("uses the bill payment session row version for refresh and confirm actions", async () => {
    const user = userEvent.setup();

    mocks.createBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(21));
    mocks.refreshBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(22));
    mocks.confirmBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(22, "Succeeded"));

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Thanh toán hóa đơn" }));

    await waitFor(() => {
      expect(mocks.createBillPaymentSession).toHaveBeenCalledWith(7, 4);
    });
    expect(await screen.findByText("bill-1")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cập nhật trạng thái" }));

    await waitFor(() => {
      expect(mocks.refreshBillPaymentSession).toHaveBeenCalledWith(7, 401, 21);
    });

    await user.click(await screen.findByRole("button", { name: "Xác nhận thanh toán" }));

    await waitFor(() => {
      expect(mocks.confirmBillPaymentSession).toHaveBeenCalledWith(7, 401, 22);
    });
    expect((await screen.findAllByText("Đã ghi nhận thanh toán")).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Đã kết nối nhà cung cấp")).toBeInTheDocument();
  });

  it("does not allow creating a second bill payment session while restoring one", async () => {
    window.sessionStorage.setItem("restaurantpos.customer.session-id.v1", "browser-session-1");
    window.sessionStorage.setItem(
      "restaurantpos.customer.payment-session.v1.bill.7",
      JSON.stringify({
        surface: "bill",
        reservation_id: 7,
        session_id: 401,
        browser_session_id: "browser-session-1",
      }),
    );
    mocks.getBillPaymentSession.mockReturnValue(new Promise(() => {}));

    renderPanel();

    expect(await screen.findByRole("button", { name: "Thanh toán hóa đơn" })).toBeDisabled();
    expect(mocks.createBillPaymentSession).not.toHaveBeenCalled();
  });

  it("prevents refresh and confirm from running at the same time", async () => {
    const user = userEvent.setup();

    mocks.createBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(21));
    mocks.refreshBillPaymentSession.mockReturnValue(new Promise(() => {}));

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Thanh toán hóa đơn" }));
    await screen.findByText("bill-1");
    await user.click(screen.getByRole("button", { name: "Cập nhật trạng thái" }));

    expect(await screen.findByRole("button", { name: "Xác nhận thanh toán" })).toBeDisabled();
    expect(mocks.confirmBillPaymentSession).not.toHaveBeenCalled();
  });

  it("renders a blocked state when bill self-service is forbidden for the current actor", async () => {
    mocks.getBillPreview.mockRejectedValue({
      kind: "forbidden",
      status: 403,
      message: "Access to this API operation is denied by policy.",
      errorCode: "forbidden",
      categoryCode: "policy_denied",
      requestId: "req-bill-forbidden",
      validationErrors: null,
    });

    renderPanel();

    expect(await screen.findByText("Không thể mở hóa đơn")).toBeInTheDocument();
    expect(
      screen.getByText("Tài khoản hoặc phiên hiện tại không thể tự xử lý hóa đơn cho lịch đặt này."),
    ).toBeInTheDocument();
  });

  it("surfaces an active order state when the final bill is not ready yet", async () => {
    mocks.getActiveOrder.mockResolvedValue({
      reservation_id: 7,
      active_order: {
        order_id: 91,
        status: "Open",
        row_version: 8,
      },
    });
    mocks.getBill.mockResolvedValue({
      reservation_id: 7,
      bill: null,
    });

    renderPanel();

    expect(await screen.findByText("Theo dõi món")).toBeInTheDocument();
    expect(
      screen.getByText("Đơn này vẫn đang được xử lý."),
    ).toBeInTheDocument();
  });

  it("shows seeded-data support messaging when bill self-pay is enabled but no payable bill path is exposed", async () => {
    mocks.getBill.mockResolvedValue({
      reservation_id: 7,
      bill: null,
    });

    renderPanel();

    expect(await screen.findByText("Đang chờ hóa đơn")).toBeInTheDocument();
    expect(
      screen.getByText("Nhà hàng chưa gửi hóa đơn có thể thanh toán trực tuyến cho lượt ghé này."),
    ).toBeInTheDocument();
  });
});
