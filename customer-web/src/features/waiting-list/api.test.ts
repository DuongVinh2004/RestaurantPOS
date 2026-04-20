import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  acceptWaitingListEntry,
  cancelWaitingListEntry,
  confirmWaitingListArrival,
  createWaitingListEntry,
  declineWaitingListEntry,
  getWaitingListEntry,
  listWaitingList,
} from "./api";

const mocks = vi.hoisted(() => ({
  idempotentOptions: vi.fn(),
  apiCall: vi.fn(),
  getV1WaitingList: vi.fn(),
  getV1WaitingListId: vi.fn(),
  postV1WaitingList: vi.fn(),
  postV1WaitingListIdAccept: vi.fn(),
  postV1WaitingListIdConfirmArrival: vi.fn(),
  postV1WaitingListIdDecline: vi.fn(),
  postV1WaitingListIdCancel: vi.fn(),
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentOptions: mocks.idempotentOptions,
}));

describe("waiting-list api adapter", () => {
  beforeEach(() => {
    mocks.idempotentOptions.mockReset();
    mocks.apiCall.mockReset();
    mocks.getV1WaitingList.mockReset();
    mocks.getV1WaitingListId.mockReset();
    mocks.postV1WaitingList.mockReset();
    mocks.postV1WaitingListIdAccept.mockReset();
    mocks.postV1WaitingListIdConfirmArrival.mockReset();
    mocks.postV1WaitingListIdDecline.mockReset();
    mocks.postV1WaitingListIdCancel.mockReset();

    mocks.idempotentOptions.mockImplementation((scope: string) => ({ idempotencyKey: `idem:${scope}` }));
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1WaitingList: mocks.getV1WaitingList,
        getV1WaitingListId: mocks.getV1WaitingListId,
        postV1WaitingList: mocks.postV1WaitingList,
        postV1WaitingListIdAccept: mocks.postV1WaitingListIdAccept,
        postV1WaitingListIdConfirmArrival: mocks.postV1WaitingListIdConfirmArrival,
        postV1WaitingListIdDecline: mocks.postV1WaitingListIdDecline,
        postV1WaitingListIdCancel: mocks.postV1WaitingListIdCancel,
      }),
    );
  });

  it("unwraps the owner-scoped waiting-list collection", async () => {
    mocks.getV1WaitingList.mockResolvedValue({
      data: [
        {
          waiting_id: 91,
          row_version: 7,
        },
      ],
    });

    const result = await listWaitingList();

    expect(mocks.getV1WaitingList).toHaveBeenCalledWith({ active_only: false });
    expect(result).toEqual([{ waiting_id: 91, row_version: 7 }]);
  });

  it("reads owner-scoped waiting-list detail by id", async () => {
    mocks.getV1WaitingListId.mockResolvedValue({
      data: {
        waiting_id: 91,
        row_version: 7,
      },
    });

    const result = await getWaitingListEntry(91);

    expect(mocks.getV1WaitingListId).toHaveBeenCalledWith({ id: 91 });
    expect(result).toEqual({ waiting_id: 91, row_version: 7 });
  });

  it("creates waiting-list entries with idempotency but without a session header contract", async () => {
    mocks.postV1WaitingList.mockResolvedValue({
      data: {
        waiting_id: 92,
        row_version: 1,
      },
    });

    const result = await createWaitingListEntry({
      guest_name: "Taylor",
      guest_count: 2,
      phone: "0900000000",
      notes: "Near window",
    });

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("waiting-list-create");
    expect(mocks.postV1WaitingList).toHaveBeenCalledWith(
      {
        guest_name: "Taylor",
        guest_count: 2,
        phone: "0900000000",
        notes: "Near window",
      },
      { idempotencyKey: "idem:waiting-list-create" },
    );
    expect(result.entry).toEqual({ waiting_id: 92, row_version: 1 });
    expect(result.meta).toBeNull();
  });

  it("keeps owner response mutations row-versioned and idempotent", async () => {
    mocks.postV1WaitingListIdAccept.mockResolvedValue({
      data: {
        waiting_id: 92,
        row_version: 2,
      },
    });

    const result = await acceptWaitingListEntry(92, { row_version: 1 });

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("waiting-list-accept");
    expect(mocks.postV1WaitingListIdAccept).toHaveBeenCalledWith(
      { id: 92 },
      { row_version: 1 },
      { idempotencyKey: "idem:waiting-list-accept" },
    );
    expect(result.entry).toEqual({ waiting_id: 92, row_version: 2 });
    expect(result.meta).toBeNull();
  });

  it("keeps arrival, decline, and cancel owner mutations row-versioned and idempotent", async () => {
    mocks.postV1WaitingListIdConfirmArrival.mockResolvedValue({
      data: {
        waiting_id: 92,
        row_version: 3,
      },
      meta: {
        action: "confirm_arrival",
        staff_seat_required: true,
        message: "Arrival confirmed.",
      },
    });
    mocks.postV1WaitingListIdDecline.mockResolvedValue({
      data: {
        waiting_id: 93,
        row_version: 4,
      },
    });
    mocks.postV1WaitingListIdCancel.mockResolvedValue({
      data: {
        waiting_id: 94,
        row_version: 2,
      },
    });

    const arrival = await confirmWaitingListArrival(92, { row_version: 2 });
    const decline = await declineWaitingListEntry(93, { row_version: 3 });
    const cancel = await cancelWaitingListEntry(94, { row_version: 1, cancel_reason: "Plans changed" });

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("waiting-list-arrival");
    expect(mocks.postV1WaitingListIdConfirmArrival).toHaveBeenCalledWith(
      { id: 92 },
      { row_version: 2 },
      { idempotencyKey: "idem:waiting-list-arrival" },
    );
    expect(mocks.idempotentOptions).toHaveBeenCalledWith("waiting-list-decline");
    expect(mocks.postV1WaitingListIdDecline).toHaveBeenCalledWith(
      { id: 93 },
      { row_version: 3 },
      { idempotencyKey: "idem:waiting-list-decline" },
    );
    expect(mocks.idempotentOptions).toHaveBeenCalledWith("waiting-list-cancel");
    expect(mocks.postV1WaitingListIdCancel).toHaveBeenCalledWith(
      { id: 94 },
      { row_version: 1, cancel_reason: "Plans changed" },
      { idempotencyKey: "idem:waiting-list-cancel" },
    );
    expect(arrival.entry).toEqual({ waiting_id: 92, row_version: 3 });
    expect(arrival.meta?.staff_seat_required).toBe(true);
    expect(decline.entry).toEqual({ waiting_id: 93, row_version: 4 });
    expect(cancel.entry).toEqual({ waiting_id: 94, row_version: 2 });
  });
});
