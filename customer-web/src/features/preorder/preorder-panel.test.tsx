import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PreorderPanel } from "./preorder-panel";

const mocks = vi.hoisted(() => ({
  clearReservationPreorder: vi.fn(),
  getReservationPreorder: vi.fn(),
  listMenuItems: vi.fn(),
  previewReservationPreorder: vi.fn(),
  replaceReservationPreorder: vi.fn(),
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
  clearReservationPreorder: mocks.clearReservationPreorder,
  getReservationPreorder: mocks.getReservationPreorder,
  previewReservationPreorder: mocks.previewReservationPreorder,
  replaceReservationPreorder: mocks.replaceReservationPreorder,
}));

vi.mock("@/features/menu/api", () => ({
  listMenuItems: mocks.listMenuItems,
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

function createMenuItem(overrides: Record<string, unknown> = {}) {
  return {
    item_id: 10,
    category_id: 1,
    category_name: "Starters",
    code: "SPRING",
    name: "Spring rolls",
    description: null,
    img_url: null,
    is_available: true,
    price: {
      price_id: 3,
      amount: "12.00",
      currency: "USD",
      effective_from: null,
      effective_to: null,
    },
    preorder: {
      enabled: true,
      cutoff_minutes: 30,
      quota_per_day: 20,
      requires_preview_validation: true,
    },
    created_at: null,
    updated_at: null,
    ...overrides,
  };
}

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
      lines: [
        {
          order_item_id: 101,
          item_id: 10,
          quantity: 1,
          status: "Open",
          name: "Spring rolls",
          code: "SPRING",
          unit_price: "12.00",
          line_total: "12.00",
          currency: "USD",
          notes: null,
          updated_at: null,
        },
      ],
      totals: {
        item_count: 1,
        quantity: 1,
        subtotal: "12.00",
      },
      normalized_pre_order_items: [{ item_id: 10, quantity: 1 }],
    },
    management_policy: {
      can_manage: true,
      reservation_status: "Confirmed",
      cutoff_minutes: 30,
      service_start: "2026-04-18T18:30:00Z",
      manage_until: "2026-04-18T18:00:00Z",
      reasons: [],
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
      mutations: {
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
    window.sessionStorage.clear();
    mocks.clearReservationPreorder.mockReset();
    mocks.getReservationPreorder.mockReset();
    mocks.listMenuItems.mockReset();
    mocks.previewReservationPreorder.mockReset();
    mocks.replaceReservationPreorder.mockReset();
    mocks.customerWebRollout.preorder.enabled = true;
    mocks.getReservationPreorder.mockResolvedValue(createPayload());
    mocks.listMenuItems.mockResolvedValue([createMenuItem()]);
  });

  it("renders a rollout-disabled state without calling preorder reads or menu reads", () => {
    mocks.customerWebRollout.preorder.enabled = false;

    renderPanel();

    expect(screen.getByText("Preorder is not in this rollout")).toBeInTheDocument();
    expect(
      screen.getByText(/replace and clear proof is outside the current launch scope/i),
    ).toBeInTheDocument();
    expect(mocks.getReservationPreorder).not.toHaveBeenCalled();
    expect(mocks.listMenuItems).not.toHaveBeenCalled();
  });

  it("requires a fresh preview before replacing preorder items", async () => {
    const user = userEvent.setup();

    mocks.previewReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );
    mocks.replaceReservationPreorder.mockResolvedValue(
      createPayload({
        reservation_row_version: 6,
        pre_order: {
          ...createPayload().pre_order,
          order_row_version: 10,
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );

    renderPanel();

    const quantityInput = await screen.findByLabelText("Số lượng Spring rolls");
    const replaceButton = screen.getByRole("button", { name: "Cập nhật món đặt trước" });

    await user.clear(quantityInput);
    await user.type(quantityInput, "2");

    expect(replaceButton).toBeDisabled();
    expect(
      screen.getByText(/Bạn đã thay đổi giỏ món. Vui lòng xem trước lại/i),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Xem trước món" }));

    await waitFor(() => {
      expect(mocks.previewReservationPreorder).toHaveBeenCalledWith(7, {
        pre_order_items: [{ item_id: 10, quantity: 2 }],
      });
    });
    expect(await screen.findByText("Bản xem trước")).toBeInTheDocument();
    expect(replaceButton).toBeEnabled();

    await user.click(replaceButton);

    await waitFor(() => {
      expect(mocks.replaceReservationPreorder).toHaveBeenCalledWith(7, {
        pre_order_items: [{ item_id: 10, quantity: 2 }],
        row_version: 5,
        pre_order_row_version: 9,
      });
    });
  });

  it("locks preorder inputs while preview is pending", async () => {
    const user = userEvent.setup();
    let resolvePreview: (value: unknown) => void = () => {};

    mocks.previewReservationPreorder.mockReturnValue(
      new Promise((resolve) => {
        resolvePreview = resolve;
      }),
    );

    renderPanel();

    const quantityInput = await screen.findByLabelText("Số lượng Spring rolls");
    await user.clear(quantityInput);
    await user.type(quantityInput, "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));

    expect(quantityInput).toBeDisabled();
    expect(screen.getByRole("button", { name: "Cập nhật món đặt trước" })).toBeDisabled();

    resolvePreview(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );

    expect(await screen.findByText("Bản xem trước")).toBeInTheDocument();
    expect(quantityInput).toBeEnabled();
  });

  it("invalidates the preview when the cart changes again", async () => {
    const user = userEvent.setup();

    mocks.previewReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );

    renderPanel();

    const quantityInput = await screen.findByLabelText("Số lượng Spring rolls");
    const replaceButton = screen.getByRole("button", { name: "Cập nhật món đặt trước" });

    await user.clear(quantityInput);
    await user.type(quantityInput, "2");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));

    expect(await screen.findByText("Bản xem trước")).toBeInTheDocument();
    expect(replaceButton).toBeEnabled();

    await user.clear(quantityInput);
    await user.type(quantityInput, "3");

    expect(replaceButton).toBeDisabled();
    expect(
      screen.getByText(/Bạn đã thay đổi giỏ món. Vui lòng xem trước lại/i),
    ).toBeInTheDocument();
  });

  it("clears preorder with reservation and preorder row versions", async () => {
    const user = userEvent.setup();

    mocks.clearReservationPreorder.mockResolvedValue(
      createPayload({
        reservation_row_version: 6,
        pre_order: {
          ...createPayload().pre_order,
          present: false,
          order_id: null,
          order_row_version: null,
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

    await user.click(await screen.findByRole("button", { name: "Xóa món đặt trước" }));

    await waitFor(() => {
      expect(mocks.clearReservationPreorder).toHaveBeenCalledWith(7, 5, 9);
    });
  });

  it("refreshes preorder and shows conflict guidance after a stale replace", async () => {
    const user = userEvent.setup();

    mocks.previewReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          totals: {
            item_count: 1,
            quantity: 3,
            subtotal: "36.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 3 }],
        },
      }),
    );
    mocks.replaceReservationPreorder.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "The preorder changed elsewhere.",
      errorCode: "row_version_conflict",
      categoryCode: "validation_error",
      requestId: "req-preorder-conflict",
      validationErrors: null,
    });

    renderPanel();

    const quantityInput = await screen.findByLabelText("Số lượng Spring rolls");
    await user.clear(quantityInput);
    await user.type(quantityInput, "3");
    await user.click(screen.getByRole("button", { name: "Xem trước món" }));
    await user.click(await screen.findByRole("button", { name: "Cập nhật món đặt trước" }));

    expect(await screen.findByText("Thông tin món đặt trước đã thay đổi")).toBeInTheDocument();
    expect(screen.getByText(/Thông tin đã thay đổi trong lúc bạn thao tác/i)).toBeInTheDocument();
    expect(mocks.getReservationPreorder).toHaveBeenCalledTimes(2);
  });

  it("keeps locked preorder read-only when the backend management policy denies changes", async () => {
    mocks.getReservationPreorder.mockResolvedValue(
      createPayload({
        management_policy: {
          can_manage: false,
          reservation_status: "Confirmed",
          cutoff_minutes: 30,
          service_start: "2026-04-18T18:30:00Z",
          manage_until: "2026-04-18T18:00:00Z",
          reasons: ["Preorder is locked for kitchen prep"],
        },
      }),
    );

    renderPanel();

    expect(await screen.findByText("Preorder is locked for kitchen prep")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Cập nhật món đặt trước" })).not.toBeInTheDocument();
  });

  it("restores a stored preorder draft after reservation create partially succeeds", async () => {
    const user = userEvent.setup();

    window.sessionStorage.setItem(
      "restaurantpos.customer.session-id.v1",
      "browser-session-1",
    );
    window.sessionStorage.setItem(
      "restaurantpos.customer.pending-preorder.v1.7",
      JSON.stringify({
        reservation_id: 7,
        browser_session_id: "browser-session-1",
        failure_stage: "replace",
        created_at_utc: new Date().toISOString(),
        items: [{ item_id: 10, quantity: 2 }],
      }),
    );
    mocks.getReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          present: false,
          order_id: null,
          order_row_version: null,
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
    mocks.previewReservationPreorder.mockResolvedValue(
      createPayload({
        pre_order: {
          ...createPayload().pre_order,
          present: false,
          order_id: null,
          order_row_version: null,
          lines: [],
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );
    mocks.replaceReservationPreorder.mockResolvedValue(
      createPayload({
        reservation_row_version: 6,
        pre_order: {
          ...createPayload().pre_order,
          present: true,
          order_row_version: 10,
          totals: {
            item_count: 1,
            quantity: 2,
            subtotal: "24.00",
          },
          normalized_pre_order_items: [{ item_id: 10, quantity: 2 }],
        },
      }),
    );

    renderPanel();

    expect(
      await screen.findByText(/Hệ thống đã giữ lại giỏ món/i),
    ).toBeInTheDocument();
    expect(await screen.findByLabelText("Số lượng Spring rolls")).toHaveValue(2);

    await user.click(screen.getByRole("button", { name: "Xem trước món" }));
    await user.click(
      await screen.findByRole("button", { name: "Cập nhật món đặt trước" }),
    );

    await waitFor(() => {
      expect(mocks.replaceReservationPreorder).toHaveBeenCalledWith(7, {
        pre_order_items: [{ item_id: 10, quantity: 2 }],
        row_version: 5,
        pre_order_row_version: null,
      });
    });
    expect(
      window.sessionStorage.getItem("restaurantpos.customer.pending-preorder.v1.7"),
    ).toBeNull();
  });
});
