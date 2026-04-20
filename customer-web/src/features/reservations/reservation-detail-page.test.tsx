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

    expect(await screen.findByText("Reservation workspace")).toBeInTheDocument();
    expect(screen.getAllByText("Table hold")[0]).toBeInTheDocument();
    expect(screen.getAllByText("Hold expired")[0]).toBeInTheDocument();
  });

  it("shows a no-longer-manageable state when the reservation is inactive", async () => {
    mocks.getReservation.mockResolvedValue(
      createReservation({
        status: "Cancelled",
      }),
    );

    renderPage();

    expect(await screen.findByText("Online changes are no longer available")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Request new time" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Cancel reservation" })).not.toBeInTheDocument();
  });

  it("shows when reservation access is coming from a linked session", async () => {
    mocks.getReservation.mockResolvedValue(
      createReservation({
        access_scope: "session",
      }),
    );

    renderPage();

    expect(await screen.findByText("Linked visit session")).toBeInTheDocument();
    expect(screen.getByText("You are viewing this reservation through the linked visit session for this browser.")).toBeInTheDocument();
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

    await screen.findByRole("button", { name: "Cancel reservation" });
    await user.click(screen.getByRole("button", { name: "Cancel reservation" }));

    await waitFor(() => {
      expect(mocks.cancelReservation).toHaveBeenCalledWith(7, 4, "");
    });
    await waitFor(() => {
      expect(mocks.getReservation).toHaveBeenCalledTimes(2);
    });

    expect(await screen.findByText("Reservation details changed")).toBeInTheDocument();
    expect(screen.getByText("This changed while you were working.")).toBeInTheDocument();
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

    expect(await screen.findByText("This reservation is unavailable")).toBeInTheDocument();
    expect(screen.getByText("The reservation could not be found or is no longer available from customer self-service.")).toBeInTheDocument();
  });
});
