import { describe, expect, it } from "vitest";
import { RestaurantPosApiError } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getApiErrorDisplay } from "./errors";

describe("API error display helpers", () => {
  it("surfaces request id, status, and retry guidance for conflict errors", () => {
    const display = getApiErrorDisplay(
      new RestaurantPosApiError("Conflict", 409, {
        message: "Reservation row version is stale.",
        error_code: "row_version_conflict",
        request_id: "req-conflict-1",
      }),
    );

    expect(display).toMatchObject({
      message: "This changed while you were working.",
      retryHint: "Refresh the page to load the latest reservation or linked session, then retry.",
      statusLabel: "Status 409",
      requestIdLabel: "Request ID: req-conflict-1",
      errorCodeLabel: "Code row_version_conflict",
    });
  });

  it("adds retry guidance for backend connectivity failures", () => {
    const display = getApiErrorDisplay(new TypeError("fetch failed"));

    expect(display).toMatchObject({
      message: "We cannot reach the restaurant service right now.",
      retryHint: "Check that the backend is running, then try again.",
      statusLabel: null,
      requestIdLabel: null,
      errorCodeLabel: "Code backend_unavailable",
    });
  });

  it("surfaces the first validation message when the backend returns a generic validation envelope", () => {
    const display = getApiErrorDisplay(
      new RestaurantPosApiError("Validation error.", 422, {
        message: "Validation error.",
        error_code: "validation_error",
        request_id: "req-validation-1",
        errors: {
          identifier: ["Invalid credentials."],
        },
      }),
    );

    expect(display).toMatchObject({
      message: "Invalid credentials.",
      retryHint: null,
      statusLabel: "Status 422",
      requestIdLabel: "Request ID: req-validation-1",
      errorCodeLabel: "Code validation_error",
    });
  });
});
