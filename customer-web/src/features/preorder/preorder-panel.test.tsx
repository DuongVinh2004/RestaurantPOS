import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PreorderPanel } from "./preorder-panel";

const mocks = vi.hoisted(() => ({
  getReservationPreorder: vi.fn(),
  customerWebRollout: {
    preorder: {
      enabled: true,
      disabledTitle: "Preorder is not in this rollout",
      disabledDescription:
        "Preorder stays off by default because live replace and clear proof is outside the current launch scope. Enable the preorder flag only for a focused contract or QA pass.",
    },
  },
}));

vi.mock("./api", () => ({
  getReservationPreorder: mocks.getReservationPreorder,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

function createPayload(overrides: Record<string, unknown> = {}) {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    reservation_status: "Confirmed",
    reservation_row_version: 5,
    pre_order: {
      present: true,
      order_id: 88,
      order_row_version: 9,
      order_status: "Open",
      service_time: "2026-04-18T18:30:00Z",
      currency: "USD",
      lines: [],
      totals: {
        item_count: 1,
        quantity: 2,
        subtotal: "34.00",
      },
      normalized_pre_order_items: [],
    },
    management_policy: {
      can_manage: false,
      reservation_status: "Confirmed",
      cutoff_minutes: 30,
      service_start: "2026-04-18T18:30:00Z",
      manage_until: "2026-04-18T18:00:00Z",
      reasons: ["Preorder is locked for kitchen prep"],
    },
    ...overrides,
  };
}

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <PreorderPanel reservationId={7} />
    </QueryClientProvider>,
  );
}

describe("PreorderPanel", () => {
  beforeEach(() => {
    mocks.getReservationPreorder.mockReset();
    mocks.customerWebRollout.preorder.enabled = true;
  });

  it("renders a rollout-disabled state without calling preorder reads", () => {
    mocks.customerWebRollout.preorder.enabled = false;

    renderPanel();

    expect(screen.getByText("Preorder is not in this rollout")).toBeInTheDocument();
    expect(screen.getByText(/replace and clear proof is outside the current launch scope/i)).toBeInTheDocument();
    expect(mocks.getReservationPreorder).not.toHaveBeenCalled();
  });

  it("shows preorder details as read-only even when preorder data exists", async () => {
    mocks.getReservationPreorder.mockResolvedValue(createPayload());

    renderPanel();

    expect(await screen.findByText("Preorder summary")).toBeInTheDocument();
    expect(screen.getByText("Preorder details are shown here for reference only while this workspace keeps preorder changes gated.")).toBeInTheDocument();
    expect(screen.getByText("Preorder is locked for kitchen prep")).toBeInTheDocument();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("shows a gated empty state when no preorder is attached", async () => {
    mocks.getReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          present: false,
          order_id: null,
          order_row_version: null,
          order_status: null,
          service_time: "2026-04-18T18:30:00Z",
          currency: "USD",
          lines: [],
          totals: {
            item_count: 0,
            quantity: 0,
            subtotal: "0.00",
          },
          normalized_pre_order_items: [],
        },
      }),
    );

    renderPanel();

    expect(await screen.findAllByText("No preorder attached")).toHaveLength(2);
    expect(screen.getAllByText("If the restaurant attaches a preorder, it will appear here. Online preorder changes stay gated from this workspace.")).toHaveLength(2);
  });
});
