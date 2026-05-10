import { beforeEach, describe, expect, it } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import {
  clearStoredPendingReservationPreorderDraft,
  getReservationPreorderRecoveryMessage,
  readStoredPendingReservationPreorderDraft,
  storePendingReservationPreorderDraft,
} from "./reservation-draft-storage";

describe("reservation preorder draft storage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    ensureCustomerSessionId();
  });

  it("stores and reads a normalized preorder retry draft for the same browser session", () => {
    storePendingReservationPreorderDraft(501, [
      { item_id: 101, quantity: 2 },
      { item_id: 101, quantity: 0 },
      { item_id: 88, quantity: 1 },
    ], "replace");

    expect(readStoredPendingReservationPreorderDraft(501)).toMatchObject({
      reservation_id: 501,
      failure_stage: "replace",
      items: [
        { item_id: 88, quantity: 1 },
        { item_id: 101, quantity: 2 },
      ],
    });
  });

  it("fails closed when the browser session changes", () => {
    storePendingReservationPreorderDraft(501, [{ item_id: 101, quantity: 2 }], "preview");
    window.sessionStorage.setItem(
      "restaurantpos.customer.session-id.v1",
      "other-session",
    );

    expect(readStoredPendingReservationPreorderDraft(501)).toBeNull();
  });

  it("clears stored drafts explicitly", () => {
    storePendingReservationPreorderDraft(501, [{ item_id: 101, quantity: 2 }], "snapshot");
    clearStoredPendingReservationPreorderDraft(501);

    expect(readStoredPendingReservationPreorderDraft(501)).toBeNull();
  });

  it("returns customer-facing retry copy for each failure stage", () => {
    expect(getReservationPreorderRecoveryMessage("snapshot")).toMatch(/chưa tải được/i);
    expect(getReservationPreorderRecoveryMessage("preview")).toMatch(/chưa xem trước được/i);
    expect(getReservationPreorderRecoveryMessage("replace")).toMatch(/chưa lưu được/i);
  });
});
