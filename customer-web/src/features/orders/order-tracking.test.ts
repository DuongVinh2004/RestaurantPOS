import { describe, expect, it } from "vitest";
import {
  getOrderTrackingState,
  isOrderTrackingStepComplete,
  normalizeOrderTrackingStatus,
} from "./order-tracking";

describe("order tracking state", () => {
  it("maps active order and item status into customer tracking state", () => {
    const state = getOrderTrackingState({
      order_id: 42,
      status: "Cooking",
      estimated_remaining_minutes: 12,
      items: [
        {
          order_item_id: 7,
          item_name_snapshot: "Pho",
          quantity: 2,
          status: "Ready",
        },
      ],
    });

    expect(state.present).toBe(true);
    expect(state.status).toBe("cooking");
    expect(state.terminal).toBe(false);
    expect(state.items[0]).toMatchObject({
      name: "Pho",
      quantity: 2,
      status: "ready",
    });
  });

  it("treats completed and cancelled statuses as terminal", () => {
    expect(getOrderTrackingState({ status: "Completed" }).terminal).toBe(true);
    expect(getOrderTrackingState({ status: "Cancelled by staff" }).terminal).toBe(true);
    expect(getOrderTrackingState({ status: "Served" }).terminal).toBe(false);
  });

  it("normalizes common kitchen lifecycle labels", () => {
    expect(normalizeOrderTrackingStatus("new")).toBe("received");
    expect(normalizeOrderTrackingStatus("preparing")).toBe("preparing");
    expect(normalizeOrderTrackingStatus("in_kitchen")).toBe("cooking");
    expect(normalizeOrderTrackingStatus("ready_for_service")).toBe("ready");
  });

  it("marks timeline steps complete up to the current status", () => {
    expect(isOrderTrackingStepComplete("ready", "received")).toBe(true);
    expect(isOrderTrackingStepComplete("ready", "ready")).toBe(true);
    expect(isOrderTrackingStepComplete("ready", "served")).toBe(false);
  });
});
