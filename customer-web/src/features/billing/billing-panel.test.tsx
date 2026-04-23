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
  refreshBillPaymentSession: vi.fn(),
  confirmBillPaymentSession: vi.fn(),
}));

vi.mock("./api", () => ({
  getActiveOrder: mocks.getActiveOrder,
  getBillPreview: mocks.getBillPreview,
  getBill: mocks.getBill,
  createBillPaymentSession: mocks.createBillPaymentSession,
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
      provider_expires_at: "2026-05-20T20:00:00Z",
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
    mocks.getActiveOrder.mockReset();
    mocks.getBillPreview.mockReset();
    mocks.getBill.mockReset();
    mocks.createBillPaymentSession.mockReset();
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

  it("uses the bill payment session row version for refresh and confirm actions", async () => {
    const user = userEvent.setup();

    mocks.createBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(21));
    mocks.refreshBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(22));
    mocks.confirmBillPaymentSession.mockResolvedValue(createPaymentSessionEnvelope(22, "Succeeded"));

    renderPanel();

    await user.click(await screen.findByRole("button", { name: "Continue to bill payment" }));

    await waitFor(() => {
      expect(mocks.createBillPaymentSession).toHaveBeenCalledWith(7, 4);
    });
    expect(await screen.findByText("bill-1")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Refresh status" }));

    await waitFor(() => {
      expect(mocks.refreshBillPaymentSession).toHaveBeenCalledWith(7, 401, 21);
    });

    await user.click(await screen.findByRole("button", { name: "Confirm payment" }));

    await waitFor(() => {
      expect(mocks.confirmBillPaymentSession).toHaveBeenCalledWith(7, 401, 22);
    });
    expect(await screen.findByText("Payment applied")).toBeInTheDocument();
    expect(screen.getByText("Provider runtime confirmed")).toBeInTheDocument();
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

    expect(await screen.findByText("Bill access is blocked")).toBeInTheDocument();
    expect(screen.getByText("Bill self-service is not available for the current actor on this reservation.")).toBeInTheDocument();
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

    expect(await screen.findByText("Active order in progress")).toBeInTheDocument();
    expect(screen.getByText("An active order is still open. The final bill will appear after staff closes it.")).toBeInTheDocument();
  });

  it("shows seeded-data support messaging when bill self-pay is enabled but no payable bill path is exposed", async () => {
    mocks.getBill.mockResolvedValue({
      reservation_id: 7,
      bill: null,
    });

    renderPanel();

    expect(await screen.findByText("Waiting for live bill data")).toBeInTheDocument();
    expect(
      screen.getByText("Bill self-pay stays enabled for this surface, but the current live or seeded UAT data does not expose a payable bill session yet."),
    ).toBeInTheDocument();
  });
});
