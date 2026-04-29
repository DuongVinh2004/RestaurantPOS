import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  clearReservationPreorder,
  getReservationPreorder,
  previewReservationPreorder,
  replaceReservationPreorder,
} from "./api";

const mocks = vi.hoisted(() => ({
  apiCall: vi.fn(),
  idempotentSessionOptions: vi.fn(),
  getV1ReservationsIdPreorder: vi.fn(),
  postV1ReservationsIdPreorderPreview: vi.fn(),
  putV1ReservationsIdPreorder: vi.fn(),
  deleteV1ReservationsIdPreorder: vi.fn(),
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentSessionOptions: mocks.idempotentSessionOptions,
}));

describe("preorder api", () => {
  beforeEach(() => {
    mocks.apiCall.mockReset();
    mocks.idempotentSessionOptions.mockReset();
    mocks.getV1ReservationsIdPreorder.mockReset();
    mocks.postV1ReservationsIdPreorderPreview.mockReset();
    mocks.putV1ReservationsIdPreorder.mockReset();
    mocks.deleteV1ReservationsIdPreorder.mockReset();

    mocks.idempotentSessionOptions.mockReturnValue({ idempotencyKey: "idem-preorder" });
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1ReservationsIdPreorder: mocks.getV1ReservationsIdPreorder,
        postV1ReservationsIdPreorderPreview: mocks.postV1ReservationsIdPreorderPreview,
        putV1ReservationsIdPreorder: mocks.putV1ReservationsIdPreorder,
        deleteV1ReservationsIdPreorder: mocks.deleteV1ReservationsIdPreorder,
      }),
    );
  });

  it("reads reservation preorder through the generated SDK", async () => {
    mocks.getV1ReservationsIdPreorder.mockResolvedValue({ data: { reservation_id: 7 } });

    await getReservationPreorder(7);

    expect(mocks.getV1ReservationsIdPreorder).toHaveBeenCalledWith({ id: 7 });
  });

  it("previews preorder items through the idempotent session boundary", async () => {
    mocks.postV1ReservationsIdPreorderPreview.mockResolvedValue({ data: { reservation_id: 7 } });

    await previewReservationPreorder(7, {
      pre_order_items: [{ item_id: 10, quantity: 2 }],
    });

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-preorder-preview");
    expect(mocks.postV1ReservationsIdPreorderPreview).toHaveBeenCalledWith(
      { id: 7 },
      { pre_order_items: [{ item_id: 10, quantity: 2 }] },
      { idempotencyKey: "idem-preorder" },
    );
  });

  it("replaces preorder with reservation and preorder row versions", async () => {
    mocks.putV1ReservationsIdPreorder.mockResolvedValue({ data: { reservation_id: 7 } });

    await replaceReservationPreorder(7, {
      pre_order_items: [{ item_id: 10, quantity: 2 }],
      row_version: 5,
      pre_order_row_version: 9,
    });

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-preorder-replace");
    expect(mocks.putV1ReservationsIdPreorder).toHaveBeenCalledWith(
      { id: 7 },
      {
        pre_order_items: [{ item_id: 10, quantity: 2 }],
        row_version: 5,
        pre_order_row_version: 9,
      },
      { idempotencyKey: "idem-preorder" },
    );
  });

  it("clears preorder with query row versions and idempotency", async () => {
    mocks.deleteV1ReservationsIdPreorder.mockResolvedValue({ data: { reservation_id: 7 } });

    await clearReservationPreorder(7, 5, 9);

    expect(mocks.idempotentSessionOptions).toHaveBeenCalledWith("reservation-preorder-clear");
    expect(mocks.deleteV1ReservationsIdPreorder).toHaveBeenCalledWith(
      { id: 7 },
      {
        row_version: 5,
        pre_order_row_version: 9,
      },
      { idempotencyKey: "idem-preorder" },
    );
  });
});
