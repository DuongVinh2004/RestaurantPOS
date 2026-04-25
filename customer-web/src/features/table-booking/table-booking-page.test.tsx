import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { TableBookingPage } from "./table-booking-page";

const mocks = vi.hoisted(() => ({
  searchAvailableTables: vi.fn(),
  createTableHold: vi.fn(),
  refreshTableHold: vi.fn(),
  cancelTableHold: vi.fn(),
  toastSuccess: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

vi.mock("./api", () => ({
  searchAvailableTables: mocks.searchAvailableTables,
  createTableHold: mocks.createTableHold,
  refreshTableHold: mocks.refreshTableHold,
  cancelTableHold: mocks.cancelTableHold,
}));

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
      <TableBookingPage />
    </QueryClientProvider>,
  );
}

function futureIso(minutesFromNow: number): string {
  return new Date(Date.now() + minutesFromNow * 60_000).toISOString();
}

describe("TableBookingPage", () => {
  beforeEach(() => {
    mocks.searchAvailableTables.mockReset();
    mocks.createTableHold.mockReset();
    mocks.refreshTableHold.mockReset();
    mocks.cancelTableHold.mockReset();
    mocks.toastSuccess.mockReset();
  });

  it("searches live availability, creates a hold, and links into reservation create", async () => {
    const user = userEvent.setup();
    const activeHoldExpiresAt = futureIso(90);

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          status: "Available",
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: activeHoldExpiresAt,
      hold_status: "Holding",
    });

    renderPage();

    await user.click(screen.getByRole("button", { name: "Search tables" }));

    await waitFor(() => {
      expect(mocks.searchAvailableTables).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_count: 2,
          duration_minutes: 90,
        }),
        expect.any(Object),
      );
    });

    const tableOption = await screen.findByRole("button", { name: "T7 table option" });
    expect(tableOption).toHaveAttribute("aria-pressed", "false");

    await user.click(tableOption);
    expect(tableOption).toHaveAttribute("aria-pressed", "true");

    await user.click(screen.getByRole("button", { name: "Create hold" }));

    await waitFor(() => {
      expect(mocks.createTableHold).toHaveBeenCalledWith(
        expect.objectContaining({
          guest_count: 2,
          duration_minutes: 90,
        }),
        [7],
      );
    });

    const continueLink = await screen.findByRole("link", { name: "Continue to reservation" });
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_id=hold-123"));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_status=Holding"));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("hold_expires_at="));
    expect(continueLink).toHaveAttribute("href", expect.stringContaining("tables=7"));
  });

  it("refreshes and cancels an active hold with the latest row version from the live contract", async () => {
    const user = userEvent.setup();
    const activeHoldExpiresAt = futureIso(90);

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          status: "Available",
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: activeHoldExpiresAt,
      hold_status: "Holding",
      row_version: 2,
      tables: [{ table_id: 7 }],
    });
    mocks.refreshTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(120),
      hold_status: "Holding",
      row_version: 3,
      tables: [{ table_id: 7 }],
    });
    mocks.cancelTableHold.mockResolvedValue({
      hold_id: "hold-123",
      expire_at: futureIso(120),
      hold_status: "Cancelled",
      row_version: 4,
      tables: [{ table_id: 7 }],
    });

    renderPage();

    await user.click(screen.getByRole("button", { name: "Search tables" }));
    await user.click(await screen.findByRole("button", { name: /T7/i }));
    await user.click(screen.getByRole("button", { name: "Create hold" }));
    await screen.findByRole("button", { name: "Refresh hold" });

    await user.click(screen.getByRole("button", { name: "Refresh hold" }));

    await waitFor(() => {
      expect(mocks.refreshTableHold).toHaveBeenCalledWith("hold-123", 2);
    });

    await user.click(screen.getByRole("button", { name: "Cancel hold" }));

    await waitFor(() => {
      expect(mocks.cancelTableHold).toHaveBeenCalledWith("hold-123", 3);
    });

    expect(await screen.findByText("This hold already expired")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Continue to reservation" })).not.toBeInTheDocument();
  });

  it("does not continue with an expired hold", async () => {
    const user = userEvent.setup();
    const expiredHoldAt = futureIso(-90);

    mocks.searchAvailableTables.mockResolvedValue({
      tables: [
        {
          table_id: 7,
          branch_id: 1,
          table_code: "T7",
          seats: 4,
          status: "Available",
        },
      ],
      meta: null,
    });
    mocks.createTableHold.mockResolvedValue({
      hold_id: "hold-expired",
      expire_at: expiredHoldAt,
      hold_status: "Expired",
    });

    renderPage();

    await user.click(screen.getByRole("button", { name: "Search tables" }));
    await user.click(await screen.findByRole("button", { name: /T7/i }));
    await user.click(screen.getByRole("button", { name: "Create hold" }));

    expect(await screen.findByText("This hold already expired")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Continue to reservation" })).not.toBeInTheDocument();
  });
});
