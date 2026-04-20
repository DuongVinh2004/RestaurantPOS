import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ReservationCreatePage } from "./reservation-create-page";

function formatLocalDateTimeInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function futureLocalDateTime(hoursFromNow: number): string {
  return formatLocalDateTimeInput(new Date(Date.now() + hoursFromNow * 60 * 60 * 1000));
}

function futureIso(hoursFromNow: number): string {
  return new Date(Date.now() + hoursFromNow * 60 * 60 * 1000).toISOString();
}

function createHoldSearchParams(overrides: Record<string, string> = {}): URLSearchParams {
  return new URLSearchParams({
    hold_id: "hold-123",
    hold_status: "Holding",
    hold_expires_at: futureIso(2),
    tables: "7,8",
    start_time: futureLocalDateTime(8),
    duration_minutes: "90",
    guest_count: "4",
    ...overrides,
  });
}

const mocks = vi.hoisted(() => ({
  searchParams: createHoldSearchParams(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
  useSearchParams: () => mocks.searchParams,
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
      <ReservationCreatePage />
    </QueryClientProvider>,
  );
}

describe("ReservationCreatePage", () => {
  beforeEach(() => {
    mocks.searchParams = createHoldSearchParams();
  });

  it("locks held visit details when the page is opened from a table hold", () => {
    const holdStartTime = mocks.searchParams.get("start_time") as string;

    renderPage();

    expect(screen.getByDisplayValue(holdStartTime)).toBeDisabled();
    expect(screen.getByDisplayValue("90")).toBeDisabled();
    expect(screen.getByDisplayValue("4")).toBeDisabled();
    expect(screen.getByText(/Using table hold hold-123/i)).toBeInTheDocument();
    expect(screen.getByText(/Search again if you need to change visit details/i)).toBeInTheDocument();
  });

  it("blocks reservation create when the linked hold is already expired", () => {
    mocks.searchParams = createHoldSearchParams({
      hold_status: "Expired",
      hold_expires_at: futureIso(-2),
    });

    renderPage();

    expect(screen.getByText(/is no longer active/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Create reservation" })).toBeDisabled();
    expect(screen.getByRole("link", { name: "Search availability again" })).toHaveAttribute("href", "/booking");
  });
});
