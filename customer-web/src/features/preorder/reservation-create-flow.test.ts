import { describe, expect, it, vi } from "vitest";
import {
  ReservationPreorderPersistenceError,
  createReservationWithPreorderDraft,
} from "./reservation-create-flow";

function createReservationResult(overrides: Record<string, unknown> = {}) {
  return {
    reservation_id: 501,
    reservation_code: "RSV-501",
    row_version: 1,
    status: "Confirmed",
    guest_count: 4,
    ...overrides,
  };
}

function createPreorderSnapshot(overrides: Record<string, unknown> = {}) {
  return {
    reservation_id: 501,
    reservation_code: "RSV-501",
    reservation_status: "Confirmed",
    reservation_row_version: 7,
    pre_order: {
      present: false,
      order_id: null,
      order_row_version: null,
      order_status: null,
      service_time: "2026-05-07T11:00:00Z",
      currency: "VND",
      lines: [],
      totals: {
        item_count: 0,
        quantity: 0,
        subtotal: "0.00",
      },
      normalized_pre_order_items: [],
    },
    management_policy: {
      can_manage: true,
      reservation_status: "Confirmed",
      cutoff_minutes: 30,
      service_start: "2026-05-07T11:00:00Z",
      manage_until: "2026-05-07T10:30:00Z",
      reasons: [],
    },
    ...overrides,
  };
}

function createDependencies() {
  return {
    createReservation: vi.fn(),
    getReservationPreorder: vi.fn(),
    previewReservationPreorder: vi.fn(),
    replaceReservationPreorder: vi.fn(),
    submitReservationPreorder: vi.fn(),
  };
}

describe("createReservationWithPreorderDraft", () => {
  it("creates a reservation without preorder persistence when the draft is empty", async () => {
    const dependencies = createDependencies();
    dependencies.createReservation.mockResolvedValue(createReservationResult());

    const result = await createReservationWithPreorderDraft(
      {
        reservationInput: {
          guest_name: "Demo Customer",
          guest_phone: "5550100",
          guest_email: "demo@example.test",
          start_time: "2026-05-07T18:00",
          duration_minutes: 90,
          guest_count: 4,
          notes: "Window seat",
          hold_id: "hold-123",
          table_ids: [7, 8],
        },
        preorderItems: [],
      },
      dependencies,
    );

    expect(result).toEqual({
      reservation: createReservationResult(),
      preorder: null,
    });
    expect(dependencies.getReservationPreorder).not.toHaveBeenCalled();
    expect(dependencies.previewReservationPreorder).not.toHaveBeenCalled();
    expect(dependencies.replaceReservationPreorder).not.toHaveBeenCalled();
  });

  it("persists preorder after the reservation is created", async () => {
    const dependencies = createDependencies();
    const snapshot = createPreorderSnapshot();
    const persisted = createPreorderSnapshot({
      reservation_row_version: 8,
      pre_order: {
        ...snapshot.pre_order,
        present: true,
        order_id: 900,
        order_row_version: 3,
        totals: {
          item_count: 1,
          quantity: 2,
          subtotal: "240000.00",
        },
        normalized_pre_order_items: [{ item_id: 101, quantity: 2 }],
      },
    });

    dependencies.createReservation.mockResolvedValue(createReservationResult());
    dependencies.getReservationPreorder.mockResolvedValue(snapshot);
    dependencies.previewReservationPreorder.mockResolvedValue(snapshot);
    dependencies.replaceReservationPreorder.mockResolvedValue(persisted);
    dependencies.submitReservationPreorder.mockResolvedValue(persisted);

    const result = await createReservationWithPreorderDraft(
      {
        reservationInput: {
          guest_name: "Demo Customer",
          guest_phone: "5550100",
          guest_email: "demo@example.test",
          start_time: "2026-05-07T18:00",
          duration_minutes: 90,
          guest_count: 4,
          notes: "Window seat",
          hold_id: "hold-123",
          table_ids: [7, 8],
        },
        preorderItems: [{ item_id: 101, quantity: 2 }],
      },
      dependencies,
    );

    expect(dependencies.getReservationPreorder).toHaveBeenCalledWith(501);
    expect(dependencies.previewReservationPreorder).toHaveBeenCalledWith(501, {
      pre_order_items: [{ item_id: 101, quantity: 2 }],
    });
    expect(dependencies.replaceReservationPreorder).toHaveBeenCalledWith(501, {
      pre_order_items: [{ item_id: 101, quantity: 2 }],
      row_version: 7,
      pre_order_row_version: null,
    });
    expect(dependencies.submitReservationPreorder).toHaveBeenCalledWith(
      501,
      8,
      3
    );
    expect(result).toEqual({
      reservation: createReservationResult(),
      preorder: persisted,
    });
  });

  it("surfaces snapshot failures after reservation create as a tagged persistence error", async () => {
    const dependencies = createDependencies();
    const cause = new Error("snapshot failed");
    dependencies.createReservation.mockResolvedValue(createReservationResult());
    dependencies.getReservationPreorder.mockRejectedValue(cause);

    await expect(
      createReservationWithPreorderDraft(
        {
          reservationInput: {
            guest_name: "Demo Customer",
            guest_phone: "5550100",
            guest_email: "",
            start_time: "2026-05-07T18:00",
            duration_minutes: 90,
            guest_count: 4,
            notes: "",
            hold_id: "hold-123",
            table_ids: [7, 8],
          },
          preorderItems: [{ item_id: 101, quantity: 1 }],
        },
        dependencies,
      ),
    ).rejects.toMatchObject({
      reservation: createReservationResult(),
      stage: "snapshot",
      cause,
    });
  });

  it("surfaces replace failures after reservation create as a tagged persistence error", async () => {
    const dependencies = createDependencies();
    const snapshot = createPreorderSnapshot();
    const cause = new Error("replace failed");
    dependencies.createReservation.mockResolvedValue(createReservationResult());
    dependencies.getReservationPreorder.mockResolvedValue(snapshot);
    dependencies.previewReservationPreorder.mockResolvedValue(snapshot);
    dependencies.replaceReservationPreorder.mockRejectedValue(cause);

    const request = createReservationWithPreorderDraft(
      {
        reservationInput: {
          guest_name: "Demo Customer",
          guest_phone: "5550100",
          guest_email: "",
          start_time: "2026-05-07T18:00",
          duration_minutes: 90,
          guest_count: 4,
          notes: "",
          hold_id: "hold-123",
          table_ids: [7, 8],
        },
        preorderItems: [{ item_id: 101, quantity: 1 }],
      },
      dependencies,
    );

    await expect(request).rejects.toBeInstanceOf(
      ReservationPreorderPersistenceError,
    );
    await expect(request).rejects.toMatchObject({
      reservation: createReservationResult(),
      stage: "replace",
      cause,
    });
  });
});
