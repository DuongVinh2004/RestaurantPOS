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
      message: "Thông tin đã thay đổi trong lúc bạn thao tác.",
      retryHint: "Tải lại trang để lấy thông tin mới nhất rồi thử lại.",
      statusLabel: "Trạng thái 409",
      requestIdLabel: "Mã hỗ trợ: req-conflict-1",
      errorCodeLabel: "Mã lỗi row_version_conflict",
    });
  });

  it("adds retry guidance for backend connectivity failures", () => {
    const display = getApiErrorDisplay(new TypeError("fetch failed"));

    expect(display).toMatchObject({
      message: "Hiện chưa kết nối được với hệ thống nhà hàng.",
      retryHint: "Kiểm tra hệ thống nhà hàng rồi thử lại.",
      statusLabel: null,
      requestIdLabel: null,
      errorCodeLabel: "Mã lỗi backend_unavailable",
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
      statusLabel: "Trạng thái 422",
      requestIdLabel: "Mã hỗ trợ: req-validation-1",
      errorCodeLabel: "Mã lỗi validation_error",
    });
  });
});
